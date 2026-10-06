<?php
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/account.php';
require_admin();

$errors = [];
if (is_post()) {
    verify_csrf();
    $errors = change_password($conn, (int) $_SESSION['user_id'], $_POST);
    if (!$errors) {
        flash('success', 'Your password has been changed.');
        redirect('update_admin_profile.php');
    }
}

$page_title = 'Change admin password';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
render_password_form($errors, 'Change admin password');
include __DIR__ . '/../../src/footer.php';
