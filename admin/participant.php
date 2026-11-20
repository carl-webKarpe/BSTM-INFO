<?php
require __DIR__ . '/_init.php';
require_admin();

$id = (int) get('id');
$r = q_row(registration_select_sql() . ' WHERE r.id = ?', [$id]);
if (!$r || !can_access_conference((int) $r['conference_id'])) {
    flash('error', 'Participant record not found.');
    redirect('admin/participants.php');
}
$updatedBy = $r['status_updated_by'] ? q_val('SELECT full_name FROM admins WHERE id = ?', [$r['status_updated_by']]) : null;
$checkedBy = $r['checked_in_at'] ? q_val('SELECT a.full_name FROM attendance_records ar LEFT JOIN admins a ON a.id = ar.checked_in_by WHERE ar.registration_id = ?', [$id]) : null;
$qrLabel   = $r['qr_code_id'] ? q_val('SELECT label FROM qr_codes WHERE id = ?', [$r['qr_code_id']]) : null;
$others    = q_all('SELECT r.id, r.reference_no, r.status, c.title, c.start_datetime FROM conference_registrations r JOIN conferences c ON c.id = r.conference_id
                    WHERE r.student_id = ? AND r.id <> ?' . conf_scope('r.conference_id') . ' ORDER BY c.start_datetime DESC', [$r['student_id'], $id]);
$confirmUrl = absolute_url('confirmation.php?ref=' . urlencode($r['reference_no']) . '&key=' . $r['access_key']);
$isGuest = $r['type_code'] === 'guest';

$sections = [
    'Personal Information' => [
        'Full Name' => full_name($r),
        'Last Name' => $r['last_name'],
        'First Name' => $r['first_name'],
        'Middle Name' => $r['middle_name'] ?: '—',
        ($r['type_code'] === 'faculty' ? 'Employee' : 'Student') . ' ID Number' => $r['id_number'] ?: '—',
        'Participant Type' => $r['type_name'],
    ],
    'Contact Information' => [
        'Contact Number' => $r['contact_number'],
        'Address' => $r['address'],
    ],
    'Academic Information' => array_filter([
        ($r['type_code'] === 'faculty' ? 'Department / Program' : 'Course / Program') => $isGuest ? null : course_label($r),
        'Year Level' => $r['type_code'] === 'student' ? ($r['year_level'] ?: '—') : null,
        'Section' => $r['type_code'] === 'student' ? ($r['section'] ?: '—') : null,
    ], fn($v) => $v !== null),
    'Conference Information' => [
        'Conference' => $r['conference_title'],
        'Date & Time' => fmt_conf_schedule(['start_datetime' => $r['conference_start'], 'end_datetime' => $r['conference_end']]),
        'Venue' => $r['conference_venue'],
        'Attendance Category' => $r['attendance_category'] ?: '—',
        'Special Requirements' => $r['special_requirements'] ?: 'None',
    ],
    'Registration Details' => [
        'Reference Number' => $r['reference_no'],
        'Registered On' => fmt_datetime($r['registered_at']),
        'Registered Via' => $qrLabel ?: '—',
        'Record Archived' => $r['is_archived'] ? 'Yes' : 'No',
    ],
    'Registration Status' => [
        'Status' => ucfirst($r['status']),
        'Status Updated' => $r['status_updated_at'] ? fmt_datetime($r['status_updated_at']) . ($updatedBy ? ' by ' . $updatedBy : '') : '—',
        'Attendance' => $r['checked_in_at'] ? 'Present – checked in ' . fmt_datetime($r['checked_in_at']) . ($checkedBy ? ' by ' . $checkedBy : '') . ' (' . ($r['checkin_method'] === 'qr' ? 'QR scan' : 'manual') . ')' : 'Not yet checked in',
    ],
];
$icons = ['person-vcard', 'telephone', 'mortarboard', 'calendar-event', 'receipt', 'patch-check'];
$exportRows = [];
foreach ($sections as $sec => $fields) {
    foreach ($fields as $k => $v) {
        $exportRows[] = [$sec, $k, (string) $v];
    }
}
$initials = mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1);

admin_header('Participant Profile', 'participants', 'Participant Records');
?>
<div class="print-only" style="margin-bottom:10px"><strong><?= e(APP_NAME) ?></strong> — Participant Information Sheet</div>

<div class="card mb-2">
  <div class="card-body flex-between">
    <div class="profile-head">
      <span class="avatar"><?= e(strtoupper($initials)) ?></span>
      <div>
        <h2><?= e(full_name($r)) ?></h2>
        <div class="flex mt-1" style="gap:.4rem">
          <?= type_badge($r['type_name']) ?> <?= status_badge($r['status']) ?>
          <?= $r['checked_in_at'] ? '<span class="badge badge-blue"><i class="bi bi-person-check-fill"></i> Present</span>' : '<span class="badge badge-muted">Not yet checked in</span>' ?>
          <?= $r['is_archived'] ? '<span class="badge badge-muted"><i class="bi bi-archive"></i> Archived</span>' : '' ?>
          <span class="small muted"><?= e($r['reference_no']) ?></span>
        </div>
      </div>
    </div>
    <div class="toolbar no-print">
      <a class="btn btn-outline btn-sm" href="<?= e(url('admin/participant_edit.php?id=' . $id)) ?>"><i class="bi bi-pencil"></i> Edit</a>
      <form id="act-form" method="post" action="<?= e(url('admin/participant_action.php')) ?>" class="flex" style="gap:.5rem">
        <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
        <?php if ($r['status'] !== 'confirmed'): ?><button class="btn btn-success btn-sm" name="single" value="confirm:<?= $id ?>"><i class="bi bi-check-circle"></i> Confirm</button><?php endif; ?>
        <?php if ($r['status'] !== 'pending'): ?><button class="btn btn-outline btn-sm" name="single" value="pending:<?= $id ?>"><i class="bi bi-hourglass-split"></i> Mark Pending</button><?php endif; ?>
        <?php if ($r['status'] !== 'cancelled'): ?><button class="btn btn-outline btn-sm" name="single" value="cancel:<?= $id ?>" data-confirm="Cancel this registration?" style="color:var(--danger)"><i class="bi bi-x-circle"></i> Cancel</button><?php endif; ?>
        <button class="btn btn-outline btn-sm" name="single" value="<?= $r['is_archived'] ? 'restore' : 'archive' ?>:<?= $id ?>" <?= $r['is_archived'] ? '' : 'data-confirm="Archive this participant record?"' ?>><i class="bi bi-archive"></i> <?= $r['is_archived'] ? 'Restore' : 'Archive' ?></button>
      </form>
      <div class="dropdown">
        <button class="btn btn-primary btn-sm" data-dropdown><i class="bi bi-download"></i> Print / Export</button>
        <div class="dropdown-menu">
          <button type="button" data-print><i class="bi bi-printer"></i> Print</button>
          <button type="button" id="exp-pdf"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
          <button type="button" id="exp-xlsx"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="grid-2">
  <?php $i = 0; foreach ($sections as $title => $fields): ?>
    <div class="card">
      <div class="card-header"><h3><i class="bi bi-<?= $icons[$i++] ?>"></i> <?= e($title) ?></h3></div>
      <div class="card-body">
        <dl class="info-list">
          <?php foreach ($fields as $k => $v): ?>
            <div class="<?= mb_strlen((string) $v) > 40 ? 'full' : '' ?>"><dt><?= e($k) ?></dt><dd><?= $k === 'Status' ? status_badge($r['status']) : e($v) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-2 mt-3 no-print">
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-link-45deg"></i> Participant's Confirmation Page</h3></div>
    <div class="card-body">
      <p class="small muted">Send this private link to the participant if they lost their confirmation / attendance QR code.</p>
      <div class="copy-row"><input class="input input-sm" value="<?= e($confirmUrl) ?>" readonly onfocus="this.select()"><button class="btn btn-outline btn-sm" data-copy="<?= e($confirmUrl) ?>"><i class="bi bi-clipboard"></i></button></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="bi bi-clock-history"></i> Other Conference Registrations</h3></div>
    <div class="table-wrap">
      <table class="table">
        <tbody>
        <?php if (!$others) echo empty_row(3, 'No other registrations.'); ?>
        <?php foreach ($others as $o): ?>
          <tr><td><a href="<?= e(url('admin/participant.php?id=' . $o['id'])) ?>"><?= e($o['title']) ?></a><div class="cell-sub"><?= e($o['reference_no']) ?></div></td><td class="small"><?= e(fmt_date($o['start_datetime'])) ?></td><td><?= status_badge($o['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$js = 'var PROFILE=' . json_encode($exportRows) . ', PNAME=' . json_encode(full_name($r)) . ', PREF=' . json_encode($r['reference_no']) . ';'
    . 'document.getElementById("exp-pdf").onclick=function(){exportPDF([{title:"",head:["Section","Field","Value"],rows:PROFILE}],"participant_"+PREF,"Participant Information – "+PNAME,PREF,"portrait");};'
    . 'document.getElementById("exp-xlsx").onclick=function(){exportXLSX([{title:"Participant",head:["Section","Field","Value"],rows:PROFILE}],"participant_"+PREF);};';
admin_footer(['vendor/xlsx.full.min.js', 'vendor/jspdf.umd.min.js', 'vendor/jspdf.plugin.autotable.min.js'], $js);
