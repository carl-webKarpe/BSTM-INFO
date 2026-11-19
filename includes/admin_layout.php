<?php
/** Admin dashboard layout: sidebar + top bar. */

function admin_nav(): array
{
    return [
        ['index.php',         'dashboard',    'speedometer2',     'Dashboard',             false],
        ['conferences.php',   'conferences',  'calendar-event',   'Conference Management', false],
        ['participants.php',  'participants', 'people',           'Participant Records',   false],
        ['qrcodes.php',       'qrcodes',      'qr-code',          'QR Code Management',    false],
        ['attendance.php',    'attendance',   'person-check',     'Attendance Monitoring', false],
        ['reports.php',       'reports',      'bar-chart-line',   'Reports & Analytics',   false],
        ['logs.php',          'logs',         'clock-history',    'Activity Logs',         false],
        ['admins.php',        'admins',       'shield-lock',      'Admin Accounts',        true],
        ['settings.php',      'settings',     'gear',             'System Settings',       true],
    ];
}

function admin_header(string $title, string $active, string $crumb = ''): void
{
    $admin  = current_admin();
    $system = setting('system_name', 'Conference Registration');
    $logo   = logo_url();
    $initials = strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($admin['full_name'])), 0, 2))));
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Admin · <?= e($system) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="admin-body" data-org="<?= e(setting('institution_name', '')) ?>">
<aside class="sidebar" aria-label="Main navigation">
  <div class="sidebar-brand">
    <span class="brand-logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><i class="bi bi-mortarboard-fill"></i><?php endif; ?></span>
    <div><?= e($system) ?><small>Admin Dashboard</small></div>
  </div>
  <nav>
    <div class="nav-label">Main Menu</div>
    <?php foreach (admin_nav() as [$file, $key, $icon, $label, $superOnly]):
        if ($superOnly && $admin['role'] !== 'super_admin') continue;
        if ($key === 'admins') echo '<div class="nav-label">Administration</div>'; ?>
      <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url('admin/' . $file)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
        <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
    <div class="nav-label">Account</div>
    <a class="nav-link <?= $active === 'password' ? 'active' : '' ?>" href="<?= e(url('admin/change_password.php')) ?>"><i class="bi bi-key"></i><span>Change Password</span></a>
    <a class="nav-link logout" href="<?= e(url('admin/logout.php?_csrf=' . csrf_token())) ?>"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
  </nav>
  <div class="sidebar-user">
    <span class="avatar"><?= e($initials ?: 'A') ?></span>
    <div>
      <div class="name"><?= e($admin['full_name']) ?></div>
      <div class="role"><?= $admin['role'] === 'super_admin' ? 'Super Admin' : 'Conference Admin' ?></div>
    </div>
  </div>
</aside>
<div class="backdrop"></div>
<div class="main">
  <header class="topbar">
    <button class="btn btn-ghost btn-icon menu-toggle" aria-label="Open menu"><i class="bi bi-list" style="font-size:1.3rem"></i></button>
    <div class="grow">
      <div class="crumbs">Admin<?= $crumb ? ' / ' . e($crumb) : '' ?></div>
      <h1><?= e($title) ?></h1>
    </div>
    <a class="btn btn-outline btn-sm hide-sm" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Student Portal</a>
  </header>
  <div class="content">
    <?= render_flashes() ?>
<?php
}

function admin_footer(array $scripts = [], string $inline = ''): void
{
    ?>
  </div>
</div>
<?php foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if ($inline): ?><script><?= $inline ?></script><?php endif; ?>
</body>
</html>
<?php
}

/** Standard "empty state" table row. */
function empty_row(int $cols, string $msg, string $icon = 'inbox'): string
{
    return '<tr><td colspan="' . $cols . '" class="table-empty"><i class="bi bi-' . $icon . '"></i>' . e($msg) . '</td></tr>';
}
