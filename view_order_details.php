<?php
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/order_view.php';
require_admin();   // checked BEFORE any output (the original included the header first)

$order_id = (int) ($_GET['order_id'] ?? 0);
$order = $order_id ? load_order($conn, $order_id) : null;
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('manage_orders.php');
}
$items = load_order_items($conn, $order_id);

$page_title = 'Order #' . $order_id;
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-4">Order #<?= (int) $order['id'] ?></h1>

<div class="card p-4 mb-4">
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <p class="mb-1"><strong>Customer:</strong> <?= e($order['user_name']) ?></p>
            <p class="mb-1"><strong>Email:</strong> <?= e($order['user_email']) ?></p>
            <p class="mb-1"><strong>Phone:</strong> <?= e($order['user_phone'] ?: '–') ?></p>
            <p class="mb-1"><strong>Placed:</strong> <?= e(fmt_datetime($order['order_date'])) ?></p>
            <p class="mb-1"><strong><?= $order['delivery_type'] === 'Pickup' ? 'Pickup' : 'Deliver to' ?>:</strong>
                <?= $order['delivery_type'] === 'Pickup' ? 'Customer collects at counter' : nl2br(e($order['delivery_address'])) ?></p>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1"><strong>Status:</strong> <span class="badge fs-6 <?= status_badge_class($order['order_status']) ?>"><?= e($order['order_status']) ?></span></p>
            <p class="mb-1"><strong>Payment:</strong> <?= e($order['payment_method']) ?>
                <span class="badge <?= payment_badge_class($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></p>
        </div>
    </div>
    <?php render_order_items($order, $items, $base); ?>
    <div class="mt-3"><a href="manage_orders.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to orders</a></div>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
