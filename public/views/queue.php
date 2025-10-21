<?php
/**
 * Barber Turns queue view placeholder.
 */

declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? 'viewer';
$isViewer = $role === 'viewer';
?>
<section class="view-queue">
    <header class="queue-header">
        <h2>Queue</h2>
        <span class="badge <?= $isViewer ? 'badge-viewer' : 'badge-active'; ?>">
            <?= $isViewer ? 'View-only' : sanitize_text(ucfirst($role)); ?>
        </span>
    </header>
    <?php if ($isViewer): ?>
        <p class="queue-note">You are in view-only mode. Status toggles and reordering are disabled.</p>
    <?php endif; ?>
    <div id="barber-list">
        <p>Queue UI coming soon.</p>
    </div>
    <div class="queue-controls">
        <button class="btn btn-status" <?= $isViewer ? 'disabled' : ''; ?>>Toggle Status</button>
        <button class="btn btn-reorder" <?= $isViewer ? 'disabled' : ''; ?>>Reorder Queue</button>
    </div>
</section>
