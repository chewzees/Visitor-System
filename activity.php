<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_roles(['Admin']);

$users = $pdo->query('SELECT id, username FROM users ORDER BY username')->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$userFilter = trim((string) ($_GET['user'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$sql = 'SELECT * FROM activity_logs WHERE 1=1';
$params = [];
if ($userFilter !== '') {
    $sql .= ' AND username = ?';
    $params[] = $userFilter;
}
if ($actionFilter !== '') {
    $sql .= ' AND action = ?';
    $params[] = $actionFilter;
}
if ($from !== '') {
    $sql .= ' AND DATE(created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND DATE(created_at) <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY created_at DESC, id DESC';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare($sql . ' LIMIT 5000');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'User', 'Action', 'Description', 'IP Address']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['created_at'], $row['username'], $row['action'], $row['description'], $row['ip_address']]);
    }
    fclose($out);
    exit;
}

$stmt = $pdo->prepare($sql . ' LIMIT 200');
$stmt->execute($params);
$rows = $stmt->fetchAll();

$qs = $_GET;
unset($qs['export']);
$exportUrl = 'activity.php?' . http_build_query(array_merge($qs, ['export' => 'csv']));

render_header(__('activity_log'), 'activity');
?>
<form class="filter-bar" method="get">
    <div class="field">
        <label>User</label>
        <select name="user">
            <option value="">—</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= e($u['username']) ?>" <?= $userFilter === $u['username'] ? 'selected' : '' ?>><?= e($u['username']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Action</label>
        <select name="action">
            <option value="">—</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Date From</label>
        <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="field">
        <label>Date To</label>
        <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div style="align-self:end; display:flex; gap:0.45rem;">
        <button class="btn btn-solid" type="submit"><?= e(__('filter')) ?></button>
        <a class="btn" href="<?= e(app_url($exportUrl)) ?>">Export CSV</a>
    </div>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5" class="muted">No activity found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['created_at']) ?></td>
                <td><?= e($row['username'] ?: '—') ?></td>
                <td><?= e($row['action']) ?></td>
                <td><?= e($row['description']) ?></td>
                <td><?= e($row['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
