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
header('Location: /login');
exit;
