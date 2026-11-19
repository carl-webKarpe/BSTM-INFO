<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

$where = ' WHERE 1=1';
$params = [];
if (!is_super()) {
    // Conference Admins only see their own activity.
    $where .= ' AND l.admin_id = ?';
    $params[] = $admin['id'];
} elseif (ctype_digit(get('admin'))) {
    $where .= ' AND l.admin_id = ?';
    $params[] = (int) get('admin');
}
if (get('action') === 'login') {
    $where .= " AND (l.action LIKE 'login%' OR l.action = 'logout' OR l.action = 'password_change')";
} elseif (get('action') !== '') {
    $where .= ' AND l.action LIKE ?';
    $params[] = get('action') . '%';
}
if (get('q') !== '') {
    $where .= ' AND l.description LIKE ?';
    $params[] = '%' . get('q') . '%';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from'))) { $where .= ' AND l.created_at >= ?'; $params[] = get('from') . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')))   { $where .= ' AND l.created_at <= ?'; $params[] = get('to') . ' 23:59:59'; }

$total = (int) q_val('SELECT COUNT(*) FROM activity_logs l' . $where, $params);
$p = paginate($total, 30, (int) get('page', '1'));
$logs = q_all('SELECT l.*, a.full_name, a.username FROM activity_logs l LEFT JOIN admins a ON a.id = l.admin_id' . $where
    . " ORDER BY l.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
$admins = is_super() ? q_all('SELECT id, full_name FROM admins ORDER BY full_name') : [];
$groups = ['login' => 'Login / Logout / Password', 'conference' => 'Conferences', 'participant' => 'Participants', 'qr' => 'QR Codes',
           'attendance' => 'Attendance', 'admin' => 'Admin Accounts', 'settings' => 'Settings', 'export' => 'Exports', 'backup' => 'Backups'];
$icon = function (string $a): string {
    foreach (['login' => 'box-arrow-in-right', 'logout' => 'box-arrow-right', 'conference' => 'calendar-event', 'participant' => 'person',
              'qr' => 'qr-code', 'attendance' => 'person-check', 'admin' => 'shield-lock', 'settings' => 'gear', 'export' => 'download',
              'backup' => 'database', 'password' => 'key'] as $k => $i) {
        if (strpos($a, $k) === 0) return $i;
    }
    return 'dot';
};

admin_header('Activity Logs', 'logs');
?>
<div class="page-head">
  <div><h2>Activity Logs</h2><p><?= is_super() ? 'A record of every administrator action in the system.' : 'A record of your own actions in the system.' ?></p></div>
</div>
<div class="card mb-2"><div class="card-body">
  <form method="get" class="filters">
    <div class="field"><label for="l-q">Search</label><input class="input input-sm" id="l-q" name="q" value="<?= e(get('q')) ?>" placeholder="Description…"></div>
    <?php if (is_super()): ?>
    <div class="field"><label for="l-admin">Admin</label><select class="input input-sm" id="l-admin" name="admin"><option value="">All</option><?php foreach ($admins as $a): ?><option value="<?= (int) $a['id'] ?>" <?= get('admin') === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['full_name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="field"><label for="l-act">Category</label><select class="input input-sm" id="l-act" name="action"><option value="">All</option><?php foreach ($groups as $k => $v): ?><option value="<?= $k ?>" <?= get('action') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="l-from">From</label><input class="input input-sm" type="date" id="l-from" name="from" value="<?= e(get('from')) ?>"></div>
    <div class="field"><label for="l-to">To</label><input class="input input-sm" type="date" id="l-to" name="to" value="<?= e(get('to')) ?>"></div>
    <div class="filter-actions"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button><a class="btn btn-outline btn-sm" href="?">Reset</a></div>
  </form>
</div></div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Date &amp; Time</th><th>Admin</th><th>Action</th><th>Description</th><th class="hide-sm">IP Address</th></tr></thead>
      <tbody>
      <?php if (!$logs) echo empty_row(5, 'No activity recorded.', 'clock-history'); ?>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="nowrap small"><?= e(fmt_datetime($l['created_at'])) ?></td>
          <td><?= e($l['full_name'] ?? 'System') ?><div class="cell-sub"><?= $l['username'] ? '@' . e($l['username']) : '' ?></div></td>
          <td class="nowrap"><span class="badge badge-muted"><i class="bi bi-<?= $icon($l['action']) ?>"></i> <?= e(str_replace('_', ' ', $l['action'])) ?></span></td>
          <td><?= e($l['description']) ?></td>
          <td class="hide-sm small muted"><?= e($l['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($p['pages'] > 1): ?><div class="card-footer flex-between"><span class="small muted"><?= number_format($total) ?> entries</span><?= render_pagination($p) ?></div><?php endif; ?>
</div>
<?php admin_footer(); ?>
