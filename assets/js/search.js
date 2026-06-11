// assets/js/search.js  v2
// Handles free-text search, tag-based search, and rendering ranked tutor cards.

(function () {
  'use strict';

  const freeInput  = document.getElementById('freeSearch');
  const btnText    = document.getElementById('btn-text-search');
  const btnTag     = document.getElementById('btn-tag-search');
  const btnClear   = document.getElementById('btn-clear');
  const selCount   = document.getElementById('selection-count');
  const results    = document.getElementById('search-results');
  const tpl        = document.getElementById('tutor-card-tpl');

  // ── Tag checkbox visual sync ──
  document.querySelectorAll('.tag-checkbox-grid label').forEach(lbl => {
    const cb = lbl.querySelector('input[type="checkbox"]');
    lbl.classList.toggle('selected', cb.checked);
    cb.addEventListener('change', () => {
      lbl.classList.toggle('selected', cb.checked);
      updateCount();
    });
  });

  function updateCount() {
    const n = document.querySelectorAll('.tag-filter:checked').length;
    selCount.textContent = n === 0 ? 'Showing all tutors' : `${n} tag${n>1?'s':''} selected`;
  }

  // ── Stars ──
  function starsHtml(avg) {
    return [1,2,3,4,5].map(i => `<span class="star ${i<=Math.round(avg)?'filled':''}">★</span>`).join('');
  }

  // ── Initials ──
  function initials(name) {
    return name.split(' ').map(p=>p[0]).join('').substring(0,2).toUpperCase();
  }

  // ── Render card ──
  function renderCard(t) {
    const node = tpl.content.cloneNode(true);
    const card = node.querySelector('.tutor-card');
    if (t.rank === 1) card.classList.add('rank-1');

    // Avatar — real photo or initials
    const av = card.querySelector('[data-avatar]');
    if (t.avatar_url) {
      av.innerHTML = `<img src="${t.avatar_url}" alt="${t.name}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
    } else {
      av.textContent = initials(t.name);
    }

    card.querySelector('[data-name]').textContent = t.name;

    const dot  = card.querySelector('[data-avail-dot]');
    const atxt = card.querySelector('[data-avail-text]');
    if (t.is_available) { dot.classList.add('available'); atxt.textContent = t.availability_note || 'Available'; }
    else                { atxt.textContent = 'Currently unavailable'; }

    // Session types (badges under availability)
    const stDiv = card.querySelector('[data-session-types]');
    // We show dept/year if available
    if (t.department) {
      const d = document.createElement('span');
      d.style.cssText = 'font-size:.7rem;color:var(--text-400);';
      d.textContent = t.department + (t.year_of_study ? ' · ' + t.year_of_study : '');
      stDiv.appendChild(d);
    }

    const rb = card.querySelector('[data-rank]');
    rb.textContent = `#${t.rank}`;
    if (t.rank === 1) rb.classList.add('top');

    // Tags
    const tl = card.querySelector('[data-tags]');
    t.tags.forEach(tag => {
      const s = document.createElement('span');
      s.className = 'tag' + (t.matched_tags.includes(tag) ? ' matched' : '');
      s.textContent = tag;
      tl.appendChild(s);
    });

    card.querySelector('[data-bio]').textContent       = t.bio || 'No bio provided.';
    card.querySelector('[data-score]').textContent     = t.score.toFixed(2);
    card.querySelector('[data-stars]').innerHTML       = starsHtml(t.avg_rating);
    card.querySelector('[data-rating-count]').textContent = t.rating_count > 0
      ? `${t.avg_rating.toFixed(1)} (${t.rating_count})`
      : 'No ratings';
    card.querySelector('[data-match]').textContent = t.match_count;

    const bookLink = card.querySelector('[data-book-link]');
    const viewLink = card.querySelector('[data-view-link]');
    bookLink.href = `/peer-tutoring/student/book.php?tutor_id=${t.tutor_id}`;
    viewLink.href = `/peer-tutoring/student/tutor_view.php?tutor_id=${t.tutor_id}`;
    if (!t.is_available) {
      bookLink.style.opacity = '0.4';
      bookLink.style.pointerEvents = 'none';
      bookLink.textContent = 'Unavailable';
    }
    return node;
  }

  // ── Fetch and render ──
  async function doSearch(params) {
    results.innerHTML = `<div class="empty-state"><div class="empty-icon" style="animation:spin 1s linear infinite">⏳</div><p>Searching…</p></div>`;
    try {
      const resp = await fetch('/peer-tutoring/api/recommend.php?' + params, { credentials: 'same-origin' });
      if (!resp.ok) throw new Error('Server error ' + resp.status);
      const json = await resp.json();
      if (!json.data || !json.data.length) {
        results.innerHTML = `<div class="empty-state"><div class="empty-icon">😕</div><h2>No tutors found</h2><p>Try different keywords or fewer tags.</p></div>`;
        return;
      }
      const hdr  = document.createElement('div');
      hdr.className = 'section-row';
      hdr.innerHTML = `<h2 class="section-title" style="margin:0;">${json.data.length} tutor${json.data.length>1?'s':''} found</h2>
        <span style="font-size:.78rem;color:var(--text-400);">Ranked: tag matches + 0.5 × rating</span>`;
      const grid = document.createElement('div');
      grid.className = 'tutor-grid';
      json.data.forEach(t => grid.appendChild(renderCard(t)));
      results.innerHTML = '';
      results.appendChild(hdr);
      results.appendChild(grid);
    } catch (e) {
      results.innerHTML = `<div class="alert alert-error">Error: ${e.message}</div>`;
    }
  }

  // ── Trigger: free text ──
  function searchByText() {
    const q = freeInput.value.trim();
    // Also check checked tags — if text + tags both present, text wins
    doSearch(q ? `q=${encodeURIComponent(q)}` : '');
  }

  // ── Trigger: tags ──
  function searchByTags() {
    const ids = [...document.querySelectorAll('.tag-filter:checked')].map(c => `tags[]=${c.value}`);
    doSearch(ids.length ? ids.join('&') : '');
  }

  btnText.addEventListener('click', searchByText);
  freeInput.addEventListener('keydown', e => { if (e.key === 'Enter') searchByText(); });

  // Auto-search as user types (debounced)
  let debounce;
  freeInput.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => { if (freeInput.value.trim().length >= 2) searchByText(); }, 380);
  });

  btnTag.addEventListener('click', searchByTags);

  btnClear.addEventListener('click', () => {
    freeInput.value = '';
    document.querySelectorAll('.tag-filter:checked').forEach(cb => {
      cb.checked = false;
      cb.closest('label').classList.remove('selected');
    });
    updateCount();
    doSearch('');
  });

  // Initial load — show all
  doSearch('');

  // Spinner keyframe
  const s = document.createElement('style');
  s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
  document.head.appendChild(s);
})();
