<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require_roles(['Admin', 'Security']);

$checkinUrl = app_url('checkin.php?e=' . urlencode($config['ENTRANCE_TOKEN']));

render_header(__('entrance_qr'), 'entrance');
?>
<div class="center-card">
    <h2><?= e(__('entrance_qr')) ?></h2>
    <p class="muted">Print this page and place the QR at the entrance. Visitors must scan it to open the check-in form.</p>
    <div class="qr-box">
        <div id="entrance-qr"></div>
    </div>
    <p>Link encoded in QR:</p>
    <p class="link-accent"><?= e($checkinUrl) ?></p>
    <p class="no-print" style="margin-top:1.25rem">
        <button class="btn btn-solid" type="button" onclick="window.print()"><?= e(__('print')) ?></button>
    </p>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('entrance-qr'), {
    text: <?= json_encode($checkinUrl) ?>,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.M
});
</script>
<?php render_footer(); ?>
