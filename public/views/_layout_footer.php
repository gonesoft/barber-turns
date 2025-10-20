<?php
/**
 * Barber Turns shared HTML footer layout.
 */

declare(strict_types=1);

$baseUrl = rtrim(bt_config()['base_url'] ?? '/', '/');
?>
</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y'); ?> Barber Turns</p>
</footer>
<script src="<?= sanitize_text($baseUrl); ?>/assets/js/app.js" defer></script>
<script src="<?= sanitize_text($baseUrl); ?>/assets/js/tv.js" defer></script>
<script src="<?= sanitize_text($baseUrl); ?>/assets/js/auth.js" defer></script>
</body>
</html>
