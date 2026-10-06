<?php
/**
 * Shared page header. Before including, a page may set:
 *   $page_title  – text for <title>
 *   $base        – path to web root: '' for root pages, '../' for admin pages
 *   $admin_area  – true to show the admin navigation
 */
$base       = $base ?? '';
$admin_area = $admin_area ?? false;
$page_title = isset($page_title) ? $page_title . ' | ' . APP_NAME : APP_NAME;
$current    = basename($_SERVER['PHP_SELF']);

$nav = $admin_area
    ? ['dashboard.php' => 'Dashboard', 'manage_menu.php' => 'Menu', 'manage_orders.php' => 'Orders',
       'manage_users.php' => 'Users', 'view_feedback.php' => 'Feedback']
    : ['index.php' => 'Home', 'menu.php' => 'Menu'] + (is_logged_in() && !is_admin()
        ? ['order_history.php' => 'My Orders', 'feedback.php' => 'Feedback'] : []);
$cart_count = cart_item_count($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= $admin_area ? 'dashboard.php' : $base . 'index.php' ?>">
            <i class="fas fa-utensils"></i> <strong>Hadramaut</strong><?= $admin_area ? ' <span class="badge brand-badge">Admin</span>' : '' ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php foreach ($nav as $href => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $current === $href ? ' active' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="navbar-nav align-items-lg-center">
                <?php if (!$admin_area && !is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $current === 'cart.php' ? ' active' : '' ?>" href="<?= $base ?>cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if ($cart_count > 0): ?><span class="badge cart-badge ms-1"><?= $cart_count ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas <?= is_admin() ? 'fa-user-shield' : 'fa-user-circle' ?>"></i> <?= e($_SESSION['user_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (is_admin()): ?>
                                <?php if (!$admin_area): ?><li><a class="dropdown-item" href="admin/dashboard.php">Admin dashboard</a></li><?php endif; ?>
                                <li><a class="dropdown-item" href="<?= $admin_area ? '' : 'admin/' ?>update_admin_profile.php">My profile</a></li>
                                <li><a class="dropdown-item" href="<?= $admin_area ? '' : 'admin/' ?>change_admin_password.php">Change password</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= $base ?>update_profile.php">My profile</a></li>
                                <li><a class="dropdown-item" href="<?= $base ?>change_password.php">Change password</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?= $base ?>logout.php" method="POST" class="px-3">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-link dropdown-item px-0">Log out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $base ?>login.php">Log in</a></li>
                    <li class="nav-item"><a class="btn btn-saffron btn-sm ms-lg-2" href="<?= $base ?>register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1 <?= !empty($full_width) ? '' : 'container my-4' ?>">
<?= empty($full_width) ? render_flash() : '' ?>
