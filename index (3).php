<?php
require_once __DIR__ . '/../src/functions.php';

$featured = $conn->query('SELECT id, name, description, price, image FROM menu_items ORDER BY RAND() LIMIT 3')
                 ->fetch_all(MYSQLI_ASSOC);

$page_title = 'Home';
$full_width = true;
include __DIR__ . '/../src/header.php';
?>

<section class="hero-section">
    <div class="container hero-content">
        <h1 class="display-4 fw-bold">Hadrami cooking, ordered online</h1>
        <p class="lead col-lg-7">Mandi, hanith and masoub from a family-style Yemeni kitchen. Order for delivery or pick it up yourself.</p>
        <a href="menu.php" class="btn btn-saffron btn-lg mt-2"><i class="fas fa-concierge-bell"></i> Browse the menu</a>
    </div>
</section>

<div class="container my-5">
    <?= render_flash() ?>

    <div class="row g-4 text-center mb-5">
        <div class="col-md-4">
            <div class="feature">
                <i class="fas fa-fire-burner feature-icon"></i>
                <h2 class="h5">Slow-cooked mains</h2>
                <p class="text-muted mb-0">Rice dishes cooked the traditional way, with the meat steamed over the rice.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature">
                <i class="fas fa-store feature-icon"></i>
                <h2 class="h5">Delivery or pickup</h2>
                <p class="text-muted mb-0">Flat <?= money(DELIVERY_FEE) ?> delivery fee, or collect from the counter for free.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature">
                <i class="fas fa-receipt feature-icon"></i>
                <h2 class="h5">Track every order</h2>
                <p class="text-muted mb-0">See each order move from pending to delivered in your order history.</p>
            </div>
        </div>
    </div>

    <h2 class="section-title mb-4">Today's picks</h2>
    <?php if ($featured): ?>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php foreach ($featured as $item): ?>
                <div class="col">
                    <div class="card h-100 menu-item-card">
                        <img src="<?= e(img_url($item['image'])) ?>" class="card-img-top" alt="<?= e($item['name']) ?>">
                        <div class="card-body d-flex flex-column">
                            <h3 class="h5 card-title"><?= e($item['name']) ?></h3>
                            <p class="card-text text-muted"><?= e($item['description']) ?></p>
                            <p class="price mt-auto mb-0"><?= money($item['price']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No dishes on the menu yet.</p>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="menu.php" class="btn btn-outline-palm btn-lg">View full menu</a>
    </div>
</div>

<?php include __DIR__ . '/../src/footer.php'; ?>
