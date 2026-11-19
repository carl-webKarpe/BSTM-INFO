<?php
require __DIR__ . '/_init.php';
require APP_ROOT . '/includes/stats.php';
require_admin();

$confs  = accessible_conferences();
$confId = (int) get('conference');
if ($confId && !can_access_conference($confId)) $confId = 0;
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : '';
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : '';

$where  = conf_scope('r.conference_id');
$params = [];
if ($confId) { $where .= ' AND r.conference_id = ?'; $params[] = $confId; }
if ($from)   { $where .= ' AND r.registered_at >= ?'; $params[] = "$from 00:00:00"; }
if ($to)     { $where .= ' AND r.registered_at <= ?'; $params[] = "$to 23:59:59"; }

$sum     = stats_summary($where, $params);
$bySchool = stats_by_school($where, $params);
$byCourse = stats_by_course($where, $params);
$byYear   = stats_by_year($where, $params);
$byType   = stats_by_type($where, $params);

// per-conference summary
$perConf = q_all("SELECT c.id, c.title, c.start_datetime, c.participant_limit,
        SUM(r.status <> 'cancelled') AS total,
        SUM(r.status <> 'cancelled' AND pt.code = 'student') AS students,
        SUM(r.status <> 'cancelled' AND pt.code = 'faculty') AS faculty,
        SUM(r.status <> 'cancelled' AND pt.code = 'guest') AS guests,
        SUM(r.status = 'confirmed') AS confirmed, SUM(r.status = 'pending') AS pending, SUM(r.status = 'cancelled') AS cancelled,
        SUM(ar.id IS NOT NULL) AS checked_in" . stats_from() . $where . ' GROUP BY c.id, c.title, c.start_datetime, c.participant_limit ORDER BY c.start_datetime DESC', $params);

// registration history (daily)
$history = q_all("SELECT DATE(r.registered_at) AS d, COUNT(*) AS total, SUM(r.status = 'confirmed') AS confirmed,
        SUM(r.status = 'pending') AS pending, SUM(r.status = 'cancelled') AS cancelled" . stats_from() . $where . ' GROUP BY d ORDER BY d DESC LIMIT 366', $params);
$histChart = array_reverse(array_slice($history, 0, 60));

$confTitle = $confId ? (string) q_val('SELECT title FROM conferences WHERE id = ?', [$confId]) : 'All conferences';
$period = ($from || $to) ? (($from ? fmt_date($from) : 'Start') . ' – ' . ($to ? fmt_date($to) : 'Today')) : 'All time';
$pct = fn($a, $b) => $b ? round($a / $b * 100, 1) . '%' : '0%';

function report_table(string $title, string $labelHead, array $rows, int $total): string
{
    $h = '<div class="table-wrap"><table class="table report-table" data-export-title="' . e($title) . '"><thead><tr><th>' . e($labelHead) . '</th><th class="text-right">Participants</th><th class="text-right">Share</th></tr></thead><tbody>';
    if (!$rows) {
        $h .= empty_row(3, 'No data');
    }
    foreach ($rows as $r) {
        $h .= '<tr><td>' . e($r['label']) . '</td><td class="text-right">' . (int) $r['cnt'] . '</td><td class="text-right">' . ($total ? round($r['cnt'] / $total * 100, 1) : 0) . '%</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

admin_header('Reports & Analytics', 'reports');
?>
<div class="print-only" style="margin-bottom:12px">
  <h2 style="margin:0"><?= e(setting('institution_name', '')) ?></h2>
  <div>Conference Participation Report — <?= e($confTitle) ?> · <?= e($period) ?> · Generated <?= date('M j, Y g:i A') ?></div>
</div>

<div class="page-head">
  <div><h2>Reports &amp; Analytics</h2><p><?= e($confTitle) ?> · <?= e($period) ?></p></div>
  <div class="toolbar">
    <button class="btn btn-outline btn-sm" data-print><i class="bi bi-printer"></i> Print</button>
    <button class="btn btn-outline btn-sm" id="rep-xlsx"><i class="bi bi-file-earmark-excel"></i> Excel</button>
    <button class="btn btn-primary btn-sm" id="rep-pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
  </div>
</div>

<div class="card mb-2 no-print">
  <div class="card-body">
    <form method="get" class="filters">
      <div class="field" style="grid-column:span 2"><label for="r-conf">Conference</label><select class="input input-sm" id="r-conf" name="conference"><option value="">All conferences</option><?php foreach ($confs as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $confId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="r-from">Registered From</label><input class="input input-sm" type="date" id="r-from" name="from" value="<?= e($from) ?>"></div>
      <div class="field"><label for="r-to">Registered To</label><input class="input input-sm" type="date" id="r-to" name="to" value="<?= e($to) ?>"></div>
      <div class="filter-actions"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Generate</button><a class="btn btn-outline btn-sm" href="?">Reset</a></div>
    </form>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="ic ic-blue"><i class="bi bi-people-fill"></i></div><div><div class="val"><?= $sum['total'] ?></div><div class="lbl">Total Participants</div></div></div>
  <div class="stat"><div class="ic ic-green"><i class="bi bi-patch-check-fill"></i></div><div><div class="val"><?= $sum['confirmed'] ?></div><div class="lbl">Confirmed</div></div></div>
  <div class="stat"><div class="ic ic-orange"><i class="bi bi-hourglass-split"></i></div><div><div class="val"><?= $sum['pending'] ?></div><div class="lbl">Pending</div></div></div>
  <div class="stat"><div class="ic ic-red"><i class="bi bi-x-circle-fill"></i></div><div><div class="val"><?= $sum['cancelled'] ?></div><div class="lbl">Cancelled</div></div></div>
  <div class="stat"><div class="ic ic-purple"><i class="bi bi-person-check-fill"></i></div><div><div class="val"><?= $sum['checked_in'] ?></div><div class="lbl">Checked In (<?= $pct($sum['checked_in'], $sum['total']) ?>)</div></div></div>
</div>

<div class="card mb-3">
  <div class="card-header"><h3>Conference Participation Summary</h3></div>
  <div class="table-wrap">
    <table class="table report-table" data-export-title="Conference Summary">
      <thead><tr><th>Conference</th><th>Date</th><th class="text-right">Total</th><th class="text-right">Students</th><th class="text-right">Faculty</th><th class="text-right">Guests</th><th class="text-right">Confirmed</th><th class="text-right">Pending</th><th class="text-right">Cancelled</th><th class="text-right">Checked In</th><th class="text-right">Attendance</th></tr></thead>
      <tbody>
      <?php if (!$perConf) echo empty_row(11, 'No registrations for the selected filters.'); ?>
      <?php foreach ($perConf as $c): ?>
        <tr>
          <td class="cell-title"><?= e($c['title']) ?></td><td class="nowrap"><?= e(fmt_date($c['start_datetime'])) ?></td>
          <td class="text-right"><strong><?= (int) $c['total'] ?></strong><?= $c['participant_limit'] ? '<span class="muted small"> / ' . (int) $c['participant_limit'] . '</span>' : '' ?></td>
          <td class="text-right"><?= (int) $c['students'] ?></td><td class="text-right"><?= (int) $c['faculty'] ?></td><td class="text-right"><?= (int) $c['guests'] ?></td>
          <td class="text-right"><?= (int) $c['confirmed'] ?></td><td class="text-right"><?= (int) $c['pending'] ?></td><td class="text-right"><?= (int) $c['cancelled'] ?></td>
          <td class="text-right"><?= (int) $c['checked_in'] ?></td><td class="text-right"><?= $pct((int) $c['checked_in'], (int) $c['total']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-2 mb-3">
  <div class="card"><div class="card-header"><h3>Participants by School / Institution</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-school"></canvas></div></div><?= report_table('By School', 'School / Institution', $bySchool, $sum['total']) ?></div>
  <div class="card"><div class="card-header"><h3>Participants by Course / Program</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-course"></canvas></div></div><?= report_table('By Course', 'Course / Program', $byCourse, $sum['students'] + $sum['faculty']) ?></div>
  <div class="card"><div class="card-header"><h3>Students by Year Level</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-year"></canvas></div></div><?= report_table('By Year Level', 'Year Level', $byYear, $sum['students']) ?></div>
  <div class="card"><div class="card-header"><h3>Participant Types &amp; Registration Status</h3></div>
    <div class="card-body grid-2" style="gap:.5rem"><div class="chart-box" style="height:220px"><canvas id="c-type"></canvas></div><div class="chart-box" style="height:220px"><canvas id="c-status"></canvas></div></div>
    <?= report_table('By Participant Type', 'Participant Type', $byType, $sum['total']) ?>
    <div class="table-wrap"><table class="table report-table" data-export-title="By Status"><thead><tr><th>Registration Status</th><th class="text-right">Count</th><th class="text-right">Share</th></tr></thead><tbody>
      <?php $all = $sum['confirmed'] + $sum['pending'] + $sum['cancelled']; foreach (['Confirmed' => $sum['confirmed'], 'Pending' => $sum['pending'], 'Cancelled' => $sum['cancelled']] as $k => $v): ?>
        <tr><td><?= $k ?></td><td class="text-right"><?= $v ?></td><td class="text-right"><?= $pct($v, $all) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Registration History</h3><span class="small muted">Daily registrations</span></div>
  <div class="card-body"><div class="chart-box" style="height:240px"><canvas id="c-hist"></canvas></div></div>
  <div class="table-wrap">
    <table class="table report-table" data-export-title="Registration History">
      <thead><tr><th>Date</th><th class="text-right">Registrations</th><th class="text-right">Confirmed</th><th class="text-right">Pending</th><th class="text-right">Cancelled</th></tr></thead>
      <tbody>
      <?php if (!$history) echo empty_row(5, 'No registrations yet.'); ?>
      <?php foreach ($history as $h): ?>
        <tr><td><?= e(date('D, M j, Y', strtotime($h['d']))) ?></td><td class="text-right"><strong><?= (int) $h['total'] ?></strong></td><td class="text-right"><?= (int) $h['confirmed'] ?></td><td class="text-right"><?= (int) $h['pending'] ?></td><td class="text-right"><?= (int) $h['cancelled'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$cd = fn($rows) => json_encode(chart_data($rows));
$js = 'var d;'
    . 'd=' . $cd(array_slice($bySchool, 0, 10)) . ';makeChart("c-school","hbar",d[0],d[1]);'
    . 'd=' . $cd(array_slice($byCourse, 0, 10)) . ';makeChart("c-course","hbar",d[0],d[1],{colors:"#172554"});'
    . 'd=' . $cd($byYear) . ';makeChart("c-year","bar",d[0],d[1],{colors:"#16A34A"});'
    . 'd=' . $cd($byType) . ';makeChart("c-type","doughnut",d[0],d[1]);'
    . 'makeChart("c-status","doughnut",["Confirmed","Pending","Cancelled"],' . json_encode([$sum['confirmed'], $sum['pending'], $sum['cancelled']]) . ',{colors:["#16A34A","#F59E0B","#DC2626"]});'
    . 'makeChart("c-hist","bar",' . json_encode(array_map(fn($h) => date('M j', strtotime($h['d'])), $histChart)) . ',' . json_encode(array_map(fn($h) => (int) $h['total'], $histChart)) . ',{label:"Registrations"});'
    . 'var RT=' . json_encode('Conference Participation Report – ' . $confTitle) . ',RS=' . json_encode($period) . ';'
    . 'document.getElementById("rep-xlsx").onclick=function(){exportXLSX(sectionsFromTables(document.querySelectorAll(".report-table")),RT);};'
    . 'document.getElementById("rep-pdf").onclick=function(){exportPDF(sectionsFromTables(document.querySelectorAll(".report-table")),RT,RT,RS);};';
admin_footer(['vendor/chart.umd.min.js', 'js/charts.js', 'vendor/xlsx.full.min.js', 'vendor/jspdf.umd.min.js', 'vendor/jspdf.plugin.autotable.min.js'], $js);
