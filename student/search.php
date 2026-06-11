<?php
// student/search.php — tutor search with free-text bar + tag picker
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
auth_require('student');

$tags_by_cat = [];
foreach (db()->query("SELECT tag_id, label, category FROM tags ORDER BY category, label")->fetchAll() as $t) {
    $tags_by_cat[$t['category']][] = $t;
}

$page_title = 'Find a Tutor';
$extra_js   = 'search.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Find a Tutor</h1>
  <p>Search by subject name, or select tags below. Results are ranked by match score then rating.</p>
</div>

<div class="search-bar">

  <!-- Free-text search bar -->
  <div class="form-group" style="margin-bottom:16px;">
    <label for="freeSearch" style="font-size:.82rem;font-weight:600;color:var(--text-700);margin-bottom:6px;display:block;">Search by subject or tutor name</label>
    <div class="search-input-wrap" style="display:flex;gap:10px;align-items:center;">
      <div style="position:relative;flex:1;">
        <span class="search-icon">🔍</span>
        <input type="text" id="freeSearch" class="form-control"
               placeholder="e.g. Python, Machine Learning, Linear Algebra…"
               style="padding-left:36px;">
      </div>
      <button id="btn-text-search" class="btn btn-primary">Search</button>
    </div>
  </div>

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
    <div style="flex:1;height:1px;background:var(--border);"></div>
    <span style="font-size:.75rem;color:var(--text-400);font-weight:600;white-space:nowrap;">OR FILTER BY TAGS</span>
    <div style="flex:1;height:1px;background:var(--border);"></div>
  </div>

  <!-- Tag picker grouped by category -->
  <?php foreach ($tags_by_cat as $cat => $tags): ?>
  <div style="margin-bottom:12px;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-400);margin-bottom:6px;"><?= htmlspecialchars($cat) ?></div>
    <div class="tag-checkbox-grid" style="max-height:none;overflow:visible;">
      <?php foreach ($tags as $tag): ?>
      <label>
        <input type="checkbox" class="tag-filter" value="<?= $tag['tag_id'] ?>"
               data-label="<?= htmlspecialchars(strtolower($tag['label'])) ?>">
        <span><?= htmlspecialchars($tag['label']) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:10px;margin-top:16px;align-items:center;flex-wrap:wrap;">
    <button id="btn-tag-search" class="btn btn-primary">Search by Tags</button>
    <button id="btn-clear"      class="btn btn-outline-dark">Clear All</button>
    <span id="selection-count" style="font-size:.82rem;color:var(--text-400);">Showing all tutors</span>
  </div>
</div>

<!-- Algorithm explanation -->
<details style="margin-bottom:22px;background:#fff;border:1px solid var(--border);border-radius:var(--r-md);padding:13px 17px;cursor:pointer;">
  <summary style="font-weight:600;font-size:.875rem;color:var(--text-900);">ℹ️ How are tutors ranked?</summary>
  <div style="margin-top:10px;font-size:.83rem;color:var(--text-500);line-height:1.7;">
    <strong>Recommendation Score = M + 0.5 × R</strong><br>
    <em>M</em> = number of subject tag matches · <em>R</em> = tutor's average rating (1–5).<br>
    More tag matches = higher rank. Equal matches? Higher rating breaks the tie. Completely transparent — you see the score on every card.
  </div>
</details>

<div id="search-results">
  <div class="empty-state">
    <div class="empty-icon">🔍</div>
    <p>Loading tutors…</p>
  </div>
</div>

<!-- Tutor card template -->
<template id="tutor-card-tpl">
  <div class="tutor-card">
    <div class="tutor-card-top">
      <div class="tutor-avatar" data-avatar></div>
      <div class="tutor-info">
        <div class="tutor-name" data-name></div>
        <div class="tutor-avail">
          <span class="avail-dot" data-avail-dot></span>
          <span data-avail-text></span>
        </div>
        <div data-session-types style="margin-top:3px;display:flex;gap:4px;flex-wrap:wrap;"></div>
      </div>
      <span class="rank-badge" data-rank></span>
    </div>
    <p class="tutor-bio" data-bio></p>
    <div class="tag-list" data-tags></div>
    <div class="tutor-card-footer">
      <div class="score-display">
        <div>
          <div class="score-val" data-score></div>
          <div class="score-label">score</div>
        </div>
        <div>
          <div class="stars" data-stars></div>
          <div style="font-size:.7rem;color:var(--text-400);" data-rating-count></div>
        </div>
        <div>
          <div style="font-size:.88rem;font-weight:700;color:var(--text-900);" data-match></div>
          <div class="score-label">tag matches</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
        <a class="btn btn-primary btn-sm" data-book-link>Book Session</a>
        <a class="btn btn-outline-dark btn-sm" data-view-link>View Profile</a>
      </div>
    </div>
  </div>
</template>

<?php include __DIR__ . '/../includes/footer.php'; ?>
