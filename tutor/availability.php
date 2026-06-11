<?php
// tutor/availability.php
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

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$errors = [];

// Add slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    $day   = $_POST['day']        ?? '';
    $start = $_POST['time_start'] ?? '';
    $end   = $_POST['time_end']   ?? '';

    if (!in_array($day, $days))          $errors[] = 'Invalid day.';
    if (!$start || !$end)                $errors[] = 'Start and end times are required.';
    if ($start >= $end)                  $errors[] = 'End time must be after start time.';

    // Duplicate check
    if (!$errors) {
        $dup = $pdo->prepare("SELECT slot_id FROM availability WHERE tutor_id=? AND day_of_week=? AND time_start=? AND time_end=?");
        $dup->execute([$tid,$day,$start,$end]);
        if ($dup->fetch()) $errors[] = 'That slot already exists.';
    }

    if (!$errors) {
        $pdo->prepare("INSERT INTO availability (tutor_id,day_of_week,time_start,time_end) VALUES (?,?,?,?)")
            ->execute([$tid,$day,$start,$end]);
        flash('success', 'Slot added.');
        header('Location: /peer-tutoring/tutor/availability.php'); exit;
    }
}

// Delete slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slot_id'])) {
    $pdo->prepare("DELETE FROM availability WHERE slot_id=? AND tutor_id=?")
        ->execute([(int)$_POST['delete_slot_id'], $tid]);
    flash('success', 'Slot removed.');
    header('Location: /peer-tutoring/tutor/availability.php'); exit;
}

// Load slots
$slots_stmt = $pdo->prepare("SELECT * FROM availability WHERE tutor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), time_start");
$slots_stmt->execute([$tid]);
$slots = $slots_stmt->fetchAll();

// Group by day
$by_day = array_fill_keys($days, []);
foreach ($slots as $s) $by_day[$s['day_of_week']][] = $s;

$page_title = 'Availability';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Manage Availability</h1>
  <p>Add your weekly recurring availability slots. Students see these when booking.</p>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error"><?php foreach($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="two-col">
  <!-- Add slot form -->
  <div class="card">
    <div class="card-header"><h3>Add Availability Slot</h3></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="add_slot" value="1">
        <div class="form-group">
          <label for="day">Day of Week</label>
          <select id="day" name="day" class="form-control">
            <?php foreach ($days as $d): ?>
              <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="two-col">
          <div class="form-group">
            <label for="time_start">Start Time</label>
            <input type="time" id="time_start" name="time_start" class="form-control" value="09:00" required>
          </div>
          <div class="form-group">
            <label for="time_end">End Time</label>
            <input type="time" id="time_end" name="time_end" class="form-control" value="11:00" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Add Slot</button>
      </form>
    </div>
  </div>

  <!-- Current slots -->
  <div class="card">
    <div class="card-header"><h3>Current Slots</h3></div>
    <div class="card-body" style="padding:0;">
      <?php $has_any = false; ?>
      <?php foreach ($days as $day): ?>
        <?php if (!empty($by_day[$day])): $has_any = true; ?>
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);">
          <div style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:7px;"><?= $day ?></div>
          <?php foreach ($by_day[$day] as $s): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
            <span style="font-size:.875rem;"><?= substr($s['time_start'],0,5) ?> – <?= substr($s['time_end'],0,5) ?></span>
            <form method="POST" onsubmit="return confirm('Remove this slot?');">
              <input type="hidden" name="delete_slot_id" value="<?= $s['slot_id'] ?>">
              <button class="btn btn-sm btn-danger">Remove</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$has_any): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);">No availability slots set yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="margin-top:20px;">
  <a href="/peer-tutoring/tutor/dashboard.php" class="btn btn-outline-dark">← Back to Dashboard</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
