<?php
// tutor/dashboard.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('tutor');

$user = current_user();
$pdo  = db();

// Load user + tutor rows
$urow = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$urow->execute([$user['id']]); $urow = $urow->fetch();
$_SESSION['avatar'] = $urow['avatar'] ?? null;

$trow = $pdo->prepare("SELECT * FROM tutors WHERE user_id = ?");
$trow->execute([$user['id']]); $trow = $trow->fetch();
if (!$trow) { header('Location: /peer-tutoring/tutor/profile.php'); exit; }

// Stats
$stats = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(status='pending')   AS pending,
           SUM(status='confirmed') AS confirmed,
           SUM(status='completed') AS completed
    FROM bookings WHERE tutor_id = ?");
$stats->execute([$trow['tutor_id']]); $s = $stats->fetch();

// Pending requests
$pending_stmt = $pdo->prepare("
    SELECT b.*, u.name AS student_name, u.avatar AS student_avatar
    FROM bookings b JOIN users u ON u.user_id = b.student_id
    WHERE b.tutor_id = ? AND b.status = 'pending'
    ORDER BY b.session_date ASC, b.session_time ASC LIMIT 5");
$pending_stmt->execute([$trow['tutor_id']]);
$pending_list = $pending_stmt->fetchAll();

// Upcoming confirmed
$upcoming_stmt = $pdo->prepare("
    SELECT b.*, u.name AS student_name, u.avatar AS student_avatar
    FROM bookings b JOIN users u ON u.user_id = b.student_id
    WHERE b.tutor_id = ? AND b.status = 'confirmed' AND b.session_date >= CURDATE()
    ORDER BY b.session_date ASC LIMIT 5");
$upcoming_stmt->execute([$trow['tutor_id']]);
$upcoming_list = $upcoming_stmt->fetchAll();

// Recent reviews
$reviews_stmt = $pdo->prepare("
    SELECT r.score, r.comment, u.name AS student_name, u.avatar AS student_avatar, b.subject, r.created_at
    FROM ratings r JOIN bookings b ON b.booking_id = r.booking_id JOIN users u ON u.user_id = r.student_id
    WHERE r.tutor_id = ? ORDER BY r.created_at DESC LIMIT 3");
$reviews_stmt->execute([$trow['tutor_id']]);
$review_list = $reviews_stmt->fetchAll();

$av_url   = avatar_url($urow['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $urow['name'])))));
$initials = substr($initials, 0, 2);

// Profile completeness check
$missing = [];
if (!$urow['avatar'])         $missing[] = 'profile photo';
if (!$trow['bio'])            $missing[] = 'bio';
if (!$urow['department'])     $missing[] = 'department';
$my_tags = $pdo->prepare("SELECT COUNT(*) FROM tutor_tags WHERE tutor_id = ?");
$my_tags->execute([$trow['tutor_id']]);
if ((int)$my_tags->fetchColumn() === 0) $missing[] = 'subject tags';

$page_title = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Profile hero strip -->
<div style="background:var(--blue);border-radius:var(--r-xl);padding:28px 32px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:28px;flex-wrap:wrap;">
  <div style="width:62px;height:62px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.4);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;flex-shrink:0;">
    <?php if ($av_url): ?><img src="<?= $av_url ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= htmlspecialchars($initials) ?><?php endif; ?>
  </div>
  <div style="flex:1;min-width:200px;">
    <h1 style="color:#fff;font-size:1.35rem;margin-bottom:2px;">Welcome, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></h1>
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:4px;opacity:.85;font-size:.82rem;">
      <?php if ($trow['avg_rating'] > 0): ?>
        <span>⭐ <?= number_format($trow['avg_rating'],1) ?> avg rating (<?= $trow['rating_count'] ?> reviews)</span>
      <?php else: ?>
        <span>No ratings yet</span>
      <?php endif; ?>
      <span><?= $trow['is_available'] ? '🟢 Available' : '🔴 Unavailable' ?></span>
      <?php if ($urow['department']): ?><span>🏫 <?= htmlspecialchars($urow['department']) ?></span><?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/peer-tutoring/tutor/bookings.php" class="btn" style="background:#fff;color:var(--blue);font-weight:700;">📋 Manage Bookings</a>
    <a href="/peer-tutoring/tutor/profile.php"  class="btn btn-outline" style="font-size:.82rem;">Edit Profile</a>
  </div>
</div>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Avg Rating</div>
    <div class="stat-value" style="color:<?= $trow['avg_rating']>0?'#f59e0b':'var(--text-900)' ?>;"><?= $trow['avg_rating']>0 ? number_format($trow['avg_rating'],1) : '—' ?></div>
    <div class="stat-sub"><?= $trow['rating_count'] ?> review<?= $trow['rating_count']!=1?'s':'' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending Requests</div>
    <div class="stat-value" style="<?= (int)$s['pending']>0?'color:var(--amber)':'' ?>"><?= (int)$s['pending'] ?></div>
    <div class="stat-sub">Awaiting your response</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Confirmed</div>
    <div class="stat-value"><?= (int)$s['confirmed'] ?></div>
    <div class="stat-sub">Upcoming sessions</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?= (int)$s['completed'] ?></div>
  </div>
</div>

<!-- Profile completeness -->
<?php if ($missing): ?>
<div class="card mt-3" style="border-color:var(--blue);">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div style="font-weight:700;color:var(--blue);margin-bottom:3px;">📝 Complete your profile to attract more students</div>
      <p style="font-size:.82rem;color:var(--text-500);margin:0;">Missing: <?= implode(', ', $missing) ?>.</p>
    </div>
    <a href="/peer-tutoring/tutor/profile.php" class="btn btn-primary btn-sm">Complete Profile</a>
  </div>
</div>
<?php endif; ?>

<!-- Pending bookings -->
<?php if ($pending_list): ?>
<div class="card mt-3" style="border-color:var(--amber);">
  <div class="card-header" style="background:rgba(217,119,6,.05);">
    <h3>⏳ Pending Requests (<?= count($pending_list) ?>)</h3>
    <a href="/peer-tutoring/tutor/bookings.php?status=pending" class="btn btn-sm btn-outline-dark">View all pending</a>
  </div>
  <div class="card-body" style="padding:0;">
    <?php foreach ($pending_list as $b):
      $sav = avatar_url($b['student_avatar']);
      $sin = strtoupper(substr($b['student_name'],0,1));
    ?>
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <div style="width:36px;height:36px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.75rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
        <?php if ($sav): ?><img src="<?= $sav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $sin ?><?php endif; ?>
      </div>
      <div style="flex:1;min-width:160px;">
        <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($b['student_name']) ?></div>
        <div style="font-size:.78rem;color:var(--text-400);">
          <?= htmlspecialchars($b['subject']) ?> · <?= date('D j M Y', strtotime($b['session_date'])) ?> <?= substr($b['session_time'],0,5) ?>
          · <?= ucfirst($b['session_type'] ?? 'online') ?>
        </div>
        <?php if ($b['notes']): ?><div style="font-size:.76rem;color:var(--text-500);margin-top:2px;font-style:italic;">"<?= htmlspecialchars(substr($b['notes'],0,90)) ?><?= strlen($b['notes'])>90?'…':'' ?>"</div><?php endif; ?>
      </div>
      <div style="display:flex;gap:7px;">
        <form method="POST" action="/peer-tutoring/tutor/bookings.php">
          <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
          <input type="hidden" name="action" value="confirm">
          <button class="btn btn-success btn-sm">✓ Accept</button>
        </form>
        <form method="POST" action="/peer-tutoring/tutor/bookings.php" onsubmit="return confirm('Decline this booking?');">
          <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn btn-danger btn-sm">✕ Decline</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="two-col mt-3">
  <!-- Upcoming -->
  <div class="card">
    <div class="card-header">
      <h3>Upcoming Confirmed</h3>
      <a href="/peer-tutoring/tutor/bookings.php?status=confirmed" class="btn btn-sm btn-outline-dark">View all</a>
    </div>
    <?php if (empty($upcoming_list)): ?>
      <div class="card-body"><div class="empty-state" style="padding:24px 0;"><div class="empty-icon">📅</div><p>No confirmed sessions yet.</p></div></div>
    <?php else: ?>
      <div class="card-body" style="padding:0;">
        <?php foreach ($upcoming_list as $b):
          $sav = avatar_url($b['student_avatar']);
          $sin = strtoupper(substr($b['student_name'],0,1));
        ?>
        <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <?php if ($sav): ?><img src="<?= $sav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $sin ?><?php endif; ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($b['student_name']) ?></div>
            <div style="font-size:.77rem;color:var(--text-400);"><?= htmlspecialchars($b['subject']) ?> · <?= date('D j M', strtotime($b['session_date'])) ?> <?= substr($b['session_time'],0,5) ?> · <?= ucfirst($b['session_type']??'online') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent reviews -->
  <div class="card">
    <div class="card-header"><h3>Recent Reviews</h3></div>
    <?php if (empty($review_list)): ?>
      <div class="card-body"><div class="empty-state" style="padding:24px 0;"><div class="empty-icon">⭐</div><p>No reviews yet.</p></div></div>
    <?php else: ?>
      <div class="card-body" style="padding:0;">
        <?php foreach ($review_list as $r):
          $sav = avatar_url($r['student_avatar']);
          $sin = strtoupper(substr($r['student_name'],0,1));
        ?>
        <div style="padding:13px 20px;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.68rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                <?php if ($sav): ?><img src="<?= $sav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $sin ?><?php endif; ?>
              </div>
              <span style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($r['student_name']) ?></span>
            </div>
            <div class="stars"><?php for($i=1;$i<=5;$i++): ?><span class="star <?=$i<=$r['score']?'filled':''?>">★</span><?php endfor; ?></div>
          </div>
          <div style="font-size:.75rem;color:var(--text-400);"><?= htmlspecialchars($r['subject']) ?></div>
          <?php if ($r['comment']): ?><p style="font-size:.82rem;color:var(--text-500);margin-top:4px;"><?= htmlspecialchars($r['comment']) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Quick actions -->
<div class="card mt-3">
  <div class="card-header"><h3>Quick Actions</h3></div>
  <div class="card-body" style="display:flex;gap:9px;flex-wrap:wrap;">
    <a href="/peer-tutoring/tutor/profile.php"      class="btn btn-outline-dark">✏️ Edit Profile</a>
    <a href="/peer-tutoring/tutor/availability.php" class="btn btn-outline-dark">📅 Availability</a>
    <a href="/peer-tutoring/tutor/bookings.php"     class="btn btn-outline-dark">📋 All Bookings</a>
    <?php $toggle = $trow['is_available'] ? 0 : 1; ?>
    <form method="POST" action="/peer-tutoring/tutor/profile.php" style="display:inline;">
      <input type="hidden" name="toggle_availability" value="<?= $toggle ?>">
      <button class="btn btn-outline-dark"><?= $trow['is_available'] ? '🔴 Go Unavailable' : '🟢 Go Available' ?></button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
