<?php

/** @var array|null rendered by index.php → pf_handle_login() */
$loginError = isset($_GET['error']);
$next       = (string) ($_GET['next'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in — Peddleflash</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body class="auth-body">

  <aside class="auth-brand" aria-hidden="true">
    <a class="auth-logo" href="index.php?route=home">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" width="18" height="18">
          <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z" fill="currentColor" />
        </svg>
      </span>
      Peddle<b>flash</b>
    </a>
    <p class="auth-quote">“I listed the bike at lunch,<br>sold it by dinner.”</p>
    <p class="auth-quote-sub">— Jonas, seller in Riverside</p>
    <ul class="auth-points">
      <li>⚡ Listings go live in minutes</li>
      <li>📍 Buyers around the corner, not across the country</li>
      <li> Verified profiles &amp; safe-meet tips</li>
    </ul>
  </aside>

  <main class="auth-panel">
    <form class="auth-card" id="login-form" method="post" action="index.php?route=login" novalidate>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Log in to manage your listings.</p>

      <?php if ($loginError): ?>
        <div class="auth-error" role="alert">⚠ Email or password doesn’t match. Try again.</div>
      <?php endif; ?>

      <?php if ($next !== ''): ?>
        <input type="hidden" name="next" value="<?php echo pf_e($next); ?>">
      <?php endif; ?>

      <div class="field">
        <label for="email-input">Email</label>
        <input id="email-input" name="email" type="email" autocomplete="email"
          placeholder="you@example.com" required>
        <span class="field-error" id="email-error" style="display:none"></span>
      </div>

      <div class="field">
        <label for="password-input">Password</label>
        <div class="field-box">
          <input id="password-input" name="password" type="password"
            autocomplete="current-password" placeholder="••••••••" required>
          <button type="button" class="field-toggle" id="toggle-password" aria-label="Show password">👁️</button>
        </div>
        <span class="field-error" id="password-error" style="display:none"></span>
      </div>

      <button class="btn-brand auth-submit" type="submit">
        <span class="btn-label">Log in</span>
        <span class="btn-spinner" aria-hidden="true"></span>
      </button>

      <p class="auth-demo">🔑 Demo access — <b>demo@peddleflash.com</b> / <b>flash123</b></p>

      <p class="auth-alt">New to Peddleflash? <a href="index.php?route=home">Learn more first</a></p>
    </form>
  </main>

  <script src="../assets/js/login.js"></script>
</body>

</html>