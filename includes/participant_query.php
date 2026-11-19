<?php
/**
 * Builds the WHERE / ORDER BY for participant lists from request filters.
 * Used by participants.php and export.php so both show the same records.
 */

function participant_sort_columns(): array
{
    return [
        'id_number'  => 's.id_number',
        'name'       => 's.last_name, s.first_name',
        'school'     => 'COALESCE(sc.name, r.school_other)',
        'course'     => 'COALESCE(co.code, r.course_other)',
        'year'       => 'r.year_level',
        'type'       => 'pt.id',
        'conference' => 'c.title',
        'date'       => 'r.registered_at',
        'status'     => 'r.status',
    ];
}

/** @return array{0: string, 1: array, 2: string} where, params, order by */
function participant_filters(array $in): array
{
    $g = fn($k) => isset($in[$k]) && is_string($in[$k]) ? trim($in[$k]) : '';
    $where  = conf_scope('r.conference_id');
    $params = [];

    $where .= ' AND r.is_archived = ?';
    $params[] = $g('archived') === '1' ? 1 : 0;

    if (($q = $g('q')) !== '') {
        $where .= " AND (s.id_number LIKE ? OR r.reference_no LIKE ? OR s.contact_number LIKE ?
                    OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR CONCAT(s.last_name, ', ', s.first_name) LIKE ?
                    OR CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) LIKE ?)";
        array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    if (ctype_digit($g('conference'))) {
        $where .= ' AND r.conference_id = ?';
        $params[] = (int) $g('conference');
    }
    if ($g('course') === 'other') {
        $where .= ' AND r.course_id IS NULL AND r.course_other IS NOT NULL';
    } elseif (ctype_digit($g('course'))) {
        $where .= ' AND r.course_id = ?';
        $params[] = (int) $g('course');
    }
    if ($g('school') === 'other') {
        $where .= ' AND r.school_id IS NULL AND r.school_other IS NOT NULL';
    } elseif (ctype_digit($g('school'))) {
        $where .= ' AND r.school_id = ?';
        $params[] = (int) $g('school');
    }
    if (in_array($g('year'), year_levels(), true)) {
        $where .= ' AND r.year_level = ?';
        $params[] = $g('year');
    }
    if (in_array($g('type'), ['student', 'faculty', 'guest'], true)) {
        $where .= ' AND pt.code = ?';
        $params[] = $g('type');
    }
    if (isset(statuses()[$g('status')])) {
        $where .= ' AND r.status = ?';
        $params[] = $g('status');
    }
    if ($g('attendance') === 'in') {
        $where .= ' AND ar.id IS NOT NULL';
    } elseif ($g('attendance') === 'out') {
        $where .= ' AND ar.id IS NULL';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('from'))) {
        $where .= ' AND r.registered_at >= ?';
        $params[] = $g('from') . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('to'))) {
        $where .= ' AND r.registered_at <= ?';
        $params[] = $g('to') . ' 23:59:59';
    }

    $cols = participant_sort_columns();
    $sort = isset($cols[$g('sort')]) ? $g('sort') : 'date';
    $dir  = strtolower($g('dir')) === 'asc' ? 'ASC' : (strtolower($g('dir')) === 'desc' ? 'DESC' : ($sort === 'date' ? 'DESC' : 'ASC'));
    $order = implode(', ', array_map(fn($c) => "$c $dir", explode(', ', $cols[$sort]))) . ', r.id DESC';

    return [$where, $params, $order];
}
