<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['ok' => false, 'message' => 'Session expired. Please sign in again.'], 401);
}

$role = current_user()['role'] ?? '';
if (!in_array($role, ['Admin', 'Security'], true)) {
    json_response(['ok' => false, 'message' => 'Permission denied.'], 403);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST ?: $_GET;
}

$csrf = (string) ($input['_csrf'] ?? '');
if ($csrf === '' || !hash_equals(csrf_token(), $csrf)) {
    json_response(['ok' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
}

$tokenRaw = trim((string) ($input['token'] ?? ''));
$token = parse_badge_qr($tokenRaw);
if ($token === null || $token === '') {
    json_response(['ok' => false, 'message' => 'Invalid or expired QR code.']);
}

$stmt = $pdo->prepare('SELECT * FROM visitors WHERE qr_token = ? LIMIT 1');
$stmt->execute([$token]);
$v = $stmt->fetch();

if (!$v && ctype_digit($token)) {
    $stmt = $pdo->prepare('SELECT * FROM visitors WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $token]);
    $v = $stmt->fetch();
}

if (!$v) {
    json_response(['ok' => false, 'message' => 'Visitor not found for this QR.']);
}

if (is_blacklisted($pdo, $v['id_passport'])) {
    json_response(['ok' => false, 'message' => 'Visitor is blacklisted. Access denied.']);
}

if ($v['status'] === 'Rejected') {
    json_response(['ok' => false, 'message' => 'This visit was rejected.']);
}

$hours = (int) ($config['OVERDUE_HOURS'] ?? 8);

if ($v['status'] === 'Checked In') {
    $pdo->prepare("UPDATE visitors SET status='Checked Out', checked_out_at=NOW() WHERE id=?")->execute([$v['id']]);
    $message = 'Checked out.';
    $newStatus = 'Checked Out';
    log_activity($pdo, 'checkout', $v['full_name'] . ' checked out via scan');
    $v['status'] = $newStatus;
    notify_host($pdo, $v, 'checkout');
} else {
    $pdo->prepare("UPDATE visitors SET status='Checked In', checked_in_at=NOW(), checked_out_at=NULL, expected_out_at=DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id=?")->execute([$hours, $v['id']]);
    $message = 'Checked in.';
    $newStatus = 'Checked In';
    log_activity($pdo, 'checkin', $v['full_name'] . ' checked in via scan');
    $v['status'] = $newStatus;
    if (!empty($config['NOTIFY_HOST_ON_CHECKIN'])) {
        notify_host($pdo, $v, 'checkin');
    }
}

json_response([
    'ok' => true,
    'message' => $message,
    'visitor' => [
        'id' => (int) $v['id'],
        'full_name' => $v['full_name'],
        'host_name' => $v['host_name'],
        'visit_date' => $v['visit_date'],
        'status' => $newStatus,
        'qr_token' => $v['qr_token'],
        'photo_path' => $v['photo_path'] ?? null,
    ],
]);
