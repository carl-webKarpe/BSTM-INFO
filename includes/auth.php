<?php
/**
 * Administrator authentication and role-based access control.
 *
 *  super_admin       – everything
 *  conference_admin  – only conferences assigned in admin_conferences
 */

const SESSION_IDLE_TIMEOUT = 7200;   // 2 hours of inactivity
const MAX_LOGIN_ATTEMPTS   = 5;
const LOCKOUT_MINUTES      = 15;

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
            logout_admin();
            flash('warning', 'You were logged out after a period of inactivity.');
            return null;
        }
        $_SESSION['last_activity'] = time();
        $row = q_row('SELECT * FROM admins WHERE id = ? AND is_active = 1', [$_SESSION['admin_id']]);
        if ($row) {
            $admin = $row;
        } else {
            logout_admin();
        }
    }
    return $admin;
}

function is_super(): bool
{
    $a = current_admin();
    return $a !== null && $a['role'] === 'super_admin';
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']      = (int) $admin['id'];
    $_SESSION['last_activity'] = time();
    q('UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?', [$admin['id']]);
}

function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['last_activity']);
    session_regenerate_id(true);
}

/** Use at the top of every admin page. */
function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php' . ($next ? '?next=' . urlencode($next) : ''));
    }
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ((int) $admin['must_change_password'] === 1 && !in_array($page, ['change_password.php', 'logout.php'], true)) {
        flash('warning', 'For security, please change your password before continuing.');
        redirect('admin/change_password.php');
    }
    return $admin;
}

function require_super(): array
{
    $admin = require_admin();
    if ($admin['role'] !== 'super_admin') {
        http_response_code(403);
        flash('error', 'Only a Super Admin can access that page.');
        redirect('admin/index.php');
    }
    return $admin;
}

/**
 * Conference IDs the current admin may manage.
 * Returns null for Super Admins (meaning "all conferences").
 */
function admin_conf_ids(): ?array
{
    static $ids = false;
    if ($ids !== false) {
        return $ids;
    }
    if (is_super()) {
        return $ids = null;
    }
    $a = current_admin();
    $ids = $a ? array_map('intval', q('SELECT conference_id FROM admin_conferences WHERE admin_id = ?', [$a['id']])->fetchAll(PDO::FETCH_COLUMN)) : [];
    return $ids;
}

/** SQL fragment limiting a conference id column to what the admin can see. */
function conf_scope(string $column): string
{
    $ids = admin_conf_ids();
    if ($ids === null) {
        return ' AND 1=1';
    }
    if (!$ids) {
        return ' AND 1=0';
    }
    return ' AND ' . $column . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
}

function can_access_conference(int $conferenceId): bool
{
    $ids = admin_conf_ids();
    return $ids === null || in_array($conferenceId, $ids, true);
}

function require_conference_access(int $conferenceId): void
{
    if (!can_access_conference($conferenceId)) {
        flash('error', 'You do not have access to that conference.');
        redirect('admin/index.php');
    }
}

/** Conferences visible to the admin (for dropdowns). */
function accessible_conferences(bool $includeArchived = true): array
{
    return q_all('SELECT id, title, start_datetime, is_archived FROM conferences c WHERE 1=1'
        . ($includeArchived ? '' : ' AND c.is_archived = 0') . conf_scope('c.id')
        . ' ORDER BY c.is_archived ASC, c.start_datetime DESC');
}

/** Returns a list of problems with a proposed password (empty = OK). */
function password_problems(string $pw): array
{
    $p = [];
    if (strlen($pw) < 8) {
        $p[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $p[] = 'Password must contain an uppercase letter, a lowercase letter and a number.';
    }
    return $p;
}
