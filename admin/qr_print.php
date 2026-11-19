<?php
/** Printable QR code poster (A4). */
require __DIR__ . '/_init.php';
require_admin();

$qr = q_row('SELECT q.*, c.* , q.id AS qr_id FROM qr_codes q JOIN conferences c ON c.id = q.conference_id WHERE q.id = ?', [(int) get('id')]);
if (!$qr || !can_access_conference((int) $qr['conference_id'])) {
    flash('error', 'QR code not found.');
    redirect('admin/qrcodes.php');
}
$link = registration_url($qr['token']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Poster · <?= e($qr['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
  body { background: #E2E8F0; }
  .poster { width: 210mm; min-height: 297mm; margin: 20px auto; background: #fff; padding: 18mm 16mm; text-align: center; display: flex; flex-direction: column; align-items: center; box-shadow: 0 10px 40px rgba(0,0,0,.15); }
  .poster .org { font-weight: 700; color: var(--gray-500); letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
  .poster h1 { font-size: 34px; font-weight: 800; color: var(--navy); margin: 10px 0 6px; }
  .poster .meta { color: var(--gray-700); font-size: 16px; margin: 4px 0; }
  .poster .cta { margin-top: 26px; background: var(--primary); color: #fff; display: inline-block; padding: 10px 26px; border-radius: 999px; font-weight: 700; font-size: 20px; letter-spacing: .02em; }
  .poster .qr { margin: 22px 0; padding: 18px; border: 3px solid var(--navy); border-radius: 20px; }
  .poster .steps-p { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; width: 100%; margin-top: 10px; }
  .poster .steps-p div { background: var(--bg); border-radius: 12px; padding: 12px; font-size: 14px; color: var(--gray-700); }
  .poster .steps-p strong { display: block; font-size: 22px; color: var(--primary); }
  .poster .url { margin-top: auto; font-size: 12px; color: var(--gray-500); word-break: break-all; }
  .bar { text-align: center; padding: 14px; }
  @media print { body { background: #fff; } .poster { margin: 0; box-shadow: none; width: auto; min-height: auto; } .bar { display: none; } @page { size: A4; margin: 10mm; } }
</style>
</head>
<body>
<div class="bar">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Poster</button>
  <a class="btn btn-outline" href="<?= e(url('admin/qrcodes.php?conference=' . $qr['conference_id'])) ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="poster">
  <div class="org"><?= e(APP_NAME) ?></div>
  <h1><?= e($qr['title']) ?></h1>
  <div class="meta"><i class="bi bi-calendar3"></i> <?= e(fmt_conf_schedule($qr)) ?></div>
  <div class="meta"><i class="bi bi-geo-alt"></i> <?= e($qr['venue']) ?></div>
  <div class="cta">SCAN TO REGISTER</div>
  <div class="qr"><canvas data-qr="<?= e($link) ?>" data-size="400" data-margin="8"></canvas></div>
  <div class="steps-p">
    <div><strong>1</strong>Open your phone camera and scan the QR code</div>
    <div><strong>2</strong>Fill out your personal &amp; academic information</div>
    <div><strong>3</strong>Review, submit and save your confirmation</div>
  </div>
  <?php if (!$qr['is_active']): ?><p style="color:var(--danger);font-weight:700;margin-top:14px">⚠ This QR code is currently INACTIVE</p><?php endif; ?>
  <div class="url">Or visit: <?= e($link) ?></div>
</div>
<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
