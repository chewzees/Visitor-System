<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_roles(['Admin']);

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$showForm = isset($_GET['add']) || $editId > 0;
$editUser = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch();
    if (!$editUser) {
        flash_set('error', 'User not found.');
        redirect('users.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = trim((string) ($_POST['role'] ?? 'Staff'));
    $status = trim((string) ($_POST['status'] ?? 'Active'));
    $password = (string) ($_POST['password'] ?? '');

    if (!in_array($role, ['Admin', 'Security', 'Staff'], true)) $role = 'Staff';
    if (!in_array($status, ['Active', 'Inactive'], true)) $status = 'Active';

    if ($username === '' || $fullName === '' || $email === '') {
        flash_set('error', 'Username, full name, and email are required.');
        redirect('users.php' . ($id ? '?edit=' . $id : '?add=1'));
    }

    if ($id > 0) {
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, status=?, password_hash=? WHERE id=?');
            $stmt->execute([$username, $fullName, $email, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE id=?');
            $stmt->execute([$username, $fullName, $email, $role, $status, $id]);
        }
        log_activity($pdo, 'user_update', 'Updated user ' . $username);
        flash_set('success', 'User updated.');
    } else {
        if ($password === '') {
            flash_set('error', 'Password is required for new users.');
            redirect('users.php?add=1');
        }
        $stmt = $pdo->prepare('INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, $fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
        log_activity($pdo, 'user_create', 'Created user ' . $username);
        flash_set('success', 'User created.');
    }
    redirect('users.php');
}

$rows = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
render_header(__('users'), 'users');
?>
<div class="section-head">
    <div>
        <h2>User List</h2>
        <p>Roles that keep the lobby moving.</p>
    </div>
    <a class="btn btn-solid" href="<?= e(app_url('users.php?add=1')) ?>"><?= e(__('add_user')) ?></a>
</div>

<?php if ($showForm): ?>
<div class="form-card" style="margin-bottom:1.25rem;">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
        <div class="form-grid">
            <div class="field">
                <label>Username</label>
                <input name="username" required value="<?= e($editUser['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Full name</label>
                <input name="full_name" required value="<?= e($editUser['full_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role">
                    <?php foreach (['Admin','Security','Staff'] as $r): ?>
                        <option value="<?= $r ?>" <?= ($editUser['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['Active','Inactive'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($editUser['status'] ?? 'Active') === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Password <?= $editUser ? '(leave blank to keep)' : '' ?></label>
                <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
            </div>
        </div>
        <div style="margin-top:1rem; display:flex; gap:0.5rem;">
            <button class="btn btn-solid" type="submit">Save</button>
            <a class="btn" href="<?= e(app_url('users.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Role</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['username']) ?></td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['email']) ?></td>
                <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                <td><?= e($row['role']) ?></td>
                <td><?= e($row['last_login'] ?: '—') ?></td>
                <td><a class="btn btn-sm" href="<?= e(app_url('users.php?edit=' . $row['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
