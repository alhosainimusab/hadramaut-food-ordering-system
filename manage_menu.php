<?php
require_once __DIR__ . '/../../src/functions.php';
require_admin();

/**
 * Validate and store an uploaded image. Returns [relative_path|null, error|null].
 * No file chosen => [null, null].
 */
function handle_image_upload(): array
{
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Image upload failed (error code ' . (int) $f['error'] . ').'];
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        return [null, 'Image must be 2 MB or smaller.'];
    }
    $name = safe_upload_name($f['name']);
    if ($name === null) {
        return [null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }
    if (@getimagesize($f['tmp_name']) === false) {
        return [null, 'The uploaded file is not a valid image.'];
    }
    $dir = IMG_DIR . UPLOAD_SUBDIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return [null, 'Upload folder is missing and could not be created.'];
    }
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        return [null, 'Could not save the image. Check folder permissions for public/assets/img/uploads/.'];
    }
    return [UPLOAD_SUBDIR . $name, null];
}

/** Only delete images that were uploaded through this page (never the seeded ones). */
function delete_uploaded_image(?string $path): void
{
    if ($path && strpos($path, UPLOAD_SUBDIR) === 0 && is_file(IMG_DIR . $path)) {
        unlink(IMG_DIR . $path);
    }
}

$errors = [];
$form = ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'category' => '', 'image' => null];

if (is_post()) {
    verify_csrf();

    // ---- Delete ----
    if (isset($_POST['delete_item'])) {
        $id = (int) $_POST['item_id'];
        $stmt = $conn->prepare('SELECT image FROM menu_items WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc()['image'] ?? null;
        $stmt->close();
        try {
            $stmt = $conn->prepare('DELETE FROM menu_items WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            delete_uploaded_image($img);
            flash('success', 'Menu item deleted.');
        } catch (mysqli_sql_exception $ex) {
            // FK from order_items (ON DELETE RESTRICT) keeps past orders intact.
            flash('warning', 'This item appears in past orders, so it cannot be deleted. Edit it instead.');
        }
        redirect('manage_menu.php');
    }

    // ---- Add / edit ----
    $form = [
        'id'          => (int) ($_POST['item_id'] ?? 0),
        'name'        => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price'       => trim($_POST['price'] ?? ''),
        'category'    => trim($_POST['category'] ?? ''),
        'image'       => null,
    ];
    if ($form['name'] === '' || mb_strlen($form['name']) > 150) $errors[] = 'Name is required (max 150 characters).';
    if ($form['description'] === '')                             $errors[] = 'Description is required.';
    if (!is_numeric($form['price']) || $form['price'] <= 0 || $form['price'] > 9999) $errors[] = 'Price must be a number between 0.01 and 9999.';
    if ($form['category'] === '' || mb_strlen($form['category']) > 100) $errors[] = 'Category is required.';

    if (!$errors) {
        [$new_image, $upload_error] = handle_image_upload();
        if ($upload_error) {
            $errors[] = $upload_error;
        }
    }

    if (!$errors) {
        $price = round((float) $form['price'], 2);
        if ($form['id'] === 0) {
            $image = $new_image ?? 'default.jpg';
            $stmt = $conn->prepare('INSERT INTO menu_items (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)');
            // NB: category is a string ("s"). The original bound it as "d" (double), which stored every category as 0.
            $stmt->bind_param('ssdss', $form['name'], $form['description'], $price, $form['category'], $image);
            $stmt->execute();
            $stmt->close();
            flash('success', 'Menu item added.');
        } else {
            $stmt = $conn->prepare('SELECT image FROM menu_items WHERE id = ?');
            $stmt->bind_param('i', $form['id']);
            $stmt->execute();
            $old_image = $stmt->get_result()->fetch_assoc()['image'] ?? null;
            $stmt->close();

            $image = $new_image ?? $old_image;
            $stmt = $conn->prepare('UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?');
            $stmt->bind_param('ssdssi', $form['name'], $form['description'], $price, $form['category'], $image, $form['id']);
            $stmt->execute();
            $stmt->close();
            if ($new_image) {
                delete_uploaded_image($old_image);
            }
            flash('success', 'Menu item updated.');
        }
        redirect('manage_menu.php');
    }
}

// ---- Edit mode ----
if (!is_post() && isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM menu_items WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $form = $row;
    } else {
        flash('danger', 'Menu item not found.');
        redirect('manage_menu.php');
    }
}
$editing = (int) $form['id'] > 0;

$items = $conn->query('SELECT m.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.menu_item_id = m.id) AS times_ordered
                       FROM menu_items m ORDER BY m.category, m.name')->fetch_all(MYSQLI_ASSOC);
$categories = array_unique(array_merge(['Mains', 'Sides', 'Desserts', 'Drinks'], array_column($items, 'category')));

$page_title = 'Manage menu';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-4"><i class="fas fa-clipboard-list"></i> Manage menu</h1>

<div class="card p-4 mb-5">
    <h2 class="h5 mb-3"><?= $editing ? 'Edit "' . e($form['name']) . '"' : 'Add a new dish' ?></h2>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form action="manage_menu.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= (int) $form['id'] ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="150" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="price" class="form-label">Price (RM)</label>
                <input type="number" step="0.01" min="0.01" max="9999" class="form-control" id="price" name="price" value="<?= e($form['price']) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <input type="text" class="form-control" id="category" name="category" list="categoryList" maxlength="100" value="<?= e($form['category']) ?>" required>
                <datalist id="categoryList">
                    <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2" required><?= e($form['description']) ?></textarea>
            </div>
            <div class="col-md-8">
                <label for="image" class="form-label">Image <span class="text-muted">(JPG/PNG/GIF/WEBP, max 2 MB<?= $editing ? ', leave empty to keep the current one' : '' ?>)</span></label>
                <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
            </div>
            <?php if ($editing): ?>
                <div class="col-md-4 d-flex align-items-end">
                    <img src="<?= e(img_url($form['image'], $base)) ?>" alt="Current image" class="thumb-lg">
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" name="save_item" value="1" class="btn btn-palm"><?= $editing ? 'Save changes' : 'Add dish' ?></button>
            <?php if ($editing): ?><a href="manage_menu.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<h2 class="h5 mb-3">All dishes (<?= count($items) ?>)</h2>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr><th>Image</th><th>Name</th><th>Category</th><th class="text-end">Price</th><th class="text-center">Times ordered</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td><img src="<?= e(img_url($row['image'], $base)) ?>" alt="" class="thumb"></td>
                <td><strong><?= e($row['name']) ?></strong><br><small class="text-muted"><?= e(truncate($row['description'], 60)) ?></small></td>
                <td><?= e($row['category']) ?></td>
                <td class="text-end"><?= money($row['price']) ?></td>
                <td class="text-center"><?= (int) $row['times_ordered'] ?></td>
                <td class="text-end text-nowrap">
                    <a href="manage_menu.php?edit=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                    <form method="POST" class="d-inline" data-confirm="Delete <?= e($row['name']) ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" name="delete_item" value="1" class="btn btn-sm btn-outline-danger" <?= $row['times_ordered'] ? 'disabled title="In past orders – cannot delete"' : '' ?>><i class="fas fa-trash-alt"></i> Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="6" class="text-center text-muted">No dishes yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
