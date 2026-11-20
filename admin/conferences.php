<?php
require __DIR__ . '/_init.php';
require_admin();

$tab = get('tab', 'active') === 'archived' ? 'archived' : 'active';
$search = get('q');

$sql = "SELECT c.*, ay.name AS ay_name,
          (SELECT COUNT(*) FROM conference_registrations x WHERE x.conference_id = c.id AND x.status <> 'cancelled' AND x.is_archived = 0) AS reg_count,
          (SELECT COUNT(*) FROM attendance_records a WHERE a.conference_id = c.id) AS att_count,
          (SELECT COUNT(*) FROM conference_registrations y WHERE y.conference_id = c.id) AS all_regs
        FROM conferences c LEFT JOIN academic_years ay ON ay.id = c.academic_year_id
        WHERE c.is_archived = ?" . conf_scope('c.id');
$params = [$tab === 'archived' ? 1 : 0];
if ($search !== '') {
    $sql .= ' AND (c.title LIKE ? OR c.venue LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= ' ORDER BY c.start_datetime ' . ($tab === 'archived' ? 'DESC' : 'ASC');
$rows = q_all($sql, $params);

admin_header('Conference Management', 'conferences');
?>
<div class="page-head">
  <div>
    <h2>Conferences</h2>
    <p>Create and manage conferences. Each conference has its own registration link, QR code and participant records.</p>
  </div>
  <?php if (is_super()): ?>
    <a class="btn btn-primary" href="<?= e(url('admin/conference_form.php')) ?>"><i class="bi bi-plus-lg"></i> New Conference</a>
  <?php endif; ?>
</div>

<div class="flex-between mb-2">
  <div class="tabs mb-0" style="border:0">
    <a href="?tab=active" class="<?= $tab === 'active' ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i> Active</a>
    <a href="?tab=archived" class="<?= $tab === 'archived' ? 'active' : '' ?>"><i class="bi bi-archive"></i> Archived</a>
  </div>
  <form method="get" class="flex">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input class="input input-sm" name="q" value="<?= e($search) ?>" placeholder="Search title or venue…" style="width:240px">
    <button class="btn btn-outline btn-sm"><i class="bi bi-search"></i></button>
  </form>
</div>

<?php if (!$rows): ?>
  <div class="card"><div class="card-body table-empty"><i class="bi bi-calendar-x"></i>
    <?= $tab === 'archived' ? 'No archived conferences.' : 'No active conferences.' ?>
    <?php if (is_super() && $tab === 'active'): ?><div class="mt-2"><a class="btn btn-primary btn-sm" href="<?= e(url('admin/conference_form.php')) ?>"><i class="bi bi-plus-lg"></i> Create a conference</a></div><?php endif; ?>
  </div></div>
<?php endif; ?>

<div class="grid-auto">
<?php foreach ($rows as $c):
    $st = conference_state($c);
    $limit = (int) $c['participant_limit'];
    $pct = $limit ? min(100, round($c['reg_count'] / $limit * 100)) : 0; ?>
  <div class="card conf-card">
    <div class="card-body">
      <div class="flex-between mb-1">
        <span class="badge badge-<?= $st['open'] ? 'success' : ($st['tone'] === 'warning' ? 'warning' : 'muted') ?>"><span class="dot"></span> Registration <?= e($st['label']) ?></span>
        <?php if ($c['ay_name']): ?><span class="small muted">A.Y. <?= e($c['ay_name']) ?></span><?php endif; ?>
      </div>
      <h3 class="mb-0"><a href="<?= e(url('admin/conference_view.php?id=' . $c['id'])) ?>" style="color:inherit"><?= e($c['title']) ?></a></h3>
      <ul class="conf-meta">
        <li><i class="bi bi-calendar3"></i><span><?= e(fmt_conf_schedule($c)) ?></span></li>
        <li><i class="bi bi-geo-alt"></i><span><?= e($c['venue']) ?></span></li>
        <li><i class="bi bi-people"></i><span><strong><?= (int) $c['reg_count'] ?></strong> registered<?= $limit ? ' of ' . $limit : '' ?> · <strong><?= (int) $c['att_count'] ?></strong> checked in</span></li>
      </ul>
      <?php if ($limit): ?><div class="progress mt-2"><span style="width:<?= $pct ?>%"></span></div><?php endif; ?>
    </div>
    <div class="card-footer flex">
      <a class="btn btn-primary btn-sm" href="<?= e(url('admin/conference_view.php?id=' . $c['id'])) ?>"><i class="bi bi-eye"></i> View</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/conference_form.php?id=' . $c['id'])) ?>"><i class="bi bi-pencil"></i> Edit</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/qrcodes.php?conference=' . $c['id'])) ?>"><i class="bi bi-qr-code"></i> QR</a>
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/participants.php?conference=' . $c['id'])) ?>" title="Participants"><i class="bi bi-people"></i></a>
      <?php if (is_super()):
          $n = (int) $c['all_regs'];
          $msg = 'Permanently DELETE "' . $c['title'] . '"?' . ($n ? "\n\nThis will also permanently delete $n registration" . ($n === 1 ? '' : 's') . ', their attendance records and the QR codes of this conference.' : '') . "\n\nThis cannot be undone."; ?>
        <form method="post" action="<?= e(url('admin/conference_action.php')) ?>" class="conf-del">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="return" value="list">
          <button class="btn btn-outline btn-sm btn-del" name="action" value="delete" data-confirm="<?= e($msg) ?>" title="Delete conference"><i class="bi bi-trash"></i> Delete</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php admin_footer(); ?>
