<?php
require __DIR__ . '/_init.php';
$me = require_super();

$errors = [];
$editId = (int) get('edit');
$form = ['id' => 0, 'username' => '', 'full_name' => '', 'email' => '', 'role' => 'conference_admin', 'is_active' => 1, 'conferences' => []];
$allConfs = q_all('SELECT id, title, is_archived FROM conferences ORDER BY is_archived, start_datetime DESC');

if ($editId) {
    $row = q_row('SELECT * FROM admins WHERE id = ?', [$editId]);
    if ($row) {
        $form = $row;
        $form['conferences'] = array_map('intval', q('SELECT conference_id FROM admin_conferences WHERE admin_id = ?', [$editId])->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $id = (int) post('id');
        $form = [
            'id' => $id, 'username' => strtolower(post('username')), 'full_name' => post('full_name'), 'email' => post('email'),
            'role' => post('role') === 'super_admin' ? 'super_admin' : 'conference_admin',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'conferences' => array_map('intval', (array) ($_POST['conferences'] ?? [])),
        ];
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $form['username'])) $errors[] = 'Username must be 3–50 characters: letters, numbers, dot, dash or underscore.';
        elseif (q_val('SELECT id FROM admins WHERE username = ? AND id <> ?', [$form['username'], $id])) $errors[] = 'That username is already taken.';
        if ($form['full_name'] === '' || mb_strlen($form['full_name']) > 120) $errors[] = 'Full name is required.';
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!$id || $password !== '') $errors = array_merge($errors, password_problems($password));
        if ($id === (int) $me['id'] && ($form['role'] !== 'super_admin' || !$form['is_active'])) $errors[] = 'You cannot remove your own Super Admin role or deactivate your own account.';

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            if ($id) {
                q('UPDATE admins SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?',
                    [$form['username'], $form['full_name'], $form['email'] ?: null, $form['role'], $form['is_active'], $id]);
                if ($password !== '') {
                    q('UPDATE admins SET password_hash = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                log_activity('admin_update', 'Updated admin account @' . $form['username'] . ($password !== '' ? ' (password reset)' : ''));
            } else {
                q('INSERT INTO admins (username, full_name, email, password_hash, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)',
                    [$form['username'], $form['full_name'], $form['email'] ?: null, password_hash($password, PASSWORD_DEFAULT), $form['role'], $form['is_active']]);
                $id = (int) $pdo->lastInsertId();
                log_activity('admin_create', 'Created ' . str_replace('_', ' ', $form['role']) . ' account @' . $form['username']);
            }
            q('DELETE FROM admin_conferences WHERE admin_id = ?', [$id]);
            if ($form['role'] === 'conference_admin') {
                $valid = array_map('intval', array_column($allConfs, 'id'));
                foreach (array_intersect($form['conferences'], $valid) as $cid) {
                    q('INSERT INTO admin_conferences (admin_id, conference_id) VALUES (?, ?)', [$id, $cid]);
                }
            }
            $pdo->commit();
            flash('success', 'Admin account saved.' . ($password !== '' ? ' They will be asked to change the password at first login.' : ''));
            redirect('admin/admins.php');
        }
        $editId = $id;
    } elseif ($action === 'toggle' || $action === 'unlock') {
        $id = (int) post('id');
        $row = q_row('SELECT * FROM admins WHERE id = ?', [$id]);
        if ($row && $id !== (int) $me['id']) {
            if ($action === 'toggle') {
                q('UPDATE admins SET is_active = ? WHERE id = ?', [$row['is_active'] ? 0 : 1, $id]);
                log_activity('admin_toggle', ($row['is_active'] ? 'Deactivated' : 'Activated') . ' admin @' . $row['username']);
                flash('success', 'Account ' . ($row['is_active'] ? 'deactivated' : 'activated') . '.');
            } else {
                q('UPDATE admins SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [$id]);
                log_activity('admin_unlock', 'Unlocked admin @' . $row['username']);
                flash('success', 'Account unlocked.');
            }
        }
        redirect('admin/admins.php');
    }
}

$admins = q_all("SELECT a.*, (SELECT GROUP_CONCAT(c.title ORDER BY c.title SEPARATOR ', ') FROM admin_conferences ac JOIN conferences c ON c.id = ac.conference_id WHERE ac.admin_id = a.id) AS confs
                 FROM admins a ORDER BY a.role DESC, a.full_name");
$showForm = $editId || get('new') === '1' || $errors;

admin_header('Admin Accounts', 'admins', 'Administration');
?>
<div class="page-head">
  <div><h2>Admin Accounts</h2><p>Super Admins have full access. Conference Admins manage only their assigned conferences.</p></div>
  <?php if (!$showForm): ?><a class="btn btn-primary" href="?new=1"><i class="bi bi-person-plus"></i> New Admin</a><?php endif; ?>
</div>

<?php if ($showForm): ?>
<div class="card mb-3">
  <div class="card-header"><h3><?= $form['id'] ? 'Edit Admin: ' . e($form['full_name']) : 'Create Admin Account' ?></h3><a class="btn btn-ghost btn-sm" href="?">Cancel</a></div>
  <div class="card-body">
    <?php if ($errors): ?><div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><ul class="mt-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
    <form method="post" class="form-grid cols-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
      <div class="field"><label for="a-full">Full Name<span class="req">*</span></label><input class="input" id="a-full" name="full_name" value="<?= e($form['full_name']) ?>" required maxlength="120"></div>
      <div class="field"><label for="a-user">Username<span class="req">*</span></label><input class="input" id="a-user" name="username" value="<?= e($form['username']) ?>" required maxlength="50" autocomplete="off"></div>
      <div class="field"><label for="a-email">Email<span class="opt">(optional)</span></label><input class="input" type="email" id="a-email" name="email" value="<?= e($form['email']) ?>" maxlength="150"></div>
      <div class="field"><label for="a-role">Role</label><select class="input" id="a-role" name="role" onchange="document.getElementById('conf-assign').classList.toggle('hidden', this.value!=='conference_admin')"><option value="conference_admin" <?= $form['role'] === 'conference_admin' ? 'selected' : '' ?>>Conference Admin</option><option value="super_admin" <?= $form['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option></select></div>
      <div class="field"><label for="a-pass"><?= $form['id'] ? 'Reset Password' : 'Temporary Password' ?><?= $form['id'] ? '<span class="opt">(leave blank to keep)</span>' : '<span class="req">*</span>' ?></label><input class="input" type="password" id="a-pass" name="password" autocomplete="new-password" <?= $form['id'] ? '' : 'required' ?>><div class="help">8+ characters with upper &amp; lower case letters and a number. The admin must change it at first login.</div></div>
      <div class="field" style="justify-content:center"><label class="check"><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Account active</label></div>
      <div class="field full <?= $form['role'] === 'conference_admin' ? '' : 'hidden' ?>" id="conf-assign">
        <span class="label">Assigned Conferences</span>
        <div class="grid-auto" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.4rem">
          <?php if (!$allConfs): ?><span class="small muted">No conferences yet.</span><?php endif; ?>
          <?php foreach ($allConfs as $c): ?><label class="check"><input type="checkbox" name="conferences[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $form['conferences'], true) ? 'checked' : '' ?>> <?= e($c['title']) ?><?= $c['is_archived'] ? ' <span class="muted small">(archived)</span>' : '' ?></label><?php endforeach; ?>
        </div>
      </div>
      <div class="full"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Account</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Role</th><th>Assigned Conferences</th><th>Status</th><th class="hide-sm">Last Login</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): $locked = $a['locked_until'] && strtotime($a['locked_until']) > time(); ?>
        <tr>
          <td><span class="cell-title"><?= e($a['full_name']) ?></span><?= (int) $a['id'] === (int) $me['id'] ? ' <span class="badge badge-blue">You</span>' : '' ?><div class="cell-sub">@<?= e($a['username']) ?><?= $a['email'] ? ' · ' . e($a['email']) : '' ?></div></td>
          <td><?= $a['role'] === 'super_admin' ? '<span class="badge badge-navy"><i class="bi bi-shield-fill-check"></i> Super Admin</span>' : '<span class="badge badge-blue">Conference Admin</span>' ?></td>
          <td class="small"><?= $a['role'] === 'super_admin' ? '<span class="muted">All conferences</span>' : e($a['confs'] ?: '—') ?></td>
          <td><?= $a['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?><?= $locked ? ' <span class="badge badge-danger">Locked</span>' : '' ?></td>
          <td class="hide-sm small"><?= e($a['last_login_at'] ? fmt_datetime($a['last_login_at']) : 'Never') ?></td>
          <td class="text-right">
            <div class="flex" style="justify-content:flex-end;gap:.3rem">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $a['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
              <?php if ((int) $a['id'] !== (int) $me['id']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <?php if ($locked): ?><button class="btn btn-outline btn-sm" name="action" value="unlock"><i class="bi bi-unlock"></i></button><?php endif; ?>
                  <button class="btn btn-outline btn-sm" name="action" value="toggle" data-confirm="<?= $a['is_active'] ? 'Deactivate' : 'Activate' ?> this account?"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_footer(); ?>
