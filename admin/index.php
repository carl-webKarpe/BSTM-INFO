<?php
require __DIR__ . '/_init.php';
require APP_ROOT . '/includes/stats.php';
$admin = require_admin();

$confs  = accessible_conferences();
$confId = (int) get('conference');
if ($confId && !can_access_conference($confId)) {
    $confId = 0;
}

$where  = conf_scope('r.conference_id');
$params = [];
if ($confId) {
    $where .= ' AND r.conference_id = ?';
    $params[] = $confId;
}

$sum    = stats_summary($where, $params);
$course = chart_data(stats_by_course($where, $params, 10));
$year   = chart_data(stats_by_year($where, $params));
$school = chart_data(stats_by_school($where, $params, 8));
$trend  = chart_data(stats_trend($where, $params, 30));
$status = [['Pending', 'Confirmed', 'Cancelled'], [$sum['pending'], $sum['confirmed'], $sum['cancelled']]];

$recent = q_all(registration_select_sql() . ' WHERE r.is_archived = 0' . $where . ' ORDER BY r.registered_at DESC LIMIT 8', $params);

$upcoming = q_all("SELECT c.*, (SELECT COUNT(*) FROM conference_registrations x WHERE x.conference_id = c.id AND x.status <> 'cancelled' AND x.is_archived = 0) AS reg_count
                   FROM conferences c WHERE c.is_archived = 0" . conf_scope('c.id') . ' ORDER BY c.start_datetime ASC LIMIT 4');

$cards = [
    ['Total Registered', $sum['total'], 'people-fill', 'blue'],
    ['Student Participants', $sum['students'], 'mortarboard-fill', 'navy'],
    ['Faculty Participants', $sum['faculty'], 'person-workspace', 'purple'],
    ['Guest Participants', $sum['guests'], 'person-badge-fill', 'gray'],
    ['Pending Registrations', $sum['pending'], 'hourglass-split', 'orange'],
    ['Confirmed Registrations', $sum['confirmed'], 'patch-check-fill', 'green'],
];

admin_header('Dashboard', 'dashboard');
?>
<div class="page-head">
  <div>
    <h2>Welcome back, <?= e(explode(' ', $admin['full_name'])[0]) ?> 👋</h2>
    <p>Here is what is happening with your conference registrations.</p>
  </div>
  <form method="get" class="flex">
    <label class="sr-only" for="conference">Conference</label>
    <select class="input input-sm" id="conference" name="conference" data-autosubmit style="min-width:240px">
      <option value="">All conferences</option>
      <?php foreach ($confs as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $confId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?><?= $c['is_archived'] ? ' (archived)' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$confs): ?>
  <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i><div>
    <?php if (is_super()): ?>No conferences yet. <a href="<?= e(url('admin/conference_form.php')) ?>">Create your first conference</a> to generate a registration QR code.
    <?php else: ?>No conferences have been assigned to your account yet. Please contact the Super Admin.<?php endif; ?>
  </div></div>
<?php endif; ?>

<div class="stats">
  <?php foreach ($cards as [$label, $val, $icon, $color]): ?>
    <div class="stat">
      <div class="ic ic-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
      <div><div class="val"><?= number_format($val) ?></div><div class="lbl"><?= e($label) ?></div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-3 mb-3">
  <div class="card span-2">
    <div class="card-header"><h3>Registration Trend <span class="muted small">(last 30 days)</span></h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="ch-trend"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Registration Status</h3><span class="badge badge-blue"><i class="bi bi-person-check"></i> <?= number_format($sum['checked_in']) ?> checked in</span></div>
    <div class="card-body"><div class="chart-box"><canvas id="ch-status"></canvas></div></div>
  </div>
</div>

<div class="grid-3 mb-3">
  <div class="card">
    <div class="card-header"><h3>By Course / Program</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="ch-course"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>By Year Level</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="ch-year"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3>By School / Institution</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="ch-school"></canvas></div></div>
  </div>
</div>

<div class="grid-3">
  <div class="card span-2">
    <div class="card-header"><h3>Recent Registrations</h3><a class="btn btn-outline btn-sm" href="<?= e(url('admin/participants.php' . ($confId ? '?conference=' . $confId : ''))) ?>">View all</a></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Participant</th><th class="hide-sm">Conference</th><th>Type</th><th>Status</th><th class="hide-sm">Registered</th></tr></thead>
        <tbody>
        <?php if (!$recent) echo empty_row(5, 'No registrations yet. Share the conference QR code to start receiving registrations.'); ?>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a class="cell-title" href="<?= e(url('admin/participant.php?id=' . $r['id'])) ?>"><?= e(full_name($r)) ?></a><div class="cell-sub"><?= e($r['id_number'] ?: $r['reference_no']) ?></div></td>
            <td class="hide-sm"><?= e($r['conference_title']) ?></td>
            <td><?= type_badge($r['type_name']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="hide-sm nowrap small"><?= e(fmt_datetime($r['registered_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Active Conferences</h3></div>
    <div class="card-body" style="display:grid;gap:1rem">
      <?php if (!$upcoming): ?><p class="muted mb-0">No active conferences.</p><?php endif; ?>
      <?php foreach ($upcoming as $c):
          $limit = (int) $c['participant_limit'];
          $pct = $limit ? min(100, round($c['reg_count'] / $limit * 100)) : 0; ?>
        <div>
          <div class="flex-between"><a class="cell-title" href="<?= e(url('admin/conference_view.php?id=' . $c['id'])) ?>"><?= e($c['title']) ?></a>
            <?= $c['registration_enabled'] ? '<span class="badge badge-success">Open</span>' : '<span class="badge badge-muted">Closed</span>' ?></div>
          <div class="small muted"><i class="bi bi-calendar3"></i> <?= e(fmt_date($c['start_datetime'], 'M j, Y g:i A')) ?></div>
          <div class="small mt-1"><strong><?= (int) $c['reg_count'] ?></strong><?= $limit ? ' / ' . $limit : '' ?> registered</div>
          <?php if ($limit): ?><div class="progress mt-1"><span style="width:<?= $pct ?>%"></span></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php
$js = 'makeChart("ch-trend","line",' . json_encode($trend[0]) . ',' . json_encode($trend[1]) . ',{label:"Registrations"});'
    . 'makeChart("ch-status","doughnut",' . json_encode($status[0]) . ',' . json_encode($status[1]) . ',{colors:["#F59E0B","#16A34A","#DC2626"]});'
    . 'makeChart("ch-course","hbar",' . json_encode($course[0]) . ',' . json_encode($course[1]) . ');'
    . 'makeChart("ch-year","bar",' . json_encode($year[0]) . ',' . json_encode($year[1]) . ',{colors:"#172554"});'
    . 'makeChart("ch-school","doughnut",' . json_encode($school[0]) . ',' . json_encode($school[1]) . ');';
admin_footer(['vendor/chart.umd.min.js', 'js/charts.js'], $js);
