<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$done = false;

$stmt = $pdo->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1');
$stmt->execute([$token]);
$user = $token !== '' ? $stmt->fetch() : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        $error = 'Reset link is invalid or expired.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($pass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
            log_activity($pdo, 'password_reset', $user['username'] . ' reset password via link', [
                'id' => (int) $user['id'],
                'username' => $user['username'],
            ]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — <?= e($config['APP_NAME']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,600&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<div class="login-stage" style="justify-content:center; align-items:center;">
    <div class="login-card" style="width:min(420px,100%);">
        <h2>Reset password</h2>
        <?php if ($done): ?>
            <p class="auth-sub">Password updated. You can sign in now.</p>
            <p class="auth-foot"><a href="<?= e(app_url('login.php')) ?>"><strong>Sign In</strong></a></p>
        <?php elseif (!$user): ?>
            <div class="auth-alert">Reset link is invalid or expired.</div>
            <p class="auth-foot"><a href="<?= e(app_url('forgot.php')) ?>">Request a new link</a></p>
        <?php else: ?>
            <p class="auth-sub">Choose a new password for <strong><?= e($user['username']) ?></strong>.</p>
            <?php if ($error): ?><div class="auth-alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="auth-form login-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field">
                    <label>New password</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div class="field">
                    <label>Confirm password</label>
                    <input type="password" name="confirm" required minlength="6">
                </div>
                <button class="btn-auth" type="submit">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
