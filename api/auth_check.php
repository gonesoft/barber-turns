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
 * Require an authenticated user; return normalized payload.
 *
 * @return array<string,mixed>
 */
function api_require_user(): array
{
    return require_login();
}

/**
 * Ensure caller has at least a specific role.
 *
 * @return array<string,mixed>
 */
function api_require_role(string $role): array
{
    return require_role($role);
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
