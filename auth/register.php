<?php
// auth/register.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) { header('Location: /peer-tutoring/index.php'); exit; }

$errors = [];
$data   = ['name'=>'','email'=>'','role'=>'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['name']  = trim($_POST['name']  ?? '');
    $data['email'] = trim($_POST['email'] ?? '');
    $data['role']  = $_POST['role'] ?? 'student';
    $password      = $_POST['password']  ?? '';
    $confirm       = $_POST['confirm']   ?? '';

    // Validation
    if (!$data['name'])                        $errors[] = 'Full name is required.';
    if (strlen($data['name']) < 2)             $errors[] = 'Name must be at least 2 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8)                 $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))     $errors[] = 'Password must include at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $password))     $errors[] = 'Password must include at least one number.';
    if ($password !== $confirm)                $errors[] = 'Passwords do not match.';
    if (!in_array($data['role'], ['student','tutor'], true)) $errors[] = 'Invalid role selected.';

    if (!$errors) {
        // Check duplicate email
        $chk = db()->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$data['email']]);
        if ($chk->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins  = db()->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
        $ins->execute([$data['name'], $data['email'], $hash, $data['role']]);
        $uid = db()->lastInsertId();

        // If tutor, create tutor profile record
        if ($data['role'] === 'tutor') {
            $tp = db()->prepare("INSERT INTO tutors (user_id, bio) VALUES (?,?)");
            $tp->execute([$uid, '']);
        }

        // Auto-login
        session_regenerate_id(true);
        $_SESSION['user_id']   = $uid;
        $_SESSION['user_name'] = $data['name'];
        $_SESSION['role']      = $data['role'];
        $_SESSION['email']     = $data['email'];

        flash('success', 'Welcome to PeerTutor, ' . $data['name'] . '! ' . ($data['role']==='tutor' ? 'Complete your profile to start receiving bookings.' : 'Find your first tutor below.'));
        $dest = $data['role'] === 'tutor' ? '/peer-tutoring/tutor/profile.php' : '/peer-tutoring/student/search.php';
        header('Location: ' . $dest); exit;
    }
}

$page_title = 'Register';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card" style="max-width:520px;">
    <div class="auth-card-header">
      <div class="brand"><span class="brand-dot"></span>PeerTutor</div>
      <p>Create your free account</p>
    </div>
    <div class="auth-card-body">
      <?php if ($errors): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <!-- Role selector -->
        <div class="form-group">
          <label>I am a…</label>
          <div style="display:flex;gap:10px;">
            <?php foreach (['student'=>'👨‍🎓 Student','tutor'=>'👩‍🏫 Tutor'] as $val=>$label): ?>
            <label style="flex:1;cursor:pointer;">
              <input type="radio" name="role" value="<?= $val ?>"
                     <?= $data['role']===$val?'checked':'' ?>
                     style="display:none;" class="role-radio">
              <div class="role-option <?= $data['role']===$val?'active':'' ?>"
                   style="border:2px solid var(--border);border-radius:var(--radius-md);padding:14px;text-align:center;font-size:.9rem;font-weight:500;transition:all .15s;">
                <?= $label ?>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" class="form-control"
                 value="<?= htmlspecialchars($data['name']) ?>" placeholder="Jane Doe" required>
        </div>
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($data['email']) ?>" placeholder="you@university.edu" required>
        </div>
        <div class="two-col">
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 8 chars" required>
            <span class="form-hint">Uppercase + number required</span>
          </div>
          <div class="form-group">
            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px;">Create Account</button>
      </form>
    </div>
    <div class="auth-footer">
      Already have an account? <a href="/peer-tutoring/auth/login.php">Sign in</a>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.role-radio').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.role-option').forEach(o => o.classList.remove('active'));
    r.closest('label').querySelector('.role-option').classList.add('active');
  });
});
</script>
<style>
.role-option.active { border-color: var(--teal) !important; background: var(--teal-glow); color: var(--teal-dark); }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
