<?php
/**
 * Public home page. Registration itself is only reachable through a
 * conference QR code / link (register.php?t=...).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

public_header('Welcome');
?>
<section class="hero">
  <div class="eyebrow">Welcome</div>
  <h1><?= e(APP_NAME) ?></h1>
  <p>Register for school conferences, seminars and academic events in just a few taps.</p>
</section>

<div class="grid-2">
  <div class="card">
    <div class="card-body">
      <div class="big-icon info" style="width:56px;height:56px;font-size:1.6rem;margin:0 0 .8rem"><i class="bi bi-qr-code-scan"></i></div>
      <h2>How to register</h2>
      <ol class="muted" style="padding-left:1.1rem;margin:0">
        <li>Scan the conference QR code with your phone camera.</li>
        <li>Fill out your personal and academic information.</li>
        <li>Review your details, then submit.</li>
        <li>Save your confirmation and attendance QR code.</li>
      </ol>
    </div>
  </div>
  <div class="card">
    <div class="card-body">
      <div class="big-icon success" style="width:56px;height:56px;font-size:1.6rem;margin:0 0 .8rem"><i class="bi bi-search"></i></div>
      <h2>Already registered?</h2>
      <p class="muted">View or print your registration confirmation and attendance QR code.</p>
      <a class="btn btn-primary" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a>
    </div>
  </div>
</div>

<p class="text-center small mt-3"><a href="<?= e(url('admin/login.php')) ?>" class="muted"><i class="bi bi-shield-lock"></i> Organizer login</a></p>
<?php public_footer(); ?>
