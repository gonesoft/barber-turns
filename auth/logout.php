<?php
/**
 * Destroy session and redirect to login.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once INC_PATH . '/auth.php';

// Ensure session is active before flush
bt_start_session();

logout_user();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

session_unset();
session_destroy();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Location: /logout_clear.html');
exit;
