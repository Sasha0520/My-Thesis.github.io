<?php
// student/dashboard.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('student');

$user = current_user();
$pdo  = db();

// Load full user row for avatar
$urow = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$urow->execute([$user['id']]);
$urow = $urow->fetch();
$_SESSION['avatar'] = $urow['avatar'] ?? null;

// Stats
$stats = $pdo->prepare("
    SELECT
        COUNT(*)                                       AS total,
        SUM(b.status='pending')                        AS pending,
        SUM(b.status='confirmed')                      AS confirmed,
        SUM(b.status='completed')                      AS completed,
        SUM(b.status='cancelled')                      AS cancelled,
        SUM(b.status='completed' AND r.rating_id IS NULL) AS unrated
    FROM bookings b
    LEFT JOIN ratings r ON r.booking_id = b.booking_id
    WHERE b.student_id = ?
");
$stats->execute([$user['id']]);
$s = $stats->fetch();

// Upcoming
$upcoming = $pdo->prepare("
    SELECT b.*, u.name AS tutor_name, u.avatar AS tutor_avatar, t.tutor_id
    FROM bookings b
    JOIN tutors t ON t.tutor_id = b.tutor_id
    JOIN users  u ON u.user_id  = t.user_id
    WHERE b.student_id = ? AND b.status IN ('pending','confirmed') AND b.session_date >= CURDATE()
    ORDER BY b.session_date ASC, b.session_time ASC LIMIT 5
");
$upcoming->execute([$user['id']]);
$upcoming_list = $upcoming->fetchAll();

// Unrated completed sessions
$unrated = $pdo->prepare("
    SELECT b.*, u.name AS tutor_name, u.avatar AS tutor_avatar, t.tutor_id
    FROM bookings b
    JOIN tutors t ON t.tutor_id = b.tutor_id
    JOIN users  u ON u.user_id  = t.user_id
    LEFT JOIN ratings r ON r.booking_id = b.booking_id
    WHERE b.student_id = ? AND b.status = 'completed' AND r.rating_id IS NULL
    ORDER BY b.session_date DESC LIMIT 3
");
$unrated->execute([$user['id']]);
$unrated_list = $unrated->fetchAll();

$av_url   = avatar_url($urow['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $urow['name'])))));
$initials = substr($initials, 0, 2);

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Profile hero strip -->
<div style="background:var(--blue);border-radius:var(--r-xl);padding:28px 32px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:28px;flex-wrap:wrap;">
  <div style="width:62px;height:62px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.4);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;flex-shrink:0;">
    <?php if ($av_url): ?>
      <img src="<?= $av_url ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
    <?php else: ?>
      <?= htmlspecialchars($initials) ?>
    <?php endif; ?>
  </div>
  <div style="flex:1;min-width:200px;">
    <h1 style="color:#fff;font-size:1.35rem;margin-bottom:2px;">Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></h1>
    <p style="opacity:.75;font-size:.875rem;margin:0;">
      <?= htmlspecialchars($urow['department'] ?: 'No department set') ?>
      <?= $urow['year_of_study'] ? ' · ' . htmlspecialchars($urow['year_of_study']) : '' ?>
    </p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/peer-tutoring/student/search.php"  class="btn" style="background:#fff;color:var(--blue);font-weight:700;">🔍 Find a Tutor</a>
    <a href="/peer-tutoring/student/profile.php" class="btn btn-outline" style="font-size:.82rem;">Edit Profile</a>
  </div>
</div>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Sessions</div>
    <div class="stat-value"><?= (int)$s['total'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Upcoming</div>
    <div class="stat-value"><?= (int)$s['pending'] + (int)$s['confirmed'] ?></div>
    <div class="stat-sub">Pending + confirmed</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?= (int)$s['completed'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">To Rate</div>
    <div class="stat-value" style="<?= (int)$s['unrated']>0?'color:var(--amber)':'' ?>"><?= (int)$s['unrated'] ?></div>
    <div class="stat-sub">Awaiting your review</div>
  </div>
</div>

<!-- Rating reminders -->
<?php if ($unrated_list): ?>
<div class="card mt-3 mb-3" style="border-color:var(--amber);">
  <div class="card-header" style="background:rgba(217,119,6,.05);">
    <h3>⭐ Please rate your recent sessions</h3>
    <a href="/peer-tutoring/student/history.php" class="btn btn-sm btn-outline-dark">View all</a>
  </div>
  <div class="card-body" style="padding:0;">
    <?php foreach ($unrated_list as $b):
      $tav = avatar_url($b['tutor_avatar']);
      $tin = strtoupper(substr($b['tutor_name'], 0, 1));
    ?>
    <div style="padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div style="display:flex;align-items:center;gap:11px;">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--blue-subtle);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--blue);overflow:hidden;flex-shrink:0;">
          <?php if ($tav): ?><img src="<?= $tav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $tin ?><?php endif; ?>
        </div>
        <div>
          <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($b['tutor_name']) ?></div>
          <div style="font-size:.78rem;color:var(--text-400);"><?= htmlspecialchars($b['subject']) ?> · <?= date('D j M', strtotime($b['session_date'])) ?></div>
        </div>
      </div>
      <a href="/peer-tutoring/student/rate.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-primary">Rate Now</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="two-col mt-3">
  <!-- Upcoming sessions -->
  <div class="card">
    <div class="card-header">
      <h3>Upcoming Sessions</h3>
      <a href="/peer-tutoring/student/history.php" class="btn btn-sm btn-outline-dark">All sessions</a>
    </div>
    <?php if (empty($upcoming_list)): ?>
      <div class="card-body">
        <div class="empty-state" style="padding:28px 0;">
          <div class="empty-icon">📅</div>
          <p>No upcoming sessions yet.</p>
          <a href="/peer-tutoring/student/search.php" class="btn btn-primary btn-sm">Find a Tutor</a>
        </div>
      </div>
    <?php else: ?>
      <div class="card-body" style="padding:0;">
        <?php foreach ($upcoming_list as $b):
          $tav = avatar_url($b['tutor_avatar']);
          $tin = strtoupper(substr($b['tutor_name'], 0, 1));
        ?>
        <div style="padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px;">
          <div style="width:34px;height:34px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
            <?php if ($tav): ?><img src="<?= $tav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $tin ?><?php endif; ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($b['tutor_name']) ?></div>
            <div style="font-size:.77rem;color:var(--text-400);">
              <?= htmlspecialchars($b['subject']) ?> · <?= date('D j M', strtotime($b['session_date'])) ?> <?= substr($b['session_time'],0,5) ?>
              · <span style="text-transform:capitalize;"><?= $b['session_type'] ?? 'online' ?></span>
            </div>
          </div>
          <span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Quick links -->
  <div>
    <div class="card mb-3">
      <div class="card-header"><h3>Quick Actions</h3></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:9px;">
        <a href="/peer-tutoring/student/search.php"  class="btn btn-primary">🔍 Find a Tutor</a>
        <a href="/peer-tutoring/student/history.php" class="btn btn-outline-dark">📋 All My Sessions</a>
        <a href="/peer-tutoring/student/profile.php" class="btn btn-outline-dark">👤 Edit My Profile</a>
      </div>
    </div>

    <!-- Profile completeness hint -->
    <?php
    $missing = [];
    if (!$urow['avatar'])       $missing[] = 'profile photo';
    if (!$urow['department'])   $missing[] = 'department';
    if (!$urow['year_of_study']) $missing[] = 'year of study';
    if (!$urow['bio'])           $missing[] = 'bio';
    ?>
    <?php if ($missing): ?>
    <div class="card" style="border-color:var(--blue);">
      <div class="card-body">
        <div style="font-weight:700;font-size:.875rem;color:var(--blue);margin-bottom:6px;">📝 Complete your profile</div>
        <p style="font-size:.8rem;color:var(--text-500);margin-bottom:12px;">
          Still missing: <?= implode(', ', $missing) ?>.
        </p>
        <a href="/peer-tutoring/student/profile.php" class="btn btn-primary btn-sm">Update Profile</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
