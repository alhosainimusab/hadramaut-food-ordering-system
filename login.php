<?php
require_once __DIR__ . '/../src/functions.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'index.php');
}

$error = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, name, password, user_type FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        redirect($user['user_type'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
    }
    $error = 'Invalid email or password.';
}

$page_title = 'Log in';
include __DIR__ . '/../src/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card form-card p-4">
            <h1 class="h3 text-center mb-4"><i class="fas fa-sign-in-alt"></i> Log in</h1>

            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-palm btn-lg">Log in</button>
                </div>
            </form>

            <p class="text-center mt-3 mb-0">No account yet? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../src/footer.php'; ?>
