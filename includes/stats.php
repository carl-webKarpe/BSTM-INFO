<?php
/**
 * Statistics queries shared by the Dashboard and Reports pages.
 * All functions take a WHERE fragment (starting with " AND ...") applied to
 * alias r = conference_registrations, plus its parameters.
 */

function stats_from(): string
{
    return ' FROM conference_registrations r
             JOIN participant_types pt ON pt.id = r.participant_type_id
             JOIN conferences c ON c.id = r.conference_id
             LEFT JOIN schools sc ON sc.id = r.school_id
             LEFT JOIN courses co ON co.id = r.course_id
             LEFT JOIN attendance_records ar ON ar.registration_id = r.id
             WHERE r.is_archived = 0';
}

function stats_summary(string $where, array $params): array
{
    $row = q_row("SELECT
            SUM(r.status <> 'cancelled') AS total,
            SUM(r.status <> 'cancelled' AND pt.code = 'student') AS students,
            SUM(r.status <> 'cancelled' AND pt.code = 'faculty') AS faculty,
            SUM(r.status <> 'cancelled' AND pt.code = 'guest')   AS guests,
            SUM(r.status = 'pending')   AS pending,
            SUM(r.status = 'confirmed') AS confirmed,
            SUM(r.status = 'cancelled') AS cancelled,
            SUM(ar.id IS NOT NULL)      AS checked_in" . stats_from() . $where, $params);
    return array_map('intval', $row ?: []);
}

/** Generic grouped count. $labelExpr is an SQL expression. */
function stats_group(string $labelExpr, string $where, array $params, int $limit = 0, string $order = 'cnt DESC, label ASC', bool $excludeCancelled = true): array
{
    $sql = "SELECT $labelExpr AS label, COUNT(*) AS cnt" . stats_from() . $where
        . ($excludeCancelled ? " AND r.status <> 'cancelled'" : '')
        . " GROUP BY label ORDER BY $order" . ($limit ? " LIMIT $limit" : '');
    return q_all($sql, $params);
}

function stats_by_course(string $where, array $params, int $limit = 0): array
{
    return stats_group("COALESCE(co.code, NULLIF(r.course_other, ''), 'Not specified')", $where . " AND pt.code <> 'guest'", $params, $limit);
}

function stats_by_school(string $where, array $params, int $limit = 0): array
{
    return stats_group("COALESCE(sc.name, NULLIF(r.school_other, ''), 'Not specified')", $where, $params, $limit);
}

function stats_by_year(string $where, array $params): array
{
    $rows = stats_group("COALESCE(r.year_level, 'Not specified')", $where . " AND pt.code = 'student'", $params, 0, 'label ASC');
    // keep the natural year-level order
    $order = array_flip(year_levels());
    usort($rows, fn($a, $b) => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));
    return $rows;
}

function stats_by_type(string $where, array $params): array
{
    return stats_group('pt.name', $where, $params, 0, 'MIN(pt.id)');
}

function stats_trend(string $where, array $params, int $days = 30): array
{
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $rows = q_all('SELECT DATE(r.registered_at) AS d, COUNT(*) AS cnt' . stats_from() . $where
        . ' AND r.registered_at >= ? GROUP BY d ORDER BY d', array_merge($params, [$from . ' 00:00:00']));
    $map = array_column($rows, 'cnt', 'd');
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $d = date('Y-m-d', strtotime($from . " +$i days"));
        $out[] = ['label' => date('M j', strtotime($d)), 'cnt' => (int) ($map[$d] ?? 0)];
    }
    return $out;
}

function chart_data(array $rows): array
{
    return [array_column($rows, 'label'), array_map('intval', array_column($rows, 'cnt'))];
}
