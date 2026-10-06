<?php
require_once __DIR__ . '/../src/functions.php';
require_login();

if (is_admin()) {
    flash('warning', 'Admin accounts cannot place orders.');
    redirect('admin/dashboard.php');
}
if (empty($_SESSION['cart'])) {
    flash('warning', 'Your cart is empty. Add some dishes first.');
    redirect('menu.php');
}

$user_id = (int) $_SESSION['user_id'];

/*
 * Re-read prices from the database instead of trusting the session copy,
 * so a price change (or a deleted item) between "add to cart" and checkout is handled.
 */
$ids = array_map('intval', array_keys($_SESSION['cart']));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("SELECT id, name, price, image FROM menu_items WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$db_items = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $db_items[(int) $row['id']] = $row;
}
$stmt->close();

$cart_items = [];
foreach ($_SESSION['cart'] as $id => $line) {
    if (!isset($db_items[$id])) {
        unset($_SESSION['cart'][$id]);           // item was removed from the menu
        continue;
    }
    $cart_items[$id] = ['id' => $id, 'name' => $db_items[$id]['name'],
                        'price' => (float) $db_items[$id]['price'], 'quantity' => (int) $line['quantity']];
}
if (!$cart_items) {
    flash('warning', 'The items in your cart are no longer available.');
    redirect('menu.php');
}
$subtotal = cart_subtotal($cart_items);

$stmt = $conn->prepare('SELECT address FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$saved_address = $stmt->get_result()->fetch_assoc()['address'] ?? '';
$stmt->close();

$errors = [];
$form = ['delivery_type' => 'Delivery', 'payment_method' => 'Cash', 'delivery_address' => $saved_address];

if (is_post() && isset($_POST['place_order'])) {
    verify_csrf();
    $form['delivery_type']    = $_POST['delivery_type'] ?? '';
    $form['payment_method']   = $_POST['payment_method'] ?? '';
    $form['delivery_address'] = trim($_POST['delivery_address'] ?? '');

    if (!in_array($form['delivery_type'], DELIVERY_TYPES, true)) {
        $errors[] = 'Please choose delivery or pickup.';
    }
    if (!in_array($form['payment_method'], PAYMENT_METHODS, true)) {
        $errors[] = 'Please choose a payment method.';
    }
    if ($form['delivery_type'] === 'Delivery' && mb_strlen($form['delivery_address']) < 10) {
        $errors[] = 'Please enter a full delivery address.';
    }

    if (!$errors) {
        $fee      = delivery_fee($form['delivery_type'], DELIVERY_FEE);
        $total    = round($subtotal + $fee, 2);
        $address  = $form['delivery_type'] === 'Pickup' ? 'Self pickup' : $form['delivery_address'];
        // Card payment is simulated: no card data is sent to or stored on the server.
        $pay_status = $form['payment_method'] === 'Card' ? 'Paid' : 'Pending';

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO orders (user_id, total_amount, delivery_fee, delivery_type, delivery_address, payment_method, payment_status, order_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')"
            );
            $stmt->bind_param('iddssss', $user_id, $total, $fee, $form['delivery_type'], $address, $form['payment_method'], $pay_status);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_order) VALUES (?, ?, ?, ?)');
            foreach ($cart_items as $item) {
                $stmt->bind_param('iiid', $order_id, $item['id'], $item['quantity'], $item['price']);
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();
            unset($_SESSION['cart']);
            flash('success', "Order #$order_id placed. We'll start preparing it shortly.");
            redirect('view_order.php?order_id=' . $order_id);
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            error_log('Checkout failed: ' . $ex->getMessage());
            $errors[] = 'Sorry, your order could not be placed. Please try again.';
        }
    }
}

$page_title = 'Checkout';
include __DIR__ . '/../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-cash-register"></i> Checkout</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form action="checkout.php" method="POST" class="row g-4">
    <?= csrf_field() ?>
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">How would you like your order?</h2>
            <?php foreach (DELIVERY_TYPES as $type): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="delivery_type" id="dt<?= $type ?>" value="<?= $type ?>" <?= $form['delivery_type'] === $type ? 'checked' : '' ?>>
                    <label class="form-check-label" for="dt<?= $type ?>">
                        <?= $type === 'Delivery' ? 'Delivery (' . money(DELIVERY_FEE) . ')' : 'Self pickup (free)' ?>
                    </label>
                </div>
            <?php endforeach; ?>

            <div class="mt-3" id="address-group">
                <label for="delivery_address" class="form-label">Delivery address</label>
                <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3"><?= e($form['delivery_address']) ?></textarea>
                <div class="form-text">Saved address from your profile is filled in automatically.</div>
            </div>
        </div>

        <div class="card p-4">
            <h2 class="h5 mb-3">Payment</h2>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmCash" value="Cash" <?= $form['payment_method'] === 'Cash' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pmCash">Cash on delivery / at counter</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method" id="pmCard" value="Card" <?= $form['payment_method'] === 'Card' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pmCard">Card <span class="badge text-bg-light border">simulated</span></label>
            </div>

            <!-- Demo only: these inputs have no name attribute, so card details never leave the browser. -->
            <fieldset id="card-form" class="border rounded p-3 mt-3" hidden>
                <legend class="h6 float-none w-auto px-2 mb-0">Card details (demo)</legend>
                <p class="small text-muted">No real payment is processed. Use any test number such as 4242 4242 4242 4242.</p>
                <div class="mb-3">
                    <label class="form-label" for="cardNumber">Card number</label>
                    <input type="text" id="cardNumber" class="form-control" inputmode="numeric" autocomplete="off" pattern="[0-9 ]{13,19}" placeholder="4242 4242 4242 4242">
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label" for="cardExp">Expiry</label>
                        <input type="text" id="cardExp" class="form-control" autocomplete="off" pattern="(0[1-9]|1[0-2])/[0-9]{2}" placeholder="MM/YY">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="cardCvv">CVV</label>
                        <input type="password" id="cardCvv" class="form-control" autocomplete="off" pattern="[0-9]{3,4}" placeholder="123">
                    </div>
                </div>
            </fieldset>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card p-4 summary-card">
            <h2 class="h5 mb-3">Order summary</h2>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($cart_items as $item): ?>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span><?= e($item['name']) ?> <span class="text-muted">× <?= (int) $item['quantity'] ?></span></span>
                        <span><?= money($item['price'] * $item['quantity']) ?></span>
                    </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between px-0"><span>Subtotal</span><span><?= money($subtotal) ?></span></li>
                <li class="list-group-item d-flex justify-content-between px-0"><span>Delivery fee</span><span id="fee-line"><?= money(delivery_fee($form['delivery_type'], DELIVERY_FEE)) ?></span></li>
                <li class="list-group-item d-flex justify-content-between px-0 fw-bold fs-5">
                    <span>Total</span>
                    <span id="grand-total" data-subtotal="<?= $subtotal ?>" data-fee="<?= DELIVERY_FEE ?>"><?= money($subtotal + delivery_fee($form['delivery_type'], DELIVERY_FEE)) ?></span>
                </li>
            </ul>
            <button class="btn btn-palm btn-lg w-100" type="submit" name="place_order" value="1"><i class="fas fa-check"></i> Place order</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../src/footer.php'; ?>
