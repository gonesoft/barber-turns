<?php
/**
 * Barber Turns user management helpers.
 *
 * Supports listing users and updating roles with owner-only restrictions.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const USER_ALLOWED_ROLES = ['viewer', 'frontdesk', 'owner'];

/**
 * Return users ordered by role priority then name.
 *
 * @return array<int,array<string,mixed>>
 */
function users_list(?PDO $pdo = null): array
{
    $pdo ??= bt_db();
    $stmt = $pdo->query(
        "SELECT id, name, email, role, oauth_provider, oauth_id
         FROM users
         ORDER BY FIELD(role, 'owner', 'frontdesk', 'viewer'), name ASC"
    );

    return $stmt->fetchAll() ?: [];
}

/**
 * Update a user's role (owner only) ensuring at least one owner remains.
 *
 * @return array<string,mixed> Updated user row
 */
function users_set_role(int $userId, string $role, string $actorRole, ?PDO $pdo = null): array
{
    if ($actorRole !== 'owner') {
        throw new RuntimeException('insufficient_role');
    }
    if (!in_array($role, USER_ALLOWED_ROLES, true)) {
        throw new InvalidArgumentException('Invalid role.');
    }

    $pdo ??= bt_db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $currentRole = $user['role'];
        if ($currentRole === 'owner' && $role !== 'owner') {
            $ownerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
            if ($ownerCount <= 1) {
                throw new RuntimeException('At least one owner is required.');
            }
        }

        $update = $pdo->prepare('UPDATE users SET role = :role, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([
            ':role' => $role,
            ':id' => $userId,
        ]);

        $fetch = $pdo->prepare(
            'SELECT id, name, email, role, oauth_provider, oauth_id
             FROM users WHERE id = :id LIMIT 1'
        );
        $fetch->execute([':id' => $userId]);
        $updated = $fetch->fetch();

        $pdo->commit();

        if (!$updated) {
            throw new RuntimeException('Unable to load user after update.');
        }

        return $updated;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
