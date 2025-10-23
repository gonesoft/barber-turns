<?php
/**
 * Local username/email + password authentication endpoint.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once INC_PATH . '/session.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/passwords.php';
require_once INC_PATH . '/security.php';

bt_start_session();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /login');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    record_login_error('Security token expired. Please try again.');
    header('Location: /login');
    exit;
}

$identifier = trim((string)($_POST['identifier'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($identifier === '' || $password === '') {
    record_attempt(true);
    record_login_error('Please provide both username/email and password.');
    header('Location: /login');
    exit;
}

if (is_rate_limited()) {
    record_login_error('Too many login attempts. Please wait and try again.');
    header('Location: /login');
    exit;
}

$pdo = bt_db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :identifier OR email = :identifier LIMIT 1');
$stmt->execute(['identifier' => $identifier]);
$user = $stmt->fetch();

if (!$user || empty($user['password_hash']) || !verify_password($password, $user['password_hash'])) {
    record_attempt(true);
    record_login_error('Invalid credentials.');
    header('Location: /login');
    exit;
}

record_attempt(false, true);

$pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

login_user($user);

header('Location: /queue');
exit;

/**
 * Store a flash error message for the login form.
 */
function record_login_error(string $message): void
{
    $_SESSION['login_error'] = $message;
}

/**
 * Track login attempts per IP within a rolling window.
 */
function record_attempt(bool $increment, bool $reset = false): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'local_login_attempts';

    if ($reset) {
        unset($_SESSION[$key][$ip]);
        return;
    }

    if (!isset($_SESSION[$key][$ip])) {
        $_SESSION[$key][$ip] = [];
    }

    // Remove expired attempts (older than 15 minutes)
    $_SESSION[$key][$ip] = array_filter(
        $_SESSION[$key][$ip],
        static fn(int $timestamp): bool => ($timestamp + 900) >= time()
    );

    if ($increment) {
        $_SESSION[$key][$ip][] = time();
    }
}

/**
 * Determine if the caller should be throttled.
 */
function is_rate_limited(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts = $_SESSION['local_login_attempts'][$ip] ?? [];

    $recent = array_filter(
        $attempts,
        static fn(int $timestamp): bool => ($timestamp + 900) >= time()
    );

    $_SESSION['local_login_attempts'][$ip] = $recent;

    return count($recent) >= 5;
}
