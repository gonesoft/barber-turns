<?php
/**
 * Barber Turns user management helpers.
 *
 * Supports listing users and performing CRUD operations with admin/owner restrictions.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/passwords.php';

const USER_ALLOWED_ROLES = ['viewer', 'frontdesk', 'admin', 'owner'];
const USER_MANAGE_ROLES = ['admin', 'owner'];

/**
 * Return users ordered by role priority then name.
 *
 * @param string|null $search Optional case-insensitive search string.
 * @return array<int,array<string,mixed>>
 */
function users_list(?PDO $pdo = null, ?string $search = null): array
{
    $pdo ??= bt_db();
    $search = $search !== null ? trim($search) : null;

    if ($search !== null && $search !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, username, role, oauth_provider, oauth_id
             FROM users
             WHERE LOWER(name) LIKE :term
                OR LOWER(email) LIKE :term
                OR LOWER(IFNULL(username, '')) LIKE :term
             ORDER BY FIELD(role, 'owner', 'admin', 'frontdesk', 'viewer'), name ASC"
        );
        $lowered = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $term = '%' . $lowered . '%';
        $stmt->execute([':term' => $term]);
    } else {
        $stmt = $pdo->query(
            "SELECT id, name, email, username, role, oauth_provider, oauth_id
             FROM users
             ORDER BY FIELD(role, 'owner', 'admin', 'frontdesk', 'viewer'), name ASC"
        );
    }

    return $stmt->fetchAll() ?: [];
}

/**
 * Fetch a single user by ID.
 *
 * @return array<string,mixed>|null
 */
function users_get(int $userId, ?PDO $pdo = null): ?array
{
    $pdo ??= bt_db();
    $stmt = $pdo->prepare(
        "SELECT id, name, email, username, role, oauth_provider, oauth_id
         FROM users
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $userId]);

    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Create a new user (admin/owner only).
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed> Newly created user row
 */
function users_create(array $payload, string $actorRole, ?PDO $pdo = null): array
{
    if (!users_can_manage($actorRole)) {
        throw new RuntimeException('insufficient_role');
    }

    $pdo ??= bt_db();
    $pdo->beginTransaction();
    $prepared = users_prepare_payload($payload, false);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (oauth_provider, oauth_id, email, name, username, password_hash, role)
             VALUES (:provider, :provider_id, :email, :name, :username, :password_hash, :role)'
        );
        $stmt->execute([
            ':provider' => $prepared['oauth_provider'],
            ':provider_id' => $prepared['oauth_id'],
            ':email' => $prepared['email'],
            ':name' => $prepared['name'],
            ':username' => $prepared['username'],
            ':password_hash' => $prepared['password_hash'],
            ':role' => $prepared['role'],
        ]);

        $userId = (int)$pdo->lastInsertId();
        $user = users_get($userId, $pdo);

        $pdo->commit();

        if ($user === null) {
            throw new RuntimeException('Unable to load user after insert.');
        }

        return $user;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Update an existing user (admin/owner only).
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function users_update(int $userId, array $payload, string $actorRole, ?PDO $pdo = null): array
{
    if (!users_can_manage($actorRole)) {
        throw new RuntimeException('insufficient_role');
    }

    $pdo ??= bt_db();
    $pdo->beginTransaction();

    try {
        $existing = users_get($userId, $pdo);

        if ($existing === null) {
            throw new RuntimeException('User not found.');
        }

        users_assert_role_balance($existing['role'], $payload['role'] ?? $existing['role'], $pdo);

        $prepared = users_prepare_payload($payload, true);

        $updates = [
            'email' => $prepared['email'] ?? $existing['email'],
            'name' => $prepared['name'] ?? $existing['name'],
            'username' => array_key_exists('username', $prepared) ? $prepared['username'] : $existing['username'],
            'role' => $prepared['role'] ?? $existing['role'],
        ];

        $setClauses = [
            'email = :email',
            'name = :name',
            'username = :username',
            'role = :role',
        ];

        $params = [
            ':email' => $updates['email'],
            ':name' => $updates['name'],
            ':username' => $updates['username'],
            ':role' => $updates['role'],
            ':id' => $userId,
        ];

        if (isset($prepared['password_hash'])) {
            $setClauses[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $prepared['password_hash'];
        }

        if (isset($prepared['oauth_provider'])) {
            $setClauses[] = 'oauth_provider = :provider';
            $params[':provider'] = $prepared['oauth_provider'];
        }

        if (array_key_exists('oauth_id', $prepared)) {
            $setClauses[] = 'oauth_id = :provider_id';
            $params[':provider_id'] = $prepared['oauth_id'];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setClauses) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $updated = users_get($userId, $pdo);

        $pdo->commit();

        if ($updated === null) {
            throw new RuntimeException('Unable to load user after update.');
        }

        return $updated;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Delete a user (admin/owner only).
 */
function users_delete(int $userId, string $actorRole, ?PDO $pdo = null): void
{
    if (!users_can_manage($actorRole)) {
        throw new RuntimeException('insufficient_role');
    }

    $pdo ??= bt_db();
    $pdo->beginTransaction();

    try {
        $existing = users_get($userId, $pdo);

        if ($existing === null) {
            throw new RuntimeException('User not found.');
        }

        users_assert_role_balance($existing['role'], null, $pdo);

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Failed to delete user.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Update a user's role while preserving historical compatibility (owner-only form).
 *
 * @return array<string,mixed> Updated user row
 */
function users_set_role(int $userId, string $role, string $actorRole, ?PDO $pdo = null): array
{
    $payload = ['role' => $role];

    return users_update($userId, $payload, $actorRole, $pdo);
}

/**
 * Determine if the acting user can manage accounts.
 */
function users_can_manage(string $actorRole): bool
{
    return in_array($actorRole, USER_MANAGE_ROLES, true);
}

/**
 * Normalize and validate input payload.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function users_prepare_payload(array $payload, bool $isUpdate): array
{
    $normalized = [];

    if (isset($payload['email'])) {
        $email = trim((string)$payload['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }
        $normalized['email'] = strtolower($email);
    } elseif (!$isUpdate) {
        throw new InvalidArgumentException('Email is required.');
    }

    if (isset($payload['name'])) {
        $name = trim((string)$payload['name']);
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        $normalized['name'] = $name;
    } elseif (!$isUpdate) {
        throw new InvalidArgumentException('Name is required.');
    }

    if (array_key_exists('username', $payload)) {
        $username = trim((string)$payload['username']);
        $normalized['username'] = $username !== '' ? $username : null;
    } elseif (!$isUpdate) {
        $normalized['username'] = null;
    }

    if (isset($payload['role'])) {
        $role = (string)$payload['role'];
        if (!in_array($role, USER_ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        $normalized['role'] = $role;
    } elseif (!$isUpdate) {
        $normalized['role'] = 'viewer';
    }

    if (isset($payload['password'])) {
        $password = (string)$payload['password'];
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $normalized['password_hash'] = hash_password($password);
    } elseif (!$isUpdate) {
        throw new InvalidArgumentException('Password is required.');
    }

    if (isset($payload['oauth_provider'])) {
        $provider = (string)$payload['oauth_provider'];
        if (!in_array($provider, ['google', 'apple', 'local'], true)) {
            throw new InvalidArgumentException('Invalid auth provider.');
        }
        $normalized['oauth_provider'] = $provider;
    } elseif (!$isUpdate) {
        $normalized['oauth_provider'] = 'local';
    }

    if (array_key_exists('oauth_id', $payload)) {
        $normalized['oauth_id'] = $payload['oauth_id'] !== null ? (string)$payload['oauth_id'] : null;
    } elseif (!$isUpdate) {
        $normalized['oauth_id'] = null;
    }

    return $normalized;
}

/**
 * Ensure at least one owner and one high-privilege (admin/owner) remain.
 */
function users_assert_role_balance(string $currentRole, ?string $newRole, PDO $pdo): void
{
    if ($currentRole === 'owner' && $newRole !== 'owner') {
        $ownerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
        if ($ownerCount <= 1) {
            throw new RuntimeException('At least one owner is required.');
        }
    }

    $highRoles = ['admin', 'owner'];
    if (in_array($currentRole, $highRoles, true) && ($newRole === null || !in_array($newRole, $highRoles, true))) {
        $placeholders = implode(',', array_fill(0, count($highRoles), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ($placeholders)");
        $stmt->execute($highRoles);
        $remaining = (int)$stmt->fetchColumn();
        if ($remaining <= 1) {
            throw new RuntimeException('At least one admin/owner is required.');
        }
    }
}
