<?php
// student/history.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
auth_require('student');

$user = current_user();
$pdo  = db();

// Cancel booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking_id'])) {
    $bid = (int)$_POST['cancel_booking_id'];
    $upd = $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE booking_id=? AND student_id=? AND status IN ('pending','confirmed')");
    $upd->execute([$bid, $user['id']]);
    flash('success', 'Booking cancelled.');
    header('Location: /peer-tutoring/student/history.php'); exit;
}

$bookings = $pdo->prepare("
    SELECT b.*, u.name AS tutor_name, t.tutor_id,
           r.rating_id, r.score AS rated_score
    FROM bookings b
    JOIN tutors t ON t.tutor_id = b.tutor_id
    JOIN users  u ON u.user_id  = t.user_id
    LEFT JOIN ratings r ON r.booking_id = b.booking_id
    WHERE b.student_id = ?
    ORDER BY b.session_date DESC, b.session_time DESC
");
$bookings->execute([$user['id']]);
$all = $bookings->fetchAll();

$page_title = 'My Sessions';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>My Sessions</h1>
  <p>All your tutoring bookings — past and upcoming.</p>
</div>

<?php if (empty($all)): ?>
  <div class="empty-state">
    <div class="empty-icon">📚</div>
    <h2>No sessions yet</h2>
    <p>You haven't booked any tutoring sessions.</p>
    <a href="/peer-tutoring/student/search.php" class="btn btn-primary">Find a Tutor</a>
  </div>
<?php else: ?>

<!-- Summary counts -->
<div class="stat-grid mb-3">
  <?php
  $counts = array_count_values(array_column($all, 'status'));
  $labels = ['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'];
  foreach ($labels as $k=>$lbl): ?>
  <div class="stat-card">
    <div class="stat-label"><?= $lbl ?></div>
    <div class="stat-value"><?= $counts[$k] ?? 0 ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tutor</th>
          <th>Subject</th>
          <th>Date & Time</th>
          <th>Type</th><th>Duration</th>
          <th>Status</th>
          <th>Rating</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all as $b): ?>
        <tr>
          <td>
            <a href="/peer-tutoring/student/tutor_view.php?tutor_id=<?= $b['tutor_id'] ?>" style="font-weight:500;">
              <?= htmlspecialchars($b['tutor_name']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($b['subject']) ?></td>
          <td>
            <?= date('D j M Y', strtotime($b['session_date'])) ?><br>
            <span class="text-muted" style="font-size:.8rem;"><?= substr($b['session_time'],0,5) ?></span>
          </td>
          <td><span class="badge" style="background:var(--blue-subtle);color:var(--blue);font-size:.68rem;"><?= $b['session_type'] ?? 'online' ?></span></td><td><?= $b['duration_hrs'] ?>h</td>
          <td><span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          <td>
            <?php if ($b['status'] === 'completed' && !$b['rating_id']): ?>
              <a href="/peer-tutoring/student/rate.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-primary">Rate</a>
            <?php elseif ($b['rated_score']): ?>
              <span class="stars">
                <?php for ($i=1;$i<=5;$i++): ?>
                  <span class="star <?= $i<=$b['rated_score']?'filled':'' ?>">★</span>
                <?php endfor; ?>
              </span>
            <?php else: ?>
              <span class="text-muted" style="font-size:.8rem;">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (in_array($b['status'], ['pending','confirmed'])): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?');">
                <input type="hidden" name="cancel_booking_id" value="<?= $b['booking_id'] ?>">
                <button class="btn btn-sm btn-danger">Cancel</button>
              </form>
            <?php else: ?>
              <span class="text-muted" style="font-size:.8rem;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
