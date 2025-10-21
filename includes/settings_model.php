<?php
/**
 * Barber Turns settings data access and helpers.
 *
 * Encapsulates shop settings CRUD with owner-only guard rails.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * Fetch the singleton settings row.
 *
 * @return array<string,mixed>
 */
function settings_get(?PDO $pdo = null): array
{
    $pdo ??= bt_db();
    $stmt = $pdo->query('SELECT * FROM settings WHERE id = 1 LIMIT 1');
    $settings = $stmt->fetch();

    if ($settings === false) {
        throw new RuntimeException('Settings row not initialized. Run sql/database.sql.');
    }

    return $settings;
}

/**
 * Update settings (owner only). Returns updated row.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function settings_update(array $payload, string $actorRole, ?PDO $pdo = null): array
{
    settings_assert_owner($actorRole);
    $pdo ??= bt_db();

    $allowedThemes = ['light', 'dark'];
    $updates = [
        'shop_name' => trim((string)($payload['shop_name'] ?? 'Your Barber Shop')),
        'logo_url' => trim((string)($payload['logo_url'] ?? '')),
        'theme' => in_array($payload['theme'] ?? '', $allowedThemes, true) ? $payload['theme'] : 'light',
        'poll_interval_ms' => clamp_poll_interval((int)($payload['poll_interval_ms'] ?? 3000)),
    ];

    if ($updates['logo_url'] === '') {
        $updates['logo_url'] = null;
    }

    $stmt = $pdo->prepare(
        'UPDATE settings
         SET shop_name = :shop_name,
             logo_url = :logo_url,
             theme = :theme,
             poll_interval_ms = :poll_interval_ms,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = 1'
    );
    $stmt->execute([
        ':shop_name' => $updates['shop_name'],
        ':logo_url' => $updates['logo_url'],
        ':theme' => $updates['theme'],
        ':poll_interval_ms' => $updates['poll_interval_ms'],
    ]);

    return settings_get($pdo);
}

/**
 * Regenerate the TV token (owner only) and return the new value.
 */
function settings_regenerate_tv_token(string $actorRole, ?PDO $pdo = null): string
{
    settings_assert_owner($actorRole);
    $pdo ??= bt_db();

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'UPDATE settings
         SET tv_token = :token,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = 1'
    );
    $stmt->execute([':token' => $token]);

    return $token;
}

/**
 * Guard: owner-only operations.
 */
function settings_assert_owner(string $role): void
{
    if ($role !== 'owner') {
        throw new RuntimeException('insufficient_role');
    }
}

/**
 * Clamp poll interval to a sensible range (1s - 10s).
 */
function clamp_poll_interval(int $value): int
{
    return max(1000, min(10000, $value));
}
