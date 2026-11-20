<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

$id = (int) get('id');
$r = q_row(registration_select_sql() . ' WHERE r.id = ?', [$id]);
if (!$r || !can_access_conference((int) $r['conference_id'])) {
    flash('error', 'Participant record not found.');
    redirect('admin/participants.php');
}
$conf    = q_row('SELECT * FROM conferences WHERE id = ?', [$r['conference_id']]);
$types   = lookup_participant_types();
$schools = lookup_schools(false);
$courses = lookup_courses(false);
$cats    = conference_categories($conf);
if ($r['attendance_category'] && !in_array($r['attendance_category'], $cats, true)) {
    $cats[] = $r['attendance_category'];
}

$d = $r;
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (['id_number', 'last_name', 'first_name', 'middle_name', 'contact_number', 'address', 'school_id', 'school_other',
              'course_id', 'course_other', 'year_level', 'attendance_category', 'special_requirements', 'status', 'participant_type_id'] as $k) {
        $d[$k] = post($k);
    }
    $d['id_number'] = strtoupper($d['id_number']);
    $typeCode = null;
    foreach ($types as $t) {
        if ((string) $t['id'] === $d['participant_type_id']) $typeCode = $t['code'];
    }

    if (!$typeCode) $errors[] = 'Invalid participant type.';
    if ($d['id_number'] === '' && $typeCode !== 'guest') $errors[] = 'ID number is required for students and faculty.';
    if ($d['id_number'] !== '' && !valid_id_number($d['id_number'])) $errors[] = 'Invalid ID number format. ' . setting('student_id_hint', '');
    if ($d['id_number'] !== '' && q_val('SELECT id FROM students WHERE id_number = ? AND id <> ?', [$d['id_number'], $r['student_id']])) {
        $errors[] = 'That ID number already belongs to another participant record.';
    }
    if ($d['last_name'] === '' || !valid_name($d['last_name'])) $errors[] = 'A valid last name is required.';
    if ($d['first_name'] === '' || !valid_name($d['first_name'])) $errors[] = 'A valid first name is required.';
    if ($d['middle_name'] !== '' && !valid_name($d['middle_name'])) $errors[] = 'Invalid middle name.';
    $contact = normalize_contact($d['contact_number']);
    if (!$contact) $errors[] = 'A valid contact number is required.'; else $d['contact_number'] = $contact;
    if (mb_strlen($d['address']) < 5 || mb_strlen($d['address']) > 255) $errors[] = 'A complete address is required.';
    if ($d['year_level'] !== '' && !in_array($d['year_level'], year_levels(), true)) $errors[] = 'Invalid year level.';
    if ($typeCode === 'student' && $d['year_level'] === '') $errors[] = 'Year level is required for students.';
    if (!isset(statuses()[$d['status']])) $errors[] = 'Invalid status.';
    if (mb_strlen($d['special_requirements']) > 500) $errors[] = 'Special requirements is too long.';
    if ($d['school_id'] !== '' && !ctype_digit($d['school_id'])) $d['school_id'] = '';
    if ($d['course_id'] !== '' && !ctype_digit($d['course_id'])) $d['course_id'] = '';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        q('UPDATE students SET id_number = ?, last_name = ?, first_name = ?, middle_name = ?, contact_number = ?, address = ? WHERE id = ?', [
            $d['id_number'] ?: null, $d['last_name'], $d['first_name'], $d['middle_name'] ?: null, $d['contact_number'], $d['address'], $r['student_id'],
        ]);
        $statusChanged = $d['status'] !== $r['status'];
        q('UPDATE conference_registrations SET participant_type_id = ?, school_id = ?, school_other = ?, course_id = ?, course_other = ?, year_level = ?, section = ?,
           attendance_category = ?, special_requirements = ?, status = ?' . ($statusChanged ? ', status_updated_at = NOW(), status_updated_by = ' . (int) $admin['id'] : '') . ' WHERE id = ?', [
            (int) $d['participant_type_id'],
            $d['school_id'] !== '' ? (int) $d['school_id'] : null, $d['school_id'] === '' && $d['school_other'] !== '' ? $d['school_other'] : null,
            $d['course_id'] !== '' ? (int) $d['course_id'] : null, $d['course_id'] === '' && $d['course_other'] !== '' ? $d['course_other'] : null,
            $typeCode === 'student' ? ($d['year_level'] ?: null) : null, $r['section'] ?: null,
            $d['attendance_category'] ?: null, $d['special_requirements'] ?: null, $d['status'], $id,
        ]);
        $pdo->commit();
        log_activity('participant_update', 'Edited participant ' . $r['reference_no'] . ' (' . $d['last_name'] . ', ' . $d['first_name'] . ')');
        flash('success', 'Participant information updated.');
        redirect('admin/participant.php?id=' . $id);
    }
}

$sel = fn($a, $b) => (string) $a === (string) $b ? 'selected' : '';
admin_header('Edit Participant', 'participants', 'Participant Records');
?>
<div class="page-head">
  <div><h2>Edit: <?= e(full_name($r)) ?></h2><p><?= e($r['reference_no']) ?> · <?= e($r['conference_title']) ?></p></div>
  <a class="btn btn-outline" href="<?= e(url('admin/participant.php?id=' . $id)) ?>"><i class="bi bi-arrow-left"></i> Back to profile</a>
</div>
<?php if ($errors): ?>
  <div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><strong>Please fix the following:</strong><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><h3><i class="bi bi-person-vcard"></i> Personal &amp; Contact Information</h3></div>
      <div class="card-body form-grid">
        <div class="field"><label>Participant Type</label><select class="input" name="participant_type_id"><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $sel($t['id'], $d['participant_type_id']) ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>ID Number</label><input class="input" name="id_number" value="<?= e($d['id_number']) ?>" maxlength="30"></div>
        <div class="field"><label>Last Name<span class="req">*</span></label><input class="input" name="last_name" value="<?= e($d['last_name']) ?>" required maxlength="80"></div>
        <div class="field"><label>First Name<span class="req">*</span></label><input class="input" name="first_name" value="<?= e($d['first_name']) ?>" required maxlength="80"></div>
        <div class="field"><label>Middle Name</label><input class="input" name="middle_name" value="<?= e($d['middle_name']) ?>" maxlength="80"></div>
        <div class="field"><label>Contact Number<span class="req">*</span></label><input class="input" name="contact_number" value="<?= e($d['contact_number']) ?>" required maxlength="20"></div>
        <div class="field full"><label>Address<span class="req">*</span></label><textarea class="input" name="address" rows="2" required maxlength="255"><?= e($d['address']) ?></textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="bi bi-mortarboard"></i> Academic Information</h3></div>
      <div class="card-body form-grid">
        <div class="field"><label>Course / Program</label><select class="input" name="course_id"><option value="">— Other / none —</option><?php foreach ($courses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $sel($c['id'], $d['course_id']) ?>><?= e($c['code'] . ' – ' . $c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Other Course (if not in list)</label><input class="input" name="course_other" value="<?= e($d['course_other']) ?>" maxlength="190"></div>
        <div class="field"><label>Year Level <span class="opt">(students)</span></label><select class="input" name="year_level"><option value="">—</option><?php foreach (year_levels() as $y): ?><option <?= $sel($y, $d['year_level']) ?>><?= e($y) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="bi bi-people"></i> Conference Participation</h3></div>
      <div class="card-body form-grid">
        <div class="field"><label>Attendance Category</label><select class="input" name="attendance_category"><option value="">—</option><?php foreach ($cats as $c): ?><option <?= $sel($c, $d['attendance_category']) ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Registration Status</label><select class="input" name="status"><?php foreach (statuses() as $k => $v): ?><option value="<?= $k ?>" <?= $sel($k, $d['status']) ?>><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label>Special Requirements</label><textarea class="input" name="special_requirements" rows="2" maxlength="500"><?= e($d['special_requirements']) ?></textarea></div>
      </div>
    </div>
    <div class="card" style="align-self:start">
      <div class="card-body">
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check-lg"></i> Save Changes</button>
        <p class="small muted mt-2 mb-0">Personal information is shared across all of this participant's conference registrations.</p>
      </div>
    </div>
  </div>
</form>
<?php admin_footer(); ?>
