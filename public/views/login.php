<?php
/**
 * Barber Turns login view placeholder.
 */

declare(strict_types=1);

$baseUrl = rtrim(bt_config()['base_url'] ?? '', '/');
?>
<section class="view-login">
    <h2>Login</h2>
    <p>Sign in with Google or Apple to manage the queue.</p>
    <div class="oauth-buttons">
        <a class="btn btn-google" href="<?= sanitize_text($baseUrl); ?>/auth/google_start.php">Continue with Google</a>
        <a class="btn btn-apple" href="<?= sanitize_text($baseUrl); ?>/auth/apple_start.php">Continue with Apple</a>
    </div>
</section>
