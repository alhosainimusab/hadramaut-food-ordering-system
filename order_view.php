<?php
/** Order loading + items table shared by view_order.php (customer) and admin/view_order_details.php. */

/** Load an order. If $owner_id is given, only return it when it belongs to that user. */
function load_order(mysqli $conn, int $order_id, ?int $owner_id = null): ?array
{
    $sql = 'SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone_number AS user_phone
            FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?';
    if ($owner_id !== null) {
        $sql .= ' AND o.user_id = ?';
    }
    $stmt = $conn->prepare($sql);
    $owner_id !== null ? $stmt->bind_param('ii', $order_id, $owner_id) : $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $order ?: null;
}

function load_order_items(mysqli $conn, int $order_id): array
{
    $stmt = $conn->prepare('SELECT oi.quantity, oi.price_at_order, m.name, m.image
                            FROM order_items oi JOIN menu_items m ON m.id = oi.menu_item_id
                            WHERE oi.order_id = ? ORDER BY oi.id');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
}

function render_order_items(array $order, array $items, string $base = ''): void
{
    if (!$items) {
        echo '<div class="alert alert-warning">No items found for this order.</div>';
        return;
    } ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Unit price</th><th class="text-end">Subtotal</th></tr>
            </thead>
            <tbody>
            <?php $sum = 0; foreach ($items as $it): $line = $it['price_at_order'] * $it['quantity']; $sum += $line; ?>
                <tr>
                    <td><img src="<?= e(img_url($it['image'], $base)) ?>" alt="" class="thumb me-2"> <?= e($it['name']) ?></td>
                    <td class="text-center"><?= (int) $it['quantity'] ?></td>
                    <td class="text-end"><?= money($it['price_at_order']) ?></td>
                    <td class="text-end"><?= money($line) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" class="text-end">Items</td><td class="text-end"><?= money($sum) ?></td></tr>
                <tr><td colspan="3" class="text-end">Delivery fee</td><td class="text-end"><?= money($order['delivery_fee']) ?></td></tr>
                <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end"><?= money($order['total_amount']) ?></td></tr>
            </tfoot>
        </table>
    </div>
<?php }
