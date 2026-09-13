<?php
// ============================================================
// CUSTOM IDE Test Page — Professional Landing Page
// ============================================================

// Configuration  
$config = [
  "app_name" => "Quirky IDE",
  "tagline"  => "A modern coding experience, right in your browser.",
  "version"  => "2.0.0",
  "
  "    => true,
  "log_file" => __DIR__ . "/test2.log",
];

// Defaults
date_default_timezone_set("UTC");
$currentTime  = date("Y-m-d H:i:s");
$randomNumber = rand(1, 100);
$randomFloat  = round(mt_rand() / mt_getrandmax(), 4);
$uuid = sprintf(
  "%04x%04x-%04x-%04x-%04x-%04x%04x%04x",
  mt_rand(0, 0xffff),
  mt_rand(0, 0xffff),
  mt_rand(0, 0xffff),
  mt_rand(0, 0x0fff) | 0x4000,
  mt_rand(0, 0x3fff) | 0x8000,
  mt_rand(0, 0xffff),
  mt_rand(0, 0xffff),
  mt_rand(0, 0xffff)
);

// Session
session_start();

// Cookie counter
$visits = isset($_COOKIE["visits"]) ? (int) $_COOKIE["visits"] + 1 : 1;
setcookie("visits", (string) $visits, time() + 86400 * 30, "/");

// GET params
$filter = $_GET["filter"] ?? "all";
$limit  = (int) ($_GET["limit"] ?? 5);
$search = $_GET["search"] ?? "";

// POST handling + validation
$message = "";
$errors  = [];
$name    =  "";
$email   = "";
$age     = "";
$colors  = [];
$notes   = "";
$submittedMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";

if ($submittedMethod === "POST") {
  $name   = trim($_POST["name"]   ?? "");
  $email  = trim($_POST["email"]  ?? "");
  $age    = trim($_POST["age"]    ?? "");
  $colors = $_POST["colors"]      ?? [];
  $notes  = trim($_POST["notes"]  ?? "");
  if (!is_array($colors)) $colors = [];

  if ($name === "")                                       $errors[] = "Name is required.";
  if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = "Valid email is required.";
  if (!ctype_digit((string) $age) || (int) $age < 1 || (int) $age > 120) $errors[] = "Age must be 1–120.";
  if (empty($colors))                                     $errors[] = "Pick at least one color.";

  if (empty($errors)) {
    $message = "Thanks, <strong>" . htmlspecialchars($name) . "</strong>! "
      . "We'll be in touch at <em>" . htmlspecialchars($email) . "</em>.";

    if ($config["debug"]) {
      $line = sprintf(
        "[%s] visit=%d name=%s email=%s age=%s ip=%s\n",
        $currentTime,
        $visits,
        $name,
        $email,
        $age,
        $_SERVER["REMOTE_ADDR"] ?? "?"
      );
      @file_put_contents($config["log_file"], $line, FILE_APPEND);
    }
  }
}

// Sample data
$items = [
  ["id" => 1, "name" => "Apple",  "category" => "fruit", "price" => 1.20],
  ["id" => 2, "name" => "Banana", "category" => "fruit", "price" => 0.50],
  ["id" => 3, "name" => "Carrot", "category" => "veg",   "price" => 0.80],
  ["id" => 4, "name" => "Donut",  "category" => "sweet", "price" => 2.50],
  ["id" => 5, "name" => "Egg",    "category" => "other", "price" => 0.30],
  ["id" => 6, "name" => "Fig",    "category" => "fruit", "price" => 3.00],
  ["id" => 7, "name" => "Grape",  "category" => "fruit", "price" => 2.10],
  ["id" => 8, "name" => "Honey",  "category" => "sweet", "price" => 5.75],
];

// Filter
if ($filter !== "all") {
  $items = array_values(array_filter($items, fn($i) => $i["category"] === $filter));
}

// Search
if ($search !== "") {
  $needle = strtolower($search);
  $items = array_values(array_filter($items, fn($i) => str_contains(strtolower($i["name"]), $needle)));
}

// Limit
$items = array_slice($items, 0, max(1, $limit));

// Totals
$totalCount = count($items);
$totalPrice = array_sum(array_column($items, "price"));

// JSON API endpoint
if (isset($_GET["api"]) && $_GET["api"] == "1") {
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode([
    "time"   => $currentTime,
    "random" => $randomNumber,
    "uuid"   => $uuid,
    "visits" => $visits,
    "items"  => $items,
    "total"  => $totalPrice,
  ], JSON_PRETTY_PRINT);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($config["app_name"]) ?> — <?= htmlspecialchars($config["tagline"]) ?></title>
  <meta name="description" content="Quirky IDE is a sleek, modern coding environment built for speed and simplicity.">

  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      font-size: 16px;
      line-height: 1.6;
      color: #1f2937;
      background: #ffffff;
      -webkit-font-smoothing: antialiased;
    }

    img {
      max-width: 100%;
      display: block;
    }

    a {
      color: #4f46e5;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    :root {
      --primary: #4f46e5;
      --primary-dark: #4338ca;
      --accent: #06b6d4;
      --dark: #0f172a;
      --muted: #64748b;
      --border: #e5e7eb;
      --bg-soft: #f8fafc;
      --bg-card: #ffffff;
      --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
      --shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
      --shadow-lg: 0 20px 40px rgba(15, 23, 42, 0.12);
      --radius: 12px;
    }

    .container {
      width: 100%;
      max-width: 1140px;
      margin: 0 auto;
      padding: 0 24px;
    }

    /* ============================================================
          Navigation
          ============================================================ */
    .nav {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: saturate(180%) blur(12px);
      -webkit-backdrop-filter: saturate(180%) blur(12px);
      border-bottom: 1px solid var(--border);
    }

    .nav__inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 68px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 700;
      font-size: 18px;
      color: var(--dark);
      text-decoration: none;
    }

    .brand:hover {
      text-decoration: none;
    }

    .brand__mark {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      display: grid;
      place-items: center;
      color: #fff;
      font-weight: 800;
    }

    .nav__links {
      display: flex;
      gap: 28px;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .nav__links a {
      color: var(--muted);
      font-weight: 500;
      font-size: 14px;
    }

    .nav__links a:hover {
      color: var(--dark);
      text-decoration: none;
    }

    .nav__cta {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--dark);
      color: #fff !important;
      padding: 9px 16px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      transition: background 0.2s;
    }

    .nav__cta:hover {
      background: #1e293b;
      text-decoration: none;
    }

    @media (max-width: 720px) {
      .nav__links {
        display: none;
      }
    }

    /* ============================================================
   Hero
   ============================================================ */
    .hero {
      position: relative;
      padding: 96px 0 80px;
      background:
        radial-gradient(circle at 20% 0%, rgba(79, 70, 229, 0.10), transparent 50%),
        radial-gradient(circle at 80% 100%, rgba(6, 182, 212, 0.10), transparent 50%),
        #ffffff;
      overflow: hidden;
    }

    .hero__inner {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .hero__badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: rgba(79, 70, 229, 0.08);
      color: var(--primary-dark);
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 20px;
    }

    .hero__badge .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--primary);
      box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
    }

    .hero h1 {
      font-size: clamp(2.2rem, 4.5vw, 3.5rem);
      line-height: 1.1;
      margin: 0 0 16px;
      color: var(--dark);
      letter-spacing: -0.02em;
    }

    .hero h1 .grad {
      background: linear-gradient(90deg, var(--primary), var(--accent));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero p.lead {
      font-size: 1.125rem;
      color: var(--muted);
      margin: 0 0 28px;
      max-width: 520px;
    }

    .hero__cta {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 22px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 15px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
      text-decoration: none;
    }

    .btn:hover {
      text-decoration: none;
      transform: translateY(-1px);
    }

    .btn-primary {
      background: var(--primary);
      color: #fff;
      box-shadow: 0 8px 20px rgba(79, 70, 229, 0.35);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
    }

    .btn-ghost {
      background: #fff;
      color: var(--dark);
      border-color: var(--border);
    }

    .btn-ghost:hover {
      border-color: #cbd5e1;
    }

    .btn-dark {
      background: var(--dark);
      color: #fff;
    }

    .btn-dark:hover {
      background: #1e293b;
    }

    .hero__meta {
      display: flex;
      gap: 24px;
      margin-top: 32px;
      color: var(--muted);
      font-size: 13px;
    }

    .hero__meta span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    /* Code preview card */
    .preview {
      background: #0f172a;
      border-radius: 16px;
      padding: 22px;
      box-shadow: var(--shadow-lg);
      color: #e2e8f0;
      font-family: "SFMono-Regular", Menlo, Consolas, monospace;
      font-size: 13.5px;
      line-height: 1.7;
      position: relative;
    }

    .preview__bar {
      display: flex;
      gap: 6px;
      margin-bottom: 16px;
    }

    .preview__bar i {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
    }

    .preview__bar i:nth-child(1) {
      background: #ef4444;
    }

    .preview__bar i:nth-child(2) {
      background: #f59e0b;
    }

    .preview__bar i:nth-child(3) {
      background: #10b981;
    }

    .preview .ln {
      color: #64748b;
      margin-right: 12px;
      user-select: none;
    }

    .preview .kw {
      color: #c084fc;
    }

    .preview .str {
      color: #86efac;
    }

    .preview .fn {
      color: #38bdf8;
    }

    .preview .cm {
      color: #64748b;
      font-style: italic;
    }

    .preview .var {
      color: #f9a8d4;
    }

    @media (max-width: 900px) {
      .hero__inner {
        grid-template-columns: 1fr;
        gap: 40px;
      }
    }

    /* ============================================================
   Stats strip
   ============================================================ */
    .stats {
      background: var(--bg-soft);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      padding: 36px 0;
    }

    .stats__grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }

    .stat {
      text-align: center;
    }

    .stat__value {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--dark);
      letter-spacing: -0.02em;
    }

    .stat__label {
      font-size: 13px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin-top: 4px;
    }

    @media (max-width: 720px) {
      .stats__grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
      }
    }

    /* ============================================================
   Sections
   ============================================================ */
    .section {
      padding: 96px 0;
    }

    .section--soft {
      background: var(--bg-soft);
    }

    .section__head {
      text-align: center;
      max-width: 720px;
      margin: 0 auto 56px;
    }

    .section__eyebrow {
      display: inline-block;
      color: var(--primary);
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 12px;
    }

    .section h2 {
      font-size: clamp(1.8rem, 3vw, 2.5rem);
      color: var(--dark);
      margin: 0 0 14px;
      letter-spacing: -0.02em;
    }

    .section__sub {
      color: var(--muted);
      font-size: 1.05rem;
      margin: 0;
    }

    /* Feature cards */
    .features {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }

    .feature {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }

    .feature:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow);
      border-color: #cbd5e1;
    }

    .feature__icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: linear-gradient(135deg, rgba(79, 70, 229, 0.1), rgba(6, 182, 212, 0.1));
      color: var(--primary);
      display: grid;
      place-items: center;
      margin-bottom: 18px;
      font-size: 22px;
    }

    .feature h3 {
      margin: 0 0 8px;
      font-size: 1.1rem;
      color: var(--dark);
    }

    .feature p {
      margin: 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    @media (max-width: 900px) {
      .features {
        grid-template-columns: 1fr;
      }
    }

    /* ============================================================
   Showcase / Items table
   ============================================================ */
    .showcase {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .showcase__bar {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      padding: 18px 20px;
      border-bottom: 1px solid var(--border);
      background: var(--bg-soft);
      align-items: center;
    }

    .showcase__bar form {
      display: contents;
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .field label {
      font-size: 11px;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .field input,
    .field select {
      padding: 9px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      background: #fff;
      color: var(--dark);
      min-width: 140px;
    }

    .field input:focus,
    .field select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    .showcase__hint {
      margin: 14px 22px 0;
      color: var(--muted);
      font-size: 13px;
    }

    .showcase__hint code {
      background: #f1f5f9;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 12px;
    }

    .table-wrap {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    th,
    td {
      padding: 14px 20px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }

    th {
      background: #fff;
      color: var(--muted);
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    tbody tr:hover {
      background: var(--bg-soft);
    }

    tbody tr:last-child td {
      border-bottom: 0;
    }

    tfoot th {
      background: var(--bg-soft);
      color: var(--dark);
      font-weight: 700;
      text-transform: none;
      letter-spacing: 0;
      font-size: 14px;
    }

    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      background: rgba(79, 70, 229, 0.1);
      color: var(--primary-dark);
      text-transform: capitalize;
    }

    .badge--sweet {
      background: rgba(245, 158, 11, 0.12);
      color: #b45309;
    }

    .badge--veg {
      background: rgba(16, 185, 129, 0.12);
      color: #047857;
    }

    .badge--other {
      background: rgba(100, 116, 139, 0.12);
      color: #475569;
    }

    .empty {
      text-align: center;
      padding: 40px 20px;
      color: var(--muted);
      font-style: italic;
    }

    /* ============================================================
   Forms
   ============================================================ */
    .form {
      max-width: 640px;
      margin: 0 auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 36px;
      box-shadow: var(--shadow);
    }

    .form__row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form .field {
      margin-bottom: 18px;
    }

    .form input[type="text"],
    .form input[type="email"],
    .form input[type="number"],
    .form textarea,
    .form select {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 15px;
      font-family: inherit;
      color: var(--dark);
      background: #fff;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form input:focus,
    .form textarea:focus,
    .form select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
    }

    .form label {
      font-weight: 600;
      font-size: 13px;
      color: var(--dark);
      margin-bottom: 6px;
      display: block;
    }

    .form textarea {
      resize: vertical;
      min-height: 90px;
    }

    .colors {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .colors label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border: 1px solid var(--border);
      border-radius: 999px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      background: #fff;
      transition: all 0.15s;
      margin: 0;
    }

    .colors label:hover {
      border-color: var(--primary);
    }

    .colors input[type="checkbox"] {
      accent-color: var(--primary);
    }

    .colors input:checked+* {
      color: var(--primary);
    }

    .colors label:has(input:checked) {
      background: rgba(79, 70, 229, 0.08);
      border-color: var(--primary);
      color: var(--primary-dark);
    }

    .form__actions {
      display: flex;
      gap: 10px;
      margin-top: 8px;
    }

    .alert {
      padding: 14px 18px;
      border-radius: 10px;
      margin: 20px auto 0;
      max-width: 640px;
      font-size: 14px;
    }

    .alert--success {
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .alert--error {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .alert--error ul {
      margin: 8px 0 0 18px;
      padding: 0;
    }

    @media (max-width: 600px) {
      .form__row {
        grid-template-columns: 1fr;
      }

      .form {
        padding: 24px;
      }
    }

    /* ============================================================
   Demo widgets
   ============================================================ */
    .demos {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }

    .demo {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 26px;
      box-shadow: var(--shadow-sm);
    }

    .demo h3 {
      margin: 0 0 6px;
      font-size: 1.05rem;
      color: var(--dark);
    }

    .demo p {
      margin: 0 0 18px;
      color: var(--muted);
      font-size: 14px;
    }

    .demo .counter {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      margin: 8px 0 14px;
    }

    .demo .btn {
      padding: 9px 16px;
      font-size: 14px;
    }

    pre {
      background: #0f172a;
      color: #e2e8f0;
      padding: 14px;
      border-radius: 8px;
      overflow-x: auto;
      font-size: 12.5px;
      margin: 12px 0 0;
      max-height: 220px;
    }

    .mixer input[type="range"] {
      width: 100%;
      accent-color: var(--primary);
    }

    .mixer-row {
      display: grid;
      grid-template-columns: 60px 1fr 44px;
      align-items: center;
      gap: 10px;
      margin-bottom: 8px;
      font-size: 13px;
      color: var(--muted);
    }

    .mixer-row span:last-child {
      text-align: right;
      color: var(--dark);
      font-weight: 600;
      font-family: "SFMono-Regular", Menlo, monospace;
    }

    .swatch {
      height: 56px;
      border-radius: 8px;
      margin-top: 14px;
      background: rgb(128, 128, 128);
      box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);
    }

    @media (max-width: 900px) {
      .demos {
        grid-template-columns: 1fr;
      }
    }

    /* ============================================================
   Footer
   ============================================================ */
    .footer {
      background: var(--dark);
      color: #cbd5e1;
      padding: 56px 0 28px;
    }

    .footer__grid {
      display: grid;
      grid-template-columns: 1.4fr 1fr 1fr 1fr;
      gap: 40px;
      margin-bottom: 40px;
    }

    .footer h4 {
      color: #fff;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin: 0 0 14px;
    }

    .footer ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .footer li {
      margin-bottom: 8px;
    }

    .footer a {
      color: #cbd5e1;
      font-size: 14px;
    }

    .footer a:hover {
      color: #fff;
      text-decoration: none;
    }

    .footer__brand p {
      font-size: 14px;
      color: #94a3b8;
      max-width: 320px;
      margin: 12px 0 0;
    }

    .footer__bar {
      border-top: 1px solid #1e293b;
      padding-top: 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: #94a3b8;
      flex-wrap: wrap;
      gap: 12px;
    }

    @media (max-width: 720px) {
      .footer__grid {
        grid-template-columns: 1fr 1fr;
        gap: 28px;
      }
    }
  </style>
</head>

<body>

  <!-- ============================================================
     Navigation
     ============================================================ -->
  <nav class="nav">
    <div class="container nav__inner">
      <a href="#top" class="brand">
        <span class="brand__mark">Q</span>
        <?= htmlspecialchars($config["app_name"]) ?>
      </a>
      <ul class="nav__links">
        <li><a href="#features">Features</a></li>
        <li><a href="#showcase">Showcase</a></li>
        <li><a href="#demos">Live Demos</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>
      <a href="#contact" class="nav__cta">Get Started →</a>
    </div>
  </nav>

  <!-- ============================================================
     Hero
     ============================================================ -->
  <header class="hero" id="top">
    <div class="container hero__inner">
      <div>
        <span class="hero__badge"><span class="dot"></span> v<?= htmlspecialchars($config["version"]) ?> · Now in beta</span>
        <h1>Code faster with the <span class="grad">IDE you'll love</span>.</h1>
        <p class="lead"><?= htmlspecialchars($config["tagline"]) ?> Powered by smart tooling, real-time previews, and an interface that just gets out of your way.</p>
        <div class="hero__cta">
          <a href="#contact" class="btn btn-primary">Start free trial</a>
          <a href="#features" class="btn btn-ghost">See features</a>
        </div>
        <div class="hero__meta">
          <span>✓ No credit card</span>
          <span>✓ Setup in 60 seconds</span>
        </div>
      </div>
      
      <div class="preview" aria-hidden="true">
        <div class="preview__bar"><i></i><i></i><i></i></div>
        <div><span class="ln">1</span><span class="cm">// welcome.php</span></div>
        <div><span class="ln">2</span><span class="kw">function</span> <span class="fn">greet</span>(<span class="var">$name</span>) {</div>
        <div><span class="ln">3</span>&nbsp;&nbsp;&nbsp;&nbsp;<span class="kw">return</span> <span class="str">"Hello, <span class="var">$name</span>!"</span>;</div>
        <div><span class="ln">4</span>}</div>
        <div><span class="ln">5</span></div>
        <div><span class="ln">6</span><span class="kw">echo</span> <span class="fn">greet</span>(<span class="str">"<?= htmlspecialchars($name ?: 'developer') ?>"</span>);</div>
        <div><span class="ln">7</span><span class="cm">// UUID: <?= htmlspecialchars($uuid) ?></span></div>
      </div>
    </div>
  </header>
  
  <!-- ============================================================
     Stats
     ============================================================ -->
  <section class="stats">
    <div class="container">
      <div class="stats__grid">
        <div class="stat">
          <div class="stat__value"><?= $randomNumber ?>K+</div>
          <div class="stat__label">Active developers</div>
        </div>
        <div class="stat">
          <div class="stat__value">99.9%</div>
          <div class="stat__label">Uptime</div>
        </div>
        <div class="stat">
          <div class="stat__value"><?= $visits ?></div>
          <div class="stat__label">Your visits</div>
        </div>
        <div class="stat">
          <div class="stat__value"><?= $totalCount ?></div>
          <div class="stat__label">Items shown</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
     Features
     ============================================================ -->
  <section class="section" id="features">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Features</span>
        <h2>Everything you need to ship faster</h2>
        <p class="section__sub">A complete toolkit for modern web development — from editing to deployment, all in one place.</p>
      </div>

      <div class="features">
        <div class="feature">
          <div class="feature__icon">⚡</div>
          <h3>Lightning fast</h3>
          <p>Optimized for instant startup and snappy interactions, even on large projects.</p>
        </div>
        <div class="feature">
          <div class="feature__icon">🎨</div>
          <h3>Beautiful by default</h3>
          <p>A polished UI that adapts to your workflow with light & dark themes out of the box.</p>
        </div>
        <div class="feature">
          <div class="feature__icon">🔌</div>
          <h3>Extensible</h3>
          <p>Plug into your favorite tools with a powerful extension API and rich integrations.</p>
        </div>
        <div class="feature">
          <div class="feature__icon">🔒</div>
          <h3>Secure by design</h3>
          <p>Built-in authentication, encrypted secrets, and SOC 2-ready infrastructure.</p>
        </div>
        <div class="feature">
          <div class="feature__icon">👥</div>
          <h3>Real-time collaboration</h3>
          <p>Edit together with your team using live cursors, comments, and shared terminals.</p>
        </div>
        <div class="feature">
          <div class="feature__icon">🚀</div>
          <h3>Deploy in one click</h3>
          <p>Push to production with zero-downtime deployments and automatic rollbacks.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
     Showcase (Filterable items table)
     ============================================================ -->
  <section class="section section--soft" id="showcase">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Showcase</span>
        <h2>Filter, search, and paginate with ease</h2>
        <p class="section__sub">A live demo powered by PHP query parameters. Try the filter dropdown, search box, or the JSON API.</p>
      </div>

      <div class="showcase">
        <div class="showcase__bar">
          <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" form="filterForm" placeholder="e.g. apple" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="field">
            <label for="filter">Category</label>
            <select id="filter" name="filter" form="filterForm">
              <option value="all" <?= $filter === "all" ? "selected" : "" ?>>All</option>
              <?php foreach (["fruit", "veg", "sweet", "other"] as $cat): ?>
                <option value="<?= $cat ?>" <?= $filter === $cat ? "selected" : "" ?>><?= ucfirst($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="limit">Limit</label>
            <input type="number" id="limit" name="limit" form="filterForm" min="1" max="20" value="<?= $limit ?>">
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <div style="display:flex; gap:8px;">
              <button type="submit" form="filterForm" class="btn btn-primary" style="padding:9px 16px; font-size:14px;">Apply</button>
              <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" class="btn btn-ghost" style="padding:9px 16px; font-size:14px;">Reset</a>
            </div>
          </div>
        </div>

        <form id="filterForm" method="get" style="display:none;"></form>

        <p class="showcase__hint">
          Tip: Try <code><?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>?filter=fruit&amp;search=ap&amp;limit=3</code>
          or hit the JSON endpoint <code>?api=1</code>.
        </p>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Category</th>
                <th style="text-align:right;">Price</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="4" class="empty">No matching items.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td style="color: var(--muted);">#<?= $item["id"] ?></td>
                    <td style="font-weight: 600;"><?= htmlspecialchars($item["name"]) ?></td>
                    <td><span class="badge badge--<?= htmlspecialchars($item["category"]) ?>"><?= htmlspecialchars($item["category"]) ?></span></td>
                    <td style="text-align:right; font-family: 'SFMono-Regular', Menlo, monospace;">$<?= number_format($item["price"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3">Total (<?= $totalCount ?> item<?= $totalCount === 1 ? '' : 's' ?>)</th>
                <th style="text-align:right;">$<?= number_format($totalPrice, 2) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
     Live Demos (JS widgets)
     ============================================================ -->
  <section class="section" id="demos">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Interactive</span>
        <h2>Try it live in your browser</h2>
        <p class="section__sub">Three little JavaScript demos that show what's possible — no install required.</p>
      </div>

      <div class="demos">
        <div class="demo">
          <h3>Click Counter</h3>
          <p>Track button clicks with vanilla JS.</p>
          <div class="counter"><span id="counter">0</span></div>
          <button id="clickBtn" class="btn btn-primary">Click me</button>
        </div>

        <div class="demo">
          <h3>JSON API</h3>
          <p>Fetch live data from the server endpoint.</p>
          <button id="fetchBtn" class="btn btn-primary">Load JSON</button>
          <pre id="jsonOut">// click "Load JSON" to fetch ?api=1</pre>
        </div>

        <div class="demo mixer">
          <h3>Color Mixer</h3>
          <p>Drag the sliders to mix a
            color.</p>
          <div class="mixer-row"><span>Red</span>
            <input type="range" id="r" min="0" max="255" value="128"><span id="rVal">128</span>
          </div>
          <div class="mixer-row"><span>Green</span>
            <input type="range" id="g" min="0" max="255" value="128"><span id="gVal">128</span>
          </div>
          <div class="mixer-row"><span>Blue</span>
            <input type="range" id="b" min="0" max="255" value="128"><span id="bVal">128</span>
          </div>
          <div id="swatch" class="swatch"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================================================
     Contact / Signup form
     ============================================================ -->
  <section class="section section--soft" id="contact">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Get started</span>
        <h2>Ready to give it a try?</h2>
        <p class="section__sub">Drop your details and we'll set you up with a free trial. No credit card needed.</p>
      </div>

      <form class="form" method="post" novalidate>
        <div class="form__row">
          <div class="field">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
          </div>
          <div class="field">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
          </div>
        </div>

        <div class="field">
          <label for="age">Age *</label>
          <input type="number" id="age" name="age" min="1" max="120" value="<?= htmlspecialchars($age) ?>" required>
        </div>

        <div class="field">
          <label>Favorite colors *</label>
          <div class="colors">
            <?php foreach (["red", "green", "blue", "yellow", "purple"] as $c): ?>
              <label>
                <input type="checkbox" name="colors[]" value="<?= $c ?>" <?= in_array($c, $colors, true) ? "checked" : "" ?>>
                <?= ucfirst($c) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label for="notes">Notes</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Anything else we should know?"><?= htmlspecialchars($notes) ?></textarea>
        </div>

        <div class="form__actions">
          <button type="submit" class="btn btn-primary">Sign up free</button>
          <button type="reset" class="btn btn-ghost">Reset</button>
        </div>
      </form>

      <?php if (!empty($errors)): ?>
        <div class="alert alert--error">
          <strong>Please fix the following:</strong>
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php elseif (!empty($message)): ?>
        <div class="alert alert--success"><?= $message ?></div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================================================
     Footer
     ============================================================ -->
  <footer class="footer">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__brand">
          <a href="#top" class="brand" style="color:#fff;">
            <span class="brand__mark">Q</span> <?= htmlspecialchars($config["app_name"]) ?>
          </a>
          <p>A modern coding experience, right in your browser. Built for developers who care about craft.</p>
        </div>
        <div>
          <h4>Product</h4>
          <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#showcase">Showcase</a></li>
            <li><a href="#demos">Demos</a></li>
            <li><a href="#contact">Pricing</a></li>
          </ul>
        </div>
        <div>
          <h4>Company</h4>
          <ul>
            <li><a href="#">About</a></li>
            <li><a href="#">Blog</a></li>
            <li><a href="#">Careers</a></li>
            <li><a href="#">Press</a></li>
          </ul>
        </div>
        <div>
          <h4>Resources</h4>
          <ul>
            <li><a href="#contact">Contact</a></li>
            <li><a href="#">Docs</a></li>
            <li><a href="#">Support</a></li>
            <li><a href="#">Status</a></li>
          </ul>
        </div>
      </div>
      <div class="footer__bar">
        <span>© <?= date("Y") ?> <?= htmlspecialchars($config["app_name"]) ?>. All rights reserved.</span>
        <span>Server time: <?= htmlspecialchars($currentTime) ?> · Method: <?= htmlspecialchars($submittedMethod) ?></span>
      </div>
    </div>
  </footer>

  <script>
    /* ============================================================
   JavaScript Block
   ============================================================ */

    // -- Click counter ------------------------------------------------
    let count = 0;
    const counterEl = document.getElementById("counter");
    document.getElementById("clickBtn").addEventListener("click", () => {
      count++;
      counterEl.textContent = count;
      if (count % 5 === 0 && count > 0) {
        counterEl.style.transform = "scale(1.15)";
        setTimeout(() => counterEl.style.transform = "scale(1)", 200);
      }
    });
    counterEl.style.transition = "transform 0.2s";
    counterEl.style.display = "inline-block";

    // -- JSON fetch ---------------------------------------------------
    document.getElementById("fetchBtn").addEventListener("click", async () => {
      const out = document.getElementById("jsonOut");
      out.textContent = "Loading…";
      try {
        const res = await fetch("?api=1");
        const data = await res.json();
        out.textContent = JSON.stringify(data, null, 2);
        console.log("API response:", data);
      } catch (err) {
        out.textContent = "Error: " + err.message;
      }
    });

    // -- Live color mixer --------------------------------------------
    const r = document.getElementById("r");
    const g = document.getElementById("g");
    const b = document.getElementById("b");
    const swatch = document.getElementById("swatch");

    function updateColor() {
      const rv = +r.value,
        gv = +g.value,
        bv = +b.value;
      document.getElementById("rVal").textContent = rv;
      document.getElementById("gVal").textContent = gv;
      document.getElementById("bVal").textContent = bv;
      swatch.style.background = `rgb(${rv},${gv},${bv})`;
    }
    [r, g, b].forEach(el => el.addEventListener("input", updateColor));
    updateColor();
    
    // -- Console info -------------------------------------------------
    console.log("JS loaded:", new Date().toISOString());
    console.log("Browser:", navigator.userAgent);
    console.log("URL params:", Object.fromEntries(new URLSearchParams(location.search)));
  </script>
</body>

</html>