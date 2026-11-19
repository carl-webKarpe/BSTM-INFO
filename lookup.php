<?php
/**
 * "Find my registration" – participants re-open their own confirmation
 * using their reference number + ID number (or contact number for guests).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$error = '';
$ref = strtoupper(post('ref'));
$ident = post('identifier');

if (is_post()) {
    verify_csrf();
    // Simple brute-force protection: max 10 lookups per 10 minutes per session.
    $now = time();
    $_SESSION['lookup_attempts'] = array_filter($_SESSION['lookup_attempts'] ?? [], fn($t) => $t > $now - 600);
    if (count($_SESSION['lookup_attempts']) >= 10) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $_SESSION['lookup_attempts'][] = $now;
        $reg = q_row('SELECT r.reference_no, r.access_key, s.id_number, s.contact_number
                      FROM conference_registrations r JOIN students s ON s.id = r.student_id
                      WHERE r.reference_no = ? AND r.is_archived = 0', [$ref]);
        $ok = false;
        if ($reg && $ident !== '') {
            $contact = normalize_contact($ident);
            $ok = ($reg['id_number'] !== null && strcasecmp($reg['id_number'], $ident) === 0)
                || ($contact !== null && $contact === $reg['contact_number']);
        }
        if ($ok) {
            redirect('confirmation.php?ref=' . urlencode($reg['reference_no']) . '&key=' . $reg['access_key']);
        }
        $error = 'No registration matches those details. Please check your reference number and ID / contact number.';
    }
}

public_header('Find my registration');
?>
<section class="hero">
  <div class="eyebrow"><i class="bi bi-search"></i> Registration Lookup</div>
  <h1>Find my registration</h1>
  <p>Enter the reference number you received after registering to view or print your confirmation.</p>
</section>

<div class="card" style="max-width:520px;margin:0 auto">
  <div class="card-body">
    <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-octagon-fill"></i><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post" class="form-grid" style="grid-template-columns:1fr">
      <?= csrf_field() ?>
      <div class="field">
        <label for="ref">Reference Number<span class="req">*</span></label>
        <input class="input" id="ref" name="ref" value="<?= e($ref) ?>" placeholder="REG-26-XXXXXX" required autocapitalize="characters" autocomplete="off">
      </div>
      <div class="field">
        <label for="identifier">Student / Employee ID Number <span class="muted">or</span> Contact Number<span class="req">*</span></label>
        <input class="input" id="identifier" name="identifier" value="<?= e($ident) ?>" required autocomplete="off">
        <div class="help">Guests without an ID number can use the contact number they registered with.</div>
      </div>
      <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-search"></i> Find Registration</button>
    </form>
  </div>
</div>
<?php public_footer(); ?>
