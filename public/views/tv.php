<?php
/**
 * Barber Turns TV view.
 *
 * Validates TV token and renders a read-only queue optimized for large displays.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/settings_model.php';

$page_title = 'TV Display';
$token = $_GET['token'] ?? '';
$settings = null;
$isInvalid = false;

if ($token !== '') {
    $settings = settings_find_by_tv_token($token);
}

if ($token === '' || $settings === null) {
    http_response_code(403);
    $isInvalid = true;
}

if ($isInvalid): ?>
<section class="view-tv view-tv--error">
    <h2>TV Display</h2>
    <p class="tv-error">Invalid or missing TV token. Please request a fresh TV link from the front desk.</p>
</section>
<?php
    return;
endif;

$pollMs = (int)($settings['poll_interval_ms'] ?? 3000);
$shopName = $settings['shop_name'] ?? 'Barber Turns';
?>
<section class="view-tv" data-token="<?= sanitize_text($token); ?>" data-poll="<?= $pollMs; ?>">
    <header class="tv-header">
        <div>
            <h2 id="tv-shop-name"><?= sanitize_text($shopName); ?></h2>
            <p class="tv-subtitle">Live Queue</p>
        </div>
        <p id="tv-last-updated" class="tv-updated">Updating…</p>
    </header>
    <div id="tv-alert" class="tv-alert" hidden></div>
    <div id="tv-queue" class="tv-grid">
        <p class="tv-empty">Loading queue…</p>
    </div>
</section>
