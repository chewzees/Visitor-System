<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$v = fetch_visitor($pdo, $id);
if (!$v) {
    flash_set('error', 'Visitor not found.');
    redirect('visitors.php');
}
assert_visitor_access($v);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $hours = (int) ($config['OVERDUE_HOURS'] ?? 8);

    if ($action === 'checkin') {
        require_roles(['Admin', 'Security']);
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
    } elseif ($action === 'approve') {
        $pdo->prepare("UPDATE visitors SET status='Approved' WHERE id=?")->execute([$id]);
        $v['status'] = 'Approved';
        log_activity($pdo, 'approve', $v['full_name'] . ' approved');
        notify_host($pdo, $v, 'approved');
        flash_set('success', 'Visitor approved.');
    } elseif ($action === 'reject') {
        require_roles(['Admin', 'Security']);
        $pdo->prepare("UPDATE visitors SET status='Rejected' WHERE id=?")->execute([$id]);
        log_activity($pdo, 'reject', $v['full_name'] . ' rejected');
        flash_set('success', 'Visitor rejected.');
    } elseif ($action === 'notify') {
        notify_host($pdo, $v, 'register');
        flash_set('success', 'Host notification attempted.');
    }
    redirect('visitor_view.php?id=' . $id);
}

render_header('Visitor Detail', 'visitors');
?>
<div class="form-card">
    <div class="section-head">
        <div style="display:flex; gap:1rem; align-items:center;">
            <?php if (!empty($v['photo_path'])): ?>
                <img class="avatar-lg" src="<?= e(app_url($v['photo_path'])) ?>" alt="">
            <?php endif; ?>
            <div>
                <h2><?= e($v['full_name']) ?></h2>
                <p><span class="<?= e(status_class($v['status'])) ?>"><?= e($v['status']) ?></span></p>
            </div>
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(app_url('visitor_form.php?id=' . $v['id'])) ?>">Edit</a>
            <a class="btn btn-solid" href="<?= e(app_url('badge.php?id=' . $v['id'])) ?>">Badge</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data" style="min-width:0">
            <tbody>
                <tr><th>Email</th><td><?= e($v['email'] ?: '—') ?></td></tr>
                <tr><th>ID / Passport</th><td><?= e($v['id_passport']) ?></td></tr>
                <tr><th>Phone</th><td><?= e($v['phone'] ?: '—') ?></td></tr>
                <tr><th>Company</th><td><?= e($v['company'] ?: '—') ?></td></tr>
                <tr><th>Host</th><td><?= e($v['host_name']) ?></td></tr>
                <tr><th>Host email</th><td><?= e($v['host_email'] ?: '—') ?></td></tr>
                <tr><th>Purpose</th><td><?= e($v['purpose'] ?: '—') ?></td></tr>
                <tr><th>Notes</th><td><?= e($v['notes'] ?: '—') ?></td></tr>
                <tr><th>Visit date</th><td><?= e($v['visit_date']) ?></td></tr>
                <tr><th>Checked in</th><td><?= e($v['checked_in_at'] ?: '—') ?></td></tr>
                <tr><th>Expected out</th><td><?= e($v['expected_out_at'] ?: '—') ?></td></tr>
                <tr><th>Checked out</th><td><?= e($v['checked_out_at'] ?: '—') ?></td></tr>
                <tr><th>QR token</th><td><code><?= e($v['qr_token']) ?></code></td></tr>
            </tbody>
        </table>
    </div>

    <form method="post" style="margin-top:1.25rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?= csrf_field() ?>
        <?php if ($v['status'] === 'Pending'): ?>
            <button class="btn btn-solid" name="action" value="approve" type="submit">Approve</button>
            <?php if (can_access('scan')): ?>
                <button class="btn btn-danger" name="action" value="reject" type="submit">Reject</button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (can_access('scan') && $v['status'] !== 'Checked In' && $v['status'] !== 'Rejected'): ?>
            <button class="btn btn-solid" name="action" value="checkin" type="submit">Check In</button>
        <?php endif; ?>
        <?php if (can_access('scan') && $v['status'] === 'Checked In'): ?>
            <button class="btn" name="action" value="checkout" type="submit">Check Out</button>
        <?php endif; ?>
        <button class="btn" name="action" value="notify" type="submit">Notify host</button>
    </form>
</div>
<?php render_footer(); ?>
