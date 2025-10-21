<?php
/**
 * Barber Turns login view placeholder.
 */

declare(strict_types=1);

$baseUrl = rtrim(bt_config()['base_url'] ?? '', '/');
$currentUser = current_user();
$isViewer = ($currentUser['role'] ?? '') === 'viewer';
?>
<section class="view-login">
    <h2>Login</h2>
    <?php if ($currentUser): ?>
        <p class="login-status">
            Signed in as <?= sanitize_text($currentUser['name'] ?? $currentUser['email']); ?>
            <?php if ($isViewer): ?>
                <span class="badge badge-viewer">View-only</span>
            <?php else: ?>
                <span class="badge badge-active"><?= sanitize_text(ucfirst($currentUser['role'] ?? '')); ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <p>Sign in with Google or Apple to manage the queue.</p>
    <div class="oauth-buttons">
        <a class="btn btn-google" href="<?= sanitize_text($baseUrl); ?>/auth/google_start.php">Continue with Google</a>
        <a class="btn btn-apple" href="<?= sanitize_text($baseUrl); ?>/auth/apple_start.php">Continue with Apple</a>
    </div>
</section>
