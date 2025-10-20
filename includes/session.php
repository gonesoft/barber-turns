<?php
/**
 * Barber Turns session bootstrap with secure cookie defaults.
 *
 * bt_start_session() ensures a hardened session is active before use.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Start PHP session with secure cookie attributes.
 */
function bt_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = bt_config();
    $security = $config['security'];

    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $security['cookie_domain'] ?? '',
        'secure' => (bool)($security['cookie_secure'] ?? true),
        'httponly' => true,
        'samesite' => $security['cookie_samesite'] ?? 'Lax',
    ];

    session_name($security['session_name'] ?? 'barberturns_session');
    session_set_cookie_params($cookieParams);

    if (ini_get('session.use_strict_mode') !== '1') {
        ini_set('session.use_strict_mode', '1');
    }
    if (ini_get('session.cookie_httponly') !== '1') {
        ini_set('session.cookie_httponly', '1');
    }
    if (ini_get('session.use_only_cookies') !== '1') {
        ini_set('session.use_only_cookies', '1');
    }

    session_start();
}
