<?php
// includes/403.php
$page_title = 'Access Denied';
include __DIR__ . '/header.php';
?>
<div class="empty-state">
  <div class="empty-icon">🔒</div>
  <h2>Access Denied</h2>
  <p>You don't have permission to view this page.</p>
  <a href="/peer-tutoring/index.php" class="btn btn-primary">Go Home</a>
</div>
<?php include __DIR__ . '/footer.php'; ?>
