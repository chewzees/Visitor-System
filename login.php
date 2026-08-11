<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } elseif (attempt_login($pdo, $username, $password)) {
        redirect('dashboard.php');
    } else {
        $error = 'Invalid credentials or inactive account.';
        log_activity($pdo, 'login_failed', 'Failed login for ' . $username);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('sign_in')) ?> — <?= e($config['APP_NAME']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="login-body">
<div class="login-stage">
    <header class="login-top">
        <a class="auth-brand login-brand" href="<?= e(app_url('index.php')) ?>">
            <span class="auth-mark" aria-hidden="true"></span>
            <?= e($config['APP_NAME']) ?>
        </a>
        <div class="login-top-links">
            <a href="<?= e(app_url('checkin.php')) ?>">Visitor Registration</a>
            <a href="<?= e(app_url('manual.php')) ?>">User Manual</a>
            <button type="button" class="login-manual-toggle" id="manual-toggle" aria-expanded="false">Quick Guide</button>
        </div>
    </header>

    <div class="login-layout">
        <section class="login-hero">
            <p class="login-kicker">Lobby ledger</p>
            <h1 class="login-display">Sign in.<br><em>Keep the floor clear.</em></h1>
            <p class="login-lead">Staff enter here. Guests use the entrance QR. Every arrival, badge, and departure stays in one quiet record.</p>
            <div class="login-hero-frame" aria-hidden="true">
                <span class="login-hero-word">Arrival</span>
                <span class="login-hero-wave"></span>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <h2><?= e(__('welcome_back')) ?></h2>
                <p class="auth-sub"><?= e(__('login_sub')) ?></p>

                <?php if ($error): ?>
                    <div class="auth-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($config['APP_DEBUG'])): ?>
                <div class="autofill-bar" aria-label="Demo autofill">
                    <span class="autofill-label">Autofill</span>
                    <button type="button" class="autofill-chip" data-user="admin" data-pass="password">Admin</button>
                    <button type="button" class="autofill-chip" data-user="security1" data-pass="password">Security</button>
                    <button type="button" class="autofill-chip" data-user="staff1" data-pass="password">Staff</button>
                </div>
                <?php endif; ?>

                <form class="auth-form login-form" method="post" autocomplete="username">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="username"><?= e(__('username')) ?></label>
                        <input id="username" name="username" type="text" required autocomplete="username" placeholder="Enter username" value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="password"><?= e(__('password')) ?></label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter password">
                    </div>
                    <div class="auth-row">
                        <label><input type="checkbox" name="remember" value="1"> <?= e(__('remember')) ?></label>
                        <a href="<?= e(app_url('forgot.php')) ?>"><?= e(__('forgot')) ?></a>
                    </div>
                    <button class="btn-auth" type="submit"><?= e(__('sign_in')) ?></button>
                </form>

                <p class="auth-foot">
                    First time? Read the <a href="<?= e(app_url('manual.php')) ?>"><strong>User Manual</strong></a>
                </p>
            </div>
        </section>
    </div>
</div>

<aside class="manual-drawer" id="manual-drawer" hidden>
    <div class="manual-drawer-inner">
        <div class="manual-drawer-head">
            <h3>Quick Guide</h3>
            <button type="button" class="manual-close" id="manual-close" aria-label="Close">×</button>
        </div>
        <ol class="manual-steps">
            <li><strong>Autofill</strong> a demo role, then Sign In.</li>
            <li><strong>Entrance QR</strong> — print and place at the door.</li>
            <li><strong>Visitors</strong> — approve, print badge, or delete.</li>
            <li><strong>Scan QR</strong> — camera check-in / check-out.</li>
            <li><strong>Blacklist</strong> — block by ID or passport.</li>
        </ol>
        <p class="manual-note">Demo password for all seeded accounts: <code>password</code></p>
        <a class="btn-auth manual-full-link" href="<?= e(app_url('manual.php')) ?>">Open full User Manual</a>
    </div>
</aside>
<div class="manual-backdrop" id="manual-backdrop" hidden></div>

<script>
(() => {
  const user = document.getElementById('username');
  const pass = document.getElementById('password');
  document.querySelectorAll('.autofill-chip').forEach((btn) => {
    btn.addEventListener('click', () => {
      user.value = btn.dataset.user || '';
      pass.value = btn.dataset.pass || '';
      user.focus();
      document.querySelectorAll('.autofill-chip').forEach((b) => b.classList.remove('on'));
      btn.classList.add('on');
    });
  });

  const drawer = document.getElementById('manual-drawer');
  const backdrop = document.getElementById('manual-backdrop');
  const toggle = document.getElementById('manual-toggle');
  const closeBtn = document.getElementById('manual-close');

  const openManual = () => {
    drawer.hidden = false;
    backdrop.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(() => {
      drawer.classList.add('open');
      backdrop.classList.add('open');
    });
  };
  const closeManual = () => {
    drawer.classList.remove('open');
    backdrop.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    setTimeout(() => {
      drawer.hidden = true;
      backdrop.hidden = true;
    }, 220);
  };

  toggle.addEventListener('click', openManual);
  closeBtn.addEventListener('click', closeManual);
  backdrop.addEventListener('click', closeManual);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !drawer.hidden) closeManual();
  });
})();
</script>
</body>
</html>
