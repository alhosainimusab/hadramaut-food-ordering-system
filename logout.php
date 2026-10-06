<?php
require_once __DIR__ . '/../src/functions.php';

if (!is_post()) {
    redirect('index.php');
}
verify_csrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
