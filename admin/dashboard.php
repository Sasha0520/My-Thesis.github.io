<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
auth_require('admin');

$pdo = db();

$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role='student')               AS students,
        (SELECT COUNT(*) FROM users WHERE role='tutor')                 AS tutors,
        (SELECT COUNT(*) FROM bookings)                                 AS bookings_total,
        (SELECT COUNT(*) FROM bookings WHERE status='pending')          AS bookings_pending,
        (SELECT COUNT(*) FROM bookings WHERE status='confirmed')        AS bookings_confirmed,
        (SELECT COUNT(*) FROM bookings WHERE status='completed')        AS bookings_completed,
        (SELECT COUNT(*) FROM bookings WHERE status='cancelled')        AS bookings_cancelled,
        (SELECT COUNT(*) FROM ratings)                                  AS ratings_total,
        (SELECT ROUND(AVG(score),2) FROM ratings)                       AS avg_rating_platform,
        (SELECT COUNT(*) FROM bookings WHERE session_type='online')     AS sessions_online,
        (SELECT COUNT(*) FROM bookings WHERE session_type='in-person')  AS sessions_inperson
")->fetch();

$recent_bk = $pdo->query("
    SELECT b.booking_id, b.session_date, b.session_time, b.subject, b.status, b.session_type,
           us.name AS student_name, ut.name AS tutor_name
    FROM bookings b
    JOIN users us ON us.user_id = b.student_id
    JOIN tutors t  ON t.tutor_id = b.tutor_id
    JOIN users ut  ON ut.user_id = t.user_id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

$top_tutors = $pdo->query("
    SELECT u.name, u.avatar, t.avg_rating, t.rating_count, t.is_available, t.tutor_id,
           (SELECT COUNT(*) FROM bookings WHERE tutor_id=t.tutor_id AND status='completed') AS sessions
    FROM tutors t JOIN users u ON u.user_id=t.user_id
    WHERE t.rating_count > 0
    ORDER BY t.avg_rating DESC, t.rating_count DESC LIMIT 5
")->fetchAll();

$page_title = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Admin Dashboard</h1>
  <p>Platform-wide statistics and management tools.</p>
</div>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Students</div>
    <div class="stat-value"><?= (int)$stats['students'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Tutors</div>
    <div class="stat-value"><?= (int)$stats['tutors'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Bookings</div>
    <div class="stat-value"><?= (int)$stats['bookings_total'] ?></div>
    <div class="stat-sub"><?= (int)$stats['bookings_completed'] ?> completed</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending</div>
    <div class="stat-value" style="<?= (int)$stats['bookings_pending']>0?'color:var(--amber)':'' ?>"><?= (int)$stats['bookings_pending'] ?></div>
    <div class="stat-sub">Awaiting tutor response</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Platform Avg Rating</div>
    <div class="stat-value" style="color:#f59e0b;"><?= $stats['avg_rating_platform'] ?? '—' ?></div>
    <div class="stat-sub"><?= (int)$stats['ratings_total'] ?> ratings submitted</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Online Sessions</div>
    <div class="stat-value"><?= (int)$stats['sessions_online'] ?></div>
    <div class="stat-sub"><?= (int)$stats['sessions_inperson'] ?> in-person</div>
  </div>
</div>

<div class="two-col mt-3">
  <!-- Recent bookings -->
  <div class="card">
    <div class="card-header">
      <h3>Recent Bookings</h3>
      <a href="/peer-tutoring/admin/bookings.php" class="btn btn-sm btn-outline-dark">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Tutor</th><th>Subject</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($recent_bk as $b): ?>
          <tr>
            <td style="font-size:.82rem;"><?= htmlspecialchars($b['student_name']) ?></td>
            <td style="font-size:.82rem;"><?= htmlspecialchars($b['tutor_name']) ?></td>
            <td style="font-size:.78rem;color:var(--text-500);"><?= htmlspecialchars($b['subject']) ?></td>
            <td><span class="badge" style="background:var(--blue-subtle);color:var(--blue);font-size:.65rem;"><?= ucfirst($b['session_type']??'online') ?></span></td>
            <td style="font-size:.78rem;"><?= date('j M', strtotime($b['session_date'])) ?></td>
            <td><span class="badge badge-<?= $b['status'] ?>"><?= $b['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top tutors -->
  <div class="card">
    <div class="card-header">
      <h3>Top Rated Tutors</h3>
      <a href="/peer-tutoring/admin/users.php?role=tutor" class="btn btn-sm btn-outline-dark">All tutors</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php foreach ($top_tutors as $i => $t):
        require_once __DIR__ . '/../includes/upload_helper.php';
        $tav = avatar_url($t['avatar']);
        $tin = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $t['name'])))));
        $tin = substr($tin,0,2);
      ?>
      <div style="padding:12px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:11px;">
        <span style="font-size:.9rem;font-weight:800;color:var(--blue);width:20px;flex-shrink:0;"><?= $i+1 ?></span>
        <div style="width:34px;height:34px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
          <?php if ($tav): ?><img src="<?= $tav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= htmlspecialchars($tin) ?><?php endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($t['name']) ?></div>
          <div style="font-size:.75rem;color:var(--text-400);"><?= (int)$t['sessions'] ?> sessions</div>
        </div>
        <div style="text-align:right;">
          <div class="stars"><?php for($j=1;$j<=5;$j++): ?><span class="star <?=$j<=round($t['avg_rating'])?'filled':''?>">★</span><?php endfor; ?></div>
          <div style="font-size:.72rem;color:var(--text-400);"><?= number_format($t['avg_rating'],1) ?> (<?= (int)$t['rating_count'] ?>)</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Booking breakdown -->
<div class="card mt-3">
  <div class="card-header"><h3>Booking Status Breakdown</h3></div>
  <div class="card-body">
    <div style="display:flex;gap:24px;flex-wrap:wrap;">
      <?php
      $bk_statuses = [
        'pending'   => [(int)$stats['bookings_pending'],   'var(--amber)'],
        'confirmed' => [(int)$stats['bookings_confirmed'], 'var(--blue)'],
        'completed' => [(int)$stats['bookings_completed'], 'var(--green)'],
        'cancelled' => [(int)$stats['bookings_cancelled'], 'var(--red)'],
      ];
      $total_bk = max(1, (int)$stats['bookings_total']);
      foreach ($bk_statuses as $label => [$count, $color]):
        $pct = round($count / $total_bk * 100);
      ?>
      <div style="flex:1;min-width:120px;">
        <div style="display:flex;justify-content:space-between;font-size:.78rem;font-weight:600;margin-bottom:5px;">
          <span style="text-transform:capitalize;"><?= $label ?></span>
          <span style="color:var(--text-400);"><?= $pct ?>%</span>
        </div>
        <div style="height:7px;background:var(--bg-alt);border-radius:4px;overflow:hidden;">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:4px;transition:width .5s;"></div>
        </div>
        <div style="font-size:.88rem;font-weight:700;color:var(--text-900);margin-top:4px;"><?= $count ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Quick links -->
<div class="card mt-3">
  <div class="card-header"><h3>Management</h3></div>
  <div class="card-body" style="display:flex;gap:9px;flex-wrap:wrap;">
    <a href="/peer-tutoring/admin/users.php"            class="btn btn-outline-dark">👥 All Users</a>
    <a href="/peer-tutoring/admin/bookings.php"         class="btn btn-outline-dark">📋 All Bookings</a>
    <a href="/peer-tutoring/admin/users.php?role=tutor" class="btn btn-outline-dark">👩‍🏫 Tutors</a>
    <a href="/peer-tutoring/admin/users.php?role=student" class="btn btn-outline-dark">👨‍🎓 Students</a>
    <a href="/peer-tutoring/admin/bookings.php?status=pending" class="btn btn-primary">⏳ Pending Bookings</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
