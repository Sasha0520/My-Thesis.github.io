<?php
// auth/login.php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    header('Location: /peer-tutoring/index.php'); exit;
}

$error  = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare("SELECT user_id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['email']     = $user['email'];

            $redirect = $_GET['redirect'] ?? null;
            if ($redirect && str_starts_with($redirect, '/peer-tutoring/')) {
                header('Location: ' . $redirect); exit;
            }

            $map = ['student'=>'/peer-tutoring/student/dashboard.php','tutor'=>'/peer-tutoring/tutor/dashboard.php','admin'=>'/peer-tutoring/admin/dashboard.php'];
            header('Location: ' . ($map[$user['role']] ?? '/peer-tutoring/index.php')); exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$page_title = 'Log In';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="brand"><span class="brand-dot"></span>PeerTutor</div>
      <p>Sign in to your account</p>
    </div>
    <div class="auth-card-body">
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($email) ?>" placeholder="you@university.edu" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">Sign In</button>
      </form>
    </div>
    <div class="auth-footer">
      Don't have an account? <a href="/peer-tutoring/auth/register.php">Register here</a>
    </div>

    <!-- Demo credentials hint -->
    <div style="background:var(--off-white);border-top:1px solid var(--border);padding:16px 36px;font-size:.78rem;color:var(--text-muted);">
      <strong style="color:var(--text-secondary);">Demo logins:</strong><br>
      Student: tomas.vasiliauskas@student.edu / <code>Student@1234</code><br>
      Tutor: mantas.jankauskas@student.edu / <code>Tutor@1234</code><br>
      Admin: admin@peertutor.edu / <code>Admin@1234</code>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
