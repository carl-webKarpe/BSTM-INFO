<?php
/**
 * STUDENT REGISTRATION PAGE
 * Opened when a participant scans the conference QR code:
 *     register.php?t=<registration token>
 *
 * Flow:  Fill out form → Review → Submit → confirmation.php
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$token = preg_replace('/[^a-f0-9]/', '', strtolower(get('t', post('t'))));

$qr = $token !== '' ? q_row('SELECT * FROM qr_codes WHERE token = ?', [$token]) : null;
if (!$qr) {
    public_message('Invalid Registration Link',
        'This registration link or QR code is not valid. Please scan the official QR code displayed at the conference area, or ask the organizers for the correct link.',
        'danger', 'qr-code');
}
$conf = q_row('SELECT * FROM conferences WHERE id = ?', [$qr['conference_id']]);
$state = conference_state($conf, $qr);

// Count each scan once per browser session.
if (!is_post() && empty($_SESSION['scanned'][$qr['id']])) {
    $_SESSION['scanned'][$qr['id']] = 1;
    q('UPDATE qr_codes SET scan_count = scan_count + 1, last_scanned_at = NOW() WHERE id = ?', [$qr['id']]);
}

if (!$state['open']) {
    public_message('Registration ' . $state['label'], $state['message'], $state['tone'] === 'warning' ? 'warning' : 'danger', 'calendar-x', $conf);
}

// ---------------------------------------------------------------
// Lookup data
// ---------------------------------------------------------------
$types = [];
foreach (lookup_participant_types(true) as $t) {
    if (!empty($conf['allow_' . $t['code']])) {
        $types[$t['code']] = $t;
    }
}
if (!$types) {
    public_message('Registration Closed', 'No participant types are currently accepted for this conference.', 'danger', 'calendar-x', $conf);
}
$schools    = lookup_schools();
$courses    = lookup_courses();
$categories = conference_categories($conf);
$schoolMap  = array_column($schools, 'name', 'id');
$courseMap  = [];
foreach ($courses as $c) {
    $courseMap[$c['id']] = $c['code'] . ' – ' . $c['name'];
}

$draftKey = 'reg_draft_' . $qr['id'];

// ---------------------------------------------------------------
// Validation
// ---------------------------------------------------------------
function validate_registration(array $in, array $conf, array $types, array $schoolMap, array $courseMap, array $categories): array
{
    $err = [];
    $d   = [];
    $s   = fn($k) => trim((string) ($in[$k] ?? ''));

    $d['type'] = $s('type');
    if (count($types) === 1) {
        $d['type'] = array_key_first($types);
    }
    if (!isset($types[$d['type']])) {
        $err['type'] = 'Please choose how you are registering.';
        $d['type'] = '';
    }
    $type = $d['type'];

    // ---- personal
    $d['id_number'] = strtoupper($s('id_number'));
    if ($d['id_number'] === '') {
        if ($type !== 'guest') {
            $err['id_number'] = ($type === 'faculty' ? 'Employee' : 'Student') . ' ID number is required.';
        }
    } elseif (!valid_id_number($d['id_number'])) {
        $err['id_number'] = 'Invalid ID number. ' . setting('student_id_hint', '');
    }

    foreach (['last_name' => 'Last name', 'first_name' => 'First name'] as $k => $label) {
        $d[$k] = preg_replace('/\s+/', ' ', $s($k));
        if ($d[$k] === '') {
            $err[$k] = $label . ' is required.';
        } elseif (!valid_name($d[$k])) {
            $err[$k] = $label . ' may only contain letters, spaces, dots, apostrophes and dashes.';
        }
    }
    $d['middle_name'] = preg_replace('/\s+/', ' ', $s('middle_name'));
    if ($d['middle_name'] !== '' && !valid_name($d['middle_name'])) {
        $err['middle_name'] = 'Middle name may only contain letters, spaces, dots, apostrophes and dashes.';
    }

    $d['contact_number'] = $s('contact_number');
    if ($d['contact_number'] === '') {
        $err['contact_number'] = 'Contact number is required.';
    } else {
        $n = normalize_contact($d['contact_number']);
        if ($n === null) {
            $err['contact_number'] = 'Enter a valid mobile number, e.g. 09171234567 or +639171234567.';
        } else {
            $d['contact_number'] = $n;
        }
    }

    $d['address'] = preg_replace('/\s+/', ' ', $s('address'));
    if ($d['address'] === '') {
        $err['address'] = 'Complete address is required.';
    } elseif (mb_strlen($d['address']) < 8) {
        $err['address'] = 'Please enter your complete address (street / barangay, city / municipality, province).';
    } elseif (mb_strlen($d['address']) > 255) {
        $err['address'] = 'Address is too long (max 255 characters).';
    }

    // ---- academic
    $d['school_id']    = $s('school_id');
    $d['school_other'] = $s('school_other');
    $d['course_id']    = $s('course_id');
    $d['course_other'] = $s('course_other');
    $d['year_level']   = $s('year_level');
    $d['section']      = $s('section');

    // School (required for students & faculty, optional for guests)
    if ($d['school_id'] === 'other') {
        if ($d['school_other'] === '') {
            $err['school_other'] = 'Please type the name of your school / institution.';
        } elseif (mb_strlen($d['school_other']) > 190) {
            $err['school_other'] = 'School name is too long.';
        }
    } elseif ($d['school_id'] !== '' && isset($schoolMap[$d['school_id']])) {
        $d['school_other'] = '';
    } else {
        $d['school_id'] = '';
        $d['school_other'] = '';
        if ($type !== 'guest' && $type !== '') {
            $err['school_id'] = 'Please select your school / institution.';
        }
    }

    // Course / department
    if ($type === 'guest') {
        $d['course_id'] = $d['course_other'] = '';
    } elseif ($d['course_id'] === 'other') {
        if ($d['course_other'] === '') {
            $err['course_other'] = 'Please type your ' . ($type === 'faculty' ? 'department' : 'course / program') . '.';
        } elseif (mb_strlen($d['course_other']) > 190) {
            $err['course_other'] = 'Course name is too long.';
        }
    } elseif ($d['course_id'] !== '' && isset($courseMap[$d['course_id']])) {
        $d['course_other'] = '';
    } else {
        $d['course_id'] = $d['course_other'] = '';
        if ($type === 'student') {
            $err['course_id'] = 'Please select your course / program.';
        }
    }

    if ($type === 'student') {
        if (!in_array($d['year_level'], year_levels(), true)) {
            $err['year_level'] = 'Please select your year level.';
            $d['year_level'] = '';
        }
        if (mb_strlen($d['section']) > 50) {
            $err['section'] = 'Section is too long.';
        }
    } else {
        $d['year_level'] = $d['section'] = '';
    }

    // ---- conference participation
    $d['attendance_category'] = $s('attendance_category');
    if ($categories) {
        if (!in_array($d['attendance_category'], $categories, true)) {
            $err['attendance_category'] = 'Please select your attendance category.';
            $d['attendance_category'] = '';
        }
    } else {
        $d['attendance_category'] = '';
    }
    $d['special_requirements'] = $s('special_requirements');
    if (mb_strlen($d['special_requirements']) > 500) {
        $err['special_requirements'] = 'Please keep this under 500 characters.';
    }

    $d['consent'] = !empty($in['consent']) ? '1' : '';
    if ($d['consent'] !== '1') {
        $err['consent'] = 'You must agree to the data privacy notice to register.';
    }

    // ---- duplicate registration check
    if (!$err) {
        if ($d['id_number'] !== '') {
            $dup = q_val('SELECT r.id FROM conference_registrations r JOIN students s ON s.id = r.student_id
                          WHERE r.conference_id = ? AND s.id_number = ?', [$conf['id'], $d['id_number']]);
        } else {
            $dup = q_val('SELECT r.id FROM conference_registrations r JOIN students s ON s.id = r.student_id
                          WHERE r.conference_id = ? AND s.last_name = ? AND s.first_name = ? AND s.contact_number = ?',
                [$conf['id'], $d['last_name'], $d['first_name'], $d['contact_number']]);
        }
        if ($dup) {
            $err['_duplicate'] = 'You are already registered for this conference. Use “Find my registration” to view your confirmation.';
        }
    }
    return [$d, $err];
}

// ---------------------------------------------------------------
// Handle actions
// ---------------------------------------------------------------
$view   = 'form';
$data   = $_SESSION[$draftKey] ?? [];
$errors = [];
$action = is_post() ? post('action') : '';

if ($action !== '') {
    verify_csrf();
}

if ($action === 'review') {
    [$data, $errors] = validate_registration($_POST, $conf, $types, $schoolMap, $courseMap, $categories);
    if (!$errors) {
        $_SESSION[$draftKey] = $data;
        $view = 'review';
    }
} elseif ($action === 'edit') {
    $view = 'form';
} elseif ($action === 'submit') {
    if (empty($_SESSION[$draftKey])) {
        flash('warning', 'Your session expired. Please fill out the form again.');
        redirect('register.php?t=' . $token);
    }
    [$data, $errors] = validate_registration($_SESSION[$draftKey], $conf, $types, $schoolMap, $courseMap, $categories);
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // Lock the conference row so the participant limit cannot be exceeded.
            $locked = q_row('SELECT * FROM conferences WHERE id = ? FOR UPDATE', [$conf['id']]);
            $st = conference_state($locked, $qr);
            if (!$st['open']) {
                $pdo->rollBack();
                public_message('Registration ' . $st['label'], $st['message'], 'danger', 'calendar-x', $conf);
            }

            // Participant record (re-used across conferences when the ID number matches)
            $studentId = null;
            if ($data['id_number'] !== '') {
                $studentId = q_val('SELECT id FROM students WHERE id_number = ? FOR UPDATE', [$data['id_number']]);
            }
            $params = [$data['last_name'], $data['first_name'], $data['middle_name'] ?: null, $data['contact_number'], $data['address']];
            if ($studentId) {
                q('UPDATE students SET last_name = ?, first_name = ?, middle_name = ?, contact_number = ?, address = ? WHERE id = ?', array_merge($params, [$studentId]));
            } else {
                q('INSERT INTO students (last_name, first_name, middle_name, contact_number, address, id_number) VALUES (?, ?, ?, ?, ?, ?)',
                    array_merge($params, [$data['id_number'] ?: null]));
                $studentId = (int) $pdo->lastInsertId();
            }

            $ref    = generate_reference();
            $key    = random_token();
            $status = $conf['auto_confirm'] ? 'confirmed' : 'pending';
            q('INSERT INTO conference_registrations
                 (reference_no, access_key, attendance_token, conference_id, student_id, qr_code_id, participant_type_id,
                  school_id, school_other, course_id, course_other, year_level, section, attendance_category,
                  special_requirements, status, ip_address)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $ref, $key, random_token(), $conf['id'], $studentId, $qr['id'], $types[$data['type']]['id'],
                ctype_digit($data['school_id']) ? (int) $data['school_id'] : null, $data['school_other'] ?: null,
                ctype_digit($data['course_id']) ? (int) $data['course_id'] : null, $data['course_other'] ?: null,
                $data['year_level'] ?: null, $data['section'] ?: null, $data['attendance_category'] ?: null,
                $data['special_requirements'] ?: null, $status, client_ip(),
            ]);
            $pdo->commit();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($ex->getCode() === '23000') {
                $errors['_duplicate'] = 'You are already registered for this conference. Use “Find my registration” to view your confirmation.';
            } else {
                throw $ex;
            }
        }
        if (!$errors) {
            unset($_SESSION[$draftKey]);
            $_SESSION['my_registrations'][$ref] = $key;
            redirect('confirmation.php?ref=' . urlencode($ref) . '&key=' . $key . '&new=1');
        }
    }
}

// ---------------------------------------------------------------
// Rendering helpers
// ---------------------------------------------------------------
$val = fn($k) => e($data[$k] ?? '');
$err = function ($k) use ($errors) {
    return isset($errors[$k]) ? '<div class="error-text" id="err-' . e($k) . '"><i class="bi bi-exclamation-circle"></i> ' . e($errors[$k]) . '</div>' : '';
};
$inv = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
$selType = $data['type'] ?? (count($types) === 1 ? array_key_first($types) : '');
$typeIcons = ['student' => 'mortarboard', 'faculty' => 'person-workspace', 'guest' => 'person-badge'];

public_header('Register · ' . $conf['title']);
?>
<section class="hero">
  <div class="eyebrow"><i class="bi bi-qr-code-scan"></i> Conference Registration</div>
  <h1><?= e($conf['title']) ?></h1>
  <p><?= e(setting('welcome_message', 'Please complete the form below to register.')) ?></p>
</section>

<div class="steps" aria-label="Registration progress">
  <div class="step <?= $view === 'form' ? 'active' : 'done' ?>"><span class="num"><?= $view === 'form' ? '1' : '<i class="bi bi-check"></i>' ?></span><span class="lbl">Fill Out Form</span></div>
  <div class="step <?= $view === 'review' ? 'active' : '' ?>"><span class="num">2</span><span class="lbl">Review</span></div>
  <div class="step"><span class="num">3</span><span class="lbl">Confirmation</span></div>
</div>

<!-- SECTION 1 – CONFERENCE INFORMATION -->
<div class="section">
  <div class="section-head">
    <span class="icon"><i class="bi bi-calendar-event"></i></span>
    <div><h2>Conference Information</h2><p>Set by the conference organizers</p></div>
  </div>
  <div class="section-body">
    <dl class="info-list">
      <div class="full"><dt>Conference Name</dt><dd><?= e($conf['title']) ?></dd></div>
      <div><dt>Conference Date</dt><dd><?= e(fmt_conf_schedule($conf)) ?></dd></div>
      <div><dt>Conference Venue</dt><dd><?= e($conf['venue']) ?></dd></div>
      <div class="full"><dt>Registration Status</dt><dd><span class="badge badge-success"><span class="dot"></span> <?= e($state['label']) ?></span> <span class="muted small" style="font-weight:500"><?= e($state['message']) ?></span></dd></div>
      <?php if (trim((string) $conf['description']) !== ''): ?>
        <div class="full"><dt>About</dt><dd style="font-weight:400"><?= nl2br(e($conf['description'])) ?></dd></div>
      <?php endif; ?>
    </dl>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert" id="error-summary" tabindex="-1">
    <i class="bi bi-exclamation-octagon-fill"></i>
    <div>
      <?php if (isset($errors['_duplicate'])): ?>
        <strong><?= e($errors['_duplicate']) ?></strong>
        <div class="mt-1"><a href="<?= e(url('lookup.php')) ?>">Find my registration &rarr;</a></div>
      <?php else: ?>
        <strong>Please correct the <?= count($errors) ?> highlighted field<?= count($errors) > 1 ? 's' : '' ?> below.</strong>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($view === 'review'): ?>
  <?php
    $t = $data['type'];
    $schoolTxt = $data['school_id'] === 'other' ? $data['school_other'] : ($schoolMap[$data['school_id']] ?? '');
    $courseTxt = $data['course_id'] === 'other' ? $data['course_other'] : ($courseMap[$data['course_id']] ?? '');
    $dash = fn($v) => $v !== '' && $v !== null ? e($v) : '<span class="muted">—</span>';
  ?>
  <div class="alert alert-info"><i class="bi bi-eye"></i><div><strong>Please review your information.</strong> If something is wrong, tap <em>Edit Information</em>. Once everything is correct, tap <em>Confirm &amp; Submit</em>.</div></div>

  <div class="section">
    <div class="section-head"><span class="icon"><i class="bi bi-person-vcard"></i></span><div><h2>Personal Information</h2></div></div>
    <div class="section-body">
      <dl class="info-list">
        <div><dt>Registering As</dt><dd><?= e($types[$t]['name']) ?></dd></div>
        <div><dt><?= $t === 'faculty' ? 'Employee' : 'Student' ?> ID Number</dt><dd><?= $dash($data['id_number']) ?></dd></div>
        <div><dt>Last Name</dt><dd><?= e($data['last_name']) ?></dd></div>
        <div><dt>First Name</dt><dd><?= e($data['first_name']) ?></dd></div>
        <div><dt>Middle Name</dt><dd><?= $dash($data['middle_name']) ?></dd></div>
        <div><dt>Contact Number</dt><dd><?= e($data['contact_number']) ?></dd></div>
        <div class="full"><dt>Complete Address</dt><dd><?= e($data['address']) ?></dd></div>
      </dl>
    </div>
  </div>

  <div class="section">
    <div class="section-head"><span class="icon"><i class="bi bi-mortarboard"></i></span><div><h2>Academic Information</h2></div></div>
    <div class="section-body">
      <dl class="info-list">
        <div><dt><?= $t === 'guest' ? 'School / Organization' : 'School / Institution' ?></dt><dd><?= $dash($schoolTxt) ?></dd></div>
        <?php if ($t !== 'guest'): ?>
          <div><dt><?= $t === 'faculty' ? 'Department / Program' : 'Course / Program' ?></dt><dd><?= $dash($courseTxt) ?></dd></div>
        <?php endif; ?>
        <?php if ($t === 'student'): ?>
          <div><dt>Year Level</dt><dd><?= e($data['year_level']) ?></dd></div>
          <div><dt>Section</dt><dd><?= $dash($data['section']) ?></dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </div>

  <div class="section">
    <div class="section-head"><span class="icon"><i class="bi bi-people"></i></span><div><h2>Conference Participant Information</h2></div></div>
    <div class="section-body">
      <dl class="info-list">
        <div><dt>Participant Type</dt><dd><?= e($types[$t]['name']) ?></dd></div>
        <?php if ($categories): ?><div><dt>Attendance Category</dt><dd><?= e($data['attendance_category']) ?></dd></div><?php endif; ?>
        <div class="full"><dt>Special Requirements</dt><dd><?= $dash($data['special_requirements']) ?></dd></div>
      </dl>
    </div>
  </div>

  <form method="post" class="sticky-actions" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <button class="btn btn-outline btn-lg" name="action" value="edit" formnovalidate><i class="bi bi-pencil"></i> Edit Information</button>
    <button class="btn btn-primary btn-lg" name="action" value="submit"><i class="bi bi-send-check"></i> Confirm &amp; Submit</button>
  </form>

<?php else: ?>
  <p class="legend-req">Fields marked with <span class="req">*</span> are required. Fields marked <span class="opt">(optional)</span> may be left blank.</p>

  <form method="post" id="reg-form" novalidate data-types="<?= e(implode(',', array_keys($types))) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="review">

    <?php if (count($types) > 1): ?>
    <div class="section">
      <div class="section-head"><span class="icon"><i class="bi bi-person-check"></i></span><div><h2>Register As</h2><p>Choose one – the form will show only the fields you need</p></div></div>
      <div class="section-body">
        <div class="choice-group" role="radiogroup" aria-label="Participant type">
          <?php foreach ($types as $code => $t): ?>
            <label class="choice"><input type="radio" name="type" value="<?= e($code) ?>" <?= $selType === $code ? 'checked' : '' ?> required> <i class="bi bi-<?= $typeIcons[$code] ?? 'person' ?>"></i> <?= e($t['name']) ?></label>
          <?php endforeach; ?>
        </div>
        <?= $err('type') ?>
      </div>
    </div>
    <?php else: ?>
      <input type="hidden" name="type" value="<?= e($selType) ?>">
    <?php endif; ?>

    <div id="type-dependent" class="<?= $selType === '' && count($types) > 1 ? 'is-waiting' : '' ?>">

    <!-- SECTION 2 – PERSONAL INFORMATION -->
    <div class="section">
      <div class="section-head"><span class="icon"><i class="bi bi-person-vcard"></i></span><div><h2>Personal Information</h2><p>Tell us who you are</p></div></div>
      <div class="section-body">
        <div class="form-grid">
          <div class="field full">
            <label for="id_number"><span data-label-student="Student ID Number" data-label-faculty="Employee ID Number" data-label-guest="ID Number">Student ID Number</span><span class="req" data-req-for="student,faculty">*</span><span class="opt" data-show="guest">(optional)</span></label>
            <input class="input<?= $inv('id_number') ?>" id="id_number" name="id_number" value="<?= $val('id_number') ?>" maxlength="30" autocomplete="off" autocapitalize="characters" data-required-for="student,faculty" aria-describedby="id-help">
            <div class="help" id="id-help"><?= e(setting('student_id_hint', '')) ?></div>
            <?= $err('id_number') ?>
          </div>
          <div class="field">
            <label for="last_name">Last Name<span class="req">*</span></label>
            <input class="input<?= $inv('last_name') ?>" id="last_name" name="last_name" value="<?= $val('last_name') ?>" maxlength="80" required autocomplete="family-name">
            <?= $err('last_name') ?>
          </div>
          <div class="field">
            <label for="first_name">First Name<span class="req">*</span></label>
            <input class="input<?= $inv('first_name') ?>" id="first_name" name="first_name" value="<?= $val('first_name') ?>" maxlength="80" required autocomplete="given-name">
            <?= $err('first_name') ?>
          </div>
          <div class="field">
            <label for="middle_name">Middle Name<span class="opt">(optional)</span></label>
            <input class="input<?= $inv('middle_name') ?>" id="middle_name" name="middle_name" value="<?= $val('middle_name') ?>" maxlength="80" autocomplete="additional-name">
            <?= $err('middle_name') ?>
          </div>
          <div class="field">
            <label for="contact_number">Contact Number<span class="req">*</span></label>
            <input class="input<?= $inv('contact_number') ?>" id="contact_number" name="contact_number" type="tel" inputmode="tel" value="<?= $val('contact_number') ?>" maxlength="20" placeholder="09XXXXXXXXX" required autocomplete="tel">
            <div class="help">Mobile number, e.g. 09171234567</div>
            <?= $err('contact_number') ?>
          </div>
          <div class="field full">
            <label for="address">Complete Address<span class="req">*</span></label>
            <textarea class="input<?= $inv('address') ?>" id="address" name="address" rows="2" maxlength="255" required autocomplete="street-address" placeholder="House No. / Street, Barangay, City / Municipality, Province"><?= $val('address') ?></textarea>
            <?= $err('address') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 3 – ACADEMIC INFORMATION -->
    <div class="section">
      <div class="section-head"><span class="icon"><i class="bi bi-mortarboard"></i></span><div><h2>Academic Information</h2><p data-show="student">Your school, course and year level</p><p data-show="faculty">Your school and department</p><p data-show="guest">Optional for guests</p></div></div>
      <div class="section-body">
        <div class="form-grid">
          <div class="field full">
            <label for="school_id"><span data-label-student="School / Institution" data-label-faculty="School / Institution" data-label-guest="School / Organization">School / Institution</span><span class="req" data-req-for="student,faculty">*</span><span class="opt" data-show="guest">(optional)</span></label>
            <select class="input<?= $inv('school_id') ?>" id="school_id" name="school_id" data-required-for="student,faculty" data-other="school_other_wrap">
              <option value="">— Select —</option>
              <?php foreach ($schools as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= ($data['school_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
              <option value="other" <?= ($data['school_id'] ?? '') === 'other' ? 'selected' : '' ?>>Other (not in the list)</option>
            </select>
            <?= $err('school_id') ?>
          </div>
          <div class="field full <?= ($data['school_id'] ?? '') === 'other' ? '' : 'hidden' ?>" id="school_other_wrap">
            <label for="school_other">Name of School / Organization<span class="req">*</span></label>
            <input class="input<?= $inv('school_other') ?>" id="school_other" name="school_other" value="<?= $val('school_other') ?>" maxlength="190">
            <?= $err('school_other') ?>
          </div>
          <div class="field full" data-show="student,faculty">
            <label for="course_id"><span data-label-student="Course / Program" data-label-faculty="Department / Program">Course / Program</span><span class="req" data-req-for="student">*</span><span class="opt" data-show="faculty">(optional)</span></label>
            <select class="input<?= $inv('course_id') ?>" id="course_id" name="course_id" data-required-for="student" data-other="course_other_wrap">
              <option value="">— Select —</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= ($data['course_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' – ' . $c['name']) ?></option>
              <?php endforeach; ?>
              <option value="other" <?= ($data['course_id'] ?? '') === 'other' ? 'selected' : '' ?>>Other (not in the list)</option>
            </select>
            <?= $err('course_id') ?>
          </div>
          <div class="field full <?= ($data['course_id'] ?? '') === 'other' ? '' : 'hidden' ?>" id="course_other_wrap">
            <label for="course_other">Please specify<span class="req">*</span></label>
            <input class="input<?= $inv('course_other') ?>" id="course_other" name="course_other" value="<?= $val('course_other') ?>" maxlength="190">
            <?= $err('course_other') ?>
          </div>
          <div class="field" data-show="student">
            <label for="year_level">Year Level<span class="req">*</span></label>
            <select class="input<?= $inv('year_level') ?>" id="year_level" name="year_level" data-required-for="student">
              <option value="">— Select —</option>
              <?php foreach (year_levels() as $y): ?>
                <option <?= ($data['year_level'] ?? '') === $y ? 'selected' : '' ?>><?= e($y) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('year_level') ?>
          </div>
          <div class="field" data-show="student">
            <label for="section">Section<span class="opt">(optional)</span></label>
            <input class="input<?= $inv('section') ?>" id="section" name="section" value="<?= $val('section') ?>" maxlength="50" placeholder="e.g. A">
            <?= $err('section') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION 4 – CONFERENCE PARTICIPANT INFORMATION -->
    <div class="section">
      <div class="section-head"><span class="icon"><i class="bi bi-people"></i></span><div><h2>Conference Participant Information</h2><p>How you will attend</p></div></div>
      <div class="section-body">
        <div class="form-grid">
          <?php if ($categories): ?>
          <div class="field full">
            <label for="attendance_category">Attendance Category<span class="req">*</span></label>
            <select class="input<?= $inv('attendance_category') ?>" id="attendance_category" name="attendance_category" required>
              <option value="">— Select —</option>
              <?php foreach ($categories as $cat): ?>
                <option <?= ($data['attendance_category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
              <?php endforeach; ?>
            </select>
            <?= $err('attendance_category') ?>
          </div>
          <?php endif; ?>
          <div class="field full">
            <label for="special_requirements">Special Requirements<span class="opt">(optional)</span></label>
            <textarea class="input<?= $inv('special_requirements') ?>" id="special_requirements" name="special_requirements" rows="2" maxlength="500" placeholder="e.g. wheelchair access, dietary restrictions"><?= $val('special_requirements') ?></textarea>
            <?= $err('special_requirements') ?>
          </div>
          <div class="field full">
            <label class="check">
              <input type="checkbox" name="consent" value="1" <?= !empty($data['consent']) ? 'checked' : '' ?> required>
              <span>I confirm that the information I provided is correct, and I agree that it will be collected and processed by the conference organizers solely for registration and attendance purposes, in accordance with the Data Privacy Act of 2012.<span class="req">*</span></span>
            </label>
            <?= $err('consent') ?>
          </div>
        </div>
      </div>
    </div>

    </div><!-- /type-dependent -->

    <div class="sticky-actions">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-arrow-right-circle"></i> Review My Information</button>
    </div>
  </form>
<?php endif; ?>

<?php public_footer(['js/register.js']); ?>
