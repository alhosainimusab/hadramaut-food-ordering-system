<?php
require_once __DIR__ . '/../src/functions.php';
require_login();

$user_id = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT id, order_date, total_amount, order_status, payment_status, delivery_type
                        FROM orders WHERE user_id = ? ORDER BY order_date DESC, id DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My orders';
include __DIR__ . '/../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-history"></i> My orders</h1>

<?php if (!$orders): ?>
    <div class="alert alert-info text-center">You have not placed any orders yet. <a href="menu.php" class="alert-link">Order something</a>.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>Order</th><th>Date</th><th>Total</th><th>Type</th><th>Status</th><th>Payment</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?></td>
                    <td><?= e(fmt_datetime($o['order_date'])) ?></td>
                    <td><?= money($o['total_amount']) ?></td>
                    <td><span class="badge <?= $o['delivery_type'] === 'Delivery' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($o['delivery_type']) ?></span></td>
                    <td><span class="badge <?= status_badge_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
                    <td><span class="badge <?= payment_badge_class($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></td>
                    <td><a href="view_order.php?order_id=<?= (int) $o['id'] ?>" class="btn btn-sm btn-outline-palm">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../src/footer.php'; ?>
