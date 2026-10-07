<?php
/**
 * Bootstrap file included at the top of every page:
 * session, DB connection, CSRF, auth guards and flash messages.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- navigation ---------- */

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- flash messages ---------- */

function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $msg) {
        $html .= '<div class="alert alert-' . e($msg['type']) . ' alert-dismissible fade show" role="alert">'
               . e($msg['text'])
               . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the start of every POST handler. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ---------- auth ---------- */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function is_admin(): bool
{
    return ($_SESSION['user_type'] ?? '') === 'admin';
}

/** Guard for customer pages. $base is the path back to the web root ('' or '../'). */
function require_login(string $base = ''): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect($base . 'login.php');
    }
}

function require_admin(): void
{
    if (!is_logged_in() || !is_admin()) {
        flash('danger', 'You do not have permission to access that page.');
        redirect('../login.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);   // prevent session fixation
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_type'] = $user['user_type'];
}

/* ---------- shared formatting ---------- */

function money($amount): string
{
    return CURRENCY . ' ' . number_format((float) $amount, 2);
}

function img_url(?string $path, string $base = ''): string
{
    $path = $path ?: 'default.jpg';
    if (!is_file(IMG_DIR . $path)) {
        $path = 'default.jpg';
    }
    return $base . 'assets/img/' . $path;
}

function fmt_datetime(string $ts): string
{
    return date('d M Y, h:i A', strtotime($ts));
}
