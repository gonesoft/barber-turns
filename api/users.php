<?php
/**
 * Barber Turns users API.
 *
 * Admin/owner endpoints to perform user CRUD operations.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once APP_ROOT . '/api/auth_check.php';
require_once INC_PATH . '/user_model.php';
require_once INC_PATH . '/security.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    switch ($action) {
        case 'list':
            handle_list();
            break;
        case 'get':
            handle_get();
            break;
        case 'create':
            ensure_post($method);
            handle_create();
            break;
        case 'update':
            ensure_post($method);
            handle_update();
            break;
        case 'delete':
            ensure_post($method);
            handle_delete();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'invalid_action']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request', 'reason' => $e->getMessage()]);
} catch (RuntimeException $e) {
    if ($e->getMessage() === 'insufficient_role') {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden', 'reason' => 'insufficient_role']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'bad_request', 'reason' => $e->getMessage()]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}

/**
 * List users (admin/owner only) with optional search.
 */
function handle_list(): void
{
    $user = api_require_role('admin');
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    $users = users_list(null, $search !== '' ? $search : null);
    echo json_encode(['data' => $users, 'user' => $user]);
}

/**
 * Retrieve a single user by ID (admin/owner only).
 */
function handle_get(): void
{
    api_require_role('admin');
    $userId = sanitize_int($_GET['user_id'] ?? null);
    if ($userId === null || $userId <= 0) {
        throw new InvalidArgumentException('invalid_user_id');
    }

    $user = users_get($userId);
    if ($user === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        return;
    }

    echo json_encode(['data' => $user]);
}

/**
 * Create a new user (admin/owner only).
 */
function handle_create(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $created = users_create($payload, $actor['role']);
    echo json_encode(['data' => $created]);
}

/**
 * Update an existing user (admin/owner only).
 */
function handle_update(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $userId = sanitize_int($payload['user_id'] ?? null);
    if ($userId === null || $userId <= 0) {
        throw new InvalidArgumentException('invalid_user_id');
    }

    unset($payload['user_id']);

    $updated = users_update($userId, $payload, $actor['role']);
    echo json_encode(['data' => $updated]);
}

/**
 * Delete a user (admin/owner only).
 */
function handle_delete(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $userId = sanitize_int($payload['user_id'] ?? null);
    if ($userId === null || $userId <= 0) {
        throw new InvalidArgumentException('invalid_user_id');
    }

    users_delete($userId, $actor['role']);
    echo json_encode(['data' => ['deleted' => true]]);
}

function ensure_post(string $method): void
{
    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'method_not_allowed']);
        exit;
    }
}

/**
 * Decode JSON payload into associative array.
 *
 * @return array<string,mixed>
 */
function read_json_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}
