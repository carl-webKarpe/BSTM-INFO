<?php
require __DIR__ . '/_init.php';

if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
$username = post('username');

if (is_post()) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $admin = $username !== '' ? q_row('SELECT * FROM admins WHERE username = ?', [$username]) : null;

    if ($admin && $admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
        $mins = (int) ceil((strtotime($admin['locked_until']) - time()) / 60);
        $error = "Too many failed attempts. This account is locked for {$mins} more minute(s).";
    } elseif ($admin && (int) $admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }
        login_admin($admin);
        log_activity('login', 'Logged in');
        $next = (string) ($_GET['next'] ?? '');
        // only allow redirects inside this app
        if ($next !== '' && strpos($next, app_base_path() . '/admin/') === 0 && strpos($next, '//') === false) {
            redirect($next);
        }
        redirect('admin/index.php');
    } else {
        if ($admin) {
            $fails = (int) $admin['failed_attempts'] + 1;
            $lock  = $fails >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60) : null;
            q('UPDATE admins SET failed_attempts = ?, locked_until = ? WHERE id = ?', [$lock ? 0 : $fails, $lock, $admin['id']]);
            if ($lock) {
                log_activity('login_locked', 'Account "' . $admin['username'] . '" locked after too many failed logins');
            }
        }
        usleep(400000); // slow down guessing
        $error = 'Invalid username or password.';
    }
}
$system = APP_NAME;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login · <?= e($system) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-art">
    <div class="brand" style="color:#fff;font-size:1.1rem"><?= e($system) ?></div>
    <div>
      <h1>Conference Registration &amp; Information Management</h1>
      <p>Manage conferences, generate registration QR codes, monitor participants and track attendance – all in one place.</p>
      <div class="mt-3">
        <div class="feature"><i class="bi bi-qr-code"></i> QR code registration for every conference</div>
        <div class="feature"><i class="bi bi-people"></i> Real-time participant records</div>
        <div class="feature"><i class="bi bi-person-check"></i> Fast attendance check-in</div>
        <div class="feature"><i class="bi bi-bar-chart-line"></i> Reports exportable to PDF &amp; Excel</div>
      </div>
    </div>
    <small style="color:#93A3D9">&copy; <?= date('Y') ?> <?= e($system) ?></small>
  </div>
  <div class="login-form">
    <div class="box">
      <h2 style="font-size:1.5rem">Admin Login</h2>
      <p class="muted">Sign in to the organizer dashboard.</p>
      <?= render_flashes() ?>
      <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><?= e($error) ?></div></div><?php endif; ?>
      <form method="post" class="form-grid" style="grid-template-columns:1fr" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
      </form>
      <p class="small muted mt-3"><a href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Back to student portal</a></p>
    </div>
  </div>
</div>
</body>
</html>
