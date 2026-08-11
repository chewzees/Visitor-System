<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $identity = trim((string) ($_POST['identity'] ?? ''));
    if ($identity === '') {
        $error = 'Enter your username or email.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND status = "Active" LIMIT 1');
        $stmt->execute([$identity, $identity]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')
                ->execute([$token, $user['id']]);
            $link = app_url('reset.php?token=' . urlencode($token));
            $body = "Password reset requested for {$user['username']}.\n\nOpen this link within 1 hour:\n{$link}\n";
            $sent = send_app_mail($user['email'], 'Password reset — ' . $config['APP_NAME'], $body);
            log_activity($pdo, 'password_reset_request', 'Reset requested for ' . $user['username'], [
                'id' => (int) $user['id'],
                'username' => $user['username'],
            ]);
            // In debug, always show the link so local XAMPP works without SMTP
            if (!empty($config['APP_DEBUG']) || !$sent) {
                $message = 'Reset link (valid 1 hour): ' . $link;
            } else {
                $message = 'If the account exists, a reset link was sent to the registered email.';
            }
        } else {
            $message = 'If the account exists, a reset link was sent to the registered email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — <?= e($config['APP_NAME']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;1,600&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<div class="login-stage" style="justify-content:center; align-items:center;">
    <div class="login-card" style="width:min(420px,100%);">
        <h2>Forgot password</h2>
        <p class="auth-sub">Enter your username or email to get a reset link.</p>
        <?php if ($error): ?><div class="auth-alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="auth-alert" style="border-color:#b7d7be;color:#2f6b3c;background:#f3faf4;"><?= e($message) ?></div><?php endif; ?>
        <form method="post" class="auth-form login-form">
            <?= csrf_field() ?>
            <div class="field">
                <label>Username or email</label>
                <input name="identity" required value="<?= e($_POST['identity'] ?? '') ?>">
            </div>
            <button class="btn-auth" type="submit">Send reset link</button>
        </form>
        <p class="auth-foot"><a href="<?= e(app_url('login.php')) ?>">Back to Sign In</a></p>
    </div>
</div>
</body>
</html>
