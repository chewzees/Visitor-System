<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_roles(['Admin', 'Security']);

if (isset($_GET['delete'])) {
    require_roles(['Admin']);
    $id = (int) $_GET['delete'];
    $pdo->prepare('DELETE FROM blacklist WHERE id = ?')->execute([$id]);
    log_activity($pdo, 'blacklist_remove', 'Removed blacklist entry #' . $id);
    flash_set('success', 'Removed from blacklist.');
    redirect('blacklist.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $idPassport = trim((string) ($_POST['id_passport'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($fullName === '' || $idPassport === '' || $reason === '') {
        flash_set('error', 'All fields are required.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO blacklist (full_name, id_passport, reason, added_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$fullName, $idPassport, $reason, current_user()['id']]);
        log_activity($pdo, 'blacklist_add', 'Blacklisted ' . $fullName);
        flash_set('success', 'Added to blacklist.');
    }
    redirect('blacklist.php');
}

$showForm = isset($_GET['add']);
$rows = $pdo->query(
    'SELECT b.*, u.username AS added_by_name
     FROM blacklist b
     LEFT JOIN users u ON u.id = b.added_by
     ORDER BY b.created_at DESC'
)->fetchAll();

render_header(__('blacklist'), 'blacklist');
?>
<div class="section-head">
    <div>
        <h2>Blacklist List</h2>
        <p>Guests who must not be admitted.</p>
    </div>
    <a class="btn btn-solid" href="<?= e(app_url('blacklist.php?add=1')) ?>"><?= e(__('add_blacklist')) ?></a>
</div>

<?php if ($showForm): ?>
<div class="form-card" style="margin-bottom:1.25rem;">
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label>Full name</label>
                <input name="full_name" required>
            </div>
            <div class="field">
                <label>ID / Passport</label>
                <input name="id_passport" required>
            </div>
            <div class="field full">
                <label>Reason</label>
                <textarea name="reason" rows="3" required></textarea>
            </div>
        </div>
        <div style="margin-top:1rem; display:flex; gap:0.5rem;">
            <button class="btn btn-solid" type="submit">Save</button>
            <a class="btn" href="<?= e(app_url('blacklist.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>ID/Passport Number</th>
                <th>Reason</th>
                <th>Added By</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="muted">No blacklist entries.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['id_passport']) ?></td>
                <td><?= e($row['reason']) ?></td>
                <td><?= e($row['added_by_name'] ?: '—') ?></td>
                <td><?= e($row['created_at']) ?></td>
                <td>
                    <?php if (can_access('users')): ?>
                        <a class="btn btn-sm btn-danger" data-confirm="Remove this entry?" href="<?= e(app_url('blacklist.php?delete=' . $row['id'])) ?>">Remove</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
