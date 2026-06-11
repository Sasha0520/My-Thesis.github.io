<?php
// student/book.php — book a session; includes online/in-person choice
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('student');

$pdo      = db();
$user     = current_user();
$tutor_id = (int)($_GET['tutor_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.*, u.name, u.email, u.user_id, u.avatar, u.department, u.year_of_study
    FROM tutors t JOIN users u ON u.user_id = t.user_id
    WHERE t.tutor_id = ?");
$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch();
if (!$tutor) { header('Location: /peer-tutoring/student/search.php'); exit; }

$tags_stmt = $pdo->prepare("SELECT tg.label FROM tutor_tags tt JOIN tags tg ON tg.tag_id=tt.tag_id WHERE tt.tutor_id=?");
$tags_stmt->execute([$tutor_id]);
$tutor_tags = $tags_stmt->fetchAll(PDO::FETCH_COLUMN);

$slots_stmt = $pdo->prepare("SELECT day_of_week, time_start, time_end FROM availability WHERE tutor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
$slots_stmt->execute([$tutor_id]);
$slots = $slots_stmt->fetchAll();

$errors = [];
$data   = ['session_date'=>'','session_time'=>'','duration_hrs'=>1,'subject'=>'','notes'=>'','session_type'=>'online'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['session_date'] = trim($_POST['session_date']  ?? '');
    $data['session_time'] = trim($_POST['session_time']  ?? '');
    $data['duration_hrs'] = (int)($_POST['duration_hrs'] ?? 1);
    $data['subject']      = trim($_POST['subject']       ?? '');
    $data['notes']        = trim($_POST['notes']         ?? '');
    $data['session_type'] = in_array($_POST['session_type']??'', ['online','in-person']) ? $_POST['session_type'] : 'online';

    if (!$data['session_date'])  $errors[] = 'Please select a session date.';
    if (!$data['session_time'])  $errors[] = 'Please select a session time.';
    if (!$data['subject'])       $errors[] = 'Please enter the subject / topic.';
    if ($data['duration_hrs'] < 1 || $data['duration_hrs'] > 4) $errors[] = 'Duration must be 1–4 hours.';
    if (!$tutor['is_available']) $errors[] = 'This tutor is currently unavailable.';
    if (!$errors && strtotime($data['session_date']) < strtotime('today')) $errors[] = 'Session date must be today or in the future.';

    // Duplicate check
    if (!$errors) {
        $dup = $pdo->prepare("SELECT booking_id FROM bookings WHERE tutor_id=? AND session_date=? AND session_time=? AND status NOT IN ('cancelled')");
        $dup->execute([$tutor_id, $data['session_date'], $data['session_time']]);
        if ($dup->fetch()) $errors[] = 'That time slot is already booked — please choose a different time.';
    }

    if (!$errors) {
        $pdo->prepare("INSERT INTO bookings (student_id,tutor_id,session_date,session_time,duration_hrs,subject,notes,session_type,status) VALUES (?,?,?,?,?,?,?,?,'pending')")
            ->execute([$user['id'],$tutor_id,$data['session_date'],$data['session_time'],$data['duration_hrs'],$data['subject'],$data['notes'],$data['session_type']]);
        flash('success', 'Booking request sent! Your tutor will confirm shortly.');
        header('Location: /peer-tutoring/student/history.php'); exit;
    }
}

$av_url   = avatar_url($tutor['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $tutor['name'])))));
$initials = substr($initials, 0, 2);

$page_title = 'Book a Session';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Book a Tutoring Session</h1>
  <p><a href="/peer-tutoring/student/search.php">← Back to search</a></p>
</div>

<div style="max-width:700px;">

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tutor summary -->
<div class="booking-summary mb-3">
  <h4>Tutoring with</h4>
  <div class="booking-summary-tutor">
    <div class="tutor-avatar" style="width:52px;height:52px;flex-shrink:0;overflow:hidden;">
      <?php if ($av_url): ?>
        <img src="<?= $av_url ?>" alt="<?= htmlspecialchars($tutor['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      <?php else: ?>
        <?= htmlspecialchars($initials) ?>
      <?php endif; ?>
    </div>
    <div>
      <div style="font-weight:700;font-size:1rem;color:var(--text-900);"><?= htmlspecialchars($tutor['name']) ?></div>
      <?php if ($tutor['department']): ?>
        <div style="font-size:.78rem;color:var(--text-400);"><?= htmlspecialchars($tutor['department']) ?><?= $tutor['year_of_study'] ? ' · '.$tutor['year_of_study'] : '' ?></div>
      <?php endif; ?>
      <div class="tag-list mt-1">
        <?php foreach ($tutor_tags as $tag): ?><span class="tag"><?= htmlspecialchars($tag) ?></span><?php endforeach; ?>
      </div>
      <div style="font-size:.78rem;color:var(--text-400);margin-top:4px;">
        <?php if ($tutor['avg_rating'] > 0): ?>★ <?= number_format($tutor['avg_rating'],1) ?> (<?= $tutor['rating_count'] ?> review<?= $tutor['rating_count']!=1?'s':'' ?>) &nbsp;·&nbsp; <?php endif; ?>
        <?= htmlspecialchars($tutor['availability_note'] ?? 'Availability not specified') ?>
      </div>
    </div>
    <a href="/peer-tutoring/student/tutor_view.php?tutor_id=<?= $tutor_id ?>" class="btn btn-outline-dark btn-sm" style="margin-left:auto;white-space:nowrap;">View Profile</a>
  </div>
</div>

<?php if ($slots): ?>
<div class="card mb-3">
  <div class="card-header"><h3>Available Slots</h3></div>
  <div class="card-body" style="padding:14px;">
    <div style="display:flex;flex-wrap:wrap;gap:7px;">
      <?php foreach ($slots as $s): ?>
        <span class="tag"><?= $s['day_of_week'] ?> <?= substr($s['time_start'],0,5) ?>–<?= substr($s['time_end'],0,5) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Booking form -->
<div class="card">
  <div class="card-header"><h3>Session Details</h3></div>
  <div class="card-body">
    <form method="POST">

      <!-- Session type selection -->
      <div class="form-group">
        <label>Session Type</label>
        <div class="session-type-group">
          <label>
            <input type="radio" name="session_type" value="online" <?= $data['session_type']==='online'?'checked':'' ?>>
            <div class="session-type-btn">
              <span class="session-icon">💻</span>
              <span>Online</span>
              <span style="font-size:.7rem;font-weight:400;color:inherit;opacity:.75;">Video / Chat</span>
            </div>
          </label>
          <label>
            <input type="radio" name="session_type" value="in-person" <?= $data['session_type']==='in-person'?'checked':'' ?>>
            <div class="session-type-btn">
              <span class="session-icon">🏫</span>
              <span>In-Person</span>
              <span style="font-size:.7rem;font-weight:400;color:inherit;opacity:.75;">On Campus</span>
            </div>
          </label>
        </div>
      </div>

      <div class="two-col">
        <div class="form-group">
          <label for="session_date">Session Date</label>
          <input type="date" id="session_date" name="session_date" class="form-control"
                 value="<?= htmlspecialchars($data['session_date']) ?>" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label for="session_time">Session Time</label>
          <input type="time" id="session_time" name="session_time" class="form-control"
                 value="<?= htmlspecialchars($data['session_time']) ?>" required>
        </div>
      </div>

      <div class="two-col">
        <div class="form-group">
          <label for="duration_hrs">Duration</label>
          <select id="duration_hrs" name="duration_hrs" class="form-control">
            <?php for ($h=1;$h<=4;$h++): ?>
              <option value="<?=$h?>" <?=$data['duration_hrs']==$h?'selected':''?>><?=$h?> hour<?=$h>1?'s':''?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="subject">Subject / Topic</label>
          <input type="text" id="subject" name="subject" class="form-control"
                 value="<?= htmlspecialchars($data['subject']) ?>"
                 placeholder="e.g. SQL Joins, Sorting Algorithms" required
                 list="subject-suggestions">
          <datalist id="subject-suggestions">
            <?php foreach ($tutor_tags as $tag): ?><option value="<?= htmlspecialchars($tag) ?>"><?php endforeach; ?>
          </datalist>
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes for Tutor <span class="text-muted">(optional)</span></label>
        <textarea id="notes" name="notes" class="form-control"
                  placeholder="Describe what you need help with, your current level, or specific questions."><?= htmlspecialchars($data['notes']) ?></textarea>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="submit" class="btn btn-primary btn-lg">Confirm Booking Request</button>
        <a href="/peer-tutoring/student/search.php" class="btn btn-outline-dark btn-lg">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>

<!-- Session type radio JS styling -->
<script>
document.querySelectorAll('.session-type-group input[type="radio"]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.session-type-btn').forEach(b => b.classList.remove('active'));
    if (r.checked) r.nextElementSibling.classList.add('active');
  });
  if (r.checked) r.nextElementSibling.classList.add('active');
});
</script>
<style>.session-type-btn.active{border-color:var(--blue)!important;background:var(--blue-subtle)!important;color:var(--blue)!important;}</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
