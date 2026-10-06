<?php
require_once __DIR__ . '/../../src/functions.php';
require_admin();

if (is_post() && isset($_POST['update_order_status'])) {
    verify_csrf();
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $status   = $_POST['new_status'] ?? '';
    $payment  = $_POST['payment_status'] ?? '';

    if (!in_array($status, ORDER_STATUSES, true) || !in_array($payment, PAYMENT_STATUSES, true)) {
        flash('danger', 'Invalid status value.');
    } else {
        $stmt = $conn->prepare('UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?');
        $stmt->bind_param('ssi', $status, $payment, $order_id);
        $stmt->execute();
        $stmt->close();
        flash('success', "Order #$order_id updated.");
    }
    redirect('manage_orders.php' . (!empty($_POST['return_query']) ? '?' . $_POST['return_query'] : ''));
}

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$sql = 'SELECT o.id, u.name AS user_name, o.order_date, o.total_amount, o.delivery_address,
               o.order_status, o.payment_method, o.payment_status, o.delivery_type
        FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1';
$params = [];
$types = '';
if ($q !== '') {
    if (ctype_digit(ltrim($q, '#'))) {
        $sql .= ' AND o.id = ?';
        $params[] = (int) ltrim($q, '#');
        $types .= 'i';
    } else {
        $sql .= ' AND u.name LIKE ?';
        $params[] = '%' . $q . '%';
        $types .= 's';
    }
}
if (in_array($status, ORDER_STATUSES, true)) {
    $sql .= ' AND o.order_status = ?';
    $params[] = $status;
    $types .= 's';
}
$sql .= ' ORDER BY o.order_date DESC, o.id DESC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$return_query = http_build_query(array_filter(['q' => $q, 'status' => $status]));

$page_title = 'Manage orders';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-box-open"></i> Manage orders</h1>

<form method="GET" class="row g-2 mb-4" role="search">
    <div class="col-md-6">
        <label for="q" class="visually-hidden">Search</label>
        <input type="search" id="q" name="q" class="form-control" placeholder="Order number (e.g. 12) or customer name" value="<?= e($q) ?>">
    </div>
    <div class="col-md-4">
        <label for="status" class="visually-hidden">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (ORDER_STATUSES as $s): ?>
                <option value="<?= $s ?>" <?= $s === $status ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-palm" type="submit"><i class="fas fa-filter"></i> Filter</button></div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Order</th><th>Customer</th><th>Date</th><th class="text-end">Amount</th><th>Type</th><th>Address</th><th>Status</th><th>Payment</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td>#<?= (int) $o['id'] ?></td>
                <td><?= e($o['user_name']) ?></td>
                <td class="text-nowrap"><?= e(fmt_datetime($o['order_date'])) ?></td>
                <td class="text-end text-nowrap"><?= money($o['total_amount']) ?></td>
                <td><span class="badge <?= $o['delivery_type'] === 'Delivery' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($o['delivery_type']) ?></span></td>
                <td><?= $o['delivery_type'] === 'Pickup' ? '<span class="text-muted">Self pickup</span>' : e(truncate($o['delivery_address'], 40)) ?></td>
                <td><span class="badge <?= status_badge_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
                <td><span class="badge <?= payment_badge_class($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span> <small class="text-muted"><?= e($o['payment_method']) ?></small></td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-palm" data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                            data-order-id="<?= (int) $o['id'] ?>" data-current-status="<?= e($o['order_status']) ?>" data-payment-status="<?= e($o['payment_status']) ?>">
                        <i class="fas fa-edit"></i> Update
                    </button>
                    <a href="view_order_details.php?order_id=<?= (int) $o['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i> View</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="9" class="text-center text-muted">No orders found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" id="modalOrderId">
            <input type="hidden" name="return_query" value="<?= e($return_query) ?>">
            <div class="modal-header">
                <h2 class="modal-title h5" id="updateStatusTitle">Update order <span id="modalOrderLabel"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newStatus" class="form-label">Order status</label>
                    <select name="new_status" id="newStatus" class="form-select">
                        <?php foreach (ORDER_STATUSES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="paymentStatus" class="form-label">Payment status</label>
                    <select name="payment_status" id="paymentStatus" class="form-select">
                        <?php foreach (PAYMENT_STATUSES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-palm" name="update_order_status" value="1">Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
