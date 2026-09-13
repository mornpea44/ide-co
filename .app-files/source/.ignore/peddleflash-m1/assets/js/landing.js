/**
 * Peddleflash — Landing page behaviour
 * Nav toggle · smooth anchors · reveal-on-scroll · stat count-up ·
 * featured listing filters · hero search.
 */
(function () {
  'use strict';

  /* Footer year */
  var yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  /* Mobile navigation */
  var toggle = document.getElementById('nav-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', nav.classList.contains('open') ? 'true' : 'false');
    });
  }

  /* Smooth anchor scrolling (ignores bare "#") */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var href = a.getAttribute('href');
      if (!href || href.length < 2) return;
      var target = document.querySelector(href);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (nav) nav.classList.remove('open');
    });
  });

  /* Reveal-on-scroll + stat count-up */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });

    var stats = document.querySelector('.stats');
    if (stats) {
      var so = new IntersectionObserver(function (entries) {
        if (!entries[0].isIntersecting) return;
        so.disconnect();
        stats.querySelectorAll('b[data-count]').forEach(function (b) {
          var end = parseInt(b.getAttribute('data-count'), 10) || 0;
          var suffix = b.getAttribute('data-suffix') || '';
          var t0 = null;
          function step(ts) {
            if (t0 === null) t0 = ts;
            var p = Math.min(1, (ts - t0) / 1200);
            var eased = 1 - Math.pow(1 - p, 3);
            b.textContent = Math.round(end * eased).toLocaleString('en-US') + suffix;
            if (p < 1) window.requestAnimationFrame(step);
          }
          window.requestAnimationFrame(step);
        });
      }, { threshold: 0.4 });
      so.observe(stats);
    }
  } else {
    document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in'); });
  }

  /* ── Featured listing filters ── */
  var grid = document.getElementById('listing-grid');
  var chips = document.querySelectorAll('#filter-chips .chip');
  var state = { cat: 'all', q: '' };

  function applyFilters() {
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

  function setChip(cat) {
    chips.forEach(function (c) {
      c.classList.toggle('active', c.getAttribute('data-filter') === cat);
    });
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      state.cat = chip.getAttribute('data-filter') || 'all';
      setChip(state.cat);
      applyFilters();
    });
  });

  /* Hero search → filter featured + scroll to it */
  var searchForm = document.getElementById('hero-search');
  var searchInput = document.getElementById('hero-search-input');
  if (searchForm && searchInput) {
    searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      state.q = searchInput.value.trim().toLowerCase();
      state.cat = 'all';
      setChip('all');
      applyFilters();
      var featured = document.getElementById('featured');
      if (featured) featured.scrollIntoView({ behavior: 'smooth' });
    });
  }

  /* Hero quick category tags */
  document.querySelectorAll('#hero-tags button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cat = btn.getAttribute('data-tag') || 'all';
      state.cat = cat;
      state.q = '';
      if (searchInput) searchInput.value = '';
      setChip(cat);
      applyFilters();
      var featured = document.getElementById('featured');
      if (featured) featured.scrollIntoView({ behavior: 'smooth' });
    });
  });
})();