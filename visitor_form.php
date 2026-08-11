<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$visitor = null;
if ($id > 0) {
    $visitor = fetch_visitor($pdo, $id);
    if (!$visitor) {
        flash_set('error', 'Visitor not found.');
        redirect('visitors.php');
    }
    assert_visitor_access($visitor);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $data = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'id_passport' => trim((string) ($_POST['id_passport'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'company' => trim((string) ($_POST['company'] ?? '')),
        'host_name' => trim((string) ($_POST['host_name'] ?? '')),
        'host_email' => trim((string) ($_POST['host_email'] ?? '')),
        'purpose' => trim((string) ($_POST['purpose'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'visit_date' => trim((string) ($_POST['visit_date'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'Pending')),
    ];

    $allowedStatus = ['Pending', 'Approved', 'Checked In', 'Checked Out', 'Rejected'];
    if (!in_array($data['status'], $allowedStatus, true)) {
        $data['status'] = 'Pending';
    }

    if ($data['full_name'] === '' || $data['id_passport'] === '' || $data['host_name'] === '' || $data['visit_date'] === '') {
        flash_set('error', 'Name, ID/Passport, host, and visit date are required.');
        redirect($id ? 'visitor_form.php?id=' . $id : 'visitor_form.php');
    }

    if (is_blacklisted($pdo, $data['id_passport'])) {
        flash_set('error', 'This ID/Passport is on the blacklist.');
        redirect($id ? 'visitor_form.php?id=' . $id : 'visitor_form.php');
    }

    $photoPath = save_visitor_photo($_FILES['photo'] ?? null);
    $dataUrlPhoto = save_visitor_photo_data_url($_POST['photo_data'] ?? null);
    if ($dataUrlPhoto) {
        $photoPath = $dataUrlPhoto;
    }

    $user = current_user();
    if ($visitor) {
        $sql = 'UPDATE visitors SET full_name=?, email=?, id_passport=?, phone=?, company=?, host_name=?, host_email=?, purpose=?, notes=?, visit_date=?, status=?';
        $params = [
            $data['full_name'], $data['email'] ?: null, $data['id_passport'], $data['phone'] ?: null,
            $data['company'] ?: null, $data['host_name'], $data['host_email'] ?: null,
            $data['purpose'] ?: null, $data['notes'] ?: null, $data['visit_date'], $data['status'],
        ];
        if ($photoPath) {
            $sql .= ', photo_path=?';
            $params[] = $photoPath;
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        log_activity($pdo, 'visitor_update', 'Updated visitor ' . $data['full_name']);
        flash_set('success', 'Visitor updated.');
        redirect('visitor_view.php?id=' . $id);
    }

    $token = qr_token();
    $stmt = $pdo->prepare(
        'INSERT INTO visitors (full_name, email, id_passport, phone, company, host_name, host_email, purpose, notes, visit_date, status, qr_token, photo_path, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['full_name'], $data['email'] ?: null, $data['id_passport'], $data['phone'] ?: null,
        $data['company'] ?: null, $data['host_name'], $data['host_email'] ?: null,
        $data['purpose'] ?: null, $data['notes'] ?: null, $data['visit_date'],
        $data['status'], $token, $photoPath, $user['id'],
    ]);
    $newId = (int) $pdo->lastInsertId();
    log_activity($pdo, 'visitor_create', 'Created visitor ' . $data['full_name']);
    $created = fetch_visitor($pdo, $newId);
    if ($created && !empty($config['NOTIFY_HOST_ON_REGISTER'])) {
        notify_host($pdo, $created, 'register');
    }
    flash_set('success', 'Visitor added.');
    redirect('visitor_view.php?id=' . $newId);
}

$hosts = $pdo->query("SELECT full_name, email FROM users WHERE status='Active' ORDER BY full_name")->fetchAll();

$v = $visitor ?: [
    'full_name' => '', 'email' => '', 'id_passport' => '', 'phone' => '', 'company' => '',
    'host_name' => current_user()['full_name'] ?? '', 'host_email' => current_user()['email'] ?? '',
    'purpose' => '', 'notes' => '', 'visit_date' => date('Y-m-d'), 'status' => 'Pending', 'photo_path' => null,
];

render_header($visitor ? 'Edit Visitor' : __('add_visitor'), 'visitors');
?>
<div class="form-card">
    <div class="section-head">
        <div>
            <h2><?= e($visitor ? 'Edit Visitor' : __('add_visitor')) ?></h2>
            <p>Capture guest details, host contact, and an optional photo.</p>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="photo_data" id="photo_data" value="">
        <div class="form-grid">
            <div class="field">
                <label>Full name</label>
                <input name="full_name" required value="<?= e($v['full_name']) ?>">
            </div>
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="<?= e((string) $v['email']) ?>">
            </div>
            <div class="field">
                <label>ID / Passport</label>
                <input name="id_passport" required value="<?= e($v['id_passport']) ?>">
            </div>
            <div class="field">
                <label>Phone</label>
                <input name="phone" value="<?= e((string) $v['phone']) ?>">
            </div>
            <div class="field">
                <label>Company</label>
                <input name="company" value="<?= e((string) $v['company']) ?>">
            </div>
            <div class="field">
                <label>Host</label>
                <input list="host-list" name="host_name" required value="<?= e($v['host_name']) ?>" id="host_name">
                <datalist id="host-list">
                    <?php foreach ($hosts as $h): ?>
                        <option value="<?= e($h['full_name']) ?>" data-email="<?= e($h['email']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="field">
                <label>Host email (for notifications)</label>
                <input type="email" name="host_email" id="host_email" value="<?= e((string) ($v['host_email'] ?? '')) ?>">
            </div>
            <div class="field">
                <label>Visit date</label>
                <input type="date" name="visit_date" required value="<?= e($v['visit_date']) ?>">
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['Pending','Approved','Checked In','Checked Out','Rejected'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $v['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label>Purpose</label>
                <textarea name="purpose" rows="2"><?= e((string) $v['purpose']) ?></textarea>
            </div>
            <div class="field full">
                <label>Notes</label>
                <textarea name="notes" rows="2"><?= e((string) ($v['notes'] ?? '')) ?></textarea>
            </div>
            <div class="field">
                <label>Photo upload</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($v['photo_path'])): ?>
                    <p class="muted" style="margin-top:0.4rem"><img class="avatar-md" src="<?= e(app_url($v['photo_path'])) ?>" alt=""></p>
                <?php endif; ?>
            </div>
            <div class="field">
                <label>Or capture webcam</label>
                <div class="cam-capture">
                    <video id="cam-preview" autoplay playsinline muted></video>
                    <canvas id="cam-canvas" hidden></canvas>
                    <div class="actions" style="margin-top:0.5rem">
                        <button class="btn btn-sm" type="button" id="cam-start">Start cam</button>
                        <button class="btn btn-sm" type="button" id="cam-snap" hidden>Capture</button>
                    </div>
                    <img id="cam-shot" class="avatar-md" alt="" hidden>
                </div>
            </div>
        </div>
        <div style="margin-top:1.25rem; display:flex; gap:0.6rem;">
            <button class="btn btn-solid" type="submit">Save</button>
            <a class="btn" href="<?= e(app_url('visitors.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<script>
(() => {
  const hosts = <?= json_encode($hosts) ?>;
  const hostName = document.getElementById('host_name');
  const hostEmail = document.getElementById('host_email');
  hostName.addEventListener('change', () => {
    const hit = hosts.find(h => h.full_name === hostName.value);
    if (hit && !hostEmail.value) hostEmail.value = hit.email;
  });

  const video = document.getElementById('cam-preview');
  const canvas = document.getElementById('cam-canvas');
  const shot = document.getElementById('cam-shot');
  const photoData = document.getElementById('photo_data');
  let stream = null;
  document.getElementById('cam-start').addEventListener('click', async () => {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      video.srcObject = stream;
      document.getElementById('cam-snap').hidden = false;
    } catch (e) {
      alert('Camera unavailable');
    }
  });
  document.getElementById('cam-snap').addEventListener('click', () => {
    canvas.width = video.videoWidth || 320;
    canvas.height = video.videoHeight || 240;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const data = canvas.toDataURL('image/jpeg', 0.85);
    photoData.value = data;
    shot.src = data;
    shot.hidden = false;
    if (stream) stream.getTracks().forEach(t => t.stop());
  });
})();
</script>
<?php render_footer(); ?>
