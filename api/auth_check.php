<?php
/**
 * Barber Turns API auth guards.
 *
 * Provides helpers to enforce session-based role permissions for API endpoints.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once INC_PATH . '/auth.php';

/**
 * Emit a JSON error response and terminate execution.
 *
 * @param int $status HTTP status code
 * @param string $error Machine-readable error string
 * @param string|null $reason Optional human-readable reason
 */
function api_abort(int $status, string $error, ?string $reason = null): void
{
    http_response_code($status);
    $payload = ['error' => $error];
    if ($reason !== null) {
        $payload['reason'] = $reason;
    }
    echo json_encode($payload);
    exit;
}

/**
 * Require an authenticated user; return normalized payload.
 *
 * @return array<string,mixed>
 */
function api_require_user(): array
{
    $user = current_user();
    if ($user !== null) {
        return $user;
    }

    api_abort(401, 'unauthorized', 'authentication_required');
}

/**
 * Ensure caller has at least a specific role.
 *
 * @return array<string,mixed>
 */
function api_require_role(string $role): array
{
    $hierarchy = [
        'viewer' => 0,
        'frontdesk' => 1,
        'admin' => 2,
        'owner' => 3,
    ];

    $user = api_require_user();
    $userRole = $user['role'] ?? 'viewer';

    if (!isset($hierarchy[$role])) {
        api_abort(500, 'server_error', 'invalid_role_configuration');
    }

    if (($hierarchy[$userRole] ?? -1) < $hierarchy[$role]) {
        api_abort(403, 'forbidden', 'insufficient_permissions');
    }

    return $user;
}

/**
 * Ensure caller can manage queue (frontdesk or owner).
 *
 * @return array<string,mixed>
 */
function api_require_manage_role(): array
{
    return api_require_role('frontdesk');
}
