<?php
/**
 * Barber Turns settings view.
 *
 * Provides owner controls for shop configuration, barber management,
 * TV token rotation, and user role administration.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/settings_model.php';
require_once __DIR__ . '/../../includes/barber_model.php';
require_once __DIR__ . '/../../includes/user_model.php';

$currentUser = current_user();
$actorRole = $currentUser['role'] ?? 'viewer';

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        try {
            switch ($formAction) {
                case 'save_settings':
                    settings_update([
                        'shop_name' => $_POST['shop_name'] ?? '',
                        'logo_url' => $_POST['logo_url'] ?? '',
                        'theme' => $_POST['theme'] ?? 'light',
                        'poll_interval_ms' => $_POST['poll_interval_ms'] ?? 3000,
                    ], $actorRole);
                    $messages[] = 'Settings updated successfully.';
                    break;

                case 'regenerate_tv_token':
                    $newToken = settings_regenerate_tv_token($actorRole);
                    $messages[] = 'TV token regenerated.';
                    $_POST['latest_tv_token'] = $newToken;
                    break;

                case 'barbers_save':
                    $barberInput = $_POST['barber'] ?? [];
                    $orderPairs = [];

                    foreach ($barberInput as $idKey => $row) {
                        $id = (int)$idKey;
                        $name = trim((string)($row['name'] ?? ''));
                        $status = $row['status'] ?? 'available';
                        $position = isset($row['position']) ? (int)$row['position'] : PHP_INT_MAX;
                        $delete = !empty($row['delete']);

                        if ($delete || $name === '') {
                            barber_delete($id, $actorRole);
                            continue;
                        }

                        barber_update($id, [
                            'name' => $name,
                            'status' => $status,
                        ], $actorRole);

                        $orderPairs[] = [
                            'id' => $id,
                            'position' => $position > 0 ? $position : PHP_INT_MAX,
                        ];
                    }

                    $newName = trim((string)($_POST['new_barber_name'] ?? ''));
                    if ($newName !== '') {
                        $newStatus = $_POST['new_barber_status'] ?? 'available';
                        $newBarber = barber_create($newName, $actorRole, $newStatus);
                        $newPosition = (int)($_POST['new_barber_position'] ?? 0);
                        $orderPairs[] = [
                            'id' => (int)$newBarber['id'],
                            'position' => $newPosition > 0 ? $newPosition : PHP_INT_MAX,
                        ];
                    }

                    if (!empty($orderPairs)) {
                        usort($orderPairs, static function (array $a, array $b): int {
                            return [$a['position'], $a['id']] <=> [$b['position'], $b['id']];
                        });
                        barber_reorder(array_column($orderPairs, 'id'), $actorRole);
                    }

                    $messages[] = 'Barber roster updated.';
                    break;

                case 'user_role_update':
                    $userId = (int)($_POST['user_id'] ?? 0);
                    $role = $_POST['role'] ?? 'viewer';
                    users_set_role($userId, $role, $actorRole);
                    $messages[] = 'User role updated.';
                    break;

                default:
                    $errors[] = 'Unknown action.';
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$settings = settings_get();
$barbers = barber_list();
$users = users_list();

$tvToken = $_POST['latest_tv_token'] ?? ($settings['tv_token'] ?? '');
$baseUrl = rtrim(bt_config()['base_url'] ?? '', '/');
$tvUrl = $tvToken !== '' ? $baseUrl . '/tv?token=' . $tvToken : '';
$csrf = csrf_token();

$statusOptions = [
    'available' => 'Available',
    'busy_walkin' => 'Busy · Walk-In',
    'busy_appointment' => 'Busy · Appointment',
    'inactive' => 'Inactive',
];

$themeOptions = [
    'light' => 'Light',
    'dark' => 'Dark',
];

$roleOptions = [
    'viewer' => 'Viewer',
    'frontdesk' => 'Front-Desk',
    'owner' => 'Owner',
];
?>
<section class="view-settings">
    <header class="settings-header">
        <h2>Shop Settings</h2>
    </header>

    <?php if (!empty($messages)): ?>
        <div class="settings-alert settings-alert--success">
            <ul>
                <?php foreach ($messages as $message): ?>
                    <li><?= sanitize_text($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="settings-alert settings-alert--error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize_text($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="settings-card">
        <h3>General</h3>
        <form method="post" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= sanitize_text($csrf); ?>">
            <input type="hidden" name="form_action" value="save_settings">

            <label class="form-field">
                <span class="form-label">Shop Name</span>
                <input type="text" name="shop_name" value="<?= sanitize_text($settings['shop_name'] ?? ''); ?>" required>
            </label>

            <label class="form-field">
                <span class="form-label">Logo URL</span>
                <input type="url" name="logo_url" value="<?= sanitize_text($settings['logo_url'] ?? ''); ?>" placeholder="https://">
            </label>

            <label class="form-field">
                <span class="form-label">Theme</span>
                <select name="theme">
                    <?php foreach ($themeOptions as $value => $label): ?>
                        <option value="<?= $value; ?>" <?= ($settings['theme'] ?? 'light') === $value ? 'selected' : ''; ?>>
                            <?= sanitize_text($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="form-field">
                <span class="form-label">Polling Interval (ms)</span>
                <input type="number" name="poll_interval_ms" min="1000" max="10000" step="500"
                       value="<?= (int)($settings['poll_interval_ms'] ?? 3000); ?>">
                <small class="form-hint">Controls how often apps refresh the queue (1-10 seconds).</small>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </section>

    <section class="settings-card">
        <h3>TV Display Link</h3>
        <p class="settings-text">
            Share this URL with the TV display. Regenerating the token immediately invalidates old links.
        </p>
        <?php if ($tvUrl): ?>
            <code class="tv-link"><?= sanitize_text($tvUrl); ?></code>
        <?php endif; ?>
        <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= sanitize_text($csrf); ?>">
            <input type="hidden" name="form_action" value="regenerate_tv_token">
            <button type="submit" class="btn btn-warning">Regenerate TV Token</button>
        </form>
    </section>

    <section class="settings-card">
        <h3>Barber Roster</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize_text($csrf); ?>">
            <input type="hidden" name="form_action" value="barbers_save">

            <table class="settings-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Position</th>
                    <th>Remove</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($barbers)): ?>
                    <tr>
                        <td colspan="4" class="settings-table__empty">No barbers yet — add your first below.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($barbers as $barber): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="barber[<?= $barber['id']; ?>][id]" value="<?= $barber['id']; ?>">
                                <input type="text" name="barber[<?= $barber['id']; ?>][name]"
                                       value="<?= sanitize_text($barber['name']); ?>" required>
                            </td>
                            <td>
                                <select name="barber[<?= $barber['id']; ?>][status]">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $barber['status'] === $value ? 'selected' : ''; ?>>
                                            <?= sanitize_text($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="barber[<?= $barber['id']; ?>][position]"
                                       value="<?= (int)$barber['position']; ?>" min="1">
                            </td>
                            <td class="settings-table__delete">
                                <label>
                                    <input type="checkbox" name="barber[<?= $barber['id']; ?>][delete]" value="1">
                                    Remove
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td>
                        <input type="text" name="new_barber_name" placeholder="Add new barber">
                    </td>
                    <td>
                        <select name="new_barber_status">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= $value; ?>"><?= sanitize_text($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="new_barber_position" min="1" placeholder="Position">
                    </td>
                    <td></td>
                </tr>
                </tfoot>
            </table>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Barber Changes</button>
            </div>
        </form>
    </section>

    <section class="settings-card">
        <h3>User Roles</h3>
        <p class="settings-text">Promote or demote team members. At least one owner must remain.</p>
        <?php if (empty($users)): ?>
            <p class="settings-table__empty">No users found.</p>
        <?php else: ?>
            <table class="settings-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Provider</th>
                    <th>Role</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= sanitize_text($user['name'] ?? ''); ?></td>
                        <td><?= sanitize_text($user['email'] ?? ''); ?></td>
                        <td><?= sanitize_text(ucfirst($user['oauth_provider'] ?? '')); ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?= sanitize_text($csrf); ?>">
                                <input type="hidden" name="form_action" value="user_role_update">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>">
                                <select name="role">
                                    <?php foreach ($roleOptions as $value => $label): ?>
                                        <option value="<?= $value; ?>" <?= $user['role'] === $value ? 'selected' : ''; ?>>
                                            <?= sanitize_text($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-secondary">Update</button>
                            </form>
                        </td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</section>
