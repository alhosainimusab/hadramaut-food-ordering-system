<?php
require_once __DIR__ . '/../src/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone_number' => ''];

if (is_post()) {
    verify_csrf();
    $old = [
        'name'         => trim($_POST['name'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'phone_number' => trim($_POST['phone_number'] ?? ''),
    ];
    $errors = validate_registration($_POST);

    if (!$errors) {
        $stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'That email is already registered. Please log in instead.';
        }
        $stmt->close();
    }

    if (!$errors) {
        // New accounts are always customers. Admins are created in the database (see README).
        $hash  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = $old['phone_number'] !== '' ? $old['phone_number'] : null;
        $stmt  = $conn->prepare("INSERT INTO users (name, email, phone_number, password, user_type) VALUES (?, ?, ?, ?, 'user')");
        $stmt->bind_param('ssss', $old['name'], $old['email'], $phone, $hash);
        $stmt->execute();
        $stmt->close();

        flash('success', 'Registration successful. You can now log in.');
        redirect('login.php');
    }
}

$page_title = 'Register';
include __DIR__ . '/../src/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card form-card p-4">
            <h1 class="h3 text-center mb-4"><i class="fas fa-user-plus"></i> Create an account</h1>

            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= e($old['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="150" value="<?= e($old['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone number <span class="text-muted">(optional)</span></label>
                    <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= e($old['phone_number']) ?>" placeholder="012-345 6789">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required aria-describedby="pwHelp">
                    <div id="pwHelp" class="form-text">At least <?= MIN_PASSWORD_LENGTH ?> characters, with a letter and a number.</div>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-palm btn-lg">Register</button>
                </div>
            </form>

            <p class="text-center mt-3 mb-0">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../src/footer.php'; ?>
