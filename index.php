<?php
/**
 * Public home page. Registration itself is only reachable through an
 * event QR code / link (register.php?t=...).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

public_header('Welcome', ['wide' => true, 'loader' => true]);
?>
<section class="l-hero">
  <!-- animated background (decorative only) -->
  <div class="hero-bg" aria-hidden="true">
    <span class="orb o1"></span><span class="orb o2"></span><span class="orb o3"></span>
    <div class="hero-icons">
      <i class="bi bi-qr-code fl i1"></i>
      <i class="bi bi-mortarboard fl i2"></i>
      <i class="bi bi-calendar-event fl i3"></i>
      <i class="bi bi-person-check fl i4"></i>
      <i class="bi bi-qr-code-scan fl i5"></i>
      <i class="bi bi-ticket-perforated fl i6"></i>
    </div>
  </div>
  <!-- mascot: sits on the books, studies, runs, waves, does something funny, then sits again (assets/js/mascot.js) -->
  <div class="mascot-stage" data-mascot="hero"></div>
  <div class="l-wrap">
    <h1>Student Registration</h1>
    <p>Register for school events in under 2 minutes, right from your phone.</p>
    <div class="l-cta">
      <a class="btn l-btn-light btn-lg" href="#how"><i class="bi bi-qr-code-scan"></i> How to register</a>
      <a class="btn l-btn-ghost btn-lg" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a>
    </div>
  </div>
</section>

<section class="l-section" id="how">
  <div class="l-wrap">
    <h2 class="l-title">How to register</h2>
    <div class="l-steps">
      <div class="l-step"><div class="l-ic"><i class="bi bi-qr-code-scan"></i></div><h3>1. Scan the QR</h3><p>Use your phone camera to scan the event QR code.</p></div>
      <div class="l-step"><div class="l-ic"><i class="bi bi-pencil-square"></i></div><h3>2. Fill out the form</h3><p>Enter your Student ID, name, course, year level and address.</p></div>
      <div class="l-step"><div class="l-ic"><i class="bi bi-check2-circle"></i></div><h3>3. Review &amp; submit</h3><p>Check your details, then tap Confirm &amp; Submit.</p></div>
      <div class="l-step"><div class="l-ic"><i class="bi bi-ticket-perforated"></i></div><h3>4. Save your pass</h3><p>Keep your reference number and attendance QR for event day.</p></div>
    </div>

    <div class="l-lookup">
      <div>
        <h3>Already registered?</h3>
        <p>View or print your confirmation and attendance QR code.</p>
      </div>
      <a class="btn btn-primary btn-lg" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a>
    </div>

    <div class="l-organizer"><a href="<?= e(url('mascot.php')) ?>"><i class="bi bi-emoji-smile"></i> Meet our mascot</a> · <a href="<?= e(url('admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> Organizer login</a></div>
  </div>
</section>
<?php public_footer(); ?>
