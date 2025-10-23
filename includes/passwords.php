<?php
/**
 * Password hashing helpers for local authentication.
 */

declare(strict_types=1);

/**
 * Hash a plain-text password using Argon2id.
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
        'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
    ]);
}

/**
 * Verify a password against the stored hash.
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}
