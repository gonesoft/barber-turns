<?php
/**
 * Barber Turns TV view.
 */

declare(strict_types=1);

require_once APP_ROOT . '/includes/settings_model.php';

$page_title = 'TV Display';
$token = $_GET['token'] ?? '';
$settings = $token !== '' ? settings_find_by_tv_token($token) : null;

if ($settings === null) {
    http_response_code(403);
    include APP_ROOT . '/public/views/_layout_header.php';
    ?>
    <section class="view-tv view-tv--error">
        <h2>TV Display</h2>
        <p class="tv-error">Invalid or missing TV token. Please request a fresh TV link from the front desk.</p>
    </section>
    <?php
    include APP_ROOT . '/public/views/_layout_footer.php';
    return;
}

$pollMs = (int)($settings['poll_interval_ms'] ?? 3000);
$shopName = 'Finest Cut\'z Dominican Barbershop';

include APP_ROOT . '/public/views/_layout_header.php';
?>
<section class="view-tv" data-token="<?= sanitize_text($token); ?>" data-poll="<?= $pollMs; ?>">
    <header class="tv-header">
        <div>
            <h2 id="tv-shop-name"><?= sanitize_text($shopName); ?></h2>
            <p class="tv-subtitle">Walk-in and Appointment Queue</p>
        </div>
        <p id="tv-last-updated" class="tv-updated">Updating…</p>
    </header>
    <div id="tv-alert" class="tv-alert" hidden></div>
    <div id="tv-queue" class="tv-grid">
        <p class="tv-empty">Loading queue…</p>
    </div>
</section>
<?php
include APP_ROOT . '/public/views/_layout_footer.php';
