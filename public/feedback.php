<?php
require_once __DIR__ . '/../src/functions.php';
require_login();

$errors = [];
$comment = '';
$rating = '';

if (is_post()) {
    verify_csrf();
    $comment = trim($_POST['comment'] ?? '');
    $rating  = $_POST['rating'] ?? '';

    if ($comment === '' || mb_strlen($comment) > 1000) {
        $errors[] = 'Please write a comment (max 1000 characters).';
    }
    if ($rating !== '' && !is_valid_rating($rating)) {
        $errors[] = 'Rating must be between 1 and 5.';
    }

    if (!$errors) {
        $user_id = (int) $_SESSION['user_id'];
        $rating_val = $rating === '' ? null : (int) $rating;
        $stmt = $conn->prepare('INSERT INTO feedback (user_id, comment, rating) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $user_id, $comment, $rating_val);
        $stmt->execute();
        $stmt->close();
        flash('success', 'Thank you for your feedback!');
        redirect('feedback.php');
    }
}

$page_title = 'Feedback';
include __DIR__ . '/../src/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card form-card p-4">
            <h1 class="h3 text-center mb-4"><i class="fas fa-comment-dots"></i> Send us feedback</h1>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form action="feedback.php" method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="comment" class="form-label">Comments or suggestions</label>
                    <textarea class="form-control" id="comment" name="comment" rows="5" maxlength="1000" required><?= e($comment) ?></textarea>
                </div>
                <fieldset class="mb-4">
                    <legend class="form-label fs-6">Rating <span class="text-muted">(optional)</span></legend>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" <?= (string) $rating === (string) $i ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rating<?= $i ?>"><?= $i ?> <i class="fas fa-star text-warning"></i></label>
                        </div>
                    <?php endfor; ?>
                </fieldset>
                <div class="d-grid">
                    <button type="submit" class="btn btn-palm btn-lg"><i class="fas fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../src/footer.php'; ?>
