<?php
// index.php — Landing page
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db.php';

$pdo   = db();
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users  WHERE role='tutor')            AS tutors,
        (SELECT COUNT(*) FROM users  WHERE role='student')          AS students,
        (SELECT COUNT(*) FROM bookings WHERE status='completed')    AS sessions,
        (SELECT COUNT(*) FROM tags)                                 AS subjects
")->fetch();

$page_title = 'Home';
include __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ─────────────────────────────────────────── -->
<div class="hero">
  <p class="hero-eyebrow">University Peer Tutoring Platform</p>
  <h1>Connect with the right <span>peer tutor</span> for your subject</h1>
  <p>A transparent, rank-based platform that matches students with peer tutors using subject expertise and verified ratings — not a black box.</p>
  <div class="hero-actions">
    <?php if (is_logged_in()): ?>
      <?php if ($_SESSION['role'] === 'student'): ?>
        <a href="/peer-tutoring/student/search.php"    class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;">Find a Tutor</a>
        <a href="/peer-tutoring/student/dashboard.php" class="btn btn-lg btn-outline">My Dashboard</a>
      <?php elseif ($_SESSION['role'] === 'tutor'): ?>
        <a href="/peer-tutoring/tutor/dashboard.php" class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;">My Dashboard</a>
      <?php else: ?>
        <a href="/peer-tutoring/admin/dashboard.php" class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;">Admin Panel</a>
      <?php endif; ?>
    <?php else: ?>
      <a href="/peer-tutoring/auth/register.php" class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;border-color:#fff;">Get Started Free</a>
      <a href="/peer-tutoring/auth/login.php"    class="btn btn-lg btn-outline">Log In</a>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Stats ─────────────────────────────────────────── -->
<div class="stat-grid mb-4">
  <div class="stat-card">
    <div class="stat-label">Active Tutors</div>
    <div class="stat-value"><?= (int)$stats['tutors'] ?></div>
    <div class="stat-sub">Verified peer tutors</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Students</div>
    <div class="stat-value"><?= (int)$stats['students'] ?></div>
    <div class="stat-sub">Registered learners</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Sessions Completed</div>
    <div class="stat-value"><?= (int)$stats['sessions'] ?></div>
    <div class="stat-sub">Successful tutoring sessions</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Subjects Covered</div>
    <div class="stat-value"><?= (int)$stats['subjects'] ?></div>
    <div class="stat-sub">Academic subject tags</div>
  </div>
</div>

<!-- ─── Why PeerTutor ─────────────────────────────────── -->
<h2 class="section-title">Why PeerTutor?</h2>
<div class="features-grid mb-4">
  <div class="feature-card">
    <div class="feature-icon">🎯</div>
    <h3>Transparent Recommendations</h3>
    <p>Our algorithm ranks tutors by subject-tag match count plus weighted rating. You see the score on every card — no hidden logic.</p>
  </div>
  <div class="feature-card">
    <div class="feature-icon">💻</div>
    <h3>Online & In-Person</h3>
    <p>Choose between online sessions (video/chat) or in-person campus meetings when you book — whatever works for you.</p>
  </div>
  <div class="feature-card">
    <div class="feature-icon">⭐</div>
    <h3>Verified Ratings</h3>
    <p>Every rating is tied to a real completed session. Honest, earned feedback that keeps quality high and improves future rankings.</p>
  </div>
  <div class="feature-card">
    <div class="feature-icon">🔒</div>
    <h3>Secure by Design</h3>
    <p>Bcrypt password hashing, PDO prepared statements, and role-based access control on every page.</p>
  </div>
</div>

<!-- ─── How it works ──────────────────────────────────── -->
<h2 class="section-title">How it works</h2>
<div class="three-col mb-4">
  <?php foreach ([
    ['1️⃣','Register','Create your account as a student or tutor. Takes under a minute.'],
    ['2️⃣','Search & Match','Select subject tags or type a keyword. The engine ranks tutors by match score and rating.'],
    ['3️⃣','Book & Learn','Pick online or in-person, book your slot, meet your tutor, and leave a rating.'],
  ] as [$n,$t,$d]): ?>
  <div class="card">
    <div class="card-body text-center">
      <div style="font-size:2rem;margin-bottom:10px;"><?= $n ?></div>
      <h3 style="margin-bottom:7px;"><?= $t ?></h3>
      <p style="font-size:.83rem;color:var(--text-500);"><?= $d ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ─── Algorithm callout ─────────────────────────────── -->
<div style="background:var(--blue);border-radius:var(--r-xl);padding:32px 36px;color:#fff;margin-bottom:32px;display:flex;align-items:center;gap:28px;flex-wrap:wrap;">
  <div style="flex:1;min-width:260px;">
    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;opacity:.7;margin-bottom:8px;">Recommendation Formula</div>
    <div style="font-size:1.6rem;font-weight:800;letter-spacing:-.03em;font-family:monospace;">Score = M + 0.5 × R</div>
    <p style="margin-top:10px;opacity:.8;font-size:.875rem;max-width:400px;">M = number of subject-tag matches &nbsp;·&nbsp; R = average tutor rating. Completely explainable. You always know why a tutor ranks where they do.</p>
  </div>
  <?php if (!is_logged_in()): ?>
  <a href="/peer-tutoring/auth/register.php" class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;flex-shrink:0;">Try It Now</a>
  <?php else: ?>
  <a href="/peer-tutoring/student/search.php" class="btn btn-lg" style="background:#fff;color:var(--blue);font-weight:700;flex-shrink:0;">Find a Tutor</a>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
