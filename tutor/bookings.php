<?php
// tutor/bookings.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
auth_require('tutor');

$user = current_user();
$pdo  = db();

$tutor = $pdo->prepare("SELECT tutor_id FROM tutors WHERE user_id = ?");
$tutor->execute([$user['id']]);
$tutor = $tutor->fetch();
if (!$tutor) { header('Location: /peer-tutoring/tutor/profile.php'); exit; }
$tid = $tutor['tutor_id'];

// Handle confirm / cancel / complete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $allowed_transitions = [
        'confirm'  => ['pending'   => 'confirmed'],
        'cancel'   => ['pending'   => 'cancelled', 'confirmed' => 'cancelled'],
        'complete' => ['confirmed' => 'completed'],
    ];

    if (isset($allowed_transitions[$action])) {
        // Fetch current status
        $chk = $pdo->prepare("SELECT status FROM bookings WHERE booking_id=? AND tutor_id=?");
        $chk->execute([$bid, $tid]);
        $row = $chk->fetch();
        if ($row && isset($allowed_transitions[$action][$row['status']])) {
            $new = $allowed_transitions[$action][$row['status']];
            $pdo->prepare("UPDATE bookings SET status=? WHERE booking_id=?")->execute([$new, $bid]);
            flash('success', 'Booking marked as ' . $new . '.');
        }
    }
    header('Location: /peer-tutoring/tutor/bookings.php'); exit;
}

// Filter
$filter = $_GET['status'] ?? 'all';
$valid_filters = ['all','pending','confirmed','completed','cancelled'];
if (!in_array($filter, $valid_filters)) $filter = 'all';

$where = $filter === 'all' ? '' : "AND b.status = '$filter'";

$bookings = $pdo->prepare("
    SELECT b.*, u.name AS student_name, u.email AS student_email
    FROM bookings b JOIN users u ON u.user_id = b.student_id
    WHERE b.tutor_id = ? $where
    ORDER BY b.session_date DESC, b.session_time DESC
");
$bookings->execute([$tid]);
$all = $bookings->fetchAll();

$page_title = 'My Bookings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header section-row">
  <div>
    <h1>My Bookings</h1>
    <p>Manage all tutoring session requests.</p>
  </div>
  <a href="/peer-tutoring/tutor/dashboard.php" class="btn btn-outline-dark btn-sm">← Dashboard</a>
</div>

<!-- Status filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
  <?php foreach ($valid_filters as $f): ?>
    <a href="?status=<?= $f ?>" class="btn btn-sm <?= $filter===$f?'btn-secondary':'btn-outline-dark' ?>">
      <?= ucfirst($f) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($all)): ?>
  <div class="empty-state">
    <div class="empty-icon">📭</div>
    <h2>No <?= $filter === 'all' ? '' : $filter ?> bookings</h2>
    <p>There are no bookings in this category yet.</p>
  </div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Subject</th>
          <th>Date & Time</th>
          <th>Duration</th>
          <th>Type</th><th>Notes</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all as $b): ?>
        <tr>
          <td>
            <div style="font-weight:500;"><?= htmlspecialchars($b['student_name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($b['student_email']) ?></div>
          </td>
          <td><?= htmlspecialchars($b['subject']) ?></td>
          <td>
            <?= date('D j M Y', strtotime($b['session_date'])) ?><br>
            <span class="text-muted" style="font-size:.8rem;"><?= substr($b['session_time'],0,5) ?></span>
          </td>
          <td><?= $b['duration_hrs'] ?>h</td>
          <td><span class="badge" style="background:var(--blue-subtle);color:var(--blue);font-size:.68rem;"><?= $b['session_type'] ?? 'online' ?></span></td>
          <td style="max-width:200px;font-size:.82rem;color:var(--text-secondary);">
            <?= $b['notes'] ? htmlspecialchars(substr($b['notes'],0,80)) . (strlen($b['notes'])>80?'…':'') : '<span class="text-muted">—</span>' ?>
          </td>
          <td><span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if ($b['status'] === 'pending'): ?>
                <form method="POST">
                  <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                  <input type="hidden" name="action"     value="confirm">
                  <button class="btn btn-sm btn-success">Accept</button>
                </form>
                <form method="POST" onsubmit="return confirm('Decline this booking?');">
                  <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                  <input type="hidden" name="action"     value="cancel">
                  <button class="btn btn-sm btn-danger">Decline</button>
                </form>
              <?php elseif ($b['status'] === 'confirmed'): ?>
                <form method="POST">
                  <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                  <input type="hidden" name="action"     value="complete">
                  <button class="btn btn-sm btn-primary">Mark Complete</button>
                </form>
                <form method="POST" onsubmit="return confirm('Cancel this confirmed session?');">
                  <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                  <input type="hidden" name="action"     value="cancel">
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
