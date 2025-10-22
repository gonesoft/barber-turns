<?php
/**
 * Barber Turns settings API.
 *
 * Exposes read/write endpoints guarded by owner role and supports
 * regenerating the TV token.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once APP_ROOT . '/api/auth_check.php';
require_once INC_PATH . '/security.php';
require_once INC_PATH . '/settings_model.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($action) {
        case 'get':
            handle_get();
            break;
        case 'save':
            ensure_post($method);
            handle_save();
            break;
        case 'regenerate_tv_token':
            ensure_post($method);
            handle_regenerate_token();
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
 * Return current settings (requires login; viewers allowed).
 */
function handle_get(): void
{
    api_require_user();
    echo json_encode(['data' => settings_get()]);
}

/**
 * Save settings (owner only).
 */
function handle_save(): void
{
    $user = api_require_role('owner');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('invalid_csrf');
    }

    $payload = [
        'shop_name' => $_POST['shop_name'] ?? '',
        'logo_url' => $_POST['logo_url'] ?? '',
        'theme' => $_POST['theme'] ?? '',
        'poll_interval_ms' => $_POST['poll_interval_ms'] ?? 3000,
    ];

    $updated = settings_update($payload, $user['role']);

    echo json_encode(['data' => $updated]);
}

/**
 * Regenerate TV token (owner only).
 */
function handle_regenerate_token(): void
{
    $user = api_require_role('owner');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('invalid_csrf');
    }

    $token = settings_regenerate_tv_token($user['role']);
    echo json_encode(['data' => ['tv_token' => $token]]);
}

/**
 * Ensure request method is POST.
 */
function ensure_post(string $method): void
{
    if (strtoupper($method) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'method_not_allowed']);
        exit;
    }
}
