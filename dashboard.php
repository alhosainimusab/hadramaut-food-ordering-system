<?php
require_once __DIR__ . '/../../src/functions.php';
require_admin();

$one = fn(string $sql) => $conn->query($sql)->fetch_row()[0];

$stats = [
    'orders'   => (int) $one('SELECT COUNT(*) FROM orders'),
    'pending'  => (int) $one("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'"),
    'users'    => (int) $one("SELECT COUNT(*) FROM users WHERE user_type = 'user'"),
    'feedback' => (int) $one('SELECT COUNT(*) FROM feedback'),
    // Revenue = paid orders that were not cancelled.
    'revenue'  => (float) $one("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'Paid' AND order_status <> 'Cancelled'"),
];

$latest = $conn->query(
    'SELECT o.id, u.name AS user_name, o.total_amount, o.order_status, o.order_date
     FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.order_date DESC, o.id DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$top_items = $conn->query(
    "SELECT m.name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.price_at_order) AS sales
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id AND o.order_status <> 'Cancelled'
     JOIN menu_items m ON m.id = oi.menu_item_id
     GROUP BY m.id, m.name ORDER BY qty DESC, sales DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$by_status = [];
foreach ($conn->query('SELECT order_status, COUNT(*) AS n FROM orders GROUP BY order_status')->fetch_all(MYSQLI_ASSOC) as $r) {
    $by_status[$r['order_status']] = (int) $r['n'];
}

$page_title = 'Admin dashboard';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-gauge"></i> Dashboard</h1>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total orders', $stats['orders'], 'fa-receipt'],
        ['Pending orders', $stats['pending'], 'fa-hourglass-half'],
        ['Customers', $stats['users'], 'fa-users'],
        ['Feedback', $stats['feedback'], 'fa-comment-dots'],
        ['Revenue (paid)', money($stats['revenue']), 'fa-sack-dollar'],
    ] as [$label, $value, $icon]): ?>
        <div class="col-6 col-lg">
            <div class="stat-card h-100">
                <i class="fas <?= $icon ?> stat-icon" aria-hidden="true"></i>
                <div class="stat-value"><?= e($value) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Latest orders</h2>
                <a href="manage_orders.php" class="small">All orders</a>
            </div>
            <?php if ($latest): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($latest as $o): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <a href="view_order_details.php?order_id=<?= (int) $o['id'] ?>" class="fw-semibold">#<?= (int) $o['id'] ?></a>
                                by <?= e($o['user_name']) ?><br>
                                <small class="text-muted"><?= e(fmt_datetime($o['order_date'])) ?></small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?= money($o['total_amount']) ?></div>
                                <span class="badge <?= status_badge_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted mb-0">No orders yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Best sellers</h2>
            <?php if ($top_items): $max = max(array_column($top_items, 'qty')); ?>
                <?php foreach ($top_items as $t): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><?= e($t['name']) ?></span>
                            <span><?= (int) $t['qty'] ?> sold &middot; <?= money($t['sales']) ?></span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="<?= e($t['name']) ?> units sold" aria-valuenow="<?= (int) $t['qty'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) $max ?>" style="height: 8px">
                            <div class="progress-bar bar-saffron" style="width: <?= round($t['qty'] / $max * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted mb-0">No sales yet.</p>
            <?php endif; ?>
        </div>

        <div class="card p-4">
            <h2 class="h5 mb-3">Orders by status</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (ORDER_STATUSES as $s): ?>
                    <a href="manage_orders.php?status=<?= e($s) ?>" class="badge fs-6 text-decoration-none <?= status_badge_class($s) ?>">
                        <?= e($s) ?>: <?= $by_status[$s] ?? 0 ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
