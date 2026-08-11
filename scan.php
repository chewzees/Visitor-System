<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_roles(['Admin', 'Security']);

$kiosk = isset($_GET['kiosk']);
$recent = $pdo->query(
    "SELECT id, full_name, qr_token, status
     FROM visitors
     ORDER BY updated_at DESC, id DESC
     LIMIT 12"
)->fetchAll();

if ($kiosk) {
    ?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Scan — <?= e($config['APP_NAME']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Syne:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="app-body kiosk-body">
<div class="kiosk-shell">
    <header class="kiosk-top">
        <div class="brand"><?= e($config['APP_NAME']) ?> · Door Scan</div>
        <a class="btn btn-sm" href="<?= e(app_url('scan.php')) ?>">Exit kiosk</a>
    </header>
    <div class="kiosk-grid">
        <section class="panel kiosk-cam">
            <div id="reader" class="scan-reader"></div>
            <p class="camera-status" id="scan-status">Starting camera…</p>
            <div class="actions">
                <button class="btn btn-solid" type="button" id="start-scan"><?= e(__('start_camera')) ?></button>
                <button class="btn" type="button" id="stop-scan" hidden>Stop</button>
                <label class="btn" for="qr-file">Upload QR</label>
                <input type="file" id="qr-file" accept="image/*" hidden>
            </div>
        </section>
        <section class="panel kiosk-result">
            <h3>Result</h3>
            <div id="scan-result" class="muted">Awaiting scan…</div>
            <div class="field" style="margin-top:1rem;">
                <label>Manual token</label>
                <input id="manual-token" placeholder="tok_…">
            </div>
            <button class="btn" type="button" id="manual-submit" style="margin-top:0.6rem;">Lookup</button>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
window.VMS_SCAN = {
  apiUrl: <?= json_encode(app_url('api/scan.php')) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  autoStart: true
};
</script>
<script src="<?= e(app_url('assets/js/scan.js')) ?>"></script>
</body>
</html>
    <?php
    exit;
}

render_header(__('scan_qr'), 'scan');
?>
<div class="panel-grid scan-layout">
    <section class="panel">
        <div class="panel-title">
            <h3><?= e(__('scan_qr')) ?></h3>
            <a class="btn btn-sm" href="<?= e(app_url('scan.php?kiosk=1')) ?>">Kiosk mode</a>
        </div>
        <p class="muted">Scan a visitor badge QR to check in or check out. Camera works best on HTTPS or localhost.</p>

        <div id="reader" class="scan-reader"></div>
        <p class="camera-status" id="scan-status">Camera idle.</p>

        <div class="actions" style="margin-top:0.75rem;">
            <button class="btn btn-solid" type="button" id="start-scan"><?= e(__('start_camera')) ?></button>
            <button class="btn" type="button" id="stop-scan" hidden>Stop</button>
            <label class="btn" for="qr-file">Upload QR image</label>
            <input type="file" id="qr-file" accept="image/*" hidden>
        </div>
    </section>

    <section class="panel">
        <div class="panel-title">
            <h3>Result</h3>
        </div>
        <div id="scan-result" class="muted">Awaiting scan…</div>

        <div style="margin-top:1.25rem;">
            <div class="field">
                <label>Manual token / visitor ID</label>
                <input id="manual-token" placeholder="tok_… or visitor id" autocomplete="off">
            </div>
            <button class="btn" type="button" id="manual-submit" style="margin-top:0.75rem;">Lookup &amp; toggle</button>
        </div>

        <?php if ($recent): ?>
        <div style="margin-top:1.5rem;">
            <p class="muted" style="margin-bottom:0.6rem;">Quick test (click to scan token)</p>
            <div class="actions">
                <?php foreach ($recent as $row): ?>
                    <button
                        type="button"
                        class="btn btn-sm quick-token"
                        data-token="<?= e($row['qr_token']) ?>"
                        title="<?= e($row['qr_token']) ?>"
                    ><?= e($row['full_name']) ?> · <?= e($row['status']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
window.VMS_SCAN = {
  apiUrl: <?= json_encode(app_url('api/scan.php')) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  autoStart: false
};
</script>
<script src="<?= e(app_url('assets/js/scan.js')) ?>"></script>
<?php render_footer(); ?>
