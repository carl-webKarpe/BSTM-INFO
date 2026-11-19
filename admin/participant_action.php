<?php
/** Status / archive actions for one or many registrations. */
require __DIR__ . '/_init.php';
$admin = require_admin();
if (!is_post()) {
    redirect('admin/participants.php');
}
verify_csrf();

$return = post('return');
if ($return === '' || strpos($return, app_base_path() . '/admin/') !== 0 || strpos($return, '//') !== false) {
    $return = url('admin/participants.php');
}

$action = '';
$ids = [];
if (post('single') !== '' && strpos(post('single'), ':') !== false) {
    [$action, $id] = explode(':', post('single'), 2);
    $ids = [(int) $id];
} elseif (post('do_bulk') !== '') {
    $action = post('bulk');
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
}
$ids = array_values(array_filter(array_unique($ids)));

$labels = [
    'confirm' => 'confirmed', 'pending' => 'marked as pending', 'cancel' => 'cancelled',
    'archive' => 'archived', 'restore' => 'restored',
];
if (!isset($labels[$action])) {
    flash('error', 'Please choose an action.');
    redirect($return);
}
if (!$ids) {
    flash('warning', 'No records were selected.');
    redirect($return);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$rows = q_all("SELECT r.id, r.reference_no, r.conference_id, s.last_name, s.first_name FROM conference_registrations r
               JOIN students s ON s.id = r.student_id WHERE r.id IN ($placeholders)" . conf_scope('r.conference_id'), $ids);

$done = 0;
foreach ($rows as $r) {
    switch ($action) {
        case 'confirm':
        case 'pending':
        case 'cancel':
            $status = ['confirm' => 'confirmed', 'pending' => 'pending', 'cancel' => 'cancelled'][$action];
            $done += q('UPDATE conference_registrations SET status = ?, status_updated_at = NOW(), status_updated_by = ? WHERE id = ? AND status <> ?',
                [$status, $admin['id'], $r['id'], $status])->rowCount();
            break;
        case 'archive':
        case 'restore':
            $done += q('UPDATE conference_registrations SET is_archived = ? WHERE id = ?', [$action === 'archive' ? 1 : 0, $r['id']])->rowCount();
            break;
    }
}

if ($done) {
    $desc = count($rows) === 1
        ? ucfirst($labels[$action]) . ' registration ' . $rows[0]['reference_no'] . ' (' . $rows[0]['last_name'] . ', ' . $rows[0]['first_name'] . ')'
        : ucfirst($labels[$action]) . " $done registrations";
    log_activity('participant_' . $action, $desc);
    flash('success', $done === 1 ? 'Registration ' . $labels[$action] . '.' : "$done registrations {$labels[$action]}.");
} else {
    flash('info', 'Nothing changed.');
}
redirect($return);
