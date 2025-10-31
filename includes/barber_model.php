<?php
/**
 * Barber Turns data access for barbers.
 *
 * Provides CRUD helpers, ordering utilities, and status management
 * with role-based guard rails for front-desk and owner workflows.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const BARBER_ALLOWED_STATUSES = [
    'available',
    'busy_walkin',
    'busy_appointment',
    'inactive',
];

/**
 * Return all barbers ordered by queue position.
 *
 * @return array<int,array<string,mixed>>
 */
function barber_list(?PDO $pdo = null): array
{
    $pdo ??= bt_db();
    $stmt = $pdo->query('SELECT * FROM barbers ORDER BY position ASC, id ASC');

    return $stmt->fetchAll() ?: [];
}

/**
 * Fetch a single barber by id.
 *
 * @return array<string,mixed>|null
 */
function barber_find(int $id, ?PDO $pdo = null): ?array
{
    $pdo ??= bt_db();
    $stmt = $pdo->prepare('SELECT * FROM barbers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $barber = $stmt->fetch();

    return $barber ?: null;
}

/**
 * Create a new barber (owner only). Returns the inserted record.
 *
 * @return array<string,mixed>
 */
function barber_create(string $name, string $actorRole, string $status = 'available', ?PDO $pdo = null): array
{
    barber_assert_admin($actorRole);
    barber_assert_status($status);

    $pdo ??= bt_db();
    $position = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM barbers')->fetchColumn();
    $busySince = in_array($status, ['busy_walkin', 'busy_appointment'], true) ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'INSERT INTO barbers (name, status, position, busy_since)
         VALUES (:name, :status, :position, :busy_since)'
    );
    $stmt->execute([
        ':name' => $name,
        ':status' => $status,
        ':position' => $position,
        ':busy_since' => $busySince,
    ]);

    return barber_find((int)$pdo->lastInsertId(), $pdo);
}

/**
 * Update barber fields (owner only). Returns updated record.
 *
 * @param array<string,mixed> $fields
 * @return array<string,mixed>
 */
function barber_update(int $id, array $fields, string $actorRole, ?PDO $pdo = null): array
{
    barber_assert_admin($actorRole);
    $pdo ??= bt_db();

    if (array_key_exists('status', $fields)) {
        $status = (string)$fields['status'];
        barber_assert_status($status);
        if (!array_key_exists('busy_since', $fields)) {
            $fields['busy_since'] = in_array($status, ['busy_walkin', 'busy_appointment'], true)
                ? date('Y-m-d H:i:s')
                : null;
        }
    }

    $allowed = ['name', 'status', 'position', 'busy_since'];
    $updates = [];
    $params = [':id' => $id];

    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        if ($key === 'status') {
            barber_assert_status((string)$value);
        }
        $placeholder = ':' . $key;
        $updates[] = "{$key} = {$placeholder}";
        $params[$placeholder] = $value;
    }

    if (empty($updates)) {
        return barber_find($id, $pdo) ?? [];
    }

    $sql = 'UPDATE barbers SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    barber_normalize_positions($pdo);

    $barber = barber_find($id, $pdo);
    if ($barber === null) {
        throw new RuntimeException('Barber not found after update.');
    }

    return $barber;
}

/**
 * Delete a barber (owner only) and normalize positions.
 */
function barber_delete(int $id, string $actorRole, ?PDO $pdo = null): void
{
    barber_assert_admin($actorRole);
    $pdo ??= bt_db();

    $stmt = $pdo->prepare('DELETE FROM barbers WHERE id = :id');
    $stmt->execute([':id' => $id]);

    barber_normalize_positions($pdo);
}

/**
 * Reorder barbers according to provided id sequence (frontdesk+owner).
 *
 * @param array<int,int> $orderedIds
 */
function barber_reorder(array $orderedIds, string $actorRole, ?PDO $pdo = null): void
{
    barber_assert_manage_role($actorRole);
    if (empty($orderedIds)) {
        return;
    }

    $pdo ??= bt_db();
    $pdo->beginTransaction();

    try {
        $count = count($orderedIds);
        $offset = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) FROM barbers')->fetchColumn();
        $offset = max($offset, $count) + 10;

        $tempStmt = $pdo->prepare('UPDATE barbers SET position = :position, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        foreach ($orderedIds as $index => $id) {
            $tempStmt->execute([
                ':position' => $offset + $index + 1,
                ':id' => (int)$id,
            ]);
        }

        $position = 1;
        foreach ($orderedIds as $id) {
            $tempStmt->execute([
                ':position' => $position++,
                ':id' => (int)$id,
            ]);
        }

        barber_normalize_positions($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Move a barber to the bottom of the queue.
 */
function barber_move_to_bottom(int $id, ?PDO $pdo = null): void
{
    $pdo ??= bt_db();
    $nextPosition = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM barbers')->fetchColumn();
    $stmt = $pdo->prepare('UPDATE barbers SET position = :position, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([
        ':position' => $nextPosition,
        ':id' => $id,
    ]);

    barber_normalize_positions($pdo);
}

/**
 * Normalize queue positions to a contiguous 1..N sequence.
 */
function barber_normalize_positions(?PDO $pdo = null): void
{
    $pdo ??= bt_db();
    $stmt = $pdo->query('SELECT id FROM barbers ORDER BY position ASC, id ASC');
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) {
        return;
    }

    $update = $pdo->prepare('UPDATE barbers SET position = :position WHERE id = :id');
    $position = 1;
    foreach ($ids as $id) {
        $update->execute([
            ':position' => $position++,
            ':id' => (int)$id,
        ]);
    }
}

/**
 * Guard: ensure status is valid.
 */
function barber_assert_status(string $status): void
{
    if (!in_array($status, BARBER_ALLOWED_STATUSES, true)) {
        throw new InvalidArgumentException('Invalid barber status: ' . $status);
    }
}

/**
 * Guard: ensure caller is owner role.
 */
function barber_assert_owner(string $role): void
{
    if ($role !== 'owner') {
        throw new RuntimeException('insufficient_role');
    }
}

/**
 * Guard: ensure caller has admin-equivalent privileges.
 */
function barber_assert_admin(string $role): void
{
    if (!in_array($role, ['admin', 'owner'], true)) {
        throw new RuntimeException('insufficient_role');
    }
}

/**
 * Guard: ensure caller can manage queue (frontdesk or owner).
 */
function barber_assert_manage_role(string $role): void
{
    if (!in_array($role, ['frontdesk', 'admin', 'owner'], true)) {
        throw new RuntimeException('insufficient_role');
    }
}
