<?php
require_once __DIR__ . '/../src/functions.php';

// ---- Add to cart (POST-redirect-GET) ----
if (is_post() && isset($_POST['add_to_cart'])) {
    verify_csrf();
    $back = 'menu.php' . (!empty($_POST['return_query']) ? '?' . $_POST['return_query'] : '');

    if (!is_logged_in()) {
        flash('warning', 'Please log in to add items to your cart.');
        redirect('login.php');
    }
    if (is_admin()) {
        flash('warning', 'Admin accounts cannot place orders.');
        redirect($back);
    }

    $item_id  = (int) ($_POST['menu_item_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if ($quantity < 1 || $quantity > 99) {
        flash('danger', 'Quantity must be between 1 and 99.');
        redirect($back);
    }

    $stmt = $conn->prepare('SELECT id, name, price, image FROM menu_items WHERE id = ?');
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        flash('danger', 'That item no longer exists.');
        redirect($back);
    }

    $cart = $_SESSION['cart'] ?? [];
    $existing = $cart[$item_id]['quantity'] ?? 0;
    $cart[$item_id] = [
        'id'       => (int) $item['id'],
        'name'     => $item['name'],
        'price'    => (float) $item['price'],
        'image'    => $item['image'],
        'quantity' => min(99, $existing + $quantity),
    ];
    $_SESSION['cart'] = $cart;

    flash('success', $item['name'] . ' added to your cart.');
    redirect($back);
}

// ---- Listing with search + category filter ----
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$categories = array_column(
    $conn->query("SELECT DISTINCT category FROM menu_items WHERE category IS NOT NULL AND category <> '' ORDER BY category")
         ->fetch_all(MYSQLI_ASSOC),
    'category'
);

$sql = 'SELECT id, name, description, price, category, image FROM menu_items WHERE 1=1';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
    $types .= 'ss';
}
if ($category !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category;
    $types .= 's';
}
$sql .= " ORDER BY COALESCE(NULLIF(FIELD(category, 'Mains', 'Sides', 'Desserts', 'Drinks'), 0), 99), category, name";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grouped = [];
foreach ($items as $it) {
    $grouped[$it['category'] ?: 'Other'][] = $it;
}
$return_query = http_build_query(array_filter(['search' => $search, 'category' => $category]));

$page_title = 'Menu';
include __DIR__ . '/../src/header.php';
?>

<h1 class="section-title mb-4">Our menu</h1>

<form action="menu.php" method="GET" class="row g-2 mb-4" role="search">
    <div class="col-md-6">
        <label for="search" class="visually-hidden">Search dishes</label>
        <input type="search" id="search" name="search" class="form-control" placeholder="Search dishes…" value="<?= e($search) ?>">
    </div>
    <div class="col-md-4">
        <label for="category" class="visually-hidden">Category</label>
        <select id="category" name="category" class="form-select">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-palm"><i class="fas fa-search"></i> Search</button>
    </div>
</form>

<?php if (!$items): ?>
    <div class="alert alert-info text-center">No dishes match your search. <a href="menu.php">Clear filters</a></div>
<?php endif; ?>

<?php foreach ($grouped as $cat => $dishes): ?>
    <h2 class="h4 category-heading mt-4 mb-3"><?= e($cat) ?></h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
        <?php foreach ($dishes as $row): ?>
            <div class="col">
                <div class="card h-100 menu-item-card">
                    <img src="<?= e(img_url($row['image'])) ?>" class="card-img-top" alt="<?= e($row['name']) ?>" loading="lazy">
                    <div class="card-body d-flex flex-column">
                        <h3 class="h5 card-title"><?= e($row['name']) ?></h3>
                        <p class="card-text text-muted"><?= e($row['description']) ?></p>
                        <p class="price mt-auto"><?= money($row['price']) ?></p>
                        <?php if (!is_admin()): ?>
                            <form action="menu.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="menu_item_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="return_query" value="<?= e($return_query) ?>">
                                <div class="input-group">
                                    <label class="visually-hidden" for="qty<?= (int) $row['id'] ?>">Quantity</label>
                                    <input type="number" id="qty<?= (int) $row['id'] ?>" name="quantity" class="form-control qty-input" value="1" min="1" max="99" required>
                                    <button type="submit" name="add_to_cart" value="1" class="btn btn-saffron"><i class="fas fa-cart-plus"></i> Add</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../src/footer.php'; ?>
