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
require_once dirname(__DIR__) . '/bootstrap.php';
require_once APP_ROOT . '/api/auth_check.php';
require_once INC_PATH . '/security.php';
require_once INC_PATH . '/barber_model.php';
require_once INC_PATH . '/queue_logic.php';
require_once INC_PATH . '/settings_model.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

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
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    if ($token === '' && $search !== null && $search !== '') {
        $term = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $barbers = array_values(array_filter($barbers, static function (array $barber) use ($term): bool {
            $name = function_exists('mb_strtolower') ? mb_strtolower($barber['name'] ?? '') : strtolower($barber['name'] ?? '');

            return str_contains($name, $term);
        }));
    }

    $barbers = array_map('normalize_barber_record', $barbers);

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
    if ($method !== 'POST') {
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

/**
 * Fetch a single barber (admin/owner only).
 */
function handle_get(): void
{
    api_require_role('admin');
    $barberId = sanitize_int($_GET['barber_id'] ?? null);

    if ($barberId === null || $barberId <= 0) {
        throw new InvalidArgumentException('invalid_barber_id');
    }

    $barber = barber_find($barberId);
    if ($barber === null) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        return;
    }

    echo json_encode(['data' => normalize_barber_record($barber)]);
}

/**
 * Create a barber (admin/owner only).
 */
function handle_create(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('name_required');
    }

    $status = isset($payload['status']) ? (string)$payload['status'] : 'available';

    $barber = barber_create($name, $actor['role'], $status);

    echo json_encode(['data' => normalize_barber_record($barber)]);
}

/**
 * Update a barber (admin/owner only).
 */
function handle_update(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $barberId = sanitize_int($payload['barber_id'] ?? null);
    if ($barberId === null || $barberId <= 0) {
        throw new InvalidArgumentException('invalid_barber_id');
    }

    $updates = [];

    if (array_key_exists('name', $payload)) {
        $name = trim((string)$payload['name']);
        if ($name === '') {
            throw new InvalidArgumentException('name_required');
        }
        $updates['name'] = $name;
    }

    if (array_key_exists('status', $payload)) {
        $updates['status'] = (string)$payload['status'];
    }

    if (empty($updates)) {
        throw new InvalidArgumentException('no_updates');
    }

    $updated = barber_update($barberId, $updates, $actor['role']);

    echo json_encode(['data' => normalize_barber_record($updated)]);
}

/**
 * Delete a barber (admin/owner only).
 */
function handle_delete(): void
{
    $actor = api_require_role('admin');
    $payload = read_json_payload();

    $barberId = sanitize_int($payload['barber_id'] ?? null);
    if ($barberId === null || $barberId <= 0) {
        throw new InvalidArgumentException('invalid_barber_id');
    }

    barber_delete($barberId, $actor['role']);

    echo json_encode(['data' => ['deleted' => true]]);
}

/**
 * Normalize a barber record for JSON responses.
 *
 * @param array<string,mixed> $barber
 * @return array<string,mixed>
 */
function normalize_barber_record(array $barber): array
{
    $barber['id'] = (int)($barber['id'] ?? 0);
    $barber['position'] = isset($barber['position']) ? (int)$barber['position'] : 0;

    if (!empty($barber['busy_since'])) {
        $dt = new DateTime((string)$barber['busy_since'], new DateTimeZone(date_default_timezone_get()));
        $barber['busy_since'] = $dt->format(DateTimeInterface::ATOM);
    } else {
        $barber['busy_since'] = null;
    }

    return $barber;
}
