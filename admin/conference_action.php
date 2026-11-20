<?php
require __DIR__ . '/_init.php';
require_admin();
if (!is_post()) {
    redirect('admin/conferences.php');
}
verify_csrf();

$id = (int) post('id');
require_conference_access($id);
$c = q_row('SELECT * FROM conferences WHERE id = ?', [$id]);
if (!$c) {
    flash('error', 'Conference not found.');
    redirect('admin/conferences.php');
}

switch (post('action')) {
    case 'toggle_registration':
        $new = $c['registration_enabled'] ? 0 : 1;
        q('UPDATE conferences SET registration_enabled = ? WHERE id = ?', [$new, $id]);
        log_activity('conference_registration', ($new ? 'Enabled' : 'Disabled') . ' registration for "' . $c['title'] . '"');
        flash('success', 'Registration ' . ($new ? 'enabled' : 'disabled') . '.');
        break;

    case 'archive':
    case 'unarchive':
        require_super();
        $arch = post('action') === 'archive' ? 1 : 0;
        q('UPDATE conferences SET is_archived = ?' . ($arch ? ', registration_enabled = 0' : '') . ' WHERE id = ?', [$arch, $id]);
        log_activity('conference_archive', ($arch ? 'Archived' : 'Restored') . ' conference "' . $c['title'] . '"');
        flash('success', 'Conference ' . ($arch ? 'archived' : 'restored') . '.');
        break;

    case 'delete':
        require_super();
        $count = (int) q_val('SELECT COUNT(*) FROM conference_registrations WHERE conference_id = ?', [$id]);
        $pdo = db();
        $pdo->beginTransaction();
        // registrations, attendance, QR codes and admin assignments are removed by the foreign keys (ON DELETE CASCADE)
        q('DELETE FROM conferences WHERE id = ?', [$id]);
        // remove people who no longer have any registration
        q('DELETE FROM students WHERE NOT EXISTS (SELECT 1 FROM conference_registrations r WHERE r.student_id = students.id)');
        $pdo->commit();
        log_activity('conference_delete', 'Deleted conference "' . $c['title'] . '"' . ($count ? " and its $count registration(s)" : ''));
        flash('success', 'Conference "' . $c['title'] . '" deleted' . ($count ? " together with $count registration(s)." : '.'));
        redirect('admin/conferences.php');
}
redirect('admin/conference_view.php?id=' . $id);
