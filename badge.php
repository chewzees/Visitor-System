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

// Signed QR valid for 7 days (still accepts plain tok_ on older badges)
$qrText = badge_qr_payload($v['qr_token'], 7 * 24 * 3600);

render_header('Visitor Badge', 'visitors');
?>
<div class="badge-sheet">
    <div class="badge-card">
        <div class="badge-brand"><?= e($config['APP_NAME']) ?></div>
        <div class="badge-body">
            <div class="badge-photo">
                <?php if (!empty($v['photo_path'])): ?>
                    <img src="<?= e(app_url($v['photo_path'])) ?>" alt="">
                <?php else: ?>
                    <div class="badge-photo-empty"><?= e(strtoupper(substr($v['full_name'], 0, 1))) ?></div>
                <?php endif; ?>
            </div>
            <div class="badge-meta">
                <h2><?= e($v['full_name']) ?></h2>
                <p>Host: <?= e($v['host_name']) ?></p>
                <p>Date: <?= e($v['visit_date']) ?></p>
                <p>ID: <?= e($v['id_passport']) ?></p>
                <p><span class="<?= e(status_class($v['status'])) ?>"><?= e($v['status']) ?></span></p>
            </div>
            <div class="qr-box badge-qr">
                <div id="badge-qr"></div>
            </div>
        </div>
        <p class="badge-foot muted">Present this badge at reception · <?= e($v['qr_token']) ?></p>
    </div>

    <p class="no-print" style="margin-top:1rem; text-align:center;">
        <button class="btn btn-solid" type="button" onclick="window.print()"><?= e(__('print')) ?></button>
        <a class="btn" href="<?= e(app_url('scan.php')) ?>">Open scanner</a>
        <a class="btn" href="<?= e(app_url('visitor_view.php?id=' . $v['id'])) ?>">Back</a>
    </p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  var el = document.getElementById('badge-qr');
  var text = <?= json_encode($qrText, JSON_UNESCAPED_SLASHES) ?>;
  if (typeof QRCode === 'undefined') {
    el.innerHTML = '<p style="color:#c00">QR library failed to load.</p>';
    return;
  }
  new QRCode(el, { text: text, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M });
})();
</script>
<?php render_footer(); ?>
