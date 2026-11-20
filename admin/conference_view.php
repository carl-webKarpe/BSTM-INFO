<?php
require __DIR__ . '/_init.php';
require APP_ROOT . '/includes/stats.php';
require_admin();

$id = (int) get('id');
require_conference_access($id);
$c = q_row('SELECT c.*, ay.name AS ay_name, a.full_name AS creator FROM conferences c
            LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
            LEFT JOIN admins a ON a.id = c.created_by WHERE c.id = ?', [$id]);
if (!$c) {
    flash('error', 'Conference not found.');
    redirect('admin/conferences.php');
}
$st  = conference_state($c);
$sum = stats_summary(' AND r.conference_id = ?', [$id]);
$qr  = q_row('SELECT * FROM qr_codes WHERE conference_id = ? ORDER BY is_active DESC, id DESC LIMIT 1', [$id]);
$admins = q_all('SELECT a.full_name, a.username FROM admin_conferences ac JOIN admins a ON a.id = ac.admin_id WHERE ac.conference_id = ?', [$id]);
$types = array_filter(['Student' => $c['allow_student'], 'Faculty' => $c['allow_faculty'], 'Guest' => $c['allow_guest']]);

admin_header($c['title'], 'conferences', 'Conference Management');
?>
<div class="page-head">
  <div>
    <div class="flex mb-1">
      <span class="badge badge-<?= $st['open'] ? 'success' : ($st['tone'] === 'warning' ? 'warning' : 'muted') ?>"><span class="dot"></span> Registration <?= e($st['label']) ?></span>
      <?php if ($c['is_archived']): ?><span class="badge badge-muted"><i class="bi bi-archive"></i> Archived</span><?php endif; ?>
    </div>
    <h2><?= e($c['title']) ?></h2>
    <p><?= e($st['message']) ?></p>
  </div>
  <div class="toolbar">
    <a class="btn btn-outline" href="<?= e(url('admin/conference_form.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Edit</a>
    <form method="post" action="<?= e(url('admin/conference_action.php')) ?>" class="dropdown">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <button type="button" class="btn btn-outline" data-dropdown><i class="bi bi-three-dots"></i> More</button>
      <div class="dropdown-menu">
        <?php if (!$c['is_archived']): ?>
          <button name="action" value="toggle_registration"><i class="bi bi-<?= $c['registration_enabled'] ? 'pause-circle' : 'play-circle' ?>"></i> <?= $c['registration_enabled'] ? 'Disable' : 'Enable' ?> registration</button>
        <?php endif; ?>
        <?php if (is_super()): ?>
          <?php if ($c['is_archived']): ?>
            <button name="action" value="unarchive"><i class="bi bi-box-arrow-up"></i> Restore from archive</button>
          <?php else: ?>
            <button name="action" value="archive" data-confirm="Archive this conference? Registration will close and it will move to the Archived tab."><i class="bi bi-archive"></i> Archive conference</button>
          <?php endif; ?>
          <hr>
          <button name="action" value="delete" class="danger" data-confirm="Permanently delete this conference<?= $sum['total'] + $sum['cancelled'] ? ' and its ' . ($sum['total'] + $sum['cancelled']) . ' registration(s)' : '' ?>? This cannot be undone."><i class="bi bi-trash"></i> Delete conference</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="ic ic-blue"><i class="bi bi-people-fill"></i></div><div><div class="val"><?= $sum['total'] ?><?= $c['participant_limit'] ? '<span class="small muted"> / ' . (int) $c['participant_limit'] . '</span>' : '' ?></div><div class="lbl">Registered</div></div></div>
  <div class="stat"><div class="ic ic-green"><i class="bi bi-patch-check-fill"></i></div><div><div class="val"><?= $sum['confirmed'] ?></div><div class="lbl">Confirmed</div></div></div>
  <div class="stat"><div class="ic ic-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="val"><?= $sum['pending'] ?></div><div class="lbl">Pending</div></div></div>
  <div class="stat"><div class="ic ic-purple"><i class="bi bi-person-check-fill"></i></div><div><div class="val"><?= $sum['checked_in'] ?></div><div class="lbl">Checked In</div></div></div>
</div>

<div class="grid-3">
  <div class="card span-2">
    <div class="card-header"><h3>Conference Information</h3></div>
    <div class="card-body">
      <dl class="info-list">
        <div class="full"><dt>Conference Name</dt><dd><?= e($c['title']) ?></dd></div>
        <?php if ($c['description']): ?><div class="full"><dt>Description</dt><dd style="font-weight:400"><?= nl2br(e($c['description'])) ?></dd></div><?php endif; ?>
        <div><dt>Date &amp; Time</dt><dd><?= e(fmt_conf_schedule($c)) ?></dd></div>
        <div><dt>Venue</dt><dd><?= e($c['venue']) ?></dd></div>
        <div><dt>Academic Year</dt><dd><?= e($c['ay_name'] ?: '—') ?></dd></div>
        <div><dt>Participant Limit</dt><dd><?= $c['participant_limit'] ? (int) $c['participant_limit'] : 'Unlimited' ?></dd></div>
        <div><dt>Registration Opens</dt><dd><?= e($c['registration_open'] ? fmt_datetime($c['registration_open']) : 'Immediately') ?></dd></div>
        <div><dt>Registration Closes</dt><dd><?= e($c['registration_close'] ? fmt_datetime($c['registration_close']) : 'No deadline') ?></dd></div>
        <div><dt>Participant Types</dt><dd><?= e(implode(', ', array_keys($types))) ?></dd></div>
        <div><dt>New Registrations</dt><dd>Automatically confirmed</dd></div>
        <div class="full"><dt>Attendance Categories</dt><dd><?= e(implode(' · ', conference_categories($c)) ?: '—') ?></dd></div>
        <div><dt>Assigned Conference Admins</dt><dd><?= $admins ? e(implode(', ', array_column($admins, 'full_name'))) : '<span class="muted">Super Admins only</span>' ?></dd></div>
        <div><dt>Created</dt><dd><?= e(fmt_datetime($c['created_at'])) ?><?= $c['creator'] ? ' by ' . e($c['creator']) : '' ?></dd></div>
      </dl>
    </div>
    <div class="card-footer flex">
      <a class="btn btn-primary btn-sm" href="<?= e(url('admin/participants.php?conference=' . $id)) ?>"><i class="bi bi-people"></i> Participants</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/attendance.php?conference=' . $id)) ?>"><i class="bi bi-person-check"></i> Attendance</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/reports.php?conference=' . $id)) ?>"><i class="bi bi-bar-chart-line"></i> Report</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Registration QR Code</h3><?php if ($qr): ?><?= $qr['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?><?php endif; ?></div>
    <div class="card-body text-center">
      <?php if ($qr): $link = registration_url($qr['token']); ?>
        <div class="qr-holder"><canvas data-qr="<?= e($link) ?>" data-size="200"></canvas></div>
        <div class="copy-row mt-2"><input class="input input-sm" value="<?= e($link) ?>" readonly onfocus="this.select()"><button class="btn btn-outline btn-sm" data-copy="<?= e($link) ?>" title="Copy link"><i class="bi bi-clipboard"></i></button></div>
        <p class="small muted mt-1 mb-0"><?= (int) $qr['scan_count'] ?> scans</p>
      <?php else: ?>
        <p class="muted">No QR code yet.</p>
      <?php endif; ?>
      <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('admin/qrcodes.php?conference=' . $id)) ?>"><i class="bi bi-qr-code"></i> Manage QR Codes</a>
    </div>
  </div>
</div>
<?php admin_footer(['vendor/qrcode.js']); ?>
