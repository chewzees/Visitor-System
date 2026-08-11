<?php
declare(strict_types=1);

function render_header(string $title, string $active = ''): void
{
    global $config;
    $user = current_user();
    $flash = flash_get();
    ?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — <?= e($config['APP_NAME']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Syne:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= e(app_url('dashboard.php')) ?>"><?= e(__('app_name')) ?></a>
        <nav class="side-nav">
            <?php
            $items = [
                'dashboard' => ['dashboard.php', __('dashboard')],
                'visitors' => ['visitors.php', __('visitors')],
                'scan' => ['scan.php', __('scan_qr')],
                'entrance' => ['entrance.php', __('entrance_qr')],
                'blacklist' => ['blacklist.php', __('blacklist')],
                'activity' => ['activity.php', __('activity_log')],
                'users' => ['users.php', __('users')],
                'settings' => ['settings.php', __('settings')],
            ];
            foreach ($items as $key => [$href, $label]):
                if (!can_access($key)) continue;
            ?>
                <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(app_url($href)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="nav-link" href="<?= e(app_url('logout.php')) ?>"><?= e(__('logout')) ?></a>
        </nav>
    </aside>
    <div class="main-wrap">
        <header class="topbar">
            <h1 class="page-title"><?= e($title) ?></h1>
            <div class="topbar-right">
                <div class="lang-switch">
                    <span><?= e(__('language')) ?></span>
                    <a href="?lang=en" class="<?= lang() === 'en' ? 'on' : '' ?>">EN</a>
                    <a href="?lang=zh" class="<?= lang() === 'zh' ? 'on' : '' ?>">中文</a>
                </div>
                <div class="user-chip">
                    <span class="user-dot"></span>
                    <span><?= e($user['full_name'] ?? '') ?> (<?= e($user['role'] ?? '') ?>)</span>
                </div>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
        </main>
    </div>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
</body>
</html>
    <?php
}
