<?php
/**
 * Barber Turns users API.
 *
 * Owner-only endpoints to list users and adjust roles.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/user_model.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($action) {
        case 'list':
            handle_list();
            break;
        case 'set_role':
            ensure_post($method);
            handle_set_role();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'invalid_action']);
    }
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
 * List all users (owner only).
 */
function handle_list(): void
{
    $user = api_require_role('owner');
    $users = users_list();
    echo json_encode(['data' => $users, 'user' => $user]);
}

/**
 * Update a user's role (owner only).
 */
function handle_set_role(): void
{
    $user = api_require_role('owner');
    $payload = read_json_payload();

    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $role = $payload['role'] ?? '';

    if ($userId <= 0 || !is_string($role)) {
        throw new RuntimeException('invalid_payload');
    }

    $updated = users_set_role($userId, $role, $user['role']);
    echo json_encode(['data' => $updated]);
}

function ensure_post(string $method): void
{
    if (strtoupper($method) !== 'POST') {
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
