<?php
/**
 * Character sheet for the student mascot: turnaround views, expressions
 * and animation states (all rendered live by assets/js/mascot.js).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/public_layout.php';

$views = ['front' => 'Front', 'q34' => '3/4', 'right' => 'Right', 'left' => 'Left', 'back' => 'Back'];
$expressions = ['happy' => 'Happy', 'excited' => 'Excited', 'surprised' => 'Surprised', 'confused' => 'Confused',
                'thinking' => 'Thinking', 'wink' => 'Wink', 'sad' => 'Sad', 'sleepy' => 'Sleepy'];
$animations = ['idle' => 'Idle & breathing', 'walk' => 'Walk cycle', 'run' => 'Run cycle', 'jump' => 'Jumping',
               'wave' => 'Waving', 'think' => 'Thinking', 'study' => 'Studying', 'idea' => 'Got an idea!',
               'dance' => 'Dancing', 'flip' => 'Backflip', 'turnaround' => 'Turnaround', 'sit' => 'Sitting'];

public_header('Meet our mascot');
?>
<section class="card mb-3">
  <div class="card-body sheet-hero">
    <div data-mascot="wave" data-size="150" data-say="Tap me! 👆"></div>
    <div>
      <h1 class="mb-1">Meet our mascot</h1>
      <p class="muted mb-1">Your friendly registration buddy, with a blue graduation cap and golden tassel, a purple hoodie, and a stack of books to study on.</p>
      <p class="muted mb-0"><strong>Try it:</strong> move your mouse over the mascot and it will look at you. Click or tap it for a surprise reaction.</p>
    </div>
  </div>
</section>

<h2 class="mb-2">Character turnaround</h2>
<div class="sheet-grid mb-3">
  <?php foreach ($views as $k => $label): ?>
    <div class="sheet-card"><div class="sheet-stage"><div data-mascot="sheet" data-view="<?= $k ?>"></div></div><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>

<h2 class="mb-2">Expressions</h2>
<div class="sheet-grid mb-3">
  <?php foreach ($expressions as $k => $label): ?>
    <div class="sheet-card"><div class="sheet-stage"><div data-mascot="sheet" data-expr="<?= $k ?>"></div></div><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>

<h2 class="mb-2">Animations</h2>
<div class="sheet-grid">
  <?php foreach ($animations as $k => $label): ?>
    <div class="sheet-card"><div class="sheet-stage"><div data-mascot="sheet" data-anim="<?= $k ?>" data-interactive></div></div><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>
<?php public_footer(); ?>
