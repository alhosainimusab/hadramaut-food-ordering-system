<?php
/**
 * Profile and password logic shared by customer pages (update_profile.php, change_password.php)
 * and admin pages (admin/update_admin_profile.php, admin/change_admin_password.php).
 * Previously this code was copy-pasted into four files.
 */

function load_profile(mysqli $conn, int $user_id): array
{
    $stmt = $conn->prepare('SELECT name, email, phone_number, address FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: [];
}

/** Returns a list of errors; empty means the profile was saved. */
function save_profile(mysqli $conn, int $user_id, array $in): array
{
    $name    = trim($in['name'] ?? '');
    $phone   = trim($in['phone_number'] ?? '');
    $address = trim($in['address'] ?? '');
    $errors  = [];

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Name is required (max 100 characters).';
    }
    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors[] = 'Phone number may contain digits, spaces, + and - only (8–15 digits).';
    }
    if (mb_strlen($address) > 500) {
        $errors[] = 'Address is too long.';
    }
    if ($errors) {
        return $errors;
    }

    $phone   = $phone !== '' ? $phone : null;
    $address = $address !== '' ? $address : null;
    $stmt = $conn->prepare('UPDATE users SET name = ?, phone_number = ?, address = ? WHERE id = ?');
    $stmt->bind_param('sssi', $name, $phone, $address, $user_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['user_name'] = $name;
    return [];
}

/** Returns a list of errors; empty means the password was changed. */
function change_password(mysqli $conn, int $user_id, array $in): array
{
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();

    if (!password_verify($in['current_password'] ?? '', $hash)) {
        return ['Your current password is incorrect.'];
    }
    $errors = validate_new_password($in['new_password'] ?? '', $in['confirm_new_password'] ?? '');
    if ($errors) {
        return $errors;
    }

    $new_hash = password_hash($in['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $new_hash, $user_id);
    $stmt->execute();
    $stmt->close();
    session_regenerate_id(true);
    return [];
}

function render_errors(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $li = implode('', array_map(fn($e) => '<li>' . e($e) . '</li>', $errors));
    return '<div class="alert alert-danger"><ul class="mb-0">' . $li . '</ul></div>';
}

function render_profile_form(array $user, array $errors, string $password_link, string $title): void
{ ?>
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card form-card p-4">
                <h1 class="h3 text-center mb-4"><i class="fas fa-user-edit"></i> <?= e($title) ?></h1>
                <?= render_errors($errors) ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= e($user['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-muted">(cannot be changed)</span></label>
                        <input type="email" class="form-control" id="email" value="<?= e($user['email'] ?? '') ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">Phone number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?= e($user['phone_number'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" maxlength="500"><?= e($user['address'] ?? '') ?></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="update_profile" value="1" class="btn btn-palm btn-lg">Save changes</button>
                    </div>
                </form>
                <hr class="my-4">
                <div class="text-center"><a href="<?= e($password_link) ?>" class="btn btn-outline-secondary"><i class="fas fa-key"></i> Change password</a></div>
            </div>
        </div>
    </div>
<?php }

function render_password_form(array $errors, string $title): void
{ ?>
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card form-card p-4">
                <h1 class="h3 text-center mb-4"><i class="fas fa-key"></i> <?= e($title) ?></h1>
                <?= render_errors($errors) ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
                        <div class="form-text">At least <?= MIN_PASSWORD_LENGTH ?> characters, with a letter and a number.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_new_password" class="form-label">Confirm new password</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="change_password" value="1" class="btn btn-palm btn-lg">Change password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php }
