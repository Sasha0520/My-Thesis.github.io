<?php
// tutor/profile.php — full editable tutor profile with avatar upload
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/upload_helper.php';
auth_require('tutor');

$user = current_user();
$pdo  = db();

// Load user + tutor rows
$urow = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$urow->execute([$user['id']]);
$urow = $urow->fetch();

// Ensure tutor profile exists
$trow = $pdo->prepare("SELECT * FROM tutors WHERE user_id = ?");
$trow->execute([$user['id']]);
$trow = $trow->fetch();
if (!$trow) {
    $pdo->prepare("INSERT INTO tutors (user_id) VALUES (?)")->execute([$user['id']]);
    $trow = $pdo->prepare("SELECT * FROM tutors WHERE user_id = ?");
    $trow->execute([$user['id']]);
    $trow = $trow->fetch();
}

// Handle availability toggle from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $pdo->prepare("UPDATE tutors SET is_available=? WHERE user_id=?")->execute([(int)$_POST['toggle_availability'], $user['id']]);
    flash('success', 'Availability updated.');
    header('Location: /peer-tutoring/tutor/dashboard.php'); exit;
}

// All tags grouped
$all_tags = $pdo->query("SELECT tag_id, label, category FROM tags ORDER BY category, label")->fetchAll();
$tags_by_cat = [];
foreach ($all_tags as $t) $tags_by_cat[$t['category']][] = $t;

// Current tutor tags
$my_tags_stmt = $pdo->prepare("SELECT tag_id FROM tutor_tags WHERE tutor_id = ?");
$my_tags_stmt->execute([$trow['tutor_id']]);
$my_tag_ids = $my_tags_stmt->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$data = [
    'name'              => $urow['name'],
    'email'             => $urow['email'],
    'phone'             => $urow['phone']             ?? '',
    'department'        => $urow['department']        ?? '',
    'year_of_study'     => $urow['year_of_study']     ?? '',
    'bio'               => $trow['bio']               ?? '',
    'availability_note' => $trow['availability_note'] ?? '',
    'is_available'      => $trow['is_available']      ?? 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $data['name']              = trim($_POST['name']              ?? '');
    $data['email']             = trim($_POST['email']             ?? '');
    $data['phone']             = trim($_POST['phone']             ?? '');
    $data['department']        = trim($_POST['department']        ?? '');
    $data['year_of_study']     = trim($_POST['year_of_study']     ?? '');
    $data['bio']               = trim($_POST['bio']               ?? '');
    $data['availability_note'] = trim($_POST['availability_note'] ?? '');
    $data['is_available']      = isset($_POST['is_available']) ? 1 : 0;
    $selected_tags             = array_map('intval', (array)($_POST['tags'] ?? []));

    // Validation
    if (strlen($data['name']) < 2)                          $errors[] = 'Full name must be at least 2 characters.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($data['bio']) > 1500)                        $errors[] = 'Bio must be under 1500 characters.';

    // Email uniqueness
    if (!$errors && $data['email'] !== $urow['email']) {
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
            if ($urow['avatar']) {
                $old = __DIR__ . '/../assets/img/avatars/' . $urow['avatar'];
                if (file_exists($old)) @unlink($old);
            }
        }
    }

    // Password change
    $new_hash = null;
    $cur_pw   = $_POST['current_password'] ?? '';
    $new_pw   = $_POST['new_password']     ?? '';
    $conf_pw  = $_POST['confirm_password'] ?? '';
    if ($new_pw !== '') {
        if (!password_verify($cur_pw, $urow['password']))   $errors[] = 'Current password is incorrect.';
        elseif (strlen($new_pw) < 8)                        $errors[] = 'New password must be at least 8 characters.';
        elseif (!preg_match('/[A-Z]/', $new_pw))            $errors[] = 'New password needs at least one uppercase letter.';
        elseif (!preg_match('/[0-9]/', $new_pw))            $errors[] = 'New password needs at least one number.';
        elseif ($new_pw !== $conf_pw)                       $errors[] = 'Passwords do not match.';
        else $new_hash = password_hash($new_pw, PASSWORD_BCRYPT, ['cost'=>12]);
    }

    if (!$errors) {
        $avatar_col = $new_avatar ?? $urow['avatar'];

        // Update users table
        if ($new_hash) {
            $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,department=?,year_of_study=?,avatar=?,password=? WHERE user_id=?")
                ->execute([$data['name'],$data['email'],$data['phone'],$data['department'],$data['year_of_study'],$avatar_col,$new_hash,$user['id']]);
        } else {
            $pdo->prepare("UPDATE users SET name=?,email=?,phone=?,department=?,year_of_study=?,avatar=? WHERE user_id=?")
                ->execute([$data['name'],$data['email'],$data['phone'],$data['department'],$data['year_of_study'],$avatar_col,$user['id']]);
        }

        // Update tutors table
        $pdo->prepare("UPDATE tutors SET bio=?,availability_note=?,is_available=? WHERE tutor_id=?")
            ->execute([$data['bio'],$data['availability_note'],$data['is_available'],$trow['tutor_id']]);

        // Sync tags
        $pdo->prepare("DELETE FROM tutor_tags WHERE tutor_id=?")->execute([$trow['tutor_id']]);
        if ($selected_tags) {
            $ins = $pdo->prepare("INSERT IGNORE INTO tutor_tags (tutor_id, tag_id) VALUES (?,?)");
            $valid_ids = array_column($all_tags, 'tag_id');
            foreach ($selected_tags as $tid) {
                if (in_array($tid, $valid_ids)) $ins->execute([$trow['tutor_id'], $tid]);
            }
        }
        $my_tag_ids = $selected_tags;

        // Refresh session
        $_SESSION['user_name'] = $data['name'];
        $_SESSION['email']     = $data['email'];
        $_SESSION['avatar']    = $avatar_col;

        flash('success', 'Profile saved successfully.');
        header('Location: /peer-tutoring/tutor/profile.php'); exit;
    }
}

// Re-fetch for display
$urow2 = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$urow2->execute([$user['id']]); $urow = $urow2->fetch();

$av_url   = avatar_url($urow['avatar']);
$initials = strtoupper(implode('', array_map(fn($p)=>$p[0], array_filter(explode(' ', $urow['name'])))));
$initials = substr($initials, 0, 2);

$page_title = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>My Tutor Profile</h1>
  <p>Keep your profile complete — students use this to decide whether to book you.</p>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div>• <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Profile preview hero -->
<div class="profile-hero mb-3">
  <div class="tutor-avatar-lg">
    <?php if ($av_url): ?>
      <img src="<?= $av_url ?>?v=<?= time() ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
    <?php else: ?>
      <?= htmlspecialchars($initials) ?>
    <?php endif; ?>
  </div>
  <div class="profile-hero-info">
    <h1><?= htmlspecialchars($urow['name']) ?></h1>
    <p><?= htmlspecialchars($urow['email']) ?></p>
    <div class="profile-hero-meta">
      <span class="profile-meta-item"><?= $trow['is_available'] ? '🟢 Available' : '🔴 Unavailable' ?></span>
      <?php if ($trow['avg_rating'] > 0): ?>
        <span class="profile-meta-item">⭐ <?= number_format($trow['avg_rating'],1) ?> avg (<?= $trow['rating_count'] ?> reviews)</span>
      <?php else: ?>
        <span class="profile-meta-item">No ratings yet</span>
      <?php endif; ?>
      <?php if ($urow['department']): ?>
        <span class="profile-meta-item">🏫 <?= htmlspecialchars($urow['department']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="save_profile" value="1">

  <!-- Avatar -->
  <div class="profile-form-card">
    <div class="card-header"><h3>Profile Photo</h3></div>
    <div class="card-body">
      <div class="avatar-upload-wrap">
        <div class="avatar-preview-lg" id="avatarPreview">
          <?php if ($av_url): ?>
            <img src="<?= $av_url ?>?v=<?= time() ?>" alt="Avatar" id="avatarImg" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <span><?= htmlspecialchars($initials) ?></span>
          <?php endif; ?>
        </div>
        <div class="avatar-upload-info">
          <label class="btn btn-outline-dark">
            📷 Upload Photo
            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp">
          </label>
          <p>JPG, PNG, WEBP or GIF · Max 2 MB<br>Shown to students on your profile and recommendation cards.</p>
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
                 value="<?= htmlspecialchars($data['phone']) ?>" placeholder="+254 700 000 000">
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
    </div>
  </div>

  <!-- Tutor-specific -->
  <div class="two-col">
    <div class="profile-form-card">
      <div class="card-header"><h3>Tutor Bio</h3></div>
      <div class="card-body">
        <div class="form-group" style="margin:0;">
          <textarea id="bio" name="bio" class="form-control" rows="6"
                    placeholder="Describe your background, teaching style, and what students can expect."><?= htmlspecialchars($data['bio']) ?></textarea>
          <span class="form-hint">Max 1500 chars. This is what convinces students to book you.</span>
        </div>
        <div class="form-group mt-2">
          <label for="availability_note">Availability Summary</label>
          <input type="text" id="availability_note" name="availability_note" class="form-control"
                 value="<?= htmlspecialchars($data['availability_note']) ?>"
                 placeholder="e.g. Mon–Fri, 4–8 PM">
          <span class="form-hint">Short note shown on your tutor card.</span>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;">
            <input type="checkbox" name="is_available" value="1" <?= $data['is_available']?'checked':'' ?>>
            <span>I am currently available for bookings</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Subjects -->
    <div class="profile-form-card">
      <div class="card-header"><h3>Subjects I Teach</h3></div>
      <div class="card-body" style="max-height:420px;overflow-y:auto;">
        <p style="font-size:.8rem;color:var(--text-400);margin-bottom:12px;">Select all subjects you can confidently tutor. These power the recommendation engine.</p>
        <?php foreach ($tags_by_cat as $cat => $tags): ?>
        <div style="margin-bottom:10px;">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;font-weight:700;color:var(--text-400);margin-bottom:5px;"><?= htmlspecialchars($cat) ?></div>
          <div class="tag-checkbox-grid" style="max-height:none;overflow:visible;">
            <?php foreach ($tags as $tag): ?>
            <label class="<?= in_array($tag['tag_id'], $my_tag_ids)?'selected':'' ?>">
              <input type="checkbox" name="tags[]" value="<?= $tag['tag_id'] ?>"
                     <?= in_array($tag['tag_id'], $my_tag_ids)?'checked':'' ?>>
              <span><?= htmlspecialchars($tag['label']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Password change -->
  <div class="profile-form-card">
    <div class="card-header"><h3>Change Password <span class="text-muted" style="font-weight:400;font-size:.82rem;">— leave blank to keep current</span></h3></div>
    <div class="card-body">
      <div class="three-col">
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" class="form-control" placeholder="Current password">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap;">
    <button type="submit" class="btn btn-primary btn-lg">Save Profile</button>
    <a href="/peer-tutoring/tutor/availability.php" class="btn btn-outline-dark btn-lg">📅 Manage Availability →</a>
    <a href="/peer-tutoring/tutor/dashboard.php"    class="btn btn-outline-dark btn-lg">← Dashboard</a>
  </div>
</form>

<script>
// Tag checkbox styling
document.querySelectorAll('.tag-checkbox-grid input[type="checkbox"]').forEach(cb => {
  cb.addEventListener('change', () => {
    cb.closest('label').classList.toggle('selected', cb.checked);
  });
});
// Avatar preview
document.getElementById('avatarInput').addEventListener('change', function() {
  if (!this.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('avatarPreview').innerHTML =
      `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
  };
  r.readAsDataURL(this.files[0]);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
