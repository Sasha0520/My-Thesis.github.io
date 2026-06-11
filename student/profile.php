<?php
// student/profile.php  — editable student profile with avatar upload
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('student');

$user = current_user();
$pdo  = db();

// Load full user record
$row = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$row->execute([$user['id']]);
$row = $row->fetch();

$errors = [];
$data   = [
    'name'          => $row['name'],
    'email'         => $row['email'],
    'phone'         => $row['phone']         ?? '',
    'department'    => $row['department']    ?? '',
    'year_of_study' => $row['year_of_study'] ?? '',
    'bio'           => $row['bio']           ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['name']          = trim($_POST['name']          ?? '');
    $data['email']         = trim($_POST['email']         ?? '');
    $data['phone']         = trim($_POST['phone']         ?? '');
    $data['department']    = trim($_POST['department']    ?? '');
    $data['year_of_study'] = trim($_POST['year_of_study'] ?? '');
    $data['bio']           = trim($_POST['bio']           ?? '');

    // Validate
    if (strlen($data['name']) < 2)                           $errors[] = 'Full name must be at least 2 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))  $errors[] = 'Enter a valid email address.';

    // Email uniqueness (allow own email)
    if (!$errors && $data['email'] !== $row['email']) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $chk->execute([$data['email'], $user['id']]);
        if ($chk->fetch()) $errors[] = 'That email is already in use by another account.';
    }

    // Avatar upload
    $new_avatar = null;
    if (!empty($_FILES['avatar']['name'])) {
        $upload = handle_avatar_upload($_FILES['avatar'], $user['id']);
        if (!$upload['ok'] && $upload['error']) {
            $errors[] = $upload['error'];
        } elseif ($upload['ok']) {
            $new_avatar = $upload['filename'];
            // Delete old avatar file if present
            if ($row['avatar']) {
                $old = __DIR__ . '/../assets/img/avatars/' . $row['avatar'];
                if (file_exists($old)) @unlink($old);
            }
        }
    }

    // Password change (optional)
    $pw_error = '';
    $new_hash = null;
    $cur_pw   = $_POST['current_password'] ?? '';
    $new_pw   = $_POST['new_password']     ?? '';
    $conf_pw  = $_POST['confirm_password'] ?? '';

    if ($new_pw !== '') {
        if (!password_verify($cur_pw, $row['password'])) $pw_error = 'Current password is incorrect.';
        elseif (strlen($new_pw) < 8)                     $pw_error = 'New password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $new_pw))         $pw_error = 'New password needs at least one uppercase letter.';
        elseif (!preg_match('/[0-9]/', $new_pw))         $pw_error = 'New password needs at least one number.';
        elseif ($new_pw !== $conf_pw)                    $pw_error = 'Passwords do not match.';
        else $new_hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost'=>12]);
        if ($pw_error) $errors[] = $pw_error;
    }

    if (!$errors) {
        $avatar_col = $new_avatar ? $new_avatar : $row['avatar'];
        $params     = [$data['name'],$data['email'],$data['phone'],$data['department'],$data['year_of_study'],$data['bio'],$avatar_col];
        if ($new_hash) {
            $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,department=?,year_of_study=?,bio=?,avatar=?,password=? WHERE user_id=?")
                ->execute([...$params, $new_hash, $user['id']]);
        } else {
            $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,department=?,year_of_study=?,bio=?,avatar=? WHERE user_id=?")
                ->execute([...$params, $user['id']]);
        }
        // Refresh session
        $_SESSION['user_name'] = $data['name'];
        $_SESSION['email']     = $data['email'];
        $_SESSION['avatar']    = $avatar_col;

        flash('success', 'Profile updated successfully.');
        header('Location: /peer-tutoring/student/profile.php'); exit;
    }
}

// Reload after potential upload
$row = $pdo->prepare("SELECT * FROM users WHERE user_id = ?")->execute([$user['id']]) ? $row : $row;
$stmt2 = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt2->execute([$user['id']]);
$row = $stmt2->fetch();

$av_url   = avatar_url($row['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $row['name'])))));
$initials = substr($initials, 0, 2);

$page_title = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>My Profile</h1>
  <p>Manage your personal information and account settings.</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="max-width:700px;">
<form method="POST" enctype="multipart/form-data">

  <!-- Avatar upload -->
  <div class="profile-form-card">
    <div class="card-header"><h3>Profile Photo</h3></div>
    <div class="card-body">
      <div class="avatar-upload-wrap">
        <div class="avatar-preview-lg" id="avatarPreview">
          <?php if ($av_url): ?>
            <img src="<?= $av_url ?>?v=<?= time() ?>" alt="Avatar" id="avatarImg">
          <?php else: ?>
            <span id="avatarInitials"><?= htmlspecialchars($initials) ?></span>
          <?php endif; ?>
        </div>
        <div class="avatar-upload-info">
          <label class="btn btn-outline-dark">
            📷 Choose Photo
            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp">
          </label>
          <p>JPG, PNG, WEBP or GIF · Max 2 MB<br>Your photo is shown to tutors when you book sessions.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Personal info -->
  <div class="profile-form-card">
    <div class="card-header"><h3>Personal Information</h3></div>
    <div class="card-body">
      <div class="two-col">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" class="form-control"
                 value="<?= htmlspecialchars($data['name']) ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($data['email']) ?>" required>
        </div>
      </div>
      <div class="two-col">
        <div class="form-group">
          <label for="phone">Phone <span class="text-muted">(optional)</span></label>
          <input type="text" id="phone" name="phone" class="form-control"
                 value="<?= htmlspecialchars($data['phone']) ?>" placeholder="+370 600 00000">
        </div>
        <div class="form-group">
          <label for="department">Department / Faculty</label>
          <input type="text" id="department" name="department" class="form-control"
                 value="<?= htmlspecialchars($data['department']) ?>" placeholder="e.g. Computer Science">
        </div>
      </div>
      <div class="form-group">
        <label for="year_of_study">Year of Study</label>
        <select id="year_of_study" name="year_of_study" class="form-control">
          <option value="">— Select —</option>
          <?php foreach (['Year 1','Year 2','Year 3','Year 4','Year 5','Postgraduate'] as $y): ?>
            <option value="<?= $y ?>" <?= $data['year_of_study']===$y?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="bio">About Me <span class="text-muted">(optional)</span></label>
        <textarea id="bio" name="bio" class="form-control" rows="3"
                  placeholder="Briefly describe yourself — your course, interests, what you're looking for in tutoring."><?= htmlspecialchars($data['bio']) ?></textarea>
      </div>
    </div>
  </div>

  <!-- Change password -->
  <div class="profile-form-card">
    <div class="card-header"><h3>Change Password <span class="text-muted" style="font-weight:400;font-size:.82rem;">— leave blank to keep current</span></h3></div>
    <div class="card-body">
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Enter current password">
      </div>
      <div class="two-col">
        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 8 chars">
          <span class="form-hint">Uppercase letter + number required</span>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password">
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:4px;">
    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
    <a href="/peer-tutoring/student/dashboard.php" class="btn btn-outline-dark btn-lg">Cancel</a>
  </div>

</form>
</div>

<script>
// Live avatar preview
document.getElementById('avatarInput').addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById('avatarPreview');
    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
  };
  reader.readAsDataURL(file);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
