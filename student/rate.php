<?php
// student/rate.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('student');

$user       = current_user();
$pdo        = db();
$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT b.*, u.name AS tutor_name, u.avatar AS tutor_avatar, t.tutor_id
    FROM bookings b
    JOIN tutors t ON t.tutor_id = b.tutor_id
    JOIN users  u ON u.user_id  = t.user_id
    LEFT JOIN ratings r ON r.booking_id = b.booking_id
    WHERE b.booking_id = ? AND b.student_id = ? AND b.status = 'completed' AND r.rating_id IS NULL
");
$stmt->execute([$booking_id, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('error', 'This session cannot be rated — it may already have a rating or isn\'t completed.');
    header('Location: /peer-tutoring/student/history.php'); exit;
}

$errors  = [];
$score   = 0;
$comment = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score   = (int)($_POST['score']   ?? 0);
    $comment = trim($_POST['comment']  ?? '');

    if ($score < 1 || $score > 5) $errors[] = 'Please select a star rating.';

    if (!$errors) {
        $pdo->prepare("INSERT INTO ratings (booking_id, student_id, tutor_id, score, comment) VALUES (?,?,?,?,?)")
            ->execute([$booking_id, $user['id'], $booking['tutor_id'], $score, $comment]);

        // Recalculate avg_rating on tutors table
        $pdo->prepare("UPDATE tutors SET
            avg_rating   = (SELECT ROUND(AVG(score),2) FROM ratings WHERE tutor_id = ?),
            rating_count = (SELECT COUNT(*)             FROM ratings WHERE tutor_id = ?)
            WHERE tutor_id = ?")
            ->execute([$booking['tutor_id'], $booking['tutor_id'], $booking['tutor_id']]);

        flash('success', 'Thank you! Your rating has been submitted and will help future students.');
        header('Location: /peer-tutoring/student/history.php'); exit;
    }
}

$tav      = avatar_url($booking['tutor_avatar']);
$initials = strtoupper(substr($booking['tutor_name'], 0, 1));

$page_title = 'Rate Session';
include __DIR__ . '/../includes/header.php';
?>

<div style="max-width:520px;margin:0 auto;">
<div class="page-header">
  <h1>Rate your session</h1>
  <p><a href="/peer-tutoring/student/history.php">← Back to sessions</a></p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error"><?php foreach($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.9rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
        <?php if ($tav): ?><img src="<?= $tav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $initials ?><?php endif; ?>
      </div>
      <div>
        <h3 style="margin:0;"><?= htmlspecialchars($booking['tutor_name']) ?></h3>
        <div style="font-size:.78rem;color:var(--text-400);margin-top:2px;">
          <?= date('D j M Y', strtotime($booking['session_date'])) ?> · <?= substr($booking['session_time'],0,5) ?> · <?= htmlspecialchars($booking['subject']) ?>
          · <span style="text-transform:capitalize;"><?= $booking['session_type'] ?? 'online' ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group">
        <label style="margin-bottom:10px;display:block;">How would you rate this session?</label>
        <div class="star-input-group">
          <?php for ($i=5;$i>=1;$i--): ?>
            <label><input type="radio" name="score" value="<?=$i?>" <?=$score===$i?'checked':''?>>★</label>
          <?php endfor; ?>
        </div>
        <div style="font-size:.78rem;color:var(--text-400);margin-top:8px;">1 star = poor &nbsp;·&nbsp; 3 stars = good &nbsp;·&nbsp; 5 stars = excellent</div>
      </div>
      <div class="form-group">
        <label for="comment">Comments <span class="text-muted">(optional)</span></label>
        <textarea id="comment" name="comment" class="form-control" rows="3"
                  placeholder="What was most helpful? Any suggestions for the tutor?"><?= htmlspecialchars($comment) ?></textarea>
      </div>
      <p style="font-size:.78rem;color:var(--text-400);margin-bottom:16px;">
        Your rating updates this tutor's recommendation score — it directly affects how they appear in future searches.
      </p>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary btn-lg">Submit Rating</button>
        <a href="/peer-tutoring/student/history.php" class="btn btn-outline-dark btn-lg">Skip</a>
      </div>
    </form>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
