<?php
/**
 * Barber Turns TV view.
 */

declare(strict_types=1);

require_once APP_ROOT . '/includes/settings_model.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
    <div class="tv-header-wrap">
        <header class="tv-header">
            <div class="tv-header__text">
                <h2 id="tv-shop-name"><?= sanitize_text($shopName); ?></h2>
                <p class="tv-subtitle">Walk-in and Appointment Queue</p>
                <p id="tv-last-updated" class="tv-updated">Updating…</p>
            </div>
            <button type="button" id="tv-layout-toggle" class="tv-toggle" aria-pressed="false">
                <span class="tv-toggle__label">Vertical</span>
                <span class="tv-toggle__state">Off</span>
            </button>
        </header>
    </div>
    <div id="tv-alert" class="tv-alert" hidden></div>
    <div class="tv-queue-wrap">
        <div id="tv-queue" class="tv-grid">
            <p class="tv-empty">Loading queue…</p>
        </div>
    </div>
</section>
<?php
include APP_ROOT . '/public/views/_layout_footer.php';
