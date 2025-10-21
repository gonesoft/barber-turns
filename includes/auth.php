<?php
/**
 * Barber Turns authentication helpers.
 *
 * Provides session-backed user retrieval and guard functions for routes.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Normalize a database user row before storing in the session.
 *
 * @param array<string,mixed> $user
 * @return array<string,mixed>
 */
function bt_normalize_user(array $user): array
{
    return [
        'id' => isset($user['id']) ? (int)$user['id'] : 0,
        'name' => $user['name'] ?? ($user['display_name'] ?? ''),
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'viewer',
        'oauth_provider' => $user['oauth_provider'] ?? ($user['provider'] ?? ''),
        'oauth_id' => $user['oauth_id'] ?? ($user['provider_id'] ?? ''),
    ];
}

/**
 * Return the currently authenticated user from the session, if any.
 */
function current_user(): ?array
{
    bt_start_session();

    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? bt_normalize_user($user) : null;
}

/**
 * Ensure a user is logged in, redirecting to /login when absent.
 *
 * @return array Authenticated user data
 */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        http_response_code(302);
        header('Location: /login');
        exit;
    }

    return $user;
}

/**
 * Ensure the current user meets the minimum role requirement.
 *
 * @return array Authenticated user data
 */
function require_role(string $minRole): array
{
    $hierarchy = [
        'viewer' => 0,
        'frontdesk' => 1,
        'owner' => 2,
    ];

    $user = require_login();
    $userRole = $user['role'] ?? 'viewer';

    if (!isset($hierarchy[$userRole], $hierarchy[$minRole])) {
        http_response_code(403);
        exit('Forbidden: invalid role configuration.');
    }

    if ($hierarchy[$userRole] < $hierarchy[$minRole]) {
        http_response_code(403);
        exit('Forbidden: insufficient permissions.');
    }

    return $user;
}

/**
 * Ensure the current user has owner privileges.
 *
 * @return array Authenticated owner user data
 */
function require_owner(): array
{
    return require_role('owner');
}

/**
 * Persist the provided user payload into the session.
 */
function login_user(array $user): void
{
    bt_start_session();
    $_SESSION['user'] = bt_normalize_user($user);
}

/**
 * Destroy user session and clear session cookie.
 */
function logout_user(): void
{
    bt_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
