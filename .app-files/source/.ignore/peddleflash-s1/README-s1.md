# Peddleflash — Scenario 1 Test Kit
(No `.htaccess` — Redirect / Include / Asset Trap)

This mini project reproduces the 3 issues described in Scenario 1 of the
Quirky IDE cheat sheet.

## Files
```
peddleflash/
├── index.php               <- redirect + include issue (toggle inside file)
├── views/
│   └── shell.php            <- asset trap issue (broken CSS/JS paths)
└── assets/
    ├── css/styles.css       <- real stylesheet
    └── js/script.js         <- real script
```

## How to test

### 1. The Redirect Issue
Open `index.php` in the IDE and click Preview.
- **Expected (broken):** blank page or PHP warning — the IDE's fetcher
  does not follow `header('Location: ...')`.
- **Fix to try:** replace the `header()` call with a JS/meta redirect,
  per the cheat sheet.

### 2. The Include Issue
In `index.php`, comment out "Option A" (the redirect) and uncomment
"Option B" (the `include 'views/shell.php';` block). Click Preview again.
- **Expected (broken):** `Failed to open stream: No such file or directory`.
- **Fix to try:** preview `views/shell.php` directly instead of routing
  through `index.php`.

### 3. The Asset Trap
Open `views/shell.php` directly in the IDE and click Preview.
- **Expected (broken):** plain unstyled HTML — `assets/css/styles.css`
  and `assets/js/script.js` both 404, because the IDE resolves them
  relative to `views/` instead of the project root.
- **Fix to try:** in `shell.php`, comment out the broken `<link>`/
  `<script>` tags and uncomment the `../assets/...` versions instead.
  Re-preview — the page should now be styled, and the status box should
  say "JS loaded successfully!".
