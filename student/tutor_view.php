<?php
// student/tutor_view.php — public tutor profile
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('student');

$pdo      = db();
$tutor_id = (int)($_GET['tutor_id'] ?? 0);

$stmt = $pdo->prepare("SELECT t.*, u.name, u.email, u.avatar, u.department, u.year_of_study, u.created_at AS member_since FROM tutors t JOIN users u ON u.user_id=t.user_id WHERE t.tutor_id=?");
$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch();
if (!$tutor) { header('Location: /peer-tutoring/student/search.php'); exit; }

$tags = $pdo->prepare("SELECT tg.label, tg.category FROM tutor_tags tt JOIN tags tg ON tg.tag_id=tt.tag_id WHERE tt.tutor_id=? ORDER BY tg.category,tg.label");
$tags->execute([$tutor_id]);
$tag_rows = $tags->fetchAll();

$slots = $pdo->prepare("SELECT day_of_week,time_start,time_end FROM availability WHERE tutor_id=? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
$slots->execute([$tutor_id]);
$slot_rows = $slots->fetchAll();

$reviews = $pdo->prepare("SELECT r.score,r.comment,r.created_at,u.name AS student_name,u.avatar AS student_avatar,b.subject FROM ratings r JOIN bookings b ON b.booking_id=r.booking_id JOIN users u ON u.user_id=r.student_id WHERE r.tutor_id=? ORDER BY r.created_at DESC LIMIT 8");
$reviews->execute([$tutor_id]);
$review_rows = $reviews->fetchAll();

$av_url   = avatar_url($tutor['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $tutor['name'])))));
$initials = substr($initials, 0, 2);

$page_title = $tutor['name'];
include __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:16px;"><a href="/peer-tutoring/student/search.php" style="color:var(--text-400);font-size:.875rem;">← Back to search</a></div>

<!-- Hero -->
<div class="profile-hero mb-3">
  <div class="tutor-avatar-lg">
    <?php if ($av_url): ?>
      <img src="<?= $av_url ?>" alt="<?= htmlspecialchars($tutor['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
    <?php else: ?>
      <?= htmlspecialchars($initials) ?>
    <?php endif; ?>
  </div>
  <div class="profile-hero-info">
    <h1><?= htmlspecialchars($tutor['name']) ?></h1>
    <?php if ($tutor['department']): ?>
      <p><?= htmlspecialchars($tutor['department']) ?><?= $tutor['year_of_study'] ? ' · '.$tutor['year_of_study'] : '' ?></p>
    <?php endif; ?>
    <div class="profile-hero-meta">
      <?php if ($tutor['avg_rating'] > 0): ?>
        <span class="profile-meta-item">⭐ <?= number_format($tutor['avg_rating'],1) ?> / 5 (<?= $tutor['rating_count'] ?> review<?= $tutor['rating_count']!=1?'s':'' ?>)</span>
      <?php endif; ?>
      <span class="profile-meta-item"><?= $tutor['is_available'] ? '🟢 Available' : '🔴 Unavailable' ?></span>
      <?php if ($tutor['availability_note']): ?>
        <span class="profile-meta-item">📅 <?= htmlspecialchars($tutor['availability_note']) ?></span>
      <?php endif; ?>
      <span class="profile-meta-item">🗓️ Joined <?= date('M Y', strtotime($tutor['member_since'])) ?></span>
    </div>
  </div>
  <?php if ($tutor['is_available']): ?>
    <a href="/peer-tutoring/student/book.php?tutor_id=<?= $tutor_id ?>" class="btn btn-primary btn-lg" style="margin-left:auto;white-space:nowrap;">Book Session</a>
  <?php endif; ?>
</div>

<div class="two-col">
  <div>
    <!-- Bio -->
    <?php if ($tutor['bio']): ?>
    <div class="card mb-3">
      <div class="card-header"><h3>About</h3></div>
      <div class="card-body" style="font-size:.875rem;line-height:1.7;color:var(--text-500);"><?= nl2br(htmlspecialchars($tutor['bio'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Subjects -->
    <div class="card mb-3">
      <div class="card-header"><h3>Subjects & Expertise</h3></div>
      <div class="card-body">
        <?php if (empty($tag_rows)): ?>
          <p class="text-muted">No subjects listed yet.</p>
        <?php else: ?>
          <div class="tag-list"><?php foreach ($tag_rows as $t): ?><span class="tag"><?= htmlspecialchars($t['label']) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Availability -->
    <div class="card">
      <div class="card-header"><h3>Weekly Availability</h3></div>
      <div class="card-body">
        <?php if (empty($slot_rows)): ?>
          <p class="text-muted"><?= htmlspecialchars($tutor['availability_note'] ?? 'No availability set.') ?></p>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:7px;">
            <?php foreach ($slot_rows as $s): ?>
            <div style="display:flex;justify-content:space-between;padding:7px 10px;background:var(--bg);border-radius:var(--r-sm);font-size:.85rem;">
              <span style="font-weight:600;"><?= $s['day_of_week'] ?></span>
              <span class="text-muted"><?= substr($s['time_start'],0,5) ?> – <?= substr($s['time_end'],0,5) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Reviews -->
  <div class="card">
    <div class="card-header">
      <h3>Student Reviews</h3>
      <?php if ($tutor['avg_rating']>0): ?><span style="font-size:1.1rem;font-weight:800;color:var(--text-900);">★ <?= number_format($tutor['avg_rating'],1) ?></span><?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($review_rows)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-400);">No reviews yet — be the first!</div>
      <?php else: ?>
        <?php foreach ($review_rows as $r): ?>
        <div style="padding:15px 20px;border-bottom:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <div style="display:flex;align-items:center;gap:9px;">
              <?php
                $sav = avatar_url($r['student_avatar']);
                $sin = strtoupper(substr($r['student_name'],0,1));
              ?>
              <div style="width:28px;height:28px;border-radius:50%;background:var(--blue-subtle);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:var(--blue);overflow:hidden;flex-shrink:0;">
                <?php if ($sav): ?><img src="<?= $sav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= $sin ?><?php endif; ?>
              </div>
              <span style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($r['student_name']) ?></span>
            </div>
            <div class="stars"><?php for($i=1;$i<=5;$i++): ?><span class="star <?=$i<=$r['score']?'filled':''?>">★</span><?php endfor; ?></div>
          </div>
          <div style="font-size:.75rem;color:var(--text-400);margin-bottom:5px;"><?= htmlspecialchars($r['subject']) ?> · <?= date('j M Y',strtotime($r['created_at'])) ?></div>
          <?php if ($r['comment']): ?><p style="font-size:.85rem;color:var(--text-500);"><?= htmlspecialchars($r['comment']) ?></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if ($tutor['is_available']): ?>
    <div class="card-footer" style="text-align:center;">
      <a href="/peer-tutoring/student/book.php?tutor_id=<?= $tutor_id ?>" class="btn btn-primary">Book a Session with <?= htmlspecialchars(explode(' ', $tutor['name'])[0]) ?></a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
