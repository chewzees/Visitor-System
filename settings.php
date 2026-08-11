<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([current_user()['id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        flash_set('error', 'Current password is incorrect.');
    } elseif (strlen($new) < 6) {
        flash_set('error', 'New password must be at least 6 characters.');
    } elseif ($new !== $confirm) {
        flash_set('error', 'Password confirmation does not match.');
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        log_activity($pdo, 'password_change', $user['username'] . ' changed password');
        flash_set('success', 'Password updated.');
    }
    redirect('settings.php');
}

$showPw = isset($_GET['password']);
$pairs = [
    'APP_NAME' => $config['APP_FULL_NAME'],
    'APP_URL' => $config['APP_URL'],
    'APP_TIMEZONE' => $config['APP_TIMEZONE'],
    'SESSION_LIFETIME' => $config['SESSION_LIFETIME'] . ' seconds',
    'APP_DEBUG' => $config['APP_DEBUG'] ? 'Yes' : 'No',
    'DEFAULT_LANG' => $config['DEFAULT_LANG'],
    'SUPPORTED_LANGS' => implode(', ', $config['SUPPORTED_LANGS']),
    'OVERDUE_HOURS' => (string) ($config['OVERDUE_HOURS'] ?? 8),
    'NOTIFY_HOST_ON_CHECKIN' => !empty($config['NOTIFY_HOST_ON_CHECKIN']) ? 'Yes' : 'No',
    'NOTIFY_HOST_ON_REGISTER' => !empty($config['NOTIFY_HOST_ON_REGISTER']) ? 'Yes' : 'No',
    'MAIL_FROM' => $config['MAIL_FROM'] ?? '',
];

render_header(__('settings'), 'settings');
?>
<div class="section-head">
    <div>
        <h2><?= e(__('settings')) ?></h2>
        <p>Current application configuration (read-only). Edit config files to change.</p>
    </div>
</div>

<div class="table-wrap" style="max-width:720px; margin-bottom:1.25rem;">
    <table class="config-table">
        <?php foreach ($pairs as $k => $v): ?>
            <tr>
                <th><?= e($k) ?></th>
                <td class="<?= $k === 'APP_DEBUG' && $v === 'No' ? 'badge-false' : '' ?>"><?= e((string) $v) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<a class="btn btn-solid" href="<?= e(app_url('settings.php?password=1')) ?>"><?= e(__('change_password')) ?></a>

<?php if ($showPw): ?>
<div class="form-card" style="margin-top:1.25rem; max-width:520px;">
    <h2 style="font-family:var(--serif); margin-top:0;">Change Password</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:0.8rem;">
            <label>Current password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="field" style="margin-bottom:0.8rem;">
            <label>New password</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="field" style="margin-bottom:0.8rem;">
            <label>Confirm password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button class="btn btn-solid" type="submit">Update</button>
        <a class="btn" href="<?= e(app_url('settings.php')) ?>">Cancel</a>
    </form>
</div>
<?php endif; ?>
<?php render_footer(); ?>
