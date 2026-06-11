<?php
// admin/users.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('admin');

$pdo = db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $cu     = current_user();

    if ($action === 'delete' && $uid) {
        if ($uid === (int)$cu['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            // Delete avatar file if present
            $row = $pdo->prepare("SELECT avatar FROM users WHERE user_id=?");
            $row->execute([$uid]); $row = $row->fetch();
            if ($row && $row['avatar']) {
                $f = __DIR__ . '/../assets/img/avatars/' . $row['avatar'];
                if (file_exists($f)) @unlink($f);
            }
            $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
            flash('success', 'User deleted.');
        }
    } elseif ($action === 'toggle_role' && $uid) {
        $row = $pdo->prepare("SELECT role FROM users WHERE user_id=?");
        $row->execute([$uid]); $row = $row->fetch();
        if ($row && in_array($row['role'], ['student','tutor'])) {
            $new = $row['role'] === 'student' ? 'tutor' : 'student';
            $pdo->prepare("UPDATE users SET role=? WHERE user_id=?")->execute([$new, $uid]);
            if ($new === 'tutor') {
                $chk = $pdo->prepare("SELECT tutor_id FROM tutors WHERE user_id=?");
                $chk->execute([$uid]);
                if (!$chk->fetch()) $pdo->prepare("INSERT INTO tutors (user_id) VALUES (?)")->execute([$uid]);
            }
            flash('success', "Role changed to $new.");
        }
    }
    header('Location: /peer-tutoring/admin/users.php'); exit;
}

$role   = $_GET['role'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$valid_roles = ['all','student','tutor','admin'];
if (!in_array($role, $valid_roles)) $role = 'all';

$where = []; $params = [];
if ($role !== 'all') { $where[] = "u.role = ?"; $params[] = $role; }
if ($search)         { $where[] = "(u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $pdo->prepare("
    SELECT u.*, t.avg_rating, t.rating_count, t.is_available, t.tutor_id
    FROM users u
    LEFT JOIN tutors t ON t.user_id = u.user_id
    $where_sql
    ORDER BY u.created_at DESC
");
$users->execute($params);
$all = $users->fetchAll();

$page_title = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header section-row">
  <div>
    <h1>Manage Users</h1>
    <p><?= count($all) ?> user<?= count($all)!=1?'s':'' ?> shown.</p>
  </div>
  <a href="/peer-tutoring/admin/dashboard.php" class="btn btn-outline-dark btn-sm">← Dashboard</a>
</div>

<!-- Filters -->
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;align-items:center;">
  <?php foreach ($valid_roles as $r): ?>
    <a href="?role=<?=$r?><?= $search?"&q=".urlencode($search):'' ?>"
       class="btn btn-sm <?= $role===$r?'btn-secondary':'btn-outline-dark' ?>"><?= ucfirst($r) ?></a>
  <?php endforeach; ?>
  <form method="GET" style="display:flex;gap:7px;margin-left:auto;">
    <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
    <div class="search-input-wrap" style="position:relative;">
      <span class="search-icon" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-400);">🔍</span>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control"
             style="padding-left:32px;width:220px;" placeholder="Search name or email…">
    </div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if ($search): ?><a href="?role=<?= $role ?>" class="btn btn-outline-dark btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<?php if (empty($all)): ?>
  <div class="empty-state"><div class="empty-icon">👥</div><p>No users found.</p></div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Dept / Year</th><th>Joined</th><th>Rating</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($all as $u):
          $tav = avatar_url($u['avatar']);
          $tin = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $u['name'])))));
          $tin = substr($tin,0,2);
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--blue-subtle);color:var(--blue);font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                <?php if ($tav): ?><img src="<?= $tav ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><?= htmlspecialchars($tin) ?><?php endif; ?>
              </div>
              <span style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($u['name']) ?></span>
            </div>
          </td>
          <td style="font-size:.82rem;color:var(--text-500);"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <span class="badge" style="<?= match($u['role']){
              'admin'   => 'background:var(--red-bg);color:var(--red);',
              'tutor'   => 'background:var(--blue-glow);color:var(--blue);',
              default   => 'background:var(--green-bg);color:var(--green);'
            } ?>"><?= $u['role'] ?></span>
          </td>
          <td style="font-size:.8rem;color:var(--text-500);">
            <?= $u['department'] ? htmlspecialchars($u['department']) : '—' ?>
            <?= $u['year_of_study'] ? '<br><span style="color:var(--text-400);">'.htmlspecialchars($u['year_of_study']).'</span>' : '' ?>
          </td>
          <td style="font-size:.8rem;"><?= date('j M Y', strtotime($u['created_at'])) ?></td>
          <td style="font-size:.82rem;">
            <?php if ($u['role']==='tutor' && $u['avg_rating'] > 0): ?>
              <span style="color:#f59e0b;font-weight:600;">★ <?= number_format($u['avg_rating'],1) ?></span>
              <span style="color:var(--text-400);font-size:.75rem;">(<?= (int)$u['rating_count'] ?>)</span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
              <?php if ($u['role'] !== 'admin'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <input type="hidden" name="action"  value="toggle_role">
                  <button class="btn btn-sm btn-outline-dark"><?= $u['role']==='student'?'→ Tutor':'→ Student' ?></button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This cannot be undone.');">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <input type="hidden" name="action"  value="delete">
                  <button class="btn btn-sm btn-danger">Delete</button>
                </form>
              <?php else: ?><span style="font-size:.75rem;color:var(--text-400);">Admin</span><?php endif; ?>
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
