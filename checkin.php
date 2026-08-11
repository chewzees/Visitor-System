<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$entranceOk = isset($_GET['e']) && hash_equals($config['ENTRANCE_TOKEN'], (string) $_GET['e']);
$step = $entranceOk ? 'form' : 'scan';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $idPassport = trim((string) ($_POST['id_passport'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $hostName = trim((string) ($_POST['host_name'] ?? ''));
    $hostEmail = trim((string) ($_POST['host_email'] ?? ''));
    $purpose = trim((string) ($_POST['purpose'] ?? ''));
    $visitDate = trim((string) ($_POST['visit_date'] ?? date('Y-m-d')));
    $company = trim((string) ($_POST['company'] ?? ''));

    if ($fullName === '' || $idPassport === '' || $hostName === '') {
        $error = 'Name, ID/Passport, and host are required.';
        $step = 'form';
        $entranceOk = true;
    } elseif (is_blacklisted($pdo, $idPassport)) {
        $error = 'Access denied. Please see reception.';
        $step = 'form';
        $entranceOk = true;
    } else {
        $photoPath = save_visitor_photo($_FILES['photo'] ?? null);
        $dataUrlPhoto = save_visitor_photo_data_url($_POST['photo_data'] ?? null);
        if ($dataUrlPhoto) {
            $photoPath = $dataUrlPhoto;
        }

        $token = qr_token();
        $stmt = $pdo->prepare(
            'INSERT INTO visitors (full_name, email, id_passport, phone, company, host_name, host_email, purpose, visit_date, status, qr_token, photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $fullName, $email ?: null, $idPassport, $phone ?: null, $company ?: null,
            $hostName, $hostEmail ?: null, $purpose ?: null, $visitDate ?: date('Y-m-d'),
            'Pending', $token, $photoPath,
        ]);
        $newId = (int) $pdo->lastInsertId();
        log_activity($pdo, 'visitor_self_checkin', $fullName . ' submitted check-in form');
        $created = fetch_visitor($pdo, $newId);
        if ($created && !empty($config['NOTIFY_HOST_ON_REGISTER'])) {
            notify_host($pdo, $created, 'register');
        }
        $success = 'Check-in submitted. Please wait for approval at reception.';
        $step = 'done';
    }
}

$checkinScanTarget = app_url('checkin.php?e=' . urlencode($config['ENTRANCE_TOKEN']));
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('checkin_title')) ?> — <?= e($config['APP_NAME']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&family=Syne:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="public-body">
<div class="public-shell">
    <header class="public-top">
        <div class="brand"><?= e($config['APP_NAME']) ?></div>
        <nav class="public-nav">
            <span><?= e(__('language')) ?>:
                <a href="?lang=en<?= $entranceOk ? '&e=' . e($config['ENTRANCE_TOKEN']) : '' ?>">EN</a> /
                <a href="?lang=zh<?= $entranceOk ? '&e=' . e($config['ENTRANCE_TOKEN']) : '' ?>">中文</a>
            </span>
            <a href="<?= e(app_url('manual.php')) ?>">Help</a>
            <a href="<?= e(app_url('login.php')) ?>"><?= e(__('sign_in')) ?></a>
        </nav>
    </header>

    <main class="public-main">
        <div class="public-card" style="width:min(520px,100%);">
            <?php if ($step === 'scan'): ?>
                <h1><?= e(__('checkin_title')) ?></h1>
                <p><?= e(__('checkin_help')) ?></p>
                <hr>
                <div id="reader"></div>
                <div class="camera-status" id="cam-status">Camera idle.</div>
                <button class="btn-auth" type="button" id="start-cam"><?= e(__('start_camera')) ?></button>
                <p style="margin-top:1rem; font-size:0.85rem;">
                    Or open directly:
                    <a href="<?= e($checkinScanTarget) ?>">check-in form</a>
                </p>
            <?php elseif ($step === 'form'): ?>
                <h1><?= e(__('checkin_title')) ?></h1>
                <p>Fill in your details to register your visit.</p>
                <hr>
                <?php if ($error): ?><div class="auth-alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data" style="text-align:left;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="photo_data" id="photo_data" value="">
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Full name</label>
                        <input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>ID / Passport</label>
                        <input name="id_passport" required value="<?= e($_POST['id_passport'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Phone</label>
                        <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Company</label>
                        <input name="company" value="<?= e($_POST['company'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Host</label>
                        <input name="host_name" required value="<?= e($_POST['host_name'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Host email (optional)</label>
                        <input type="email" name="host_email" value="<?= e($_POST['host_email'] ?? '') ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Visit date</label>
                        <input type="date" name="visit_date" value="<?= e($_POST['visit_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Purpose</label>
                        <textarea name="purpose" rows="2"><?= e($_POST['purpose'] ?? '') ?></textarea>
                    </div>
                    <div class="field" style="margin-bottom:0.75rem;">
                        <label>Photo (optional)</label>
                        <input type="file" name="photo" accept="image/*">
                    </div>
                    <div class="field" style="margin-bottom:1rem;">
                        <label>Or take a selfie</label>
                        <video id="cam-preview" autoplay playsinline muted style="width:100%;max-height:180px;background:#111;"></video>
                        <div style="margin-top:0.4rem;display:flex;gap:0.4rem;">
                            <button class="btn btn-sm" type="button" id="cam-start">Start cam</button>
                            <button class="btn btn-sm" type="button" id="cam-snap" hidden>Capture</button>
                        </div>
                        <img id="cam-shot" alt="" hidden style="margin-top:0.5rem;max-width:120px;">
                        <canvas id="cam-canvas" hidden></canvas>
                    </div>
                    <button class="btn-auth" type="submit">Submit</button>
                </form>
            <?php else: ?>
                <h1>Thank you</h1>
                <p><?= e($success) ?></p>
                <hr>
                <a class="btn-auth" style="display:inline-block; width:auto; padding:0.8rem 1.4rem;" href="<?= e(app_url('checkin.php?e=' . urlencode($config['ENTRANCE_TOKEN']))) ?>">New registration</a>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($step === 'scan'): ?>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
  const statusEl = document.getElementById('cam-status');
  const startBtn = document.getElementById('start-cam');
  const expected = <?= json_encode($checkinScanTarget) ?>;
  startBtn.addEventListener('click', async () => {
    try {
      const scanner = new Html5Qrcode('reader');
      await scanner.start(
        { facingMode: 'environment' },
        { fps: 8, qrbox: { width: 240, height: 240 } },
        async (decoded) => {
          statusEl.textContent = 'QR detected…';
          if (decoded.includes('checkin.php') || decoded === expected) {
            await scanner.stop();
            window.location.href = decoded.includes('http') ? decoded : expected;
          } else {
            statusEl.textContent = 'Not an entrance QR. Try again.';
          }
        },
        () => {}
      );
      statusEl.textContent = 'Camera active.';
      startBtn.hidden = true;
    } catch (err) {
      statusEl.textContent = 'Camera error. Use the direct form link below.';
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($step === 'form'): ?>
<script>
(() => {
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
    } catch (e) { alert('Camera unavailable'); }
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
<?php endif; ?>
</body>
</html>
