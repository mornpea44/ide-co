/**
 * Komorebi — interaction layer
 * Vanilla JS, no dependencies.
 */

(function () {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isTouch = window.matchMedia('(pointer: coarse)').matches;

  /* ---------- Reveal observer ---------- */
  const revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-in');
        revealObserver.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.15,
    rootMargin: '0px 0px -8% 0px'
  });

  document.querySelectorAll('[data-reveal], [data-stagger]').forEach(function (el) {
    revealObserver.observe(el);
  });

  /* ---------- Stagger index setup ---------- */
  document.querySelectorAll('[data-stagger]').forEach(function (block) {
    const items = block.querySelectorAll('[data-stagger-item]');
    items.forEach(function (item, i) {
      item.style.setProperty('--i', i);
    });
  });

  /* ---------- Hero entrance ---------- */
  const hero = document.querySelector('[data-hero]');
  if (hero) {
    requestAnimationFrame(function () {
      setTimeout(function () {
        hero.classList.add('is-in');
      }, 100);
    });
  }

  /* ---------- Nav scroll state ---------- */
  const nav = document.querySelector('[data-nav]');
  if (nav) {
    let ticking = false;
    const updateNav = function () {
      if (window.scrollY > 80) {
        nav.classList.add('is-scrolled');
      } else {
        nav.classList.remove('is-scrolled');
      }
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(updateNav);
        ticking = true;
      }
    }, { passive: true });
    updateNav();
  }

  /* ---------- Mobile nav toggle ---------- */
  const navToggle = document.querySelector('[data-nav-toggle]');
  const navMenu = document.querySelector('[data-nav-menu]');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', function () {
      const isOpen = navMenu.classList.toggle('is-open');
      navToggle.classList.toggle('is-active', isOpen);
      navToggle.setAttribute('aria-expanded', String(isOpen));
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });

    navMenu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navMenu.classList.remove('is-open');
        navToggle.classList.remove('is-active');
        navToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      });
    });
  }

  /* ---------- Smooth scroll for in-page anchors ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      const href = link.getAttribute('href');
      if (!href || href === '#' || href === '#!') return;

      const target = document.querySelector(href);
      if (!target) return;

      e.preventDefault();
      const offset = 80;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;

      window.scrollTo({
        top: top,
        behavior: prefersReducedMotion ? 'auto' : 'smooth'
      });
    });
  });

  /* ---------- Hero parallax (desktop, motion allowed) ---------- */
  if (!prefersReducedMotion && !isTouch) {
    const heroImg = document.querySelector('[data-hero-img]');
    if (heroImg) {
      let ticking = false;
      const update = function () {
        const y = window.scrollY;
        const vh = window.innerHeight;
        if (y < vh) {
          heroImg.style.transform = 'translateY(' + (y * 0.18) + 'px)';
        } else {
          heroImg.style.transform = '';
        }
        ticking = false;
      };
      window.addEventListener('scroll', function () {
        if (!ticking) {
          requestAnimationFrame(update);
          ticking = true;
        }
      }, { passive: true });
    }
  }

  /* ---------- Current year ---------- */
  const yearEl = document.querySelector('[data-year]');
  if (yearEl) {
    yearEl.textContent = String(new Date().getFullYear());
  }
})();