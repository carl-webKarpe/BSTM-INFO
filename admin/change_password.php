<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

$errors = [];
if (is_post()) {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $admin['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    }
    $errors = array_merge($errors, password_problems($new));
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if ($new === $current) {
        $errors[] = 'The new password must be different from the current one.';
    }
    if (!$errors) {
        q('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        log_activity('password_change', 'Changed own password');
        flash('success', 'Your password has been changed.');
        redirect('admin/index.php');
    }
}

admin_header('Change Password', 'password', 'Account');
?>
<div class="card" style="max-width:520px">
  <div class="card-header"><h2><i class="bi bi-key"></i> Change Password</h2></div>
  <div class="card-body">
    <?php if ((int) $admin['must_change_password'] === 1): ?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>You are using a temporary password. Please set a new one to continue.</div></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><ul class="mt-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>
    <form method="post" class="form-grid" style="grid-template-columns:1fr">
      <?= csrf_field() ?>
      <div class="field"><label for="cp">Current Password</label><input class="input" type="password" id="cp" name="current_password" required autocomplete="current-password"></div>
      <div class="field"><label for="np">New Password</label><input class="input" type="password" id="np" name="new_password" required minlength="8" autocomplete="new-password">
        <div class="help">At least 8 characters with an uppercase letter, a lowercase letter and a number.</div></div>
      <div class="field"><label for="cf">Confirm New Password</label><input class="input" type="password" id="cf" name="confirm_password" required autocomplete="new-password"></div>
      <div><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Password</button></div>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
