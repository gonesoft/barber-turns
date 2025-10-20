<?php
/**
 * Barber Turns security helpers for CSRF protection and sanitization.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Retrieve or generate the CSRF token for the active session.
 */
function csrf_token(): string
{
    bt_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 */
function verify_csrf_token(?string $token): bool
{
    bt_start_session();
    if (!isset($_SESSION['csrf_token']) || $token === null) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output for HTML contexts.
 */
function sanitize_text(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize integer input, returning null when invalid.
 */
function sanitize_int(mixed $value): ?int
{
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    return (int)$value;
}
