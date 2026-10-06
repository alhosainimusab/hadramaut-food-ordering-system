<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/account.php';
require_login();
if (is_admin()) {
    redirect('admin/update_admin_profile.php');
}

$user_id = (int) $_SESSION['user_id'];
$errors = [];
if (is_post()) {
    verify_csrf();
    $errors = save_profile($conn, $user_id, $_POST);
    if (!$errors) {
        flash('success', 'Profile updated.');
        redirect('update_profile.php');
    }
}
$user = is_post() ? array_merge(load_profile($conn, $user_id), $_POST) : load_profile($conn, $user_id);

$page_title = 'My profile';
include __DIR__ . '/../src/header.php';
render_profile_form($user, $errors, 'change_password.php', 'My profile');
include __DIR__ . '/../src/footer.php';
