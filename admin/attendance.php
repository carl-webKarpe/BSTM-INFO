<?php
require __DIR__ . '/_init.php';
require_admin();

$confs = accessible_conferences(false);
$confId = (int) get('conference');
if (!$confId || !can_access_conference($confId)) {
    // default: the active conference whose date is closest to today
    $best = null;
    foreach ($confs as $c) {
        $diff = abs(strtotime($c['start_datetime']) - time());
        if ($best === null || $diff < $best[0]) $best = [$diff, (int) $c['id']];
    }
    $confId = $best[1] ?? 0;
}
$conf = $confId ? q_row('SELECT * FROM conferences WHERE id = ?', [$confId]) : null;

$view = get('view', 'in') === 'out' ? 'out' : 'in';
$rows = [];
$p = null;
$stats = ['registered' => 0, 'checked_in' => 0, 'remaining' => 0, 'rate' => 0];
if ($conf) {
    $s = q_row("SELECT SUM(r.status <> 'cancelled') AS registered, SUM(ar.id IS NOT NULL) AS checked_in
                FROM conference_registrations r LEFT JOIN attendance_records ar ON ar.registration_id = r.id
                WHERE r.conference_id = ? AND r.is_archived = 0", [$confId]);
    $stats['registered'] = (int) $s['registered'];
    $stats['checked_in'] = (int) $s['checked_in'];
    $stats['remaining']  = max(0, $stats['registered'] - $stats['checked_in']);
    $stats['rate']       = $stats['registered'] ? round($stats['checked_in'] / $stats['registered'] * 100) : 0;

    $w = $view === 'in' ? ' AND ar.id IS NOT NULL' : " AND ar.id IS NULL AND r.status <> 'cancelled'";
    $total = (int) q_val('SELECT COUNT(*) FROM conference_registrations r LEFT JOIN attendance_records ar ON ar.registration_id = r.id
                          WHERE r.conference_id = ? AND r.is_archived = 0' . $w, [$confId]);
    $p = paginate($total, 25, (int) get('page', '1'));
    $rows = q_all(registration_select_sql() . ' WHERE r.conference_id = ? AND r.is_archived = 0' . $w
        . ($view === 'in' ? ' ORDER BY ar.checked_in_at DESC' : ' ORDER BY s.last_name, s.first_name')
        . " LIMIT {$p['per_page']} OFFSET {$p['offset']}", [$confId]);
}

admin_header('Attendance Monitoring', 'attendance');
?>
<div class="page-head">
  <div>
    <h2>Attendance Monitoring</h2>
    <p>Check in participants by scanning their attendance QR code or by searching their Student ID / reference number.</p>
  </div>
  <form method="get">
    <select class="input input-sm" name="conference" data-autosubmit style="min-width:260px" aria-label="Conference">
      <?php if (!$confs): ?><option>No active conferences</option><?php endif; ?>
      <?php foreach ($confs as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $confId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?> – <?= e(fmt_date($c['start_datetime'])) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$conf): ?>
  <div class="card"><div class="card-body table-empty"><i class="bi bi-calendar-x"></i>No active conference available for attendance monitoring.</div></div>
<?php admin_footer(); return; endif; ?>

<div class="stats">
  <div class="stat"><div class="ic ic-blue"><i class="bi bi-people-fill"></i></div><div><div class="val" id="st-registered"><?= $stats['registered'] ?></div><div class="lbl">Registered Participants</div></div></div>
  <div class="stat"><div class="ic ic-green"><i class="bi bi-person-check-fill"></i></div><div><div class="val" id="st-in"><?= $stats['checked_in'] ?></div><div class="lbl">Checked In (Present)</div></div></div>
  <div class="stat"><div class="ic ic-orange"><i class="bi bi-person-dash-fill"></i></div><div><div class="val" id="st-remaining"><?= $stats['remaining'] ?></div><div class="lbl">Not Yet Checked In</div></div></div>
  <div class="stat"><div class="ic ic-purple"><i class="bi bi-graph-up-arrow"></i></div><div><div class="val"><span id="st-rate"><?= $stats['rate'] ?></span>%</div><div class="lbl">Attendance Rate</div></div></div>
</div>

<div class="grid-2 mb-3">
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-qr-code-scan"></i> Scan Attendance QR</h3>
      <button class="btn btn-primary btn-sm" id="scan-toggle"><i class="bi bi-camera-video"></i> Start Camera</button></div>
    <div class="card-body">
      <div class="scanner-box"><div id="reader"></div><div id="reader-placeholder" class="chart-empty" style="min-height:260px;color:#94A3B8"><div><i class="bi bi-camera" style="font-size:2rem;display:block"></i>Camera is off. Click “Start Camera” and point it at the participant's attendance QR code.</div></div></div>
      <p class="small muted mt-1 mb-0"><i class="bi bi-info-circle"></i> The camera works on <strong>localhost</strong> or <strong>HTTPS</strong> websites only. Registration QR codes are rejected here to avoid confusion.</p>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-search"></i> Manual Check-in</h3></div>
    <div class="card-body">
      <form id="search-form" class="copy-row mb-2">
        <input class="input" id="search-q" placeholder="Student ID, reference no. or name" autocomplete="off" aria-label="Search participant">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button>
      </form>
      <div id="result" aria-live="polite"></div>
      <div id="search-results" style="display:grid;gap:.5rem"></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="tabs mb-0" style="border:0">
      <a href="<?= e(query_with(['view' => 'in', 'page' => null])) ?>" class="<?= $view === 'in' ? 'active' : '' ?>"><i class="bi bi-person-check"></i> Checked In</a>
      <a href="<?= e(query_with(['view' => 'out', 'page' => null])) ?>" class="<?= $view === 'out' ? 'active' : '' ?>"><i class="bi bi-person-dash"></i> Not Yet Checked In</a>
    </div>
    <div class="dropdown">
      <button class="btn btn-outline btn-sm" data-dropdown><i class="bi bi-download"></i> Attendance Report</button>
      <div class="dropdown-menu">
        <?php $ex = 'admin/export.php?type=attendance&conference=' . $confId . '&attendance=' . $view; ?>
        <button type="button" onclick="exportFromUrl('<?= e(url($ex . '&format=json')) ?>','xlsx',this)"><i class="bi bi-file-earmark-excel"></i> Excel – <?= $view === 'in' ? 'checked in' : 'not checked in' ?></button>
        <button type="button" onclick="exportFromUrl('<?= e(url($ex . '&format=json')) ?>','pdf',this)"><i class="bi bi-file-earmark-pdf"></i> PDF – <?= $view === 'in' ? 'checked in' : 'not checked in' ?></button>
        <hr>
        <button type="button" onclick="exportFromUrl('<?= e(url('admin/export.php?type=attendance&conference=' . $confId . '&format=json')) ?>','xlsx',this)"><i class="bi bi-file-earmark-excel"></i> Excel – full attendance list</button>
        <button type="button" onclick="exportFromUrl('<?= e(url('admin/export.php?type=attendance&conference=' . $confId . '&format=json')) ?>','pdf',this)"><i class="bi bi-file-earmark-pdf"></i> PDF – full attendance list</button>
      </div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="table" id="att-table">
      <thead><tr><th>Participant</th><th>ID Number</th><th class="hide-sm">Type</th><th class="hide-sm">Course / Year</th><th><?= $view === 'in' ? 'Checked In' : 'Status' ?></th><th class="text-right">Action</th></tr></thead>
      <tbody>
      <?php if (!$rows) echo empty_row(6, $view === 'in' ? 'No one has checked in yet.' : 'Everyone has checked in. 🎉', $view === 'in' ? 'person-check' : 'emoji-smile'); ?>
      <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
          <td><a class="cell-title" href="<?= e(url('admin/participant.php?id=' . $r['id'])) ?>"><?= e(full_name($r)) ?></a><div class="cell-sub"><?= e($r['reference_no']) ?></div></td>
          <td><?= e($r['id_number'] ?: '—') ?></td>
          <td class="hide-sm"><?= type_badge($r['type_name']) ?></td>
          <td class="hide-sm small"><?= e($r['course_code'] ?: ($r['course_other'] ?: '—')) ?><div class="cell-sub"><?= e($r['year_level'] ?: '') ?></div></td>
          <?php if ($view === 'in'): ?>
            <td class="nowrap small"><?= e(fmt_datetime($r['checked_in_at'])) ?><div class="cell-sub"><?= $r['checkin_method'] === 'qr' ? 'QR scan' : 'Manual' ?></div></td>
            <td class="text-right"><button class="btn btn-ghost btn-sm" data-undo="<?= (int) $r['id'] ?>" title="Undo check-in"><i class="bi bi-arrow-counterclockwise"></i> Undo</button></td>
          <?php else: ?>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-right"><button class="btn btn-success btn-sm" data-checkin="<?= (int) $r['id'] ?>" data-status="<?= e($r['status']) ?>"><i class="bi bi-check-lg"></i> Check In</button></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($p && $p['pages'] > 1): ?><div class="card-footer flex-between"><span class="small muted"><?= $p['total'] ?> participant(s)</span><?= render_pagination($p) ?></div><?php endif; ?>
</div>

<?php
$cfg = json_encode(['api' => url('admin/attendance_api.php'), 'conference' => $confId, 'csrf' => csrf_token(), 'view' => $view]);
admin_footer(['vendor/html5-qrcode.min.js', 'vendor/xlsx.full.min.js', 'vendor/jspdf.umd.min.js', 'vendor/jspdf.plugin.autotable.min.js', 'js/attendance.js'], 'initAttendance(' . $cfg . ');');
