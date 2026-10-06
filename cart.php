<?php
require_once __DIR__ . '/../src/functions.php';
require_login();

if (is_post()) {
    verify_csrf();
    $item_id = (int) ($_POST['menu_item_id'] ?? 0);

    if (isset($_POST['remove_item']) && isset($_SESSION['cart'][$item_id])) {
        unset($_SESSION['cart'][$item_id]);
        flash('success', 'Item removed from cart.');
    } elseif (isset($_POST['update_quantity']) && isset($_SESSION['cart'][$item_id])) {
        $qty = (int) ($_POST['quantity'] ?? 0);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$item_id]);
            flash('success', 'Item removed from cart.');
        } else {
            $_SESSION['cart'][$item_id]['quantity'] = min(99, $qty);
            flash('success', 'Cart updated.');
        }
    }
    redirect('cart.php');
}

$cart_items = $_SESSION['cart'] ?? [];
$subtotal   = cart_subtotal($cart_items);

$page_title = 'Cart';
include __DIR__ . '/../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-shopping-cart"></i> Your cart</h1>

<?php if (!$cart_items): ?>
    <div class="alert alert-info text-center">
        Your cart is empty. <a href="menu.php" class="alert-link">Browse the menu</a>.
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <ul class="list-group">
                <?php foreach ($cart_items as $id => $item): ?>
                    <li class="list-group-item d-flex flex-wrap gap-3 justify-content-between align-items-center cart-item">
                        <div class="d-flex align-items-center">
                            <img src="<?= e(img_url($item['image'] ?? null)) ?>" class="rounded me-3" alt="">
                            <div>
                                <h2 class="h6 my-0"><?= e($item['name']) ?></h2>
                                <small class="text-muted"><?= money($item['price']) ?> each</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <form action="cart.php" method="POST" class="d-flex">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_item_id" value="<?= (int) $id ?>">
                                <label class="visually-hidden" for="q<?= (int) $id ?>">Quantity</label>
                                <input type="number" id="q<?= (int) $id ?>" name="quantity" value="<?= (int) $item['quantity'] ?>" min="0" max="99" class="form-control form-control-sm text-center qty-input">
                                <button type="submit" name="update_quantity" value="1" class="btn btn-sm btn-outline-secondary ms-2" title="Update quantity"><i class="fas fa-sync-alt"></i><span class="visually-hidden">Update</span></button>
                            </form>
                            <span class="fw-bold text-nowrap line-total"><?= money($item['price'] * $item['quantity']) ?></span>
                            <form action="cart.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_item_id" value="<?= (int) $id ?>">
                                <button type="submit" name="remove_item" value="1" class="btn btn-sm btn-outline-danger" title="Remove"><i class="fas fa-times"></i><span class="visually-hidden">Remove</span></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 summary-card">
                <h2 class="h5 mb-3">Summary</h2>
                <dl class="row mb-3">
                    <dt class="col-7">Items</dt><dd class="col-5 text-end"><?= cart_item_count($cart_items) ?></dd>
                    <dt class="col-7">Subtotal</dt><dd class="col-5 text-end fw-bold"><?= money($subtotal) ?></dd>
                </dl>
                <p class="small text-muted">Delivery fee (<?= money(DELIVERY_FEE) ?>) is added at checkout if you choose delivery.</p>
                <a href="checkout.php" class="btn btn-palm btn-lg w-100">Checkout</a>
                <a href="menu.php" class="btn btn-outline-secondary w-100 mt-2">Continue shopping</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../src/footer.php'; ?>
