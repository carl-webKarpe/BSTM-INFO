<?php
/**
 * JSON API used by the Attendance Monitoring page.
 * POST JSON: {action, conference_id, ...} with header X-CSRF-Token.
 */
require __DIR__ . '/_init.php';

if (!current_admin()) {
    json_response(['ok' => false, 'message' => 'Your session expired. Please log in again.'], 401);
}
$admin = current_admin();
if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
}
verify_csrf();

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string) ($in['action'] ?? '');
$confId = (int) ($in['conference_id'] ?? 0);
if (!$confId || !can_access_conference($confId)) {
    json_response(['ok' => false, 'message' => 'You do not have access to this conference.'], 403);
}
$conf = q_row('SELECT * FROM conferences WHERE id = ?', [$confId]);
if (!$conf) {
    json_response(['ok' => false, 'message' => 'Conference not found.'], 404);
}

function att_stats(int $confId): array
{
    $r = q_row("SELECT SUM(r.status <> 'cancelled') AS registered, SUM(ar.id IS NOT NULL) AS checked_in
                FROM conference_registrations r LEFT JOIN attendance_records ar ON ar.registration_id = r.id
                WHERE r.conference_id = ? AND r.is_archived = 0", [$confId]);
    $reg = (int) $r['registered'];
    $in  = (int) $r['checked_in'];
    return ['registered' => $reg, 'checked_in' => $in, 'remaining' => max(0, $reg - $in), 'rate' => $reg ? round($in / $reg * 100) : 0];
}

function att_person(array $r): array
{
    return [
        'id' => (int) $r['id'], 'name' => full_name($r), 'id_number' => $r['id_number'] ?: '—', 'reference_no' => $r['reference_no'],
        'type' => $r['type_name'], 'status' => $r['status'], 'school' => school_label($r),
        'course' => $r['course_code'] ?: ($r['course_other'] ?: ''), 'year' => $r['year_level'] ?: '',
        'conference_id' => (int) $r['conference_id'], 'conference' => $r['conference_title'],
        'checked_in_at' => $r['checked_in_at'] ? fmt_datetime($r['checked_in_at']) : null,
        'checked_in_time' => $r['checked_in_at'] ? date('g:i A', strtotime($r['checked_in_at'])) : null,
        'profile_url' => url('admin/participant.php?id=' . $r['id']),
    ];
}

function do_checkin(array $r, int $confId, string $method, bool $confirmPending, array $admin): array
{
    if ((int) $r['conference_id'] !== $confId) {
        return ['ok' => false, 'result' => 'wrong_conference', 'message' => 'This participant is registered for a different conference: ' . $r['conference_title'] . '.', 'person' => att_person($r)];
    }
    if ($r['is_archived']) {
        return ['ok' => false, 'result' => 'archived', 'message' => 'This registration record is archived.', 'person' => att_person($r)];
    }
    if ($r['status'] === 'cancelled') {
        return ['ok' => false, 'result' => 'cancelled', 'message' => 'This registration was CANCELLED. The participant cannot be checked in.', 'person' => att_person($r)];
    }
    if ($r['checked_in_at']) {
        return ['ok' => false, 'result' => 'duplicate', 'message' => 'Already checked in at ' . fmt_datetime($r['checked_in_at']) . '. Duplicate check-in prevented.', 'person' => att_person($r)];
    }
    if ($r['status'] === 'pending' && !$confirmPending) {
        return ['ok' => false, 'result' => 'pending', 'message' => 'This registration is still PENDING. Confirm it and check the participant in?', 'person' => att_person($r)];
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($r['status'] === 'pending') {
            q("UPDATE conference_registrations SET status = 'confirmed', status_updated_at = NOW(), status_updated_by = ? WHERE id = ?", [$admin['id'], $r['id']]);
        }
        q('INSERT INTO attendance_records (registration_id, conference_id, checked_in_by, method) VALUES (?, ?, ?, ?)', [$r['id'], $confId, $admin['id'], $method]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'result' => 'duplicate', 'message' => 'Already checked in. Duplicate check-in prevented.', 'person' => att_person($r)];
        }
        throw $e;
    }
    log_activity('attendance_checkin', 'Checked in ' . full_name($r) . ' (' . $r['reference_no'] . ') via ' . ($method === 'qr' ? 'QR scan' : 'manual search'));
    $fresh = q_row(registration_select_sql() . ' WHERE r.id = ?', [$r['id']]);
    return ['ok' => true, 'result' => 'checked_in', 'message' => full_name($r) . ' is now checked in.', 'person' => att_person($fresh)];
}

switch ($action) {
    case 'scan':
        $code = trim((string) ($in['code'] ?? ''));
        if (stripos($code, 'register.php') !== false || preg_match('~^https?://~i', $code)) {
            json_response(['ok' => false, 'result' => 'invalid', 'message' => 'This is a conference REGISTRATION QR code, not a participant attendance QR code. Ask the participant to show the QR code on their confirmation page.', 'stats' => att_stats($confId)]);
        }
        if (!preg_match('/^ATTEND:([a-f0-9]{32})$/i', $code, $m)) {
            json_response(['ok' => false, 'result' => 'invalid', 'message' => 'Unrecognized QR code. Please scan the participant\'s attendance QR code.', 'stats' => att_stats($confId)]);
        }
        $r = q_row(registration_select_sql() . ' WHERE r.attendance_token = ?', [strtolower($m[1])]);
        if (!$r) {
            json_response(['ok' => false, 'result' => 'invalid', 'message' => 'No registration found for this QR code.', 'stats' => att_stats($confId)]);
        }
        if (!can_access_conference((int) $r['conference_id'])) {
            json_response(['ok' => false, 'result' => 'wrong_conference', 'message' => 'This participant is registered for a different conference.', 'stats' => att_stats($confId)]);
        }
        $res = do_checkin($r, $confId, 'qr', !empty($in['confirm_pending']), $admin);
        json_response($res + ['stats' => att_stats($confId)]);

    case 'search':
        $q = trim((string) ($in['query'] ?? ''));
        if (mb_strlen($q) < 2) {
            json_response(['ok' => false, 'message' => 'Type at least 2 characters.']);
        }
        $rows = q_all(registration_select_sql() . " WHERE r.conference_id = ? AND r.is_archived = 0 AND (
                s.id_number = ? OR r.reference_no = ? OR s.id_number LIKE ? OR r.reference_no LIKE ?
                OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(s.last_name, ', ', s.first_name) LIKE ? OR s.last_name LIKE ?)
              ORDER BY (s.id_number = ? OR r.reference_no = ?) DESC, s.last_name, s.first_name LIMIT 15",
            [$confId, $q, strtoupper($q), "$q%", '%' . strtoupper($q) . '%', "%$q%", "%$q%", "$q%", $q, strtoupper($q)]);
        json_response(['ok' => true, 'results' => array_map('att_person', $rows)]);

    case 'checkin':
        $r = q_row(registration_select_sql() . ' WHERE r.id = ?', [(int) ($in['registration_id'] ?? 0)]);
        if (!$r) {
            json_response(['ok' => false, 'message' => 'Registration not found.']);
        }
        $res = do_checkin($r, $confId, 'manual', !empty($in['confirm_pending']), $admin);
        json_response($res + ['stats' => att_stats($confId)]);

    case 'undo':
        $r = q_row(registration_select_sql() . ' WHERE r.id = ? AND r.conference_id = ?', [(int) ($in['registration_id'] ?? 0), $confId]);
        if (!$r || !$r['checked_in_at']) {
            json_response(['ok' => false, 'message' => 'This participant is not checked in.']);
        }
        q('DELETE FROM attendance_records WHERE registration_id = ?', [$r['id']]);
        log_activity('attendance_undo', 'Removed check-in of ' . full_name($r) . ' (' . $r['reference_no'] . ')');
        json_response(['ok' => true, 'message' => 'Check-in removed for ' . full_name($r) . '.', 'stats' => att_stats($confId)]);

    case 'stats':
        json_response(['ok' => true, 'stats' => att_stats($confId)]);
}
json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
