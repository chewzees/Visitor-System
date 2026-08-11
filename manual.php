<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Manual — <?= e($config['APP_NAME']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="manual-page">
<header class="manual-page-top">
    <a class="auth-brand login-brand" href="<?= e(app_url('login.php')) ?>">
        <span class="auth-mark" aria-hidden="true"></span>
        <?= e($config['APP_NAME']) ?>
    </a>
    <nav class="manual-page-nav">
        <a href="<?= e(app_url('checkin.php')) ?>">Visitor Registration</a>
        <a class="btn-auth manual-nav-btn" href="<?= e(app_url('login.php')) ?>">Sign In</a>
    </nav>
</header>

<main class="manual-page-main">
    <header class="manual-page-hero">
        <p class="login-kicker">Documentation</p>
        <h1>User Manual</h1>
        <p>How to run the lobby — from entrance QR to checkout.</p>
    </header>

    <nav class="manual-toc">
        <a href="#accounts">Accounts</a>
        <a href="#roles">Roles</a>
        <a href="#flow">Daily flow</a>
        <a href="#modules">Modules</a>
        <a href="#checkin">Public check-in</a>
        <a href="#tips">Tips</a>
    </nav>

    <article class="manual-article" id="accounts">
        <h2>1. Demo accounts</h2>
        <p>Use <strong>Autofill</strong> on the login page, or sign in manually. Default password for all seeded users:</p>
        <p><code>password</code></p>
        <div class="table-wrap manual-table">
            <table class="data">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Best for</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>admin</code></td>
                        <td>Admin</td>
                        <td>Full access — users, activity log, settings</td>
                    </tr>
                    <tr>
                        <td><code>security1</code></td>
                        <td>Security</td>
                        <td>Scan QR, entrance QR, blacklist, visitors</td>
                    </tr>
                    <tr>
                        <td><code>staff1</code></td>
                        <td>Staff</td>
                        <td>Visitor list and hosting duties</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>

    <article class="manual-article" id="roles">
        <h2>2. Roles &amp; access</h2>
        <ul>
            <li><strong>Admin</strong> — everything, including Users and Activity Log.</li>
            <li><strong>Security</strong> — Scan QR, Entrance QR, Blacklist, Visitors, Dashboard.</li>
            <li><strong>Staff</strong> — Dashboard, Visitors, Settings (change own password).</li>
        </ul>
    </article>

    <article class="manual-article" id="flow">
        <h2>3. Recommended daily flow</h2>
        <ol>
            <li>Print <strong>Entrance QR</strong> and place it at the door.</li>
            <li>Visitors scan it on their phone → fill the check-in form.</li>
            <li>Staff/Security open <strong>Visitors</strong>, approve pending guests.</li>
            <li>Print a <strong>Badge</strong> (QR) for the visitor.</li>
            <li>At the desk, use <strong>Scan QR</strong> to check in / check out.</li>
            <li>Review <strong>Activity Log</strong> (Admin) if something needs auditing.</li>
        </ol>
    </article>

    <article class="manual-article" id="modules">
        <h2>4. Modules</h2>
        <dl class="manual-dl">
            <dt>Dashboard</dt>
            <dd>Totals, currently inside, today’s visits, pending, recent check-ins.</dd>
            <dt>Visitors</dt>
            <dd>Add, view, edit, badge, delete. Status: Pending → Approved → Checked In → Checked Out.</dd>
            <dt>Scan QR</dt>
            <dd>Camera or manual token. Toggles check-in / check-out.</dd>
            <dt>Entrance QR</dt>
            <dd>Static printable code that opens the public check-in URL.</dd>
            <dt>Blacklist</dt>
            <dd>Block people by ID/passport. Matching registrations are rejected.</dd>
            <dt>Activity Log</dt>
            <dd>Filter by user, action, and date range.</dd>
            <dt>Users</dt>
            <dd>Create and edit staff accounts and roles (Admin only).</dd>
            <dt>Settings</dt>
            <dd>Read-only config view and change password.</dd>
        </dl>
    </article>

    <article class="manual-article" id="checkin">
        <h2>5. Public visitor check-in</h2>
        <p>URL: <a href="<?= e(app_url('checkin.php')) ?>"><?= e(app_url('checkin.php')) ?></a></p>
        <ul>
            <li>Guests can start the camera and scan the entrance QR, or open the form link directly.</li>
            <li>Camera needs HTTPS or <code>localhost</code> permission in most browsers.</li>
            <li>Blacklisted IDs cannot submit a form.</li>
        </ul>
    </article>

    <article class="manual-article" id="tips">
        <h2>6. Tips</h2>
        <ul>
            <li>Language switch (EN / 中文) is in the top bar after login.</li>
            <li>Change <code>APP_URL</code> / <code>APP_SECRET</code> in <code>config/config.php</code> for production.</li>
            <li>Delete <code>install.php</code> after the first setup. Set <code>APP_DEBUG</code> to <code>false</code> to hide demo autofill.</li>
            <li>Use <strong>Forgot Password</strong> on login; in debug mode the reset link is shown on screen.</li>
            <li>Visitor list supports search, status/date filters, and one-click Approve / Check In.</li>
            <li>Dashboard shows overdue “still inside” guests and pending approvals.</li>
            <li>Scan page has <strong>Kiosk mode</strong> for door tablets.</li>
            <li>Activity Log can <strong>Export CSV</strong>.</li>
            <li>Staff accounts only see visitors they host or created.</li>
        </ul>
    </article>

    <p class="manual-back">
        <a class="btn-auth manual-nav-btn" href="<?= e(app_url('login.php')) ?>">Back to Sign In</a>
    </p>
</main>
</body>
</html>
