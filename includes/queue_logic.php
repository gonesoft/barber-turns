<?php
/**
 * Barber Turns queue logic helpers.
 *
 * Applies status transition rules, enforces role permissions, and
 * keeps queue ordering normalized.
 */

declare(strict_types=1);

require_once __DIR__ . '/barber_model.php';

/**
 * Ensure the acting role may manage the queue.
 */
function queue_assert_manage_role(string $role): void
{
    barber_assert_manage_role($role);
}

/**
 * Cycle a barber to the next status in the rotation.
 *
 * @return array<string,mixed> Updated barber row
 */
function queue_cycle_status(int $barberId, string $actorRole): array
{
    queue_assert_manage_role($actorRole);
    $barber = barber_find($barberId);
    if ($barber === null) {
        throw new RuntimeException('Barber not found.');
    }

    $nextStatus = queue_next_status($barber['status'] ?? 'available');

    return queue_apply_transition($barberId, $nextStatus, $actorRole);
}

/**
 * Apply a specific status transition respecting PRD rules.
 *
 * @return array<string,mixed> Updated barber row
 */
function queue_apply_transition(int $barberId, string $targetStatus, string $actorRole): array
{
    queue_assert_manage_role($actorRole);
    barber_assert_status($targetStatus);

    $pdo = bt_db();

    try {
        $pdo->beginTransaction();

        $barber = barber_find($barberId, $pdo);
        if ($barber === null) {
            throw new RuntimeException('Barber not found.');
        }

        $currentStatus = $barber['status'];
        if ($currentStatus === $targetStatus) {
            $pdo->rollBack();

            return $barber;
        }

        $now = date('Y-m-d H:i:s');
        $busySince = $barber['busy_since'];

        if ($currentStatus === 'available' && $targetStatus === 'busy_appointment') {
            $busySince = $now;
        } elseif ($currentStatus === 'busy_appointment' && $targetStatus === 'available') {
            $busySince = null;
        } elseif ($currentStatus === 'available' && $targetStatus === 'busy_walkin') {
            $busySince = $now;
            barber_move_to_bottom($barberId, $pdo);
        } elseif ($currentStatus === 'busy_appointment' && $targetStatus === 'busy_walkin') {
            $busySince = $now;
            barber_move_to_bottom($barberId, $pdo);
        } elseif ($currentStatus === 'busy_walkin' && $targetStatus === 'busy_appointment') {
            $busySince = $now;
        } elseif ($currentStatus === 'busy_walkin' && $targetStatus === 'available') {
            $busySince = null;
        } elseif ($currentStatus === 'inactive' && $targetStatus !== 'inactive') {
            $busySince = $now;
            barber_move_to_bottom($barberId, $pdo);
        }

        $stmt = $pdo->prepare(
            'UPDATE barbers
             SET status = :status,
                 busy_since = :busy_since,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->bindValue(':status', $targetStatus);
        if ($busySince === null) {
            $stmt->bindValue(':busy_since', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':busy_since', $busySince);
        }
        $stmt->bindValue(':id', $barberId, PDO::PARAM_INT);
        $stmt->execute();

        barber_normalize_positions($pdo);

        $updated = barber_find($barberId, $pdo);
        if ($updated === null) {
            throw new RuntimeException('Barber not found after transition.');
        }

        $pdo->commit();

        return $updated;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Reorder the queue per supplied ID list (frontdesk or owner).
 *
 * @param array<int,int> $orderedIds
 */
function queue_manual_reorder(array $orderedIds, string $actorRole): void
{
    queue_assert_manage_role($actorRole);
    barber_reorder($orderedIds, $actorRole);
}

/**
 * Helper to compute the next status in the rotation cycle.
 */
function queue_next_status(string $currentStatus): string
{
    return match ($currentStatus) {
        'available' => 'busy_appointment',
        'busy_appointment' => 'available',
        'busy_walkin' => 'available',
        default => 'available',
    };
}
