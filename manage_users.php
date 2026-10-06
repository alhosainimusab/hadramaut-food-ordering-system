<?php
require_once __DIR__ . '/../../src/functions.php';
require_admin();

if (is_post() && isset($_POST['delete_user'])) {
    verify_csrf();
    $id = (int) ($_POST['user_id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        flash('danger', 'You cannot delete your own account.');
    } else {
        // Orders, order items and feedback are removed by ON DELETE CASCADE.
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND user_type = 'user'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->affected_rows === 1 ? flash('success', 'Customer deleted.') : flash('warning', 'Only customer accounts can be deleted.');
        $stmt->close();
    }
    redirect('manage_users.php');
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT u.id, u.name, u.email, u.phone_number, u.address, u.user_type, u.created_at,
               COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.order_status <> 'Cancelled' THEN o.total_amount END), 0) AS spent
        FROM users u LEFT JOIN orders o ON o.user_id = u.id";
if ($q !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?';
}
$sql .= ' GROUP BY u.id ORDER BY u.user_type, u.created_at DESC';
$stmt = $conn->prepare($sql);
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt->bind_param('ss', $like, $like);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Manage users';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-users-cog"></i> Users</h1>

<form method="GET" class="row g-2 mb-4" role="search">
    <div class="col-md-8">
        <label for="q" class="visually-hidden">Search users</label>
        <input type="search" id="q" name="q" class="form-control" placeholder="Search by name or email" value="<?= e($q) ?>">
    </div>
    <div class="col-md-4 d-grid"><button class="btn btn-palm" type="submit"><i class="fas fa-search"></i> Search</button></div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th class="text-center">Orders</th><th class="text-end">Spent</th><th>Joined</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?><br><small class="text-muted"><?= e(truncate($u['address'] ?? '', 30)) ?></small></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['phone_number'] ?: '–') ?></td>
                <td><span class="badge <?= $u['user_type'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>"><?= e($u['user_type']) ?></span></td>
                <td class="text-center"><?= (int) $u['orders'] ?></td>
                <td class="text-end"><?= money($u['spent']) ?></td>
                <td class="text-nowrap"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                <td class="text-end">
                    <?php if ($u['user_type'] === 'user'): ?>
                        <form method="POST" data-confirm="Delete <?= e($u['name']) ?> and all of their orders and feedback?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" name="delete_user" value="1" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Delete</button>
                        </form>
                    <?php else: ?>
                        <span class="text-muted small">Admin</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="8" class="text-center text-muted">No users found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
