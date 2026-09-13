/**
 * Peddleflash — Dashboard behaviour
 * Stat count-up · listing filters + search · "New listing" modal.
 */
(function () {
  'use strict';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Stat count-up ── */
  document.querySelectorAll('.stat-num[data-count]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-count'), 10) || 0;
    var t0 = null;
    function step(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min(1, (ts - t0) / 900);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * eased).toLocaleString('en-US');
      if (p < 1) window.requestAnimationFrame(step);
    }
    window.requestAnimationFrame(step);
  });

  /* ── Filters + search ── */
  var grid = document.getElementById('listing-grid');
  var chips = document.querySelectorAll('#dash-filters .chip');
  var search = document.getElementById('listing-search');
  var state = { cat: 'all', q: '' };

  function apply() {
    if (!grid) return;
    var visible = 0;
    grid.querySelectorAll('.listing-card').forEach(function (card) {
      var okCat = state.cat === 'all' || card.getAttribute('data-category') === state.cat;
      var titleEl = card.querySelector('.listing-title');
      var title = (titleEl ? titleEl.textContent : '').toLowerCase();
      var okQ = state.q === '' || title.indexOf(state.q) !== -1;
      var show = okCat && okQ;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    var empty = document.getElementById('filter-empty');
    if (empty) empty.style.display = visible ? 'none' : '';
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      state.cat = chip.getAttribute('data-filter') || 'all';
      chips.forEach(function (c) { c.classList.toggle('active', c === chip); });
      apply();
    });
  });

  if (search) {
    search.addEventListener('input', function () {
      state.q = search.value.trim().toLowerCase();
      apply();
    });
  }

  /* ── New listing modal ── */
  var modal = document.getElementById('listing-modal');
  var openBtn = document.getElementById('new-listing-btn');
  var closeBtn = document.getElementById('modal-close');
  var cancelBtn = document.getElementById('modal-cancel');
  var modalForm = document.getElementById('modal-form');

  function openModal() {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    var t = document.getElementById('nl-title');
    if (t) setTimeout(function () { t.focus(); }, 60);
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
  }

  if (openBtn) openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  var CAT_ICONS = { tech: '💻', home: '🏠', bikes: '🚲', fashion: '🧵' };

  if (modalForm) {
    modalForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var title = (document.getElementById('nl-title').value || '').trim();
      var price = parseInt(document.getElementById('nl-price').value, 10) || 0;
      var cat = document.getElementById('nl-category').value || 'tech';
      if (!title || price < 1) return;

      var card = document.createElement('article');
      card.className = 'listing-card';
      card.setAttribute('data-category', cat);
      card.innerHTML =
        '<div class="listing-thumb">' + (CAT_ICONS[cat] || '📦') + '</div>' +
        '<div class="listing-body">' +
        '<div class="listing-top"><h3 class="listing-title">' + esc(title) + '</h3>' +
        '<span class="listing-price">$' + esc(String(price)) + '</span></div>' +
        '<p class="listing-meta">Just now · awaiting review</p>' +
        '<div class="listing-foot"><span class="badge badge-pending">Pending</span>' +
        '<span class="listing-views">👁 0</span></div></div>';

      if (grid) grid.insertBefore(card, grid.firstChild);
      modalForm.reset();
      closeModal();
      state.cat = 'all';
      state.q = '';
      if (search) search.value = '';
      chips.forEach(function (c) { c.classList.toggle('active', c.getAttribute('data-filter') === 'all'); });
      apply();
    });
  }
})();