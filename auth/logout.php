<?php
/**
 * Destroy session and redirect to login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

logout_user();
header('Location: /login');
exit;
