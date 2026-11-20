<?php
/**
 * Registration confirmation (printable).
 * Only reachable with the reference number AND the secret access key,
 * so a participant can only see their own registration.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$ref = strtoupper(get('ref'));
$key = strtolower(get('key'));

$reg = null;
if ($ref !== '' && $key !== '') {
    $reg = q_row(registration_select_sql() . ' WHERE r.reference_no = ?', [$ref]);
    if ($reg && !hash_equals($reg['access_key'], $key)) {
        $reg = null;
    }
}
if (!$reg) {
    public_message('Registration Not Found', 'We could not find that registration. Please check your link or use “Find my registration”.', 'danger', 'search');
}
$conf  = q_row('SELECT * FROM conferences WHERE id = ?', [$reg['conference_id']]);
$isNew = get('new') === '1';
$attendanceQr = 'ATTEND:' . $reg['attendance_token'];

$statusInfo = [
    'pending'   => ['warning', 'hourglass-split', 'Registration Received', 'Your registration has been received and is waiting for confirmation by the organizers.'],
    'confirmed' => ['success', 'check-lg', 'Registration Confirmed', 'You are officially registered for this conference. See you there!'],
    'cancelled' => ['danger', 'x-lg', 'Registration Cancelled', 'This registration has been cancelled. Please contact the organizers if you think this is a mistake.'],
][$reg['status']];
if ($isNew) {
    $statusInfo[2] = $reg['status'] === 'confirmed' ? 'Registration Successful!' : 'Registration Submitted!';
}

public_header('Registration ' . $reg['reference_no']);
?>
<?php if ($isNew): ?>
<div class="steps no-print" aria-label="Registration progress">
  <div class="step done"><span class="num"><i class="bi bi-check"></i></span><span class="lbl">Fill Out Form</span></div>
  <div class="step done"><span class="num"><i class="bi bi-check"></i></span><span class="lbl">Review</span></div>
  <div class="step active"><span class="num">3</span><span class="lbl">Confirmation</span></div>
</div>
<?php endif; ?>

<div class="print-only" style="text-align:center;margin-bottom:12px">
  <strong style="font-size:16px"><?= e(APP_NAME) ?></strong><br>
  <span>Registration Confirmation</span>
</div>

<div class="card status-screen" style="padding-bottom:1.5rem">
  <div class="big-icon <?= $statusInfo[0] ?>"><i class="bi bi-<?= $statusInfo[1] ?>"></i></div>
  <h1><?= e($statusInfo[2]) ?></h1>
  <p class="muted" style="max-width:460px;margin:0 auto"><?= e($statusInfo[3]) ?></p>
  <div class="ref-box">
    <small>Registration Reference No.</small>
    <strong><?= e($reg['reference_no']) ?></strong>
  </div>
  <div><?= status_badge($reg['status']) ?> <?= $reg['checked_in_at'] ? '<span class="badge badge-blue"><i class="bi bi-person-check"></i> Checked in ' . e(fmt_datetime($reg['checked_in_at'])) . '</span>' : '' ?></div>
</div>

<div class="section mt-2">
  <div class="section-head"><span class="icon"><i class="bi bi-calendar-event"></i></span><div><h2>Conference Details</h2></div></div>
  <div class="section-body">
    <dl class="info-list">
      <div class="full"><dt>Conference</dt><dd><?= e($conf['title']) ?></dd></div>
      <div><dt>Date &amp; Time</dt><dd><?= e(fmt_conf_schedule($conf)) ?></dd></div>
      <div><dt>Venue</dt><dd><?= e($conf['venue']) ?></dd></div>
      <?php if ($reg['attendance_category']): ?><div><dt>Attendance Category</dt><dd><?= e($reg['attendance_category']) ?></dd></div><?php endif; ?>
      <div><dt>Registered On</dt><dd><?= e(fmt_datetime($reg['registered_at'])) ?></dd></div>
    </dl>
  </div>
</div>

<div class="section">
  <div class="section-head"><span class="icon"><i class="bi bi-person-vcard"></i></span><div><h2>Participant</h2></div></div>
  <div class="section-body">
    <dl class="info-list">
      <div><dt>Name</dt><dd><?= e(full_name($reg)) ?></dd></div>
      <div><dt>ID Number</dt><dd><?= e($reg['id_number'] ?: '—') ?></dd></div>
      <div><dt>Participant Type</dt><dd><?= e($reg['type_name']) ?></dd></div>
      <div><dt>Contact Number</dt><dd><?= e($reg['contact_number']) ?></dd></div>
      <?php if (school_label($reg) !== '—'): ?><div><dt>School / Institution</dt><dd><?= e(school_label($reg)) ?></dd></div><?php endif; ?>
      <?php if ($reg['type_code'] !== 'guest'): ?><div><dt><?= $reg['type_code'] === 'faculty' ? 'Department' : 'Course / Program' ?></dt><dd><?= e(course_label($reg)) ?></dd></div><?php endif; ?>
      <?php if ($reg['year_level']): ?><div><dt>Year Level &amp; Section</dt><dd><?= e($reg['year_level'] . ($reg['section'] ? ' – ' . $reg['section'] : '')) ?></dd></div><?php endif; ?>
    </dl>
  </div>
</div>

<?php if ($reg['status'] !== 'cancelled'): ?>
<div class="section">
  <div class="section-head"><span class="icon"><i class="bi bi-qr-code"></i></span><div><h2>Your Attendance QR Code</h2><p>Show this at the registration desk on the day of the conference</p></div></div>
  <div class="section-body text-center">
    <div class="qr-holder"><canvas id="att-qr" data-qr="<?= e($attendanceQr) ?>" data-size="220" aria-label="Attendance QR code"></canvas></div>
    <p class="small muted mt-1 mb-0">This QR code is personal – do not share it. You can also be checked in using your reference number.</p>
  </div>
</div>
<?php endif; ?>

<div class="alert alert-info no-print"><i class="bi bi-bookmark-star"></i><div><strong>Save this page.</strong> Take a screenshot, print it, or bookmark this link. You can also find it again with your reference number and ID number on the <a href="<?= e(url('lookup.php')) ?>">Find my registration</a> page.</div></div>

<div class="flex no-print" style="justify-content:center">
  <button class="btn btn-primary" data-print><i class="bi bi-printer"></i> Print Confirmation</button>
  <?php if ($reg['status'] !== 'cancelled'): ?>
    <button class="btn btn-outline" id="dl-qr"><i class="bi bi-download"></i> Save QR Code</button>
  <?php endif; ?>
</div>

<script>
document.addEventListener('click', function (e) {
  if (e.target.closest('#dl-qr')) {
    var c = document.createElement('canvas');
    drawQR(c, <?= json_encode($attendanceQr) ?>, { size: 360, caption: <?= json_encode(full_name($reg)) ?>, subcaption: <?= json_encode($reg['reference_no'] . ' · ' . $conf['title']) ?> });
    downloadCanvas(c, 'attendance-qr-<?= e($reg['reference_no']) ?>.png');
  }
});
</script>
<?php public_footer(['vendor/qrcode.js']); ?>
