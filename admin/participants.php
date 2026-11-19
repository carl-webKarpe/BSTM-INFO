<?php
require __DIR__ . '/_init.php';
require APP_ROOT . '/includes/participant_query.php';
require_admin();

[$where, $params, $order] = participant_filters($_GET);
$base = ' FROM conference_registrations r
          JOIN students s ON s.id = r.student_id
          JOIN participant_types pt ON pt.id = r.participant_type_id
          JOIN conferences c ON c.id = r.conference_id
          LEFT JOIN schools sc ON sc.id = r.school_id
          LEFT JOIN courses co ON co.id = r.course_id
          LEFT JOIN attendance_records ar ON ar.registration_id = r.id
          WHERE 1=1' . $where;

$perPage = in_array((int) get('per_page'), [10, 25, 50, 100], true) ? (int) get('per_page') : 25;
$total = (int) q_val('SELECT COUNT(*)' . $base, $params);
$p = paginate($total, $perPage, (int) get('page', '1'));
$rows = q_all(registration_select_sql() . ' WHERE 1=1' . $where . " ORDER BY $order LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);

$confs   = accessible_conferences();
$courses = lookup_courses(false);
$schools = lookup_schools(false);
$archived = get('archived') === '1';
$sort = get('sort', 'date');
$dir  = strtolower(get('dir', $sort === 'date' ? 'desc' : 'asc'));

function sort_link(string $key, string $label, string $sort, string $dir): string
{
    $active = $sort === $key;
    $next = $active && $dir === 'asc' ? 'desc' : 'asc';
    $icon = $active ? ($dir === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up';
    return '<a class="' . ($active ? 'sorted' : '') . '" href="' . e(query_with(['sort' => $key, 'dir' => $next, 'page' => null])) . '">'
        . e($label) . ' <i class="bi ' . $icon . '" style="font-size:.7rem"></i></a>';
}

$exportQuery = http_build_query(array_diff_key($_GET, ['page' => 1, 'per_page' => 1]));
$returnUrl = $_SERVER['REQUEST_URI'] ?? '';
$activeFilters = count(array_filter(array_intersect_key($_GET, array_flip(['q', 'conference', 'course', 'school', 'year', 'type', 'status', 'from', 'to', 'attendance'])), fn($v) => $v !== ''));

admin_header('Participant Records', 'participants');
?>
<div class="page-head">
  <div>
    <h2><?= $archived ? 'Archived Participant Records' : 'Participant Records' ?></h2>
    <p><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?> found<?= $activeFilters ? ' with ' . $activeFilters . ' filter' . ($activeFilters > 1 ? 's' : '') : '' ?>.</p>
  </div>
  <div class="toolbar">
    <a class="btn btn-outline btn-sm" href="<?= e(query_with(['archived' => $archived ? null : '1', 'page' => null])) ?>"><i class="bi bi-<?= $archived ? 'people' : 'archive' ?>"></i> <?= $archived ? 'Active records' : 'Archived records' ?></a>
    <div class="dropdown">
      <button class="btn btn-primary btn-sm" data-dropdown><i class="bi bi-download"></i> Export</button>
      <div class="dropdown-menu">
        <button type="button" onclick="exportFromUrl('<?= e(url('admin/export.php?type=participants&format=json&' . $exportQuery)) ?>','xlsx',this)"><i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)</button>
        <button type="button" onclick="exportFromUrl('<?= e(url('admin/export.php?type=participants&format=json&' . $exportQuery)) ?>','pdf',this)"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
        <a href="<?= e(url('admin/export.php?type=participants&format=csv&' . $exportQuery)) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
      </div>
    </div>
  </div>
</div>

<div class="card mb-2">
  <div class="card-body">
    <form method="get" class="filters">
      <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
      <div class="field" style="grid-column:span 2;min-width:0"><label for="f-q">Search</label><input class="input input-sm" id="f-q" name="q" value="<?= e(get('q')) ?>" placeholder="Student ID, name, reference no., contact…"></div>
      <div class="field"><label for="f-conf">Conference</label><select class="input input-sm" id="f-conf" name="conference"><option value="">All</option><?php foreach ($confs as $c): ?><option value="<?= (int) $c['id'] ?>" <?= get('conference') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="f-course">Course / Program</label><select class="input input-sm" id="f-course" name="course"><option value="">All</option><?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= get('course') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?><option value="other" <?= get('course') === 'other' ? 'selected' : '' ?>>Other</option></select></div>
      <div class="field"><label for="f-year">Year Level</label><select class="input input-sm" id="f-year" name="year"><option value="">All</option><?php foreach (year_levels() as $y): ?><option <?= get('year') === $y ? 'selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="f-school">School</label><select class="input input-sm" id="f-school" name="school"><option value="">All</option><?php foreach ($schools as $s): ?><option value="<?= (int) $s['id'] ?>" <?= get('school') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?><option value="other" <?= get('school') === 'other' ? 'selected' : '' ?>>Other</option></select></div>
      <div class="field"><label for="f-type">Participant Type</label><select class="input input-sm" id="f-type" name="type"><option value="">All</option><?php foreach (['student' => 'Student', 'faculty' => 'Faculty', 'guest' => 'Guest'] as $k => $v): ?><option value="<?= $k ?>" <?= get('type') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="f-status">Status</label><select class="input input-sm" id="f-status" name="status"><option value="">All</option><?php foreach (statuses() as $k => $v): ?><option value="<?= $k ?>" <?= get('status') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="f-att">Attendance</label><select class="input input-sm" id="f-att" name="attendance"><option value="">All</option><option value="in" <?= get('attendance') === 'in' ? 'selected' : '' ?>>Checked in</option><option value="out" <?= get('attendance') === 'out' ? 'selected' : '' ?>>Not yet checked in</option></select></div>
      <div class="field"><label for="f-from">Registered From</label><input class="input input-sm" type="date" id="f-from" name="from" value="<?= e(get('from')) ?>"></div>
      <div class="field"><label for="f-to">Registered To</label><input class="input input-sm" type="date" id="f-to" name="to" value="<?= e(get('to')) ?>"></div>
      <input type="hidden" name="sort" value="<?= e(get('sort')) ?>"><input type="hidden" name="dir" value="<?= e(get('dir')) ?>">
      <div class="filter-actions">
        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
        <a class="btn btn-outline btn-sm" href="?<?= $archived ? 'archived=1' : '' ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<form id="act-form" method="post" action="<?= e(url('admin/participant_action.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
</form>

<div class="card">
  <div class="card-header">
    <div class="flex">
      <select class="input input-sm" form="act-form" name="bulk" style="width:auto" aria-label="Bulk action">
        <option value="">Bulk action…</option>
        <option value="confirm">Confirm registration</option>
        <option value="pending">Mark as pending</option>
        <option value="cancel">Cancel registration</option>
        <option value="<?= $archived ? 'restore' : 'archive' ?>"><?= $archived ? 'Restore' : 'Archive' ?></option>
      </select>
      <button class="btn btn-outline btn-sm" form="act-form" name="do_bulk" value="1" data-confirm="Apply this action to all selected records?">Apply to selected</button>
    </div>
    <form method="get" class="flex">
      <?php foreach (array_diff_key($_GET, ['per_page' => 1, 'page' => 1]) as $k => $v): if (is_string($v)): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
      <label class="small muted" for="pp">Rows</label>
      <select id="pp" class="input input-sm" name="per_page" data-autosubmit style="width:auto"><?php foreach ([10, 25, 50, 100] as $n): ?><option <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select>
    </form>
  </div>
  <div class="table-wrap">
    <table class="table table-compact">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('.row-check').forEach(function(c){c.checked=this.checked}.bind(this))"></th>
          <th><?= sort_link('id_number', 'Student ID', $sort, $dir) ?></th>
          <th class="col-name"><?= sort_link('name', 'Full Name', $sort, $dir) ?></th>
          <th>Contact No.</th>
          <th class="col-wide"><?= sort_link('school', 'School / Institution', $sort, $dir) ?></th>
          <th><?= sort_link('course', 'Course / Program', $sort, $dir) ?></th>
          <th><?= sort_link('year', 'Year', $sort, $dir) ?></th>
          <th><?= sort_link('type', 'Type', $sort, $dir) ?></th>
          <th class="col-wide"><?= sort_link('conference', 'Conference', $sort, $dir) ?></th>
          <th><?= sort_link('date', 'Registered', $sort, $dir) ?></th>
          <th><?= sort_link('status', 'Status', $sort, $dir) ?></th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows) echo empty_row(12, 'No participant records match your filters.', 'people'); ?>
      <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
        <tr>
          <td><input type="checkbox" class="row-check" form="act-form" name="ids[]" value="<?= $id ?>" aria-label="Select"></td>
          <td class="nowrap"><?= e($r['id_number'] ?: '—') ?><div class="cell-sub"><?= e($r['reference_no']) ?></div></td>
          <td><a class="cell-title" href="<?= e(url('admin/participant.php?id=' . $id)) ?>"><?= e(full_name($r)) ?></a><?= $r['checked_in_at'] ? '<div class="cell-sub" style="color:var(--success)"><i class="bi bi-person-check-fill"></i> Present</div>' : '' ?></td>
          <td class="nowrap"><?= e($r['contact_number']) ?></td>
          <td><?= e(school_label($r)) ?></td>
          <td><?= e($r['course_code'] ?: ($r['course_other'] ?: '—')) ?></td>
          <td class="nowrap"><?= e($r['year_level'] ?: '—') ?><?= $r['section'] ? '<div class="cell-sub">Sec. ' . e($r['section']) . '</div>' : '' ?></td>
          <td><?= type_badge($r['type_name']) ?></td>
          <td><?= e($r['conference_title']) ?></td>
          <td class="nowrap small"><?= e(fmt_datetime($r['registered_at'])) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="text-right">
            <div class="dropdown">
              <button class="btn btn-ghost btn-icon" data-dropdown aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
              <div class="dropdown-menu">
                <a href="<?= e(url('admin/participant.php?id=' . $id)) ?>"><i class="bi bi-eye"></i> View information</a>
                <a href="<?= e(url('admin/participant_edit.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Edit information</a>
                <hr>
                <?php if ($r['status'] !== 'confirmed'): ?><button form="act-form" name="single" value="confirm:<?= $id ?>"><i class="bi bi-check-circle"></i> Confirm registration</button><?php endif; ?>
                <?php if ($r['status'] !== 'pending'): ?><button form="act-form" name="single" value="pending:<?= $id ?>"><i class="bi bi-hourglass-split"></i> Mark as pending</button><?php endif; ?>
                <?php if ($r['status'] !== 'cancelled'): ?><button form="act-form" name="single" value="cancel:<?= $id ?>" class="danger" data-confirm="Cancel this registration?"><i class="bi bi-x-circle"></i> Cancel registration</button><?php endif; ?>
                <hr>
                <?php if ($archived): ?>
                  <button form="act-form" name="single" value="restore:<?= $id ?>"><i class="bi bi-box-arrow-up"></i> Restore record</button>
                <?php else: ?>
                  <button form="act-form" name="single" value="archive:<?= $id ?>" data-confirm="Archive this participant record?"><i class="bi bi-archive"></i> Archive record</button>
                <?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($p['pages'] > 1): ?>
  <div class="card-footer flex-between">
    <span class="small muted">Showing <?= $p['offset'] + 1 ?>–<?= min($p['offset'] + $p['per_page'], $total) ?> of <?= number_format($total) ?></span>
    <?= render_pagination($p) ?>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer(['vendor/xlsx.full.min.js', 'vendor/jspdf.umd.min.js', 'vendor/jspdf.plugin.autotable.min.js']); ?>
