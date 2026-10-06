<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/account.php';
require_login();
if (is_admin()) {
    redirect('admin/change_admin_password.php');
}

$errors = [];
if (is_post()) {
    verify_csrf();
    $errors = change_password($conn, (int) $_SESSION['user_id'], $_POST);
    if (!$errors) {
        flash('success', 'Your password has been changed.');
        redirect('update_profile.php');
    }
}

$page_title = 'Change password';
include __DIR__ . '/../src/header.php';
render_password_form($errors, 'Change password');
include __DIR__ . '/../src/footer.php';
