<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  PEDDLEFLASH · Front controller
 *  ───────────────────────────────────────────────────────────────
 *  Every request is routed through this file (see .htaccess).
 *  View templates live in views/ and are INCLUDED — never served
 *  directly (the .htaccess also blocks direct access).
 *
 *  Routes
 *    home / (empty)   Landing page
 *    login            Sign in (GET = form, POST = credentials)
 *    logout           Destroy the session → landing
 *    dashboard        Member area (requires sign-in)
 * ═══════════════════════════════════════════════════════════════
 */

/* ── Session hardening ────────────────────────────────────────── */
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

/* ── Member directory (demo data — swap for a real DB later) ─── */
const PF_MEMBERS = [
  'demo@peddleflash.com' => ['password' => 'flash123',  'name' => 'Demo Seller'],
  'mia@peddleflash.com'  => ['password' => 'lightning', 'name' => 'Mia Torres'],
];

/* ── Helpers ──────────────────────────────────────────────────── */
function pf_e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pf_current_member(): ?array
{
  $email = (string) ($_SESSION['pf_email'] ?? '');
  if ($email === '' || !isset(PF_MEMBERS[$email])) {
    return null;
  }
  return array_merge(['email' => $email], PF_MEMBERS[$email]);
}

function pf_redirect(string $route, array $extra = []): void
{
  $query = http_build_query(array_merge(['route' => $route], $extra));
  header('Location: index.php?' . $query);
  exit;
}

function pf_handle_login(): void
{
  /* Already signed in? Straight to the dashboard. */
  if (pf_current_member() !== null) {
    pf_redirect('dashboard');
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass  = (string) ($_POST['password'] ?? '');

    if (isset(PF_MEMBERS[$email]) && hash_equals(PF_MEMBERS[$email]['password'], $pass)) {
      session_regenerate_id(true);
      $_SESSION['pf_email'] = $email;
      pf_redirect('dashboard');
    }
    pf_redirect('login', ['error' => '1']);
  }

  include __DIR__ . '/views/login.php';
}

/* ── Routing ─────────────────────────────────────────────────── */
$route = trim((string) ($_GET['route'] ?? ''));

switch ($route) {
  case '':
  case 'home':
  case 'landing':
    include __DIR__ . '/views/landing.php';
    break;

  case 'login':
    pf_handle_login();
    break;

  case 'logout':
    $_SESSION = [];
    session_destroy();
    pf_redirect('home');
    break;

  case 'dashboard':
    if (pf_current_member() === null) {
      pf_redirect('login', ['next' => 'dashboard']);
    }
    include __DIR__ . '/views/dashboard.php';
    break;

  default:
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
      . '<meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>404 — Peddleflash</title>'
      . '<style>body{margin:0;font-family:system-ui,sans-serif;background:#f6f7f9;color:#0e1520;'
      . 'display:flex;align-items:center;justify-content:center;min-height:100vh}'
      . '.nf{text-align:center;padding:2rem}.nf b{font-size:3rem;display:block;color:#ff4d1c}'
      . 'a{color:#ff4d1c;text-decoration:none;font-weight:600}</style></head>'
      . '<body><div class="nf"><b>404</b><p>That page doesn&#39;t exist.</p>'
      . '<a href="index.php?route=home">Back to home</a></div></body></html>';
    break;
}
