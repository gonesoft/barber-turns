<?php
/**
 * Barber Turns barbers API.
 *
 * Supports listing, status transitions, and manual reorder with role enforcement.
 */

declare(strict_types=1);

use DateTime;
use DateTimeInterface;
use DateTimeZone;
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/barber_model.php';
require_once __DIR__ . '/../includes/queue_logic.php';
require_once __DIR__ . '/../includes/settings_model.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($action) {
        case 'list':
            handle_list();
            break;
        case 'status':
            ensure_post($method);
            handle_status();
            break;
        case 'order':
            ensure_post($method);
            handle_order();
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
 * List barbers in queue order with current server time.
 */
function handle_list(): void
{
    $token = $_GET['token'] ?? '';
    $settings = null;
    $user = null;

    if ($token !== '') {
        $settings = settings_find_by_tv_token($token);
        if ($settings === null) {
            http_response_code(403);
            echo json_encode(['error' => 'forbidden', 'reason' => 'invalid_tv_token']);
            return;
        }
    } else {
        $user = api_require_user();
        $settings = settings_get();
    }

    $barbers = barber_list();

    $barbers = array_map(static function (array $barber): array {
        $barber['position'] = (int)$barber['position'];
        $barber['id'] = (int)$barber['id'];
        if (isset($barber['busy_since']) && $barber['busy_since'] !== null) {
            $dt = new DateTime($barber['busy_since'], new DateTimeZone(date_default_timezone_get()));
            $barber['busy_since'] = $dt->format(DateTimeInterface::ATOM);
        }

        return $barber;
    }, $barbers);

    echo json_encode([
        'data' => $barbers,
        'user' => $user,
        'access' => $token !== '' ? 'token' : 'session',
        'server_time' => date(DATE_ATOM),
        'poll_interval_ms' => (int)($settings['poll_interval_ms'] ?? 3000),
        'settings' => [
            'shop_name' => $settings['shop_name'] ?? 'Barber Turns',
            'theme' => $settings['theme'] ?? 'light',
        ],
    ]);
}

/**
 * Apply a status transition respecting queue rules.
 */
function handle_status(): void
{
    $user = api_require_manage_role();
    $payload = read_json_payload();

    $barberId = sanitize_int($payload['barber_id'] ?? null);
    $targetStatus = $payload['status'] ?? null;

    if ($barberId === null || !is_string($targetStatus)) {
        throw new RuntimeException('invalid_payload');
    }

    $updated = queue_apply_transition($barberId, $targetStatus, $user['role']);

    echo json_encode(['data' => $updated]);
}

/**
 * Reorder queue using supplied array of barber IDs.
 */
function handle_order(): void
{
    $user = api_require_manage_role();
    $payload = read_json_payload();

    if (!isset($payload['order']) || !is_array($payload['order'])) {
        throw new RuntimeException('invalid_payload');
    }

    $ids = array_filter(array_map('intval', $payload['order']));
    if (empty($ids)) {
        throw new RuntimeException('invalid_order');
    }

    queue_manual_reorder($ids, $user['role']);

    echo json_encode(['success' => true]);
}

/**
 * Ensure the request method is POST.
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

/**
 * Read JSON body into associative array.
 *
 * @return array<string,mixed>
 */
function read_json_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}
