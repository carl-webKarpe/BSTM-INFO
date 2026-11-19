<?php
require __DIR__ . '/_init.php';
require_admin();

$confs  = accessible_conferences();
$confId = (int) get('conference');
if ($confId && !can_access_conference($confId)) {
    $confId = 0;
}

$sql = "SELECT q.*, c.title, c.start_datetime, c.venue, c.is_archived, c.registration_enabled,
          (SELECT COUNT(*) FROM conference_registrations r WHERE r.qr_code_id = q.id) AS reg_count,
          (SELECT COUNT(*) FROM conference_registrations r WHERE r.conference_id = q.conference_id AND r.status <> 'cancelled') AS conf_total
        FROM qr_codes q JOIN conferences c ON c.id = q.conference_id WHERE 1=1" . conf_scope('c.id');
$params = [];
if ($confId) {
    $sql .= ' AND q.conference_id = ?';
    $params[] = $confId;
} else {
    $sql .= ' AND c.is_archived = 0';
}
$sql .= ' ORDER BY c.start_datetime ASC, q.is_active DESC, q.id DESC';
$codes = q_all($sql, $params);

$base = public_base_url();
$isLocal = (bool) preg_match('~^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?(/|$)~i', $base);

admin_header('QR Code Management', 'qrcodes');
?>
<div class="page-head">
  <div>
    <h2>Registration QR Codes</h2>
    <p>Display or print these QR codes. Participants scan them to open the conference registration form.</p>
  </div>
  <div class="toolbar">
    <?php if ($confs): ?>
    <form method="get">
      <select class="input input-sm" name="conference" data-autosubmit style="min-width:220px" aria-label="Show event">
        <option value="">All active events</option>
        <?php foreach ($confs as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $confId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?><?= $c['is_archived'] ? ' (archived)' : '' ?></option><?php endforeach; ?>
      </select>
    </form>
    <button class="btn btn-outline btn-sm" data-open-modal="new-qr" title="Extra QR code for an existing event"><i class="bi bi-plus-lg"></i> Extra QR</button>
    <?php endif; ?>
  </div>
</div>

<?php if (is_super()): ?>
<div class="card mb-2">
  <div class="card-body">
    <form method="post" action="<?= e(url('admin/qr_action.php')) ?>" class="filters" style="grid-template-columns:2fr 1fr 1fr auto" data-once>
      <?= csrf_field() ?><input type="hidden" name="action" value="quick_create">
      <div class="field"><label for="qc-title"><i class="bi bi-qr-code"></i> Event Name<span class="req">*</span></label>
        <input class="input" id="qc-title" name="title" required maxlength="190" placeholder="Type the event name, e.g. BSTM Seminar 2026" autocomplete="off"></div>
      <div class="field"><label for="qc-date">Event Date <span class="opt">(optional)</span></label>
        <input class="input" type="date" id="qc-date" name="date" value="<?= e(date('Y-m-d')) ?>"></div>
      <div class="field"><label for="qc-venue">Venue <span class="opt">(optional)</span></label>
        <input class="input" id="qc-venue" name="venue" maxlength="255" placeholder="To be announced"></div>
      <div class="field"><button class="btn btn-primary" style="height:46px"><i class="bi bi-qr-code-scan"></i> Create QR Code</button></div>
    </form>
    <p class="small muted mt-1 mb-0">Creates the event and its QR code. Students who scan it go straight to the registration form. You can add more details later in Conference Management.</p>
  </div>
</div>
<?php endif; ?>

<?php if ($isLocal): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>
    <strong>These QR codes point to <code><?= e($base) ?></code>, which phones cannot open.</strong>
    “localhost” only works on this computer. For testing with phones on the same Wi-Fi, open the admin using your computer's IP address
    (e.g. <code>http://192.168.1.5<?= e(app_base_path()) ?>/admin/</code>), or set the <em>Public Base URL</em> in
    <?php if (is_super()): ?><a href="<?= e(url('admin/settings.php')) ?>">System Settings</a><?php else: ?>System Settings<?php endif; ?>.
    After deploying online, the QR codes will automatically use your website address.
  </div></div>
<?php endif; ?>

<div class="alert alert-info"><i class="bi bi-shield-check"></i><div>QR codes contain only a random registration link – never any personal information. Deactivating a QR code immediately stops new registrations through it. Participants' <em>attendance</em> QR codes are separate and are shown on their own confirmation page.</div></div>

<?php if (!$codes): ?>
  <div class="card"><div class="card-body table-empty"><i class="bi bi-qr-code"></i>No QR codes yet. <?= is_super() ? 'Type an event name above and click “Create QR Code”.' : 'Ask the Super Admin to create an event.' ?></div></div>
<?php endif; ?>

<div class="grid-auto">
<?php foreach ($codes as $q):
    $link = registration_url($q['token']);
    $fname = 'qr-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($q['title'])) . '-' . $q['id'] . '.png'; ?>
  <div class="card qr-card conf-card <?= $q['is_active'] ? '' : 'inactive' ?>">
    <div class="card-header">
      <div style="min-width:0">
        <h3 style="font-size:.98rem" class="mb-0"><?= e($q['title']) ?></h3>
        <div class="small muted"><?= e($q['label']) ?></div>
      </div>
      <?= $q['is_active'] ? '<span class="badge badge-success"><span class="dot"></span> Active</span>' : '<span class="badge badge-danger"><span class="dot"></span> Inactive</span>' ?>
    </div>
    <div class="card-body">
      <div class="qr-preview"><canvas id="qr-<?= (int) $q['id'] ?>" data-qr="<?= e($link) ?>" data-size="210"></canvas></div>
      <div class="copy-row mb-2"><input class="input input-sm" value="<?= e($link) ?>" readonly onfocus="this.select()" aria-label="Registration link"><button class="btn btn-outline btn-sm" data-copy="<?= e($link) ?>" title="Copy registration link"><i class="bi bi-clipboard"></i></button></div>
      <div class="flex small" style="gap:1rem">
        <span title="Times this QR / link was opened"><i class="bi bi-phone"></i> <strong><?= (int) $q['scan_count'] ?></strong> scans</span>
        <span title="Registrations submitted through this QR"><i class="bi bi-person-plus"></i> <strong><?= (int) $q['reg_count'] ?></strong> registrations</span>
        <span title="Total registrations for the conference"><i class="bi bi-people"></i> <strong><?= (int) $q['conf_total'] ?></strong> total</span>
      </div>
      <?php if (!$q['registration_enabled'] && $q['is_active']): ?>
        <div class="small mt-1" style="color:#B45309"><i class="bi bi-exclamation-triangle"></i> Registration is disabled for this conference.</div>
      <?php endif; ?>
    </div>
    <div class="card-footer flex">
      <button class="btn btn-primary btn-sm" onclick="downloadQr(<?= (int) $q['id'] ?>, <?= e(json_encode($link)) ?>, <?= e(json_encode($q['title'])) ?>, <?= e(json_encode($fname)) ?>)"><i class="bi bi-download"></i> PNG</button>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/qr_print.php?id=' . $q['id'])) ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
      <form method="post" action="<?= e(url('admin/qr_action.php')) ?>" class="dropdown" style="margin-left:auto">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
        <button type="button" class="btn btn-ghost btn-icon" data-dropdown aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
        <div class="dropdown-menu">
          <button name="action" value="toggle"><i class="bi bi-<?= $q['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i> <?= $q['is_active'] ? 'Deactivate' : 'Activate' ?> QR code</button>
          <button name="action" value="regenerate" data-confirm="Generate a new link? The current QR code will stop working, so printed copies must be replaced."><i class="bi bi-arrow-repeat"></i> Regenerate link</button>
          <a href="<?= e(url('admin/conference_view.php?id=' . $q['conference_id'])) ?>"><i class="bi bi-calendar-event"></i> View conference</a>
          <?php if ((int) $q['reg_count'] === 0): ?><hr><button name="action" value="delete" class="danger" data-confirm="Delete this QR code?"><i class="bi bi-trash"></i> Delete</button><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="modal-backdrop" id="new-qr">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-qr-title">
    <form method="post" action="<?= e(url('admin/qr_action.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="card-header"><h3 id="new-qr-title">Generate Registration QR Code</h3><button type="button" class="btn btn-ghost btn-icon" data-close-modal aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
      <div class="card-body form-grid" style="grid-template-columns:1fr">
        <div class="field"><label for="nq-conf">Conference</label>
          <select class="input" id="nq-conf" name="conference_id" required>
            <?php foreach ($confs as $c): if ($c['is_archived']) continue; ?><option value="<?= (int) $c['id'] ?>" <?= $confId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="nq-label">Label</label><input class="input" id="nq-label" name="label" maxlength="100" placeholder="e.g. Main Entrance Poster" value="Main Registration QR">
          <div class="help">Use separate QR codes (e.g. per poster or screen) to track where participants registered from.</div></div>
      </div>
      <div class="card-footer flex" style="justify-content:flex-end"><button type="button" class="btn btn-outline" data-close-modal>Cancel</button><button class="btn btn-primary"><i class="bi bi-qr-code"></i> Generate</button></div>
    </form>
  </div>
</div>
<?php
$js = 'function downloadQr(id, link, title, fname){var c=document.createElement("canvas");drawQR(c,link,{size:720,margin:40,caption:title,subcaption:"Scan to register"});downloadCanvas(c,fname);}';
admin_footer(['vendor/qrcode.js'], $js);
