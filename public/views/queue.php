<?php
/**
 * Barber Turns queue view placeholder.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/settings_model.php';

$user = current_user();
$role = $user['role'] ?? 'viewer';
$isViewer = $role === 'viewer';

try {
    $settings = settings_get();
    $pollMs = (int)($settings['poll_interval_ms'] ?? 3000);
} catch (Throwable $e) {
    $pollMs = 3000;
}
?>
<section class="view-queue" data-role="<?= sanitize_text($role); ?>" data-poll="<?= $pollMs; ?>">
    <header class="queue-header">
        <h2>Queue</h2>
        <span class="badge <?= $isViewer ? 'badge-viewer' : 'badge-active'; ?>">
            <?= $isViewer ? 'View-only' : sanitize_text(ucfirst($role)); ?>
        </span>
    </header>
    <?php if ($isViewer): ?>
        <p class="queue-note">You are in view-only mode. Status toggles and reordering are disabled.</p>
    <?php endif; ?>
    <div id="queue-alert" class="queue-alert" hidden></div>
    <div id="barber-list" class="queue-grid queue-root">
        <p class="queue-empty">Loading barbers…</p>
    </div>
</section>
