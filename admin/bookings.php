<?php
// admin/bookings.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
auth_require('admin');

$pdo = db();

// Admin can force-complete or force-cancel any booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map    = ['force_complete'=>'completed','force_cancel'=>'cancelled'];
    if ($bid && isset($map[$action])) {
        $pdo->prepare("UPDATE bookings SET status=? WHERE booking_id=?")->execute([$map[$action], $bid]);
        flash('success', 'Booking updated.');
    }
    header('Location: /peer-tutoring/admin/bookings.php'); exit;
}

// Filter
$filter = $_GET['status'] ?? 'all';
$valid  = ['all','pending','confirmed','completed','cancelled'];
if (!in_array($filter, $valid)) $filter = 'all';
$where  = $filter === 'all' ? '' : "WHERE b.status = '$filter'";

$bookings = $pdo->query("
    SELECT b.*, us.name AS student_name, ut.name AS tutor_name
    FROM bookings b
    JOIN users  us ON us.user_id  = b.student_id
    JOIN tutors t  ON t.tutor_id  = b.tutor_id
    JOIN users  ut ON ut.user_id  = t.user_id
    $where
    ORDER BY b.created_at DESC
    LIMIT 100
")->fetchAll();

$page_title = 'All Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header section-row">
  <div>
    <h1>All Bookings</h1>
    <p><?= count($bookings) ?> booking<?= count($bookings)!=1?'s':'' ?> shown.</p>
  </div>
  <a href="/peer-tutoring/admin/dashboard.php" class="btn btn-outline-dark btn-sm">← Dashboard</a>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
  <?php foreach ($valid as $f): ?>
    <a href="?status=<?=$f?>" class="btn btn-sm <?=$filter===$f?'btn-secondary':'btn-outline-dark'?>">
      <?= ucfirst($f) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
  <div class="empty-state"><div class="empty-icon">📭</div><p>No bookings in this category.</p></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Student</th><th>Tutor</th><th>Subject</th><th>Date & Time</th><th>Duration</th><th>Status</th><th>Admin Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['student_name']) ?></td>
          <td><?= htmlspecialchars($b['tutor_name']) ?></td>
          <td style="font-size:.83rem;"><?= htmlspecialchars($b['subject']) ?></td>
          <td style="font-size:.82rem;"><?= date('D j M Y', strtotime($b['session_date'])) ?><br><span class="text-muted"><?= substr($b['session_time'],0,5) ?></span></td>
          <td><?= $b['duration_hrs'] ?>h</td>
          <td><span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if (!in_array($b['status'],['completed','cancelled'])): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?=$b['booking_id']?>">
                  <input type="hidden" name="action"     value="force_complete">
                  <button class="btn btn-sm btn-success">Complete</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Force-cancel this booking?');">
                  <input type="hidden" name="booking_id" value="<?=$b['booking_id']?>">
                  <input type="hidden" name="action"     value="force_cancel">
                  <button class="btn btn-sm btn-danger">Cancel</button>
                </form>
              <?php else: ?>
                <span class="text-muted" style="font-size:.8rem;">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
