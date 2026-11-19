<?php
/** Header / footer for the public (student) portal. */

function public_header(string $title, array $opts = []): void
{
    $system = setting('system_name', 'Conference Registration');
    $inst   = setting('institution_name', '');
    $logo   = logo_url();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#172554">
<title><?= e($title) ?> · <?= e($system) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="public-body" data-org="<?= e($inst) ?>">
<header class="public-top">
  <div class="inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-logo"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><i class="bi bi-mortarboard-fill"></i><?php endif; ?></span>
      <span><?= e($inst ?: $system) ?><small><?= e($system) ?></small></span>
    </a>
  </div>
</header>
<main class="public-main">
<?= render_flashes() ?>
<?php
}

function public_footer(array $scripts = []): void
{
    ?>
</main>
<footer class="public-footer">
  &copy; <?= date('Y') ?> <?= e(setting('institution_name', '')) ?> · <?= e(setting('system_name', '')) ?>
  · Your information is kept private and used only for conference registration.
</footer>
<?php foreach ($scripts as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
<?php
}

/** Full-page message (invalid link, closed registration...). */
function public_message(string $title, string $message, string $tone = 'info', string $icon = 'info-circle', ?array $conference = null): void
{
    public_header($title);
    ?>
  <div class="card status-screen">
    <div class="big-icon <?= e($tone) ?>"><i class="bi bi-<?= e($icon) ?>"></i></div>
    <h1><?= e($title) ?></h1>
    <p class="muted" style="max-width:480px;margin:0 auto 1rem"><?= e($message) ?></p>
    <?php if ($conference): ?>
      <div class="card" style="max-width:520px;margin:1.25rem auto 0;text-align:left">
        <div class="card-body">
          <dl class="info-list cols-1">
            <div><dt>Conference</dt><dd><?= e($conference['title']) ?></dd></div>
            <div><dt>Date &amp; Time</dt><dd><?= e(fmt_conf_schedule($conference)) ?></dd></div>
            <div><dt>Venue</dt><dd><?= e($conference['venue']) ?></dd></div>
          </dl>
        </div>
      </div>
    <?php endif; ?>
    <p class="mt-3"><a class="btn btn-outline" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a></p>
  </div>
<?php
    public_footer();
    exit;
}
