<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$user = current_user();
[$scopeSql, $scopeParams] = visitor_scope_sql($user, 'v');

$count = static function (PDO $pdo, string $where, array $params) use ($scopeSql, $scopeParams): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitors v WHERE {$where}{$scopeSql}");
    $stmt->execute(array_merge($params, $scopeParams));
    return (int) $stmt->fetchColumn();
};

$stats = [
    'total' => $count($pdo, '1=1', []),
    'inside' => $count($pdo, "v.status = 'Checked In'", []),
    'today' => $count($pdo, 'v.visit_date = CURDATE()', []),
    'pending' => $count($pdo, "v.status = 'Pending'", []),
];

$hours = (int) ($config['OVERDUE_HOURS'] ?? 8);
$overdue = overdue_visitors($pdo, $hours);

$recentSql = "SELECT full_name, host_name, checked_in_at, id
     FROM visitors v
     WHERE v.status = 'Checked In' AND DATE(v.checked_in_at) = CURDATE()
     {$scopeSql}
     ORDER BY v.checked_in_at DESC
     LIMIT 8";
$stmt = $pdo->prepare($recentSql);
$stmt->execute($scopeParams);
$recent = $stmt->fetchAll();

$pendingSql = "SELECT id, full_name, host_name, visit_date FROM visitors v WHERE v.status='Pending' {$scopeSql} ORDER BY v.created_at DESC LIMIT 6";
$stmt = $pdo->prepare($pendingSql);
$stmt->execute($scopeParams);
$pendingRows = $stmt->fetchAll();

render_header(__('dashboard'), 'dashboard');
?>
<div class="stats-grid">
    <a class="stat-card" href="<?= e(app_url('visitors.php')) ?>">
        <div class="stat-label"><?= e(__('total_visitors')) ?></div>
        <div class="stat-value"><?= $stats['total'] ?></div>
    </a>
    <a class="stat-card green" href="<?= e(app_url('visitors.php?status=' . urlencode('Checked In'))) ?>">
        <div class="stat-label"><?= e(__('currently_inside')) ?></div>
        <div class="stat-value"><?= $stats['inside'] ?></div>
    </a>
    <a class="stat-card cyan" href="<?= e(app_url('visitors.php?from=' . date('Y-m-d') . '&to=' . date('Y-m-d'))) ?>">
        <div class="stat-label"><?= e(__('todays_visits')) ?></div>
        <div class="stat-value"><?= $stats['today'] ?></div>
    </a>
    <a class="stat-card gold" href="<?= e(app_url('visitors.php?status=Pending')) ?>">
        <div class="stat-label"><?= e(__('pending')) ?></div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
    </a>
</div>

<?php if ($overdue): ?>
<section class="panel" style="margin-bottom:1rem; border-color:rgba(201,112,112,0.35);">
    <div class="panel-title">
        <h3>Still inside (over <?= $hours ?>h)</h3>
        <span class="pill pill-danger"><?= count($overdue) ?></span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Host</th><th>Checked in</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $row): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['host_name']) ?></td>
                    <td><?= e($row['checked_in_at']) ?></td>
                    <td><a class="btn btn-sm" href="<?= e(app_url('visitor_view.php?id=' . $row['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="panel-grid panel-grid-3">
    <section class="panel">
        <div class="panel-title">
            <h3><?= e(__('recent_checkins')) ?></h3>
            <a class="btn btn-sm" href="<?= e(app_url('visitors.php')) ?>"><?= e(__('visitor_list')) ?></a>
        </div>
        <?php if (!$recent): ?>
            <p class="muted"><?= e(__('no_checkins')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Name</th><th>Host</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><a href="<?= e(app_url('visitor_view.php?id=' . $row['id'])) ?>"><?= e($row['full_name']) ?></a></td>
                            <td><?= e($row['host_name']) ?></td>
                            <td><?= e($row['checked_in_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h3>Pending approvals</h3>
            <a class="btn btn-sm" href="<?= e(app_url('visitors.php?status=Pending')) ?>">All</a>
        </div>
        <?php if (!$pendingRows): ?>
            <p class="muted">No pending visitors.</p>
        <?php else: ?>
            <ul class="pending-list">
                <?php foreach ($pendingRows as $row): ?>
                    <li>
                        <div>
                            <strong><?= e($row['full_name']) ?></strong>
                            <div class="muted"><?= e($row['host_name']) ?> · <?= e($row['visit_date']) ?></div>
                        </div>
                        <a class="btn btn-sm btn-solid" href="<?= e(app_url('visitors.php?action=approve&id=' . $row['id'] . '&_csrf=' . urlencode(csrf_token()))) ?>">Approve</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h3><?= e(__('scan_qr')) ?></h3>
        </div>
        <p class="muted">Desk scanner or full-screen kiosk for the door tablet.</p>
        <?php if (can_access('scan')): ?>
            <p style="margin-top:1.25rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                <a class="btn btn-solid" href="<?= e(app_url('scan.php')) ?>"><?= e(__('open_scanner')) ?></a>
                <a class="btn" href="<?= e(app_url('scan.php?kiosk=1')) ?>">Kiosk mode</a>
            </p>
        <?php else: ?>
            <p class="muted" style="margin-top:1rem">Scanner access is limited to Security and Admin.</p>
        <?php endif; ?>
        <p style="margin-top:1rem">
            <a class="btn" href="<?= e(app_url('visitors.php')) ?>"><?= e(__('visitor_list')) ?></a>
        </p>
    </section>
</div>
<?php render_footer(); ?>
