<?php
require_once __DIR__ . '/../../src/functions.php';
require_admin();

if (is_post() && isset($_POST['delete_feedback'])) {
    verify_csrf();
    $id = (int) ($_POST['feedback_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM feedback WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash('success', 'Feedback deleted.');
    redirect('view_feedback.php');
}

$feedback = $conn->query(
    'SELECT f.id, u.name AS user_name, f.comment, f.rating, f.feedback_date
     FROM feedback f LEFT JOIN users u ON u.id = f.user_id ORDER BY f.feedback_date DESC'
)->fetch_all(MYSQLI_ASSOC);
$avg = $conn->query('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(rating) AS rated FROM feedback')->fetch_assoc();

$page_title = 'Feedback';
$base = '../';
$admin_area = true;
include __DIR__ . '/../../src/header.php';
?>

<h1 class="section-title mb-2"><i class="fas fa-comment-alt"></i> Customer feedback</h1>
<p class="text-muted mb-4">
    <?= count($feedback) ?> comments
    <?php if ($avg['rated']): ?>&middot; average rating <strong><?= e($avg['avg_rating']) ?>/5</strong> from <?= (int) $avg['rated'] ?> ratings<?php endif; ?>
</p>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark"><tr><th>Customer</th><th>Comment</th><th>Rating</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($feedback as $f): ?>
            <tr>
                <td><?= e($f['user_name'] ?? 'Deleted user') ?></td>
                <td><?= nl2br(e($f['comment'])) ?></td>
                <td class="text-nowrap" aria-label="<?= $f['rating'] ? (int) $f['rating'] . ' out of 5' : 'No rating' ?>">
                    <?php if ($f['rating']): for ($i = 1; $i <= 5; $i++): ?>
                        <i class="<?= $i <= $f['rating'] ? 'fas text-warning' : 'far text-muted' ?> fa-star"></i>
                    <?php endfor; else: ?><span class="text-muted">–</span><?php endif; ?>
                </td>
                <td class="text-nowrap"><?= e(fmt_datetime($f['feedback_date'])) ?></td>
                <td class="text-end">
                    <form method="POST" data-confirm="Delete this feedback?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="feedback_id" value="<?= (int) $f['id'] ?>">
                        <button type="submit" name="delete_feedback" value="1" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i><span class="visually-hidden">Delete</span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$feedback): ?><tr><td colspan="5" class="text-center text-muted">No feedback yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../src/footer.php'; ?>
