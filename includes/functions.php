<?php
/**
 * General helper functions shared by the student portal and admin.
 */

// =================================================================
// Database
// =================================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL timestamps in the same time zone as PHP.
        try {
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (Throwable $e) {
            // Some hosts do not allow this – PHP still controls displayed times.
        }
    } catch (PDOException $e) {
        http_response_code(500);
        $msg = APP_DEBUG ? e($e->getMessage()) : 'Please try again later.';
        echo '<!doctype html><meta charset="utf-8"><title>Database error</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#7f1d1d">'
            . '<h2 style="margin-top:0">Cannot connect to the database</h2><p>' . $msg . '</p>'
            . (APP_DEBUG ? '<p>Check the DB_* values in <code>config.php</code>, make sure MySQL is running, and that <code>database/schema.sql</code> was imported.</p>' : '')
            . '</div>';
        exit;
    }
    return $pdo;
}

/** Run a prepared query and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

// =================================================================
// Output / URLs
// =================================================================
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Path of the application root as seen from the browser, e.g. "/bstm" or "". */
function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $root      = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $scriptDir = str_replace('\\', '/', (string) realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)));
    $rel       = trim(substr($scriptDir, strlen($root)), '/');
    $urlDir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($rel !== '' && substr($urlDir, -strlen('/' . $rel)) === '/' . $rel) {
        $urlDir = substr($urlDir, 0, -strlen('/' . $rel));
    }
    return $base = rtrim($urlDir, '/');
}

/** Relative URL inside the app. */
function url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** The automatically detected base URL (scheme + host + path). */
function detected_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host . app_base_path();
}

/** Full public URL – used inside QR codes. */
function public_base_url(): string
{
    $s = trim((string) setting('public_base_url', ''));
    if ($s !== '') {
        return rtrim($s, '/');
    }
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    return detected_base_url();
}

function absolute_url(string $path): string
{
    return public_base_url() . '/' . ltrim($path, '/');
}

function registration_url(string $token): string
{
    return absolute_url('register.php?t=' . urlencode($token));
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~', $path) && strpos($path, '/') !== 0) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

// =================================================================
// Request helpers
// =================================================================
function post(string $key, $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function get(string $key, $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// =================================================================
// CSRF protection
// =================================================================
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['_csrf'] ?? ''));
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            json_response(['ok' => false, 'message' => 'Your session expired. Please reload the page.'], 419);
        }
        http_response_code(419);
        echo '<!doctype html><meta charset="utf-8"><title>Session expired</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;text-align:center">'
            . '<h2>Your session has expired</h2><p>Please go back, reload the page and try again.</p>'
            . '<p><a href="javascript:history.back()">&larr; Go back</a></p></div>';
        exit;
    }
}

// =================================================================
// Flash messages
// =================================================================
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): string
{
    $out = '';
    foreach ($_SESSION['_flash'] ?? [] as $f) {
        $icon = ['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$f['type']] ?? 'info-circle-fill';
        $out .= '<div class="alert alert-' . e($f['type']) . '" role="alert"><i class="bi bi-' . $icon . '"></i><div>' . e($f['message']) . '</div>'
            . '<button type="button" class="alert-close" aria-label="Dismiss" onclick="this.parentElement.remove()">&times;</button></div>';
    }
    unset($_SESSION['_flash']);
    return $out;
}

// =================================================================
// Settings
// =================================================================
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null ? $cache[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}


// =================================================================
// Tokens / references
// =================================================================
function random_token(): string
{
    return bin2hex(random_bytes(16)); // 32 hex characters
}

function generate_reference(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $ref = 'REG-' . date('y') . '-' . $code;
    } while (q_val('SELECT 1 FROM conference_registrations WHERE reference_no = ?', [$ref]));
    return $ref;
}

// =================================================================
// Validation
// =================================================================
/**
 * Normalises a phone number. Philippine mobile numbers become 09XXXXXXXXX.
 * Returns null if invalid.
 */
function normalize_contact(string $raw): ?string
{
    $n = preg_replace('/[\s\-().]/', '', $raw);
    if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $n, $m)) {
        return '0' . $m[1];
    }
    if (preg_match('/^\+\d{8,15}$/', $n)) { // international numbers for guests
        return $n;
    }
    return null;
}

function valid_id_number(string $id): bool
{
    $pattern = (string) setting('student_id_pattern', '^[0-9]{7}$');
    $ok = @preg_match('~' . str_replace('~', '\~', $pattern) . '~', $id);
    if ($ok === false) { // broken pattern in settings – fall back to the default
        $ok = preg_match('~^[0-9]{7}$~', $id);
    }
    return $ok === 1 && strlen($id) <= 30;
}

function valid_name(string $s): bool
{
    return (bool) preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]{0,79}$/u", $s);
}

function year_levels(): array
{
    return ['1st Year', '2nd Year', '3rd Year', '4th Year'];
}

function statuses(): array
{
    return ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'];
}

// =================================================================
// Formatting
// =================================================================
function fmt_date(?string $dt, string $format = 'M j, Y'): string
{
    return $dt ? date($format, strtotime($dt)) : '—';
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('M j, Y g:i A', strtotime($dt)) : '—';
}

function fmt_conf_schedule(array $c): string
{
    $s = strtotime($c['start_datetime']);
    $out = date('l, F j, Y · g:i A', $s);
    if (!empty($c['end_datetime'])) {
        $en = strtotime($c['end_datetime']);
        $out .= date('Y-m-d', $s) === date('Y-m-d', $en)
            ? ' – ' . date('g:i A', $en)
            : ' – ' . date('F j, Y g:i A', $en);
    }
    return $out;
}

function full_name(array $r): string
{
    $n = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? ''));
    if (!empty($r['middle_name'])) {
        $n .= ' ' . $r['middle_name'];
    }
    return $n;
}

function status_badge(string $status): string
{
    $map = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger'];
    return '<span class="badge badge-' . ($map[$status] ?? 'muted') . '">' . e(ucfirst($status)) . '</span>';
}

function type_badge(?string $name): string
{
    $cls = ['Student' => 'blue', 'Faculty' => 'navy', 'Guest' => 'muted'][$name] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . e($name ?? '—') . '</span>';
}

/** Display text for a registration's school (lookup or "other"). */
function school_label(array $r): string
{
    if (!empty($r['school_name'])) {
        return $r['school_name'];
    }
    return !empty($r['school_other']) ? $r['school_other'] : '—';
}

function course_label(array $r): string
{
    if (!empty($r['course_code'])) {
        return $r['course_code'] . ' – ' . $r['course_name'];
    }
    return ($r['course_other'] ?? '') ?: '—';
}

/** Shared SELECT for registration rows with all joins. */
function registration_select_sql(): string
{
    return "SELECT r.*, s.id_number, s.last_name, s.first_name, s.middle_name, s.contact_number, s.address,
                   pt.name AS type_name, pt.code AS type_code,
                   sc.name AS school_name, co.code AS course_code, co.name AS course_name,
                   c.title AS conference_title, c.venue AS conference_venue,
                   c.start_datetime AS conference_start, c.end_datetime AS conference_end,
                   ar.checked_in_at, ar.method AS checkin_method
            FROM conference_registrations r
            JOIN students s           ON s.id = r.student_id
            JOIN participant_types pt ON pt.id = r.participant_type_id
            JOIN conferences c        ON c.id = r.conference_id
            LEFT JOIN schools sc      ON sc.id = r.school_id
            LEFT JOIN courses co      ON co.id = r.course_id
            LEFT JOIN attendance_records ar ON ar.registration_id = r.id";
}

// =================================================================
// Conference registration state
// =================================================================
/**
 * Works out whether a conference currently accepts registrations.
 * @return array{open: bool, label: string, tone: string, message: string, count: int}
 */
function conference_state(array $c, ?array $qr = null): array
{
    $now   = time();
    $count = (int) q_val("SELECT COUNT(*) FROM conference_registrations WHERE conference_id = ? AND status <> 'cancelled'", [$c['id']]);
    $base  = ['open' => false, 'tone' => 'danger', 'count' => $count];

    if (!empty($c['is_archived'])) {
        return $base + ['label' => 'Closed', 'message' => 'This conference has ended and is no longer accepting registrations.'];
    }
    if ($qr !== null && empty($qr['is_active'])) {
        return $base + ['label' => 'Link Inactive', 'message' => 'This registration QR code / link has been deactivated by the organizers. Please ask for the updated QR code.'];
    }
    if (empty($c['registration_enabled'])) {
        return $base + ['label' => 'Closed', 'message' => 'Registration for this conference is currently closed.'];
    }
    if (!empty($c['registration_open']) && $now < strtotime($c['registration_open'])) {
        return ['open' => false, 'tone' => 'warning', 'count' => $count, 'label' => 'Not Yet Open',
            'message' => 'Registration opens on ' . fmt_datetime($c['registration_open']) . '.'];
    }
    if (!empty($c['registration_close']) && $now > strtotime($c['registration_close'])) {
        return $base + ['label' => 'Closed', 'message' => 'Registration closed on ' . fmt_datetime($c['registration_close']) . '.'];
    }
    if (!empty($c['participant_limit']) && $count >= (int) $c['participant_limit']) {
        return $base + ['label' => 'Full', 'message' => 'This conference has reached its maximum number of participants.'];
    }
    $msg = 'Registration is open';
    if (!empty($c['participant_limit'])) {
        $msg .= ' · ' . max(0, (int) $c['participant_limit'] - $count) . ' slot(s) left';
    }
    if (!empty($c['registration_close'])) {
        $msg .= ' · closes ' . fmt_datetime($c['registration_close']);
    }
    return ['open' => true, 'tone' => 'success', 'label' => 'Open', 'message' => $msg . '.', 'count' => $count];
}

function conference_categories(array $c): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $c['attendance_categories'])), 'strlen'));
}

// =================================================================
// Lookups
// =================================================================
function lookup_participant_types(bool $activeOnly = false): array
{
    return q_all('SELECT * FROM participant_types' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY id');
}

function lookup_schools(bool $activeOnly = true): array
{
    return q_all('SELECT * FROM schools' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name');
}

function lookup_courses(bool $activeOnly = true): array
{
    return q_all('SELECT * FROM courses' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY id');
}

function lookup_academic_years(): array
{
    return q_all('SELECT * FROM academic_years ORDER BY name DESC');
}

// =================================================================
// Activity log
// =================================================================
function log_activity(string $action, string $description): void
{
    try {
        q('INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)', [
            $_SESSION['admin_id'] ?? null, $action, mb_substr($description, 0, 500), client_ip(),
        ]);
    } catch (Throwable $e) {
        // never break the request because of logging
    }
}

// =================================================================
// Pagination
// =================================================================
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Current query string with some values replaced. */
function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        }
    }
    return '?' . http_build_query($q);
}

function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    $html .= $p['page'] > 1
        ? '<a href="' . e(query_with(['page' => $p['page'] - 1])) . '" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>'
        : '<span class="disabled"><i class="bi bi-chevron-left"></i></span>';
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    if ($start > 1) {
        $html .= '<a href="' . e(query_with(['page' => 1])) . '">1</a>' . ($start > 2 ? '<span class="dots">…</span>' : '');
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $p['page'] ? '<span class="current">' . $i . '</span>' : '<a href="' . e(query_with(['page' => $i])) . '">' . $i . '</a>';
    }
    if ($end < $p['pages']) {
        $html .= ($end < $p['pages'] - 1 ? '<span class="dots">…</span>' : '') . '<a href="' . e(query_with(['page' => $p['pages']])) . '">' . $p['pages'] . '</a>';
    }
    $html .= $p['page'] < $p['pages']
        ? '<a href="' . e(query_with(['page' => $p['page'] + 1])) . '" aria-label="Next"><i class="bi bi-chevron-right"></i></a>'
        : '<span class="disabled"><i class="bi bi-chevron-right"></i></span>';
    return $html . '</nav>';
}

// =================================================================
// Philippine address lists (assets/data/address, from PSGC data)
// =================================================================
/** @return array<string, string> province key => province name */
function address_provinces(): array
{
    static $list = null;
    if ($list === null) {
        $list = [];
        $raw = json_decode((string) @file_get_contents(APP_ROOT . '/assets/data/address/provinces.json'), true) ?: [];
        foreach ($raw as $p) {
            $list[$p['k']] = $p['n'];
        }
    }
    return $list;
}

/** @return array<string, string[]> city name => barangay names */
function address_cities(string $provinceKey): array
{
    if (!preg_match('/^[A-Z0-9]{2,4}$/', $provinceKey) || !isset(address_provinces()[$provinceKey])) {
        return [];
    }
    $raw = json_decode((string) @file_get_contents(APP_ROOT . '/assets/data/address/' . $provinceKey . '.json'), true) ?: [];
    $out = [];
    foreach ($raw as $c) {
        $out[$c['n']] = $c['b'];
    }
    return $out;
}
