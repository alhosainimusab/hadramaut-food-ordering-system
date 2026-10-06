<?php
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/order_view.php';
require_login();

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$order    = $order_id ? load_order($conn, $order_id, $user_id) : null;

if (!$order) {
    flash('danger', 'Order not found.');
    redirect('order_history.php');
}

// Customers can cancel their own order while it is still Pending.
if (is_post() && isset($_POST['cancel_order'])) {
    verify_csrf();
    $stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE id = ? AND user_id = ? AND order_status = 'Pending'");
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $stmt->affected_rows === 1
        ? flash('success', "Order #$order_id has been cancelled.")
        : flash('warning', 'This order can no longer be cancelled.');
    $stmt->close();
    redirect('view_order.php?order_id=' . $order_id);
}

$items = load_order_items($conn, $order_id);

$page_title = 'Order #' . $order_id;
include __DIR__ . '/../src/header.php';
?>

<h1 class="section-title mb-4">Order #<?= (int) $order['id'] ?></h1>

<div class="card p-4 mb-4">
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <p class="mb-1"><strong>Placed:</strong> <?= e(fmt_datetime($order['order_date'])) ?></p>
            <p class="mb-1"><strong>Type:</strong> <?= e($order['delivery_type']) ?></p>
            <?php if ($order['delivery_type'] === 'Delivery'): ?>
                <p class="mb-1"><strong>Deliver to:</strong> <?= nl2br(e($order['delivery_address'])) ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Status:</strong> <span class="badge fs-6 <?= status_badge_class($order['order_status']) ?>"><?= e($order['order_status']) ?></span></p>
            <p class="mb-1"><strong>Payment:</strong> <?= e($order['payment_method']) ?>
                <span class="badge <?= payment_badge_class($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></p>
        </div>
    </div>

    <?php render_order_items($order, $items); ?>

    <div class="d-flex flex-wrap gap-2 justify-content-between mt-3">
        <a href="order_history.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to my orders</a>
        <?php if ($order['order_status'] === 'Pending'): ?>
            <form method="POST" data-confirm="Cancel this order?">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <button type="submit" name="cancel_order" value="1" class="btn btn-outline-danger"><i class="fas fa-ban"></i> Cancel order</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../src/footer.php'; ?>
