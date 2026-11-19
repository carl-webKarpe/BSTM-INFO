<?php
/**
 * Data export endpoint.
 *   type=participants  (uses the same filters as Participant Records)
 *   type=attendance    (conference=ID, attendance=in|out)
 *   format=csv  → downloads a CSV file
 *   format=json → JSON consumed by the browser to build Excel / PDF files
 */
require __DIR__ . '/_init.php';
require APP_ROOT . '/includes/participant_query.php';
require_admin();

$type   = get('type', 'participants');
$format = get('format', 'json') === 'csv' ? 'csv' : 'json';
$forPdf = get('for') === 'pdf';

[$where, $params, $order] = participant_filters($_GET);
$rows = q_all(registration_select_sql() . ' WHERE 1=1' . $where . " ORDER BY $order LIMIT 20000", $params);

$confTitle = '';
if (ctype_digit(get('conference'))) {
    $confTitle = (string) q_val('SELECT title FROM conferences WHERE id = ?', [(int) get('conference')]);
}

if ($type === 'attendance') {
    $title = 'Attendance Report';
    $columns = ['Reference No.', 'ID Number', 'Full Name', 'Type', 'School / Institution', 'Course / Program', 'Year Level', 'Status', 'Attendance', 'Check-in Time'];
    $data = array_map(fn($r) => [
        $r['reference_no'], $r['id_number'] ?? '', full_name($r), $r['type_name'], school_label($r),
        $r['course_code'] ?: ($r['course_other'] ?: ''), $r['year_level'] ?? '', ucfirst($r['status']),
        $r['checked_in_at'] ? 'Present' : 'Not yet checked in', $r['checked_in_at'] ? fmt_datetime($r['checked_in_at']) : '',
    ], $rows);
} elseif ($forPdf) {
    $title = 'Participant Records';
    $columns = ['Student ID', 'Full Name', 'Contact No.', 'School / Institution', 'Course', 'Year', 'Type', 'Conference', 'Registered', 'Status'];
    $data = array_map(fn($r) => [
        $r['id_number'] ?? '', full_name($r), $r['contact_number'], school_label($r),
        $r['course_code'] ?: ($r['course_other'] ?: ''), $r['year_level'] ?? '', $r['type_name'],
        $r['conference_title'], fmt_datetime($r['registered_at']), ucfirst($r['status']),
    ], $rows);
} else {
    $title = 'Participant Records';
    $columns = ['Reference No.', 'Student / Employee ID', 'Last Name', 'First Name', 'Middle Name', 'Contact Number', 'Complete Address',
        'Participant Type', 'School / Institution', 'Course / Program', 'Year Level', 'Section', 'Conference', 'Attendance Category',
        'Special Requirements', 'Status', 'Registration Date', 'Checked In At'];
    $data = array_map(fn($r) => [
        $r['reference_no'], $r['id_number'] ?? '', $r['last_name'], $r['first_name'], $r['middle_name'] ?? '', $r['contact_number'], $r['address'],
        $r['type_name'], school_label($r), course_label($r) === '—' ? '' : course_label($r), $r['year_level'] ?? '', $r['section'] ?? '',
        $r['conference_title'], $r['attendance_category'] ?? '', $r['special_requirements'] ?? '', ucfirst($r['status']),
        date('Y-m-d H:i', strtotime($r['registered_at'])), $r['checked_in_at'] ? date('Y-m-d H:i', strtotime($r['checked_in_at'])) : '',
    ], $rows);
}

log_activity('export', 'Exported ' . count($data) . ' ' . strtolower($title) . ' record(s) as ' . ($format === 'csv' ? 'CSV' : (get('for') === 'pdf' ? 'PDF' : 'Excel')));

if ($format === 'csv') {
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ñ and accents correctly
    fputcsv($out, $columns);
    foreach ($data as $row) {
        // prevent spreadsheet formula injection
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $row));
    }
    fclose($out);
    exit;
}

json_response([
    'title'    => $title . ($confTitle ? ' – ' . $confTitle : ''),
    'subtitle' => count($data) . ' record(s)',
    'filename' => $title . ($confTitle ? ' ' . $confTitle : ''),
    'columns'  => $columns,
    'rows'     => $data,
]);
