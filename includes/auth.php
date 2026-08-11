<?php
declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_roles(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        flash_set('error', 'You do not have permission to access that page.');
        redirect('dashboard.php');
    }
}

function attempt_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = "Active" LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];

    log_activity($pdo, 'login', $user['username'] . ' logged in', $_SESSION['user']);
    return true;
}

function logout_user(PDO $pdo): void
{
    if ($user = current_user()) {
        log_activity($pdo, 'logout', $user['username'] . ' logged out', $user);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function can_access(string $navKey): bool
{
    $role = current_user()['role'] ?? '';
    $map = [
        'dashboard' => ['Admin', 'Security', 'Staff'],
        'visitors' => ['Admin', 'Security', 'Staff'],
        'scan' => ['Admin', 'Security'],
        'entrance' => ['Admin', 'Security'],
        'blacklist' => ['Admin', 'Security'],
        'activity' => ['Admin'],
        'users' => ['Admin'],
        'settings' => ['Admin', 'Security', 'Staff'],
    ];
    return in_array($role, $map[$navKey] ?? [], true);
}
