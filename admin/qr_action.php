<?php
require __DIR__ . '/_init.php';
$admin = require_admin();
if (!is_post()) {
    redirect('admin/qrcodes.php');
}
verify_csrf();

$action = post('action');

// Type an event name -> create the event + its registration QR code in one step.
if ($action === 'quick_create') {
    if (!is_super()) {
        flash('error', 'Only a Super Admin can create events.');
        redirect('admin/qrcodes.php');
    }
    $title = preg_replace('/\s+/', ' ', post('title'));
    $venue = post('venue');
    $date  = post('date');
    if ($title === '' || mb_strlen($title) > 190) {
        flash('error', 'Please type the event name (max 190 characters).');
        redirect('admin/qrcodes.php');
    }
    $existing = q_row('SELECT id FROM conferences WHERE title = ? AND is_archived = 0', [$title]);
    if ($existing) {
        flash('info', 'That event already exists. Here is its QR code.');
        redirect('admin/qrcodes.php?conference=' . $existing['id']);
    }
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' 08:00:00' : date('Y-m-d') . ' 08:00:00';
    $pdo = db();
    $pdo->beginTransaction();
    q('INSERT INTO conferences (academic_year_id, title, venue, start_datetime, created_by)
       VALUES ((SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1), ?, ?, ?, ?)',
        [$title, mb_substr($venue !== '' ? $venue : 'To be announced', 0, 255), $start, $admin['id']]);
    $confId = (int) $pdo->lastInsertId();
    q('INSERT INTO qr_codes (conference_id, token, created_by) VALUES (?, ?, ?)', [$confId, random_token(), $admin['id']]);
    $pdo->commit();
    log_activity('conference_create', 'Created event "' . $title . '" with a registration QR code');
    flash('success', 'Event "' . $title . '" created. Its QR code is ready below. Students can scan it to open the registration form.');
    redirect('admin/qrcodes.php?conference=' . $confId);
}

if ($action === 'create') {
    $confId = (int) post('conference_id');
    require_conference_access($confId);
    $conf = q_row('SELECT * FROM conferences WHERE id = ? AND is_archived = 0', [$confId]);
    if (!$conf) {
        flash('error', 'Please choose an active conference.');
        redirect('admin/qrcodes.php');
    }
    $label = mb_substr(post('label') ?: 'Registration QR', 0, 100);
    q('INSERT INTO qr_codes (conference_id, token, label, created_by) VALUES (?, ?, ?, ?)', [$confId, random_token(), $label, $admin['id']]);
    log_activity('qr_create', 'Generated QR code "' . $label . '" for "' . $conf['title'] . '"');
    flash('success', 'QR code generated.');
    redirect('admin/qrcodes.php?conference=' . $confId);
}

$qr = q_row('SELECT q.*, c.title FROM qr_codes q JOIN conferences c ON c.id = q.conference_id WHERE q.id = ?', [(int) post('id')]);
if (!$qr) {
    flash('error', 'QR code not found.');
    redirect('admin/qrcodes.php');
}
require_conference_access((int) $qr['conference_id']);
$back = 'admin/qrcodes.php?conference=' . $qr['conference_id'];

switch ($action) {
    case 'toggle':
        $new = $qr['is_active'] ? 0 : 1;
        q('UPDATE qr_codes SET is_active = ? WHERE id = ?', [$new, $qr['id']]);
        log_activity('qr_toggle', ($new ? 'Activated' : 'Deactivated') . ' QR code "' . $qr['label'] . '" of "' . $qr['title'] . '"');
        flash('success', 'QR code ' . ($new ? 'activated' : 'deactivated') . '.');
        break;

    case 'regenerate':
        q('UPDATE qr_codes SET token = ?, scan_count = 0, last_scanned_at = NULL WHERE id = ?', [random_token(), $qr['id']]);
        log_activity('qr_regenerate', 'Regenerated link of QR code "' . $qr['label'] . '" of "' . $qr['title'] . '"');
        flash('success', 'A new registration link was generated. Remember to replace any printed copies of the old QR code.');
        break;

    case 'delete':
        if ((int) q_val('SELECT COUNT(*) FROM conference_registrations WHERE qr_code_id = ?', [$qr['id']]) > 0) {
            flash('error', 'This QR code already has registrations. Deactivate it instead.');
            break;
        }
        q('DELETE FROM qr_codes WHERE id = ?', [$qr['id']]);
        log_activity('qr_delete', 'Deleted QR code "' . $qr['label'] . '" of "' . $qr['title'] . '"');
        flash('success', 'QR code deleted.');
        break;
}
redirect($back);
