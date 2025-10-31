<?php
/**
 * Barber Turns barber management view.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/barber_model.php';

$currentUser = current_user();
$role = $currentUser['role'] ?? 'viewer';
$isAdmin = in_array($role, ['admin', 'owner'], true);

if (!$isAdmin) {
    http_response_code(403);
    echo '<section class="view-barbers"><p>Forbidden.</p></section>';
    return;
}

$statusLabels = [
    'available' => 'Available',
    'busy_walkin' => 'Busy · Walk-In',
    'busy_appointment' => 'Busy · Appointment',
    'inactive' => 'Inactive',
];
?>
<section class="view-barbers" data-role="<?= sanitize_text($role); ?>">
    <header class="barbers-header">
        <h2>Barbers</h2>
        <button type="button" class="btn btn-secondary" id="barbers-new-btn">New Barber</button>
    </header>

    <div id="barbers-feedback" class="barbers-feedback" role="alert" hidden></div>

    <div class="barbers-layout">
        <aside class="barbers-search">
            <form id="barbers-search-form" class="barbers-search-form" autocomplete="off">
                <div class="form-field">
                    <label class="form-label" for="barbers-search-input">Search Barbers</label>
                    <input id="barbers-search-input" type="search" name="query" placeholder="Search by name" spellcheck="false">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
            <ul id="barbers-results" class="barbers-results" aria-live="polite"></ul>
        </aside>

        <section class="barbers-form-card">
            <form id="barbers-form" class="barbers-form" autocomplete="off" novalidate>
                <input type="hidden" id="barbers-id" name="barber_id" value="">

                <div class="barbers-form__row">
                    <div class="form-field">
                        <label class="form-label" for="barbers-name">Full Name</label>
                        <input id="barbers-name" type="text" name="name" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="barbers-status">Status</label>
                        <select id="barbers-status" name="status" required>
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= sanitize_text($value); ?>"><?= sanitize_text($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Status changes adjust availability immediately.</p>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="barbers-position">Queue Position</label>
                    <input id="barbers-position" type="number" name="position" value="" readonly>
                    <p class="form-hint">Reorder in the queue screen via drag-and-drop.</p>
                </div>

                <div class="barbers-form-actions">
                    <button type="submit" class="btn btn-primary" id="barbers-save-btn">Save</button>
                    <button type="button" class="btn btn-secondary" id="barbers-reset-btn">Reset</button>
                    <button type="button" class="btn btn-warning" id="barbers-delete-btn" hidden>Delete</button>
                </div>
            </form>
        </section>
    </div>
</section>
