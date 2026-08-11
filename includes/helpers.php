<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function detect_app_url(): string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return '';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // When called from /Visitor/api/scan.php, go up one level
    if (str_ends_with($script, '/api')) {
        $script = dirname($script);
    }
    $base = rtrim($scheme . '://' . $host . ($script === '/' ? '' : $script), '/');
    return $base;
}

function app_url(string $path = ''): string
{
    global $config;
    $detected = detect_app_url();
    $base = rtrim($detected !== '' ? $detected : $config['APP_URL'], '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function qr_token(): string
{
    return 'tok_' . bin2hex(random_bytes(12));
}

function app_secret(): string
{
    global $config;
    return (string) ($config['APP_SECRET'] ?? 'visitor-secret');
}

/** Signed badge payload: token.exp.sig — optional expiry (0 = no expiry). */
function badge_qr_payload(string $token, int $ttlSeconds = 0): string
{
    $exp = $ttlSeconds > 0 ? (string) (time() + $ttlSeconds) : '0';
    $sig = hash_hmac('sha256', $token . '.' . $exp, app_secret());
    return $token . '.' . $exp . '.' . substr($sig, 0, 16);
}

function parse_badge_qr(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    if (str_starts_with($raw, '{')) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed) && !empty($parsed['token'])) {
            $raw = (string) $parsed['token'];
        }
    }

    if (preg_match('/[?&]token=([a-zA-Z0-9_]+)/', $raw, $m)) {
        return $m[1];
    }

    // signed: tok_xxx.exp.sig
    if (preg_match('/^(tok_[a-f0-9]+)\.(\d+)\.([a-f0-9]{16})$/i', $raw, $m)) {
        $token = $m[1];
        $exp = (int) $m[2];
        $sig = $m[3];
        $expect = substr(hash_hmac('sha256', $token . '.' . $m[2], app_secret()), 0, 16);
        if (!hash_equals($expect, $sig)) {
            return null;
        }
        if ($exp > 0 && time() > $exp) {
            return null;
        }
        return $token;
    }

    if (preg_match('/\b(tok_[a-f0-9]+)\b/i', $raw, $m)) {
        return $m[1];
    }

    return $raw;
}

function status_class(string $status): string
{
    return match ($status) {
        'Checked In' => 'pill pill-success',
        'Approved' => 'pill pill-warn',
        'Pending' => 'pill pill-info',
        'Checked Out' => 'pill pill-muted',
        'Rejected' => 'pill pill-danger',
        'Active' => 'pill pill-success',
        'Inactive' => 'pill pill-muted',
        default => 'pill',
    };
}

function log_activity(PDO $pdo, string $action, string $description, ?array $user = null): void
{
    $user = $user ?? (current_user() ?? null);
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, username, action, description, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        $user['username'] ?? null,
        $action,
        $description,
        client_ip(),
    ]);
}

function is_blacklisted(PDO $pdo, string $idPassport): bool
{
    $stmt = $pdo->prepare('SELECT id FROM blacklist WHERE id_passport = ? LIMIT 1');
    $stmt->execute([$idPassport]);
    return (bool) $stmt->fetch();
}

function json_response(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function uploads_dir(): string
{
    $dir = __DIR__ . '/../uploads/visitors';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function save_visitor_photo(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($map[$mime])) {
        return null;
    }

    $name = 'v_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $dest = uploads_dir() . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return 'uploads/visitors/' . $name;
}

function save_visitor_photo_data_url(?string $dataUrl): ?string
{
    if (!$dataUrl || !preg_match('#^data:image/(jpeg|png|webp);base64,#', $dataUrl, $m)) {
        return null;
    }
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bin === false || strlen($bin) > 3 * 1024 * 1024) {
        return null;
    }
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
    $name = 'v_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = uploads_dir() . '/' . $name;
    if (file_put_contents($dest, $bin) === false) {
        return null;
    }
    return 'uploads/visitors/' . $name;
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    global $config;
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // Skip obviously local demo addresses unless debug wants logging
    $from = $config['MAIL_FROM'] ?? 'noreply@visitor.local';
    $fromName = $config['MAIL_FROM_NAME'] ?? 'Visitor';
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=utf-8',
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $from),
    ];
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function log_notification(PDO $pdo, ?int $visitorId, string $recipient, string $subject, string $body, string $status): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (visitor_id, channel, recipient, subject, body, status) VALUES (?, "email", ?, ?, ?, ?)'
    );
    $stmt->execute([$visitorId, $recipient, $subject, $body, $status]);
}

function notify_host(PDO $pdo, array $visitor, string $event): void
{
    global $config;
    $email = trim((string) ($visitor['host_email'] ?? ''));
    if ($email === '') {
        // fallback: match host user by full name
        $stmt = $pdo->prepare('SELECT email FROM users WHERE full_name = ? AND status = "Active" LIMIT 1');
        $stmt->execute([$visitor['host_name'] ?? '']);
        $email = (string) ($stmt->fetchColumn() ?: '');
    }
    if ($email === '') {
        return;
    }

    $name = $visitor['full_name'] ?? 'Visitor';
    $subject = match ($event) {
        'register' => "New visitor registration: {$name}",
        'checkin' => "Visitor checked in: {$name}",
        'checkout' => "Visitor checked out: {$name}",
        'approved' => "Visitor approved: {$name}",
        default => "Visitor update: {$name}",
    };
    $body = "Hello,\n\n"
        . "Event: {$event}\n"
        . "Visitor: {$name}\n"
        . "ID/Passport: " . ($visitor['id_passport'] ?? '-') . "\n"
        . "Host: " . ($visitor['host_name'] ?? '-') . "\n"
        . "Visit date: " . ($visitor['visit_date'] ?? '-') . "\n"
        . "Status: " . ($visitor['status'] ?? '-') . "\n"
        . "\n— " . ($config['APP_FULL_NAME'] ?? 'Visitor Management');

    $ok = send_app_mail($email, $subject, $body);
    log_notification($pdo, isset($visitor['id']) ? (int) $visitor['id'] : null, $email, $subject, $body, $ok ? 'sent' : 'failed');
}

function visitor_scope_sql(?array $user, string $alias = 'v'): array
{
    if (!$user) {
        return ['', []];
    }
    if (($user['role'] ?? '') === 'Staff') {
        return [" AND ({$alias}.host_name = ? OR {$alias}.created_by = ?)", [$user['full_name'], $user['id']]];
    }
    return ['', []];
}

function fetch_visitor(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM visitors WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function assert_visitor_access(array $visitor): void
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    if ($user['role'] === 'Staff') {
        $ok = ($visitor['host_name'] === $user['full_name']) || ((int) ($visitor['created_by'] ?? 0) === (int) $user['id']);
        if (!$ok) {
            flash_set('error', 'You can only access visitors assigned to you.');
            redirect('visitors.php');
        }
    }
}

function overdue_visitors(PDO $pdo, int $hours = 8): array
{
    [$scope, $params] = visitor_scope_sql(current_user());
    $sql = "SELECT * FROM visitors v
            WHERE v.status = 'Checked In'
              AND v.checked_in_at IS NOT NULL
              AND v.checked_in_at < (NOW() - INTERVAL ? HOUR)
              {$scope}
            ORDER BY v.checked_in_at ASC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$hours], $params));
    return $stmt->fetchAll();
}
