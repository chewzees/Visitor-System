<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$user = current_user();

// Quick actions
if (isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['approve', 'reject', 'checkin', 'checkout', 'delete'], true)) {
    if (!hash_equals(csrf_token(), (string) ($_GET['_csrf'] ?? ''))) {
        flash_set('error', 'Invalid security token.');
        redirect('visitors.php');
    }
    $id = (int) $_GET['id'];
    $v = fetch_visitor($pdo, $id);
    if (!$v) {
        flash_set('error', 'Visitor not found.');
        redirect('visitors.php');
    }
    assert_visitor_access($v);

    $action = $_GET['action'];
    if ($action === 'delete') {
        require_roles(['Admin', 'Security']);
        $pdo->prepare('DELETE FROM visitors WHERE id = ?')->execute([$id]);
        log_activity($pdo, 'visitor_delete', 'Deleted visitor ' . $v['full_name']);
        flash_set('success', 'Visitor deleted.');
    } elseif ($action === 'approve') {
        require_roles(['Admin', 'Security', 'Staff']);
        $pdo->prepare("UPDATE visitors SET status='Approved' WHERE id=?")->execute([$id]);
        $v['status'] = 'Approved';
        log_activity($pdo, 'approve', $v['full_name'] . ' approved');
        if (!empty($config['NOTIFY_HOST_ON_REGISTER'])) {
            notify_host($pdo, $v, 'approved');
        }
        flash_set('success', 'Visitor approved.');
    } elseif ($action === 'reject') {
        require_roles(['Admin', 'Security']);
        $pdo->prepare("UPDATE visitors SET status='Rejected' WHERE id=?")->execute([$id]);
        log_activity($pdo, 'reject', $v['full_name'] . ' rejected');
        flash_set('success', 'Visitor rejected.');
    } elseif ($action === 'checkin') {
        require_roles(['Admin', 'Security']);
        $hours = (int) ($config['OVERDUE_HOURS'] ?? 8);
        $pdo->prepare("UPDATE visitors SET status='Checked In', checked_in_at=NOW(), checked_out_at=NULL, expected_out_at=DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id=?")->execute([$hours, $id]);
        $v['status'] = 'Checked In';
        log_activity($pdo, 'checkin', $v['full_name'] . ' checked in');
        if (!empty($config['NOTIFY_HOST_ON_CHECKIN'])) {
            notify_host($pdo, $v, 'checkin');
        }
        flash_set('success', 'Visitor checked in.');
    } elseif ($action === 'checkout') {
        require_roles(['Admin', 'Security']);
        $pdo->prepare("UPDATE visitors SET status='Checked Out', checked_out_at=NOW() WHERE id=?")->execute([$id]);
        $v['status'] = 'Checked Out';
        log_activity($pdo, 'checkout', $v['full_name'] . ' checked out');
        notify_host($pdo, $v, 'checkout');
        flash_set('success', 'Visitor checked out.');
    }

    $qs = $_GET;
    unset($qs['action'], $qs['id'], $qs['_csrf']);
    redirect('visitors.php' . ($qs ? ('?' . http_build_query($qs)) : ''));
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$host = trim((string) ($_GET['host'] ?? ''));

[$scopeSql, $scopeParams] = visitor_scope_sql($user, 'v');
$sql = 'SELECT v.* FROM visitors v WHERE 1=1' . $scopeSql;
$params = $scopeParams;

if ($q !== '') {
    $sql .= ' AND (v.full_name LIKE ? OR v.email LIKE ? OR v.id_passport LIKE ? OR v.company LIKE ? OR v.qr_token LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $sql .= ' AND v.status = ?';
    $params[] = $status;
}
if ($from !== '') {
    $sql .= ' AND v.visit_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND v.visit_date <= ?';
    $params[] = $to;
}
if ($host !== '' && $user['role'] !== 'Staff') {
    $sql .= ' AND v.host_name LIKE ?';
    $params[] = '%' . $host . '%';
}

$sql .= ' ORDER BY v.created_at DESC, v.id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$csrf = csrf_token();
render_header(__('visitors'), 'visitors');
?>
<div class="section-head">
    <div>
        <h2><?= e(__('visitor_list')) ?></h2>
        <p><?= $user['role'] === 'Staff' ? 'Your hosted guests.' : 'Search, approve, badge, and manage arrivals.' ?></p>
    </div>
    <a class="btn btn-solid" href="<?= e(app_url('visitor_form.php')) ?>"><?= e(__('add_visitor')) ?></a>
</div>

<form class="filter-bar filter-bar-visitors" method="get">
    <div class="field">
        <label>Search</label>
        <input name="q" value="<?= e($q) ?>" placeholder="Name, email, ID, company…">
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">All</option>
            <?php foreach (['Pending','Approved','Checked In','Checked Out','Rejected'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($to) ?>">
    </div>
    <?php if ($user['role'] !== 'Staff'): ?>
    <div class="field">
        <label>Host</label>
        <input name="host" value="<?= e($host) ?>" placeholder="Host name">
    </div>
    <?php endif; ?>
    <div style="align-self:end; display:flex; gap:0.45rem;">
        <button class="btn btn-solid" type="submit"><?= e(__('filter')) ?></button>
        <a class="btn" href="<?= e(app_url('visitors.php')) ?>">Reset</a>
    </div>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th></th>
                <th>Name</th>
                <th>Email</th>
                <th>ID/Passport</th>
                <th>Host</th>
                <th>Visit Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="muted">No visitors match your filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td>
                    <?php if (!empty($row['photo_path'])): ?>
                        <img class="avatar-sm" src="<?= e(app_url($row['photo_path'])) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar-sm avatar-empty"></span>
                    <?php endif; ?>
                </td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['email'] ?: '—') ?></td>
                <td><?= e($row['id_passport']) ?></td>
                <td><?= e($row['host_name']) ?></td>
                <td><?= e($row['visit_date']) ?></td>
                <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm" href="<?= e(app_url('visitor_view.php?id=' . $row['id'])) ?>">View</a>
                    <a class="btn btn-sm" href="<?= e(app_url('badge.php?id=' . $row['id'])) ?>">Badge</a>
                    <?php if ($row['status'] === 'Pending'): ?>
                        <a class="btn btn-sm btn-solid" href="<?= e(app_url('visitors.php?action=approve&id=' . $row['id'] . '&_csrf=' . urlencode($csrf))) ?>">Approve</a>
                    <?php endif; ?>
                    <?php if (can_access('scan') && $row['status'] !== 'Checked In' && $row['status'] !== 'Rejected'): ?>
                        <a class="btn btn-sm" href="<?= e(app_url('visitors.php?action=checkin&id=' . $row['id'] . '&_csrf=' . urlencode($csrf))) ?>">Check In</a>
                    <?php endif; ?>
                    <?php if (can_access('scan') && $row['status'] === 'Checked In'): ?>
                        <a class="btn btn-sm" href="<?= e(app_url('visitors.php?action=checkout&id=' . $row['id'] . '&_csrf=' . urlencode($csrf))) ?>">Check Out</a>
                    <?php endif; ?>
                    <?php if (can_access('scan')): ?>
                        <a class="btn btn-sm btn-danger" data-confirm="Delete this visitor?" href="<?= e(app_url('visitors.php?action=delete&id=' . $row['id'] . '&_csrf=' . urlencode($csrf))) ?>">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
