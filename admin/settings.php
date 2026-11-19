<?php
require __DIR__ . '/_init.php';
require_super();

$tab = in_array(get('tab'), ['general', 'lists', 'backup'], true) ? get('tab') : 'general';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = post('action');

    // ------------------------------------------------------------ general settings
    if ($action === 'general') {
        $base = rtrim(post('public_base_url'), '/');
        $pattern = post('student_id_pattern');
        if ($base !== '' && !preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $base)) $errors[] = 'Public Base URL must start with http:// or https://';
        if ($pattern !== '' && @preg_match('~' . str_replace('~', '\~', $pattern) . '~', '') === false) $errors[] = 'The Student ID pattern is not a valid regular expression.';

        if (!$errors) {
            save_setting('welcome_message', mb_substr(post('welcome_message'), 0, 500));
            save_setting('public_base_url', $base);
            save_setting('student_id_pattern', $pattern ?: '^[A-Za-z0-9-]{4,20}$');
            save_setting('student_id_hint', mb_substr(post('student_id_hint'), 0, 190));
            log_activity('settings_update', 'Updated general system settings');
            flash('success', 'Settings saved.');
            redirect('admin/settings.php');
        }
    }

    // ------------------------------------------------------------ lookup lists
    if (in_array($action, ['add_school', 'add_course', 'add_year', 'toggle', 'delete', 'current_year', 'toggle_type'], true)) {
        $tab = 'lists';
        $tables = ['school' => 'schools', 'course' => 'courses', 'year' => 'academic_years'];
        try {
            switch ($action) {
                case 'add_school':
                    $n = post('name');
                    if ($n === '' || mb_strlen($n) > 190) { $errors[] = 'School name is required.'; break; }
                    q('INSERT INTO schools (name) VALUES (?)', [$n]);
                    log_activity('settings_school', 'Added school "' . $n . '"');
                    break;
                case 'add_course':
                    $code = strtoupper(post('code'));
                    $n = post('name');
                    if ($code === '' || $n === '' || mb_strlen($code) > 30) { $errors[] = 'Course code and name are required.'; break; }
                    q('INSERT INTO courses (code, name) VALUES (?, ?)', [$code, $n]);
                    log_activity('settings_course', 'Added course "' . $code . '"');
                    break;
                case 'add_year':
                    $n = post('name');
                    if (!preg_match('/^\d{4}-\d{4}$/', $n)) { $errors[] = 'Academic year must look like 2026-2027.'; break; }
                    q('INSERT INTO academic_years (name) VALUES (?)', [$n]);
                    log_activity('settings_year', 'Added academic year ' . $n);
                    break;
                case 'current_year':
                    q('UPDATE academic_years SET is_current = (id = ?)', [(int) post('id')]);
                    log_activity('settings_year', 'Changed current academic year');
                    break;
                case 'toggle':
                    $t = $tables[post('kind')] ?? null;
                    if ($t && $t !== 'academic_years') {
                        q("UPDATE $t SET is_active = 1 - is_active WHERE id = ?", [(int) post('id')]);
                        log_activity('settings_' . post('kind'), 'Toggled ' . post('kind') . ' #' . (int) post('id'));
                    }
                    break;
                case 'toggle_type':
                    q('UPDATE participant_types SET is_active = 1 - is_active WHERE id = ?', [(int) post('id')]);
                    log_activity('settings_type', 'Toggled participant type #' . (int) post('id'));
                    break;
                case 'delete':
                    $t = $tables[post('kind')] ?? null;
                    if ($t) {
                        $col = ['schools' => 'school_id', 'courses' => 'course_id', 'academic_years' => null][$t];
                        $used = $col ? (int) q_val("SELECT COUNT(*) FROM conference_registrations WHERE $col = ?", [(int) post('id')])
                                     : (int) q_val('SELECT COUNT(*) FROM conferences WHERE academic_year_id = ?', [(int) post('id')]);
                        if ($used) { $errors[] = 'This item is already used by existing records. Deactivate it instead.'; break; }
                        q("DELETE FROM $t WHERE id = ?", [(int) post('id')]);
                        log_activity('settings_' . post('kind'), 'Deleted ' . post('kind') . ' #' . (int) post('id'));
                    }
                    break;
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors[] = 'That item already exists.';
        }
        if (!$errors) {
            flash('success', 'List updated.');
            redirect('admin/settings.php?tab=lists');
        }
    }

    // ------------------------------------------------------------ backup
    if ($action === 'backup') {
        log_activity('backup_download', 'Downloaded a database backup');
        $tables = ['admins', 'academic_years', 'schools', 'courses', 'participant_types', 'settings', 'conferences', 'admin_conferences',
                   'qr_codes', 'students', 'conference_registrations', 'attendance_records', 'activity_logs'];
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="conference_backup_' . date('Ymd_His') . '.sql"');
        $pdo = db();
        echo "-- Conference Registration System – database backup\n-- Created: " . date('Y-m-d H:i:s') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
        foreach ($tables as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
            echo "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
            $st = $pdo->query("SELECT * FROM `$t`");
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                echo "INSERT INTO `$t` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', $vals) . ");\n";
            }
            echo "\n";
        }
        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        exit;
    }
}

$schools = lookup_schools(false);
$courses = lookup_courses(false);
$years   = lookup_academic_years();
$ptypes  = lookup_participant_types();
$counts  = q_row('SELECT (SELECT COUNT(*) FROM conferences) AS conferences, (SELECT COUNT(*) FROM conference_registrations) AS registrations,
                         (SELECT COUNT(*) FROM students) AS students, (SELECT COUNT(*) FROM activity_logs) AS logs');

admin_header('System Settings', 'settings', 'Administration');
?>
<div class="tabs">
  <a href="?tab=general" class="<?= $tab === 'general' ? 'active' : '' ?>"><i class="bi bi-sliders"></i> General</a>
  <a href="?tab=lists" class="<?= $tab === 'lists' ? 'active' : '' ?>"><i class="bi bi-list-ul"></i> Schools, Courses &amp; Lists</a>
  <a href="?tab=backup" class="<?= $tab === 'backup' ? 'active' : '' ?>"><i class="bi bi-database"></i> Backup</a>
</div>

<?php if ($errors): ?><div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><ul class="mt-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>

<?php if ($tab === 'general'): ?>
<form method="post" class="grid-3">
  <?= csrf_field() ?><input type="hidden" name="action" value="general">
  <div class="card span-2">
    <div class="card-header"><h3>General</h3></div>
    <div class="card-body form-grid">
      <div class="field full"><label for="s-welcome">Welcome Message (registration page)</label><textarea class="input" id="s-welcome" name="welcome_message" rows="2" maxlength="500"><?= e(setting('welcome_message', '')) ?></textarea></div>
      <div class="field full"><label for="s-base">Public Base URL <span class="opt">(used inside QR codes)</span></label>
        <input class="input" id="s-base" name="public_base_url" value="<?= e(setting('public_base_url', '')) ?>" placeholder="<?= e(detected_base_url()) ?>">
        <div class="help">Leave empty to auto-detect (currently <code><?= e(detected_base_url()) ?></code>). For phone testing on your laptop use your Wi-Fi IP, e.g. <code>http://192.168.1.5<?= e(app_base_path()) ?></code>. Online, e.g. <code>https://yourname.infinityfreeapp.com</code>.</div></div>
      <div class="field"><label for="s-pat">Student ID Format (regular expression)</label><input class="input" id="s-pat" name="student_id_pattern" value="<?= e(setting('student_id_pattern', '')) ?>" style="font-family:monospace"><div class="help">Default <code>^[A-Za-z0-9-]{4,20}$</code>. Example for “2023-00123”: <code>^\d{4}-\d{5}$</code></div></div>
      <div class="field"><label for="s-hint">Student ID Hint (shown to students)</label><input class="input" id="s-hint" name="student_id_hint" value="<?= e(setting('student_id_hint', '')) ?>" maxlength="190"></div>
    </div>
  </div>
  <div style="display:grid;gap:1.25rem;align-content:start">
    <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Save Settings</button>
  </div>
</form>

<?php elseif ($tab === 'lists'): ?>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-building"></i> Schools / Institutions</h3></div>
    <div class="card-body">
      <form method="post" class="copy-row"><?= csrf_field() ?><input type="hidden" name="action" value="add_school"><input class="input input-sm" name="name" placeholder="New school name" required maxlength="190"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add</button></form>
    </div>
    <div class="table-wrap" style="max-height:420px"><table class="table"><tbody>
      <?php if (!$schools) echo empty_row(3, 'No schools yet.'); ?>
      <?php foreach ($schools as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= $s['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Hidden</span>' ?></td>
        <td class="text-right nowrap"><form method="post" class="flex" style="justify-content:flex-end;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="kind" value="school"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn btn-ghost btn-sm" name="action" value="toggle" title="<?= $s['is_active'] ? 'Hide from form' : 'Show on form' ?>"><i class="bi bi-eye<?= $s['is_active'] ? '-slash' : '' ?>"></i></button>
          <button class="btn btn-ghost btn-sm" name="action" value="delete" data-confirm="Delete this school?" title="Delete"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="bi bi-journal-bookmark"></i> Courses / Programs</h3></div>
    <div class="card-body">
      <form method="post" class="copy-row"><?= csrf_field() ?><input type="hidden" name="action" value="add_course"><input class="input input-sm" name="code" placeholder="Code (BSIT)" required maxlength="30" style="max-width:120px"><input class="input input-sm" name="name" placeholder="Course name" required maxlength="190"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add</button></form>
    </div>
    <div class="table-wrap" style="max-height:420px"><table class="table"><tbody>
      <?php if (!$courses) echo empty_row(3, 'No courses yet.'); ?>
      <?php foreach ($courses as $c): ?><tr><td><strong><?= e($c['code']) ?></strong><div class="cell-sub"><?= e($c['name']) ?></div></td><td><?= $c['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Hidden</span>' ?></td>
        <td class="text-right nowrap"><form method="post" class="flex" style="justify-content:flex-end;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="kind" value="course"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
          <button class="btn btn-ghost btn-sm" name="action" value="toggle"><i class="bi bi-eye<?= $c['is_active'] ? '-slash' : '' ?>"></i></button>
          <button class="btn btn-ghost btn-sm" name="action" value="delete" data-confirm="Delete this course?"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="bi bi-calendar3"></i> Academic Years</h3></div>
    <div class="card-body">
      <form method="post" class="copy-row"><?= csrf_field() ?><input type="hidden" name="action" value="add_year"><input class="input input-sm" name="name" placeholder="2027-2028" required pattern="\d{4}-\d{4}"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add</button></form>
    </div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($years as $y): ?><tr><td><?= e($y['name']) ?></td><td><?= $y['is_current'] ? '<span class="badge badge-blue">Current</span>' : '' ?></td>
        <td class="text-right nowrap"><form method="post" class="flex" style="justify-content:flex-end;gap:.3rem"><?= csrf_field() ?><input type="hidden" name="kind" value="year"><input type="hidden" name="id" value="<?= (int) $y['id'] ?>">
          <?php if (!$y['is_current']): ?><button class="btn btn-ghost btn-sm" name="action" value="current_year">Set current</button><?php endif; ?>
          <button class="btn btn-ghost btn-sm" name="action" value="delete" data-confirm="Delete this academic year?"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="bi bi-person-badge"></i> Participant Types</h3></div>
    <div class="card-body"><p class="small muted mb-0">Disabled types are hidden from every registration form. You can also allow/deny types per conference.</p></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($ptypes as $t): ?><tr><td><?= e($t['name']) ?></td><td><?= $t['is_active'] ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-muted">Disabled</span>' ?></td>
        <td class="text-right"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-outline btn-sm" name="action" value="toggle_type"><?= $t['is_active'] ? 'Disable' : 'Enable' ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
</div>

<?php else: ?>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-database-down"></i> Download Database Backup</h3></div>
    <div class="card-body">
      <p>Download a complete <code>.sql</code> backup of all conferences, participants, attendance records, admins and settings.</p>
      <ul class="small muted">
        <li><?= (int) $counts['conferences'] ?> conferences · <?= (int) $counts['registrations'] ?> registrations · <?= (int) $counts['students'] ?> participants · <?= (int) $counts['logs'] ?> log entries</li>
      </ul>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-primary"><i class="bi bi-download"></i> Download Backup (.sql)</button></form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-life-preserver"></i> Backup Procedure</h3></div>
    <div class="card-body small">
      <ol style="padding-left:1.1rem;margin:0">
        <li>Download a backup <strong>before and after every conference</strong>, and at least once a week during registration.</li>
        <li>Store backups in two places (e.g. a USB drive and a private cloud folder). They contain personal information – keep them private.</li>
        <li><strong>To restore locally:</strong> open MySQL Workbench → Server → Data Import → “Import from Self-Contained File”, choose the .sql file and the target schema.</li>
        <li><strong>To restore on InfinityFree:</strong> open phpMyAdmin from the control panel → select your database → Import → choose the .sql file.</li>
        <li>InfinityFree also lets you export from phpMyAdmin (Export → Quick → SQL) as an extra backup.</li>
      </ol>
    </div>
  </div>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
