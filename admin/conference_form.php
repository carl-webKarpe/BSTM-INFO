<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

$id = (int) get('id');
$conf = null;
if ($id) {
    require_conference_access($id);
    $conf = q_row('SELECT * FROM conferences WHERE id = ?', [$id]);
    if (!$conf) {
        flash('error', 'Conference not found.');
        redirect('admin/conferences.php');
    }
} elseif (!is_super()) {
    flash('error', 'Only a Super Admin can create conferences.');
    redirect('admin/conferences.php');
}

$dtIn  = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$dtOut = function (string $v): ?string {
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
};

$conferenceAdmins = is_super() ? q_all("SELECT id, full_name, username FROM admins WHERE role = 'conference_admin' AND is_active = 1 ORDER BY full_name") : [];
$assigned = $id ? array_map('intval', q('SELECT admin_id FROM admin_conferences WHERE conference_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN)) : [];

$d = $conf ?: [
    'title' => '', 'description' => '', 'venue' => '', 'academic_year_id' => q_val('SELECT id FROM academic_years WHERE is_current = 1'),
    'start_datetime' => '', 'end_datetime' => '', 'registration_open' => '', 'registration_close' => '',
    'participant_limit' => '', 'allow_student' => 1, 'allow_faculty' => 1, 'allow_guest' => 1,
    'attendance_categories' => 'Whole Day,Morning Session Only,Afternoon Session Only', 'auto_confirm' => 0, 'registration_enabled' => 1,
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $d['title']             = post('title');
    $d['description']       = post('description');
    $d['venue']             = post('venue');
    $d['academic_year_id']  = post('academic_year_id');
    $d['start_datetime']    = $dtOut(post('start_datetime'));
    $d['end_datetime']      = $dtOut(post('end_datetime'));
    $d['registration_open'] = $dtOut(post('registration_open'));
    $d['registration_close'] = $dtOut(post('registration_close'));
    $d['participant_limit'] = post('participant_limit');
    foreach (['allow_student', 'allow_faculty', 'allow_guest', 'auto_confirm', 'registration_enabled'] as $k) {
        $d[$k] = isset($_POST[$k]) ? 1 : 0;
    }
    $cats = array_unique(array_filter(array_map(fn($s) => trim(str_replace(',', ' ', $s)), preg_split('/\r\n|\r|\n/', post('attendance_categories'))), 'strlen'));
    $d['attendance_categories'] = implode(',', $cats);
    $assigned = array_map('intval', (array) ($_POST['admins'] ?? []));

    if ($d['title'] === '' || mb_strlen($d['title']) > 190) $errors[] = 'Conference name is required (max 190 characters).';
    if ($d['venue'] === '' || mb_strlen($d['venue']) > 255) $errors[] = 'Venue is required (max 255 characters).';
    if (!$d['start_datetime']) $errors[] = 'Conference start date and time is required.';
    if ($d['end_datetime'] && $d['start_datetime'] && $d['end_datetime'] < $d['start_datetime']) $errors[] = 'The end date/time must be after the start date/time.';
    if ($d['registration_open'] && $d['registration_close'] && $d['registration_close'] <= $d['registration_open']) $errors[] = 'Registration closing must be after registration opening.';
    if ($d['participant_limit'] !== '' && (!ctype_digit((string) $d['participant_limit']) || (int) $d['participant_limit'] < 1)) $errors[] = 'Participant limit must be a positive whole number (or leave it blank for unlimited).';
    if (!$d['allow_student'] && !$d['allow_faculty'] && !$d['allow_guest']) $errors[] = 'Allow at least one participant type.';
    if (mb_strlen($d['attendance_categories']) > 500) $errors[] = 'Attendance categories are too long.';
    $ayId = ctype_digit((string) $d['academic_year_id']) ? (int) $d['academic_year_id'] : null;

    if (!$errors) {
        $vals = [
            $ayId, $d['title'], $d['description'] ?: null, $d['venue'], $d['start_datetime'], $d['end_datetime'],
            $d['registration_open'], $d['registration_close'], $d['participant_limit'] === '' ? null : (int) $d['participant_limit'],
            $d['allow_student'], $d['allow_faculty'], $d['allow_guest'], $d['attendance_categories'], $d['auto_confirm'], $d['registration_enabled'],
        ];
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            q('UPDATE conferences SET academic_year_id=?, title=?, description=?, venue=?, start_datetime=?, end_datetime=?, registration_open=?, registration_close=?,
               participant_limit=?, allow_student=?, allow_faculty=?, allow_guest=?, attendance_categories=?, auto_confirm=?, registration_enabled=? WHERE id=?',
                array_merge($vals, [$id]));
            log_activity('conference_update', 'Updated conference "' . $d['title'] . '"');
        } else {
            q('INSERT INTO conferences (academic_year_id, title, description, venue, start_datetime, end_datetime, registration_open, registration_close,
               participant_limit, allow_student, allow_faculty, allow_guest, attendance_categories, auto_confirm, registration_enabled, created_by)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$admin['id']]));
            $id = (int) $pdo->lastInsertId();
            // Every new conference gets its own registration QR code.
            q('INSERT INTO qr_codes (conference_id, token, created_by) VALUES (?, ?, ?)', [$id, random_token(), $admin['id']]);
            log_activity('conference_create', 'Created conference "' . $d['title'] . '"');
        }
        if (is_super()) {
            q('DELETE FROM admin_conferences WHERE conference_id = ?', [$id]);
            $validIds = array_map('intval', array_column($conferenceAdmins, 'id'));
            foreach (array_intersect($assigned, $validIds) as $aid) {
                q('INSERT INTO admin_conferences (admin_id, conference_id) VALUES (?, ?)', [$aid, $id]);
            }
        }
        $pdo->commit();
        flash('success', $conf ? 'Conference updated.' : 'Conference created. A registration QR code was generated automatically.');
        redirect('admin/conference_view.php?id=' . $id);
    }
}

$years = lookup_academic_years();
$chk = fn($k) => !empty($d[$k]) ? 'checked' : '';
admin_header($conf ? 'Edit Conference' : 'New Conference', 'conferences', 'Conference Management');
?>
<div class="page-head">
  <div><h2><?= $conf ? 'Edit: ' . e($conf['title']) : 'Create a New Conference' ?></h2><p>Details shown here appear automatically on the student registration page.</p></div>
  <a class="btn btn-outline" href="<?= e(url($conf ? 'admin/conference_view.php?id=' . $id : 'admin/conferences.php')) ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><strong>Please fix the following:</strong><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="grid-3">
    <div class="span-2" style="display:grid;gap:1.25rem;align-content:start">
      <div class="card">
        <div class="card-header"><h3><i class="bi bi-info-circle"></i> Conference Details</h3></div>
        <div class="card-body form-grid">
          <div class="field full"><label for="title">Conference Name<span class="req">*</span></label><input class="input" id="title" name="title" value="<?= e($d['title']) ?>" maxlength="190" required></div>
          <div class="field full"><label for="description">Description<span class="opt">(optional)</span></label><textarea class="input" id="description" name="description" rows="3"><?= e($d['description']) ?></textarea></div>
          <div class="field full"><label for="venue">Venue<span class="req">*</span></label><input class="input" id="venue" name="venue" value="<?= e($d['venue']) ?>" maxlength="255" required></div>
          <div class="field"><label for="start_datetime">Start Date &amp; Time<span class="req">*</span></label><input class="input" type="datetime-local" id="start_datetime" name="start_datetime" value="<?= e($dtIn($d['start_datetime'])) ?>" required></div>
          <div class="field"><label for="end_datetime">End Date &amp; Time<span class="opt">(optional)</span></label><input class="input" type="datetime-local" id="end_datetime" name="end_datetime" value="<?= e($dtIn($d['end_datetime'])) ?>"></div>
          <div class="field"><label for="academic_year_id">Academic Year</label>
            <select class="input" id="academic_year_id" name="academic_year_id"><option value="">—</option>
              <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= (string) $d['academic_year_id'] === (string) $y['id'] ? 'selected' : '' ?>><?= e($y['name']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3><i class="bi bi-ui-checks"></i> Registration Settings</h3></div>
        <div class="card-body form-grid">
          <div class="field"><label for="registration_open">Registration Opens<span class="opt">(optional)</span></label><input class="input" type="datetime-local" id="registration_open" name="registration_open" value="<?= e($dtIn($d['registration_open'])) ?>"><div class="help">Leave blank to open immediately.</div></div>
          <div class="field"><label for="registration_close">Registration Closes<span class="opt">(optional)</span></label><input class="input" type="datetime-local" id="registration_close" name="registration_close" value="<?= e($dtIn($d['registration_close'])) ?>"><div class="help">Leave blank for no deadline.</div></div>
          <div class="field"><label for="participant_limit">Participant Limit<span class="opt">(optional)</span></label><input class="input" type="number" min="1" id="participant_limit" name="participant_limit" value="<?= e($d['participant_limit']) ?>" placeholder="Unlimited"></div>
          <div class="field"><label for="attendance_categories">Attendance Categories</label><textarea class="input" id="attendance_categories" name="attendance_categories" rows="3"><?= e(str_replace(',', "\n", $d['attendance_categories'])) ?></textarea><div class="help">One per line. Leave empty to hide this field.</div></div>
          <div class="field full">
            <span class="label">Allowed Participant Types</span>
            <div class="flex">
              <label class="check"><input type="checkbox" name="allow_student" <?= $chk('allow_student') ?>> Student</label>
              <label class="check"><input type="checkbox" name="allow_faculty" <?= $chk('allow_faculty') ?>> Faculty</label>
              <label class="check"><input type="checkbox" name="allow_guest" <?= $chk('allow_guest') ?>> Guest</label>
            </div>
          </div>
          <div class="field full">
            <label class="check"><input type="checkbox" name="registration_enabled" <?= $chk('registration_enabled') ?>> <span><strong>Registration enabled</strong><br><span class="help">Turn off to stop accepting registrations at any time.</span></span></label>
            <label class="check mt-1"><input type="checkbox" name="auto_confirm" <?= $chk('auto_confirm') ?>> <span><strong>Automatically confirm new registrations</strong><br><span class="help">If off, new registrations are “Pending” until an admin confirms them.</span></span></label>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;gap:1.25rem;align-content:start">
      <?php if (is_super()): ?>
      <div class="card">
        <div class="card-header"><h3><i class="bi bi-person-gear"></i> Conference Admins</h3></div>
        <div class="card-body">
          <p class="small muted">Conference Admins can only manage the conferences assigned to them. Super Admins can manage all.</p>
          <?php if (!$conferenceAdmins): ?><p class="small muted mb-0">No Conference Admin accounts yet. <a href="<?= e(url('admin/admins.php')) ?>">Create one</a>.</p><?php endif; ?>
          <?php foreach ($conferenceAdmins as $ca): ?>
            <label class="check mb-1"><input type="checkbox" name="admins[]" value="<?= (int) $ca['id'] ?>" <?= in_array((int) $ca['id'], $assigned, true) ? 'checked' : '' ?>> <?= e($ca['full_name']) ?> <span class="muted small">@<?= e($ca['username']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="card">
        <div class="card-body">
          <button class="btn btn-primary btn-block btn-lg"><i class="bi bi-check-lg"></i> <?= $conf ? 'Save Changes' : 'Create Conference' ?></button>
          <?php if (!$conf): ?><p class="small muted mt-2 mb-0"><i class="bi bi-qr-code"></i> A registration QR code will be generated automatically.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>
<?php admin_footer(); ?>
