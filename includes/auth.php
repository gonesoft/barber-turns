<?php
/**
 * Barber Turns authentication helpers.
 *
 * Provides session-backed user retrieval and guard functions for routes.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Return the currently authenticated user from the session, if any.
 */
function current_user(): ?array
{
    bt_start_session();

    return $_SESSION['user'] ?? null;
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
 * Ensure the current user has owner privileges.
 *
 * @return array Authenticated owner user data
 */
function require_owner(): array
{
    $user = require_login();
    if (($user['role'] ?? null) !== 'owner') {
        http_response_code(403);
        exit('Forbidden: owner role required.');
    }

    return $user;
}

/**
 * Persist the provided user payload into the session.
 */
function login_user(array $user): void
{
    bt_start_session();
    $_SESSION['user'] = $user;
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
