<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/upload_helper.php';

$user = current_user();
$page_title = $page_title ?? 'PeerTutor';

// Active link helper
function is_active(string $path): string {
    return str_contains($_SERVER['REQUEST_URI'], $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — PeerTutor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/peer-tutoring/assets/css/main.css">
<?php if (isset($extra_css)): ?>
<link rel="stylesheet" href="/peer-tutoring/assets/css/<?= $extra_css ?>">
<?php endif; ?>
</head>
<body>

<nav class="navbar">
  <a href="/peer-tutoring/index.php" class="nav-brand">
    <span class="brand-dot"></span>PeerTutor
  </a>

  <ul class="nav-links">
    <?php if ($user['role'] === 'student'): ?>
      <li><a href="/peer-tutoring/student/search.php"    class="<?= is_active('search') ?>">Find Tutors</a></li>
      <li><a href="/peer-tutoring/student/history.php"   class="<?= is_active('history') ?>">My Sessions</a></li>
      <li><a href="/peer-tutoring/student/dashboard.php" class="<?= is_active('dashboard') ?>">Dashboard</a></li>
    <?php elseif ($user['role'] === 'tutor'): ?>
      <li><a href="/peer-tutoring/tutor/bookings.php"     class="<?= is_active('bookings') ?>">Bookings</a></li>
      <li><a href="/peer-tutoring/tutor/availability.php" class="<?= is_active('availability') ?>">Availability</a></li>
      <li><a href="/peer-tutoring/tutor/dashboard.php"    class="<?= is_active('dashboard') ?>">Dashboard</a></li>
    <?php elseif ($user['role'] === 'admin'): ?>
      <li><a href="/peer-tutoring/admin/users.php"    class="<?= is_active('users') ?>">Users</a></li>
      <li><a href="/peer-tutoring/admin/bookings.php" class="<?= is_active('bookings') ?>">Bookings</a></li>
      <li><a href="/peer-tutoring/admin/dashboard.php" class="<?= is_active('dashboard') ?>">Dashboard</a></li>
    <?php endif; ?>
  </ul>

  <div class="nav-actions">
    <?php if ($user['id']): ?>
      <span class="nav-role-badge nav-role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>

      <?php
        // Nav avatar
        $av_url = avatar_url($_SESSION['avatar'] ?? null);
        $initials = implode('', array_map(fn($p)=>strtoupper($p[0]), array_filter(explode(' ', $user['name']))));
        $initials = substr($initials, 0, 2);
        $profile_link = match($user['role']) {
            'student' => '/peer-tutoring/student/profile.php',
            'tutor'   => '/peer-tutoring/tutor/profile.php',
            default   => '#'
        };
      ?>
      <a href="<?= $profile_link ?>" class="nav-avatar" title="My Profile">
        <?php if ($av_url): ?>
          <img src="<?= $av_url ?>" alt="<?= htmlspecialchars($user['name']) ?>">
        <?php else: ?>
          <span><?= htmlspecialchars($initials) ?></span>
        <?php endif; ?>
      </a>

      <span class="nav-username"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
      <a href="/peer-tutoring/auth/logout.php" class="btn btn-sm btn-outline">Sign out</a>
    <?php else: ?>
      <a href="/peer-tutoring/auth/login.php"    class="btn btn-sm btn-outline">Log in</a>
      <a href="/peer-tutoring/auth/register.php" class="btn btn-sm" style="background:#fff;color:var(--blue);font-weight:700;border-color:#fff;">Register</a>
    <?php endif; ?>
  </div>
</nav>

<main class="main-content">
<?php foreach (['success','error','info'] as $type):
    $msg = flash($type);
    if ($msg): ?>
<div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; endforeach; ?>
