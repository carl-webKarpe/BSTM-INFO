<?php
/**
 * Public home page. Registration itself is only reachable through an
 * event QR code / link (register.php?t=...).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

// Upcoming / ongoing events (registration still happens only via the QR code)
$events = q_all("SELECT * FROM conferences
                 WHERE is_archived = 0 AND COALESCE(end_datetime, start_datetime) >= CURDATE()
                 ORDER BY start_datetime ASC LIMIT 6");
$courses = lookup_courses();
$stats = q_row("SELECT (SELECT COUNT(*) FROM conference_registrations WHERE status <> 'cancelled') AS regs,
                       (SELECT COUNT(*) FROM conferences WHERE is_archived = 0) AS events");

public_header('Welcome', ['wide' => true]);
?>
<!-- ============================== HERO ============================== -->
<section class="l-hero">
  <div class="l-blob b1"></div><div class="l-blob b2"></div><div class="l-blob b3"></div>
  <div class="l-wrap l-hero-grid">
    <div class="l-hero-text">
      <span class="l-pill"><span class="pulse"></span> Registration is quick &amp; paperless</span>
      <h1>Scan. Fill. <span class="grad">You're in!</span></h1>
      <p>Register for school events, seminars and conferences in under 2 minutes, straight from your phone. No forms to print, no long lines.</p>
      <div class="l-cta">
        <a class="btn l-btn-light btn-lg" href="#how"><i class="bi bi-qr-code-scan"></i> How it works</a>
        <a class="btn l-btn-ghost btn-lg" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a>
      </div>
      <div class="l-trust">
        <div><strong><?= number_format((int) $stats['events']) ?></strong><span>Active events</span></div>
        <div><strong><?= number_format((int) $stats['regs']) ?></strong><span>Students registered</span></div>
        <div><strong>2 min</strong><span>To register</span></div>
      </div>
    </div>

    <div class="l-phone-wrap" aria-hidden="true">
      <div class="l-phone">
        <div class="l-notch"></div>
        <div class="l-screen">
          <div class="l-screen-head">
            <span class="l-check"><i class="bi bi-check-lg"></i></span>
            <strong>Registration Successful!</strong>
            <small>REG-26-K7PQ2M</small>
          </div>
          <div class="l-qr"><canvas data-qr="<?= e(APP_NAME) ?>" data-size="150" data-margin="6"></canvas></div>
          <div class="l-screen-lines"><span></span><span></span><span class="short"></span></div>
        </div>
      </div>
      <div class="l-float f1"><i class="bi bi-lightning-charge-fill"></i> Instant confirmation</div>
      <div class="l-float f2"><i class="bi bi-shield-check"></i> Private &amp; secure</div>
    </div>
  </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="l-section" id="how">
  <div class="l-wrap">
    <div class="l-head">
      <span class="l-eyebrow">How it works</span>
      <h2>Register in 4 easy steps</h2>
    </div>
    <div class="l-steps">
      <div class="l-step c1"><span class="l-num">1</span><div class="l-ic"><i class="bi bi-qr-code-scan"></i></div><h3>Scan the QR</h3><p>Point your phone camera at the event QR code on the poster or screen.</p></div>
      <div class="l-step c2"><span class="l-num">2</span><div class="l-ic"><i class="bi bi-pencil-square"></i></div><h3>Fill out the form</h3><p>Enter your Student ID, name, course, year level and address.</p></div>
      <div class="l-step c3"><span class="l-num">3</span><div class="l-ic"><i class="bi bi-eye"></i></div><h3>Review &amp; submit</h3><p>Double-check your details, then tap <em>Confirm &amp; Submit</em>.</p></div>
      <div class="l-step c4"><span class="l-num">4</span><div class="l-ic"><i class="bi bi-ticket-perforated"></i></div><h3>Get your pass</h3><p>Save your reference number and attendance QR. Show it on event day!</p></div>
    </div>
  </div>
</section>

<!-- ============================== EVENTS ============================== -->
<?php if ($events): ?>
<section class="l-section l-alt">
  <div class="l-wrap">
    <div class="l-head">
      <span class="l-eyebrow">Happening soon</span>
      <h2>Upcoming events</h2>
      <p>To register, scan the event's QR code at the venue or on the official announcement.</p>
    </div>
    <div class="l-events">
      <?php foreach ($events as $ev):
          $st = conference_state($ev);
          $ts = strtotime($ev['start_datetime']); ?>
        <article class="l-event">
          <div class="l-date"><span><?= date('M', $ts) ?></span><strong><?= date('j', $ts) ?></strong><small><?= date('D', $ts) ?></small></div>
          <div class="l-event-body">
            <h3><?= e($ev['title']) ?></h3>
            <p><i class="bi bi-clock"></i> <?= e(date('g:i A', $ts)) ?><?= $ev['end_datetime'] ? ' – ' . e(date('g:i A', strtotime($ev['end_datetime']))) : '' ?></p>
            <p><i class="bi bi-geo-alt"></i> <?= e($ev['venue']) ?></p>
            <span class="badge badge-<?= $st['open'] ? 'success' : ($st['tone'] === 'warning' ? 'warning' : 'muted') ?>"><span class="dot"></span> Registration <?= e($st['label']) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================== FEATURES + LOOKUP ============================== -->
<section class="l-section">
  <div class="l-wrap l-split">
    <div>
      <span class="l-eyebrow">Why students love it</span>
      <h2>Made for your phone</h2>
      <ul class="l-features">
        <li><span class="fi f-blue"><i class="bi bi-phone"></i></span><div><strong>Mobile-first</strong><p>Big buttons, simple dropdowns. Your address is picked from a list, not typed.</p></div></li>
        <li><span class="fi f-green"><i class="bi bi-patch-check"></i></span><div><strong>Instantly confirmed</strong><p>No waiting for approval. You're registered the moment you submit.</p></div></li>
        <li><span class="fi f-purple"><i class="bi bi-qr-code"></i></span><div><strong>Fast check-in</strong><p>Your personal attendance QR gets you in at the door in seconds.</p></div></li>
        <li><span class="fi f-orange"><i class="bi bi-lock"></i></span><div><strong>Your data is safe</strong><p>Used only for event registration and attendance, under the Data Privacy Act.</p></div></li>
      </ul>
      <?php if ($courses): ?>
        <div class="l-courses">
          <span>Open to students of</span>
          <?php foreach ($courses as $c): ?><b><?= e($c['code']) ?></b><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="l-lookup">
      <div class="l-lookup-ic"><i class="bi bi-ticket-detailed"></i></div>
      <h3>Already registered?</h3>
      <p>Lost your confirmation? Get your reference number and attendance QR code back anytime.</p>
      <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('lookup.php')) ?>"><i class="bi bi-search"></i> Find my registration</a>
      <div class="l-lookup-note"><i class="bi bi-info-circle"></i> You'll need your reference number and Student ID.</div>
    </div>
  </div>
</section>

<div class="l-organizer"><a href="<?= e(url('admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> Organizer login</a></div>
<?php public_footer(['vendor/qrcode.js']); ?>
