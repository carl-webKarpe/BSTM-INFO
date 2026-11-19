<?php
require __DIR__ . '/_init.php';
$admin = require_admin();
if (!is_post()) {
    redirect('admin/qrcodes.php');
}
verify_csrf();

$action = post('action');

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
