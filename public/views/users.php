<?php
/**
 * Barber Turns users management view.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';

$currentUser = current_user();
$role = $currentUser['role'] ?? 'viewer';
$isAdmin = in_array($role, ['admin', 'owner'], true);

if (!$isAdmin) {
    http_response_code(403);
    echo '<section class="view-users"><p>Forbidden.</p></section>';
    return;
}

$roleLabels = [
    'viewer' => 'Viewer',
    'frontdesk' => 'Front-Desk',
    'admin' => 'Admin',
    'owner' => 'Owner',
];
?>
<section class="view-users" data-role="<?= sanitize_text($role); ?>">
    <header class="users-header">
        <h2>Users</h2>
        <button type="button" class="btn btn-secondary" id="users-new-btn">New User</button>
    </header>

    <div id="users-feedback" class="users-feedback" role="alert" hidden></div>

    <div class="users-layout">
        <aside class="users-search">
            <form id="users-search-form" class="users-search-form" autocomplete="off">
                <div class="form-field">
                    <label class="form-label" for="users-search-input">Search Users</label>
                    <input id="users-search-input" type="search" name="query" placeholder="Search by name, email, or username" spellcheck="false">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
            <ul id="users-results" class="users-results" aria-live="polite"></ul>
        </aside>

        <section class="users-form-card">
            <form id="users-form" class="users-form" autocomplete="off" novalidate>
                <input type="hidden" id="users-id" name="user_id" value="">

                <div class="users-form__row">
                    <div class="form-field">
                        <label class="form-label" for="users-name">Full Name</label>
                        <input id="users-name" type="text" name="name" required>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="users-email">Email</label>
                        <input id="users-email" type="email" name="email" required>
                    </div>
                </div>

                <div class="users-form__row">
                    <div class="form-field">
                        <label class="form-label" for="users-username">Username</label>
                        <input id="users-username" type="text" name="username" placeholder="Optional">
                        <p class="form-hint">Leave blank to use email for login.</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="users-role">Role</label>
                        <select id="users-role" name="role" required>
                            <?php foreach ($roleLabels as $value => $label): ?>
                                <option value="<?= sanitize_text($value); ?>"><?= sanitize_text($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label" for="users-password">Password</label>
                    <input id="users-password" type="password" name="password" placeholder="Enter a password">
                    <p class="form-hint" id="users-password-hint">Required for new local users. Leave blank to keep current password.</p>
                </div>

                <p class="users-form__note">
                    Auth Provider: <span id="users-provider-label">Local</span>
                </p>

                <div class="users-form-actions">
                    <button type="submit" class="btn btn-primary" id="users-save-btn">Save</button>
                    <button type="button" class="btn btn-secondary" id="users-reset-btn">Reset</button>
                    <button type="button" class="btn btn-warning" id="users-delete-btn" hidden>Delete</button>
                </div>
            </form>
        </section>
    </div>
</section>
