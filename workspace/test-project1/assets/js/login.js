/**
 * Peddleflash — Login page behaviour
 * Show/hide password · client-side validation · submit loading state.
 */
(function () {
  'use strict';

  var form = document.getElementById('login-form');
  var email = document.getElementById('email-input');
  var password = document.getElementById('password-input');
  var toggleBtn = document.getElementById('toggle-password');
  var emailErr = document.getElementById('email-error');
  var passErr = document.getElementById('password-error');

  /* Show / hide password */
  if (toggleBtn && password) {
    toggleBtn.addEventListener('click', function () {
      var show = password.type === 'password';
      password.type = show ? 'text' : 'password';
      toggleBtn.textContent = show ? '🙈' : '👁️';
      toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      password.focus();
    });
  }

  function setErr(el, msg) {
    if (!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? '' : 'none';
  }

  /* Validate before it leaves the browser */
  if (form) {
    form.addEventListener('submit', function (e) {
      var ok = true;

      var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((email.value || '').trim());
      if (!emailOk) { setErr(emailErr, 'Enter a valid email address.'); ok = false; }
      else setErr(emailErr, '');

      if ((password.value || '').length < 6) { setErr(passErr, 'Password must be at least 6 characters.'); ok = false; }
      else setErr(passErr, '');

      if (!ok) { e.preventDefault(); return; }

      /* Valid → show spinner while the server answers */
      form.classList.add('loading');
      var btn = form.querySelector('.auth-submit');
      if (btn) btn.disabled = true;
    });
  }
})();