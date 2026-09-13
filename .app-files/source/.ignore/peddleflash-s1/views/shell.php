<?php
/**
 * PEDDLEFLASH - Scenario 1 Test File
 * ------------------------------------------------------------------
 * This is the "shell" page. Open THIS file directly in the IDE and
 * click Preview (this sidesteps the redirect/include issues so you
 * can isolate and test the Asset Trap on its own).
 *
 * As written, the CSS/JS links below are BROKEN on purpose, because
 * they assume the project root instead of being relative to this
 * file's own folder (views/). You should see plain, unstyled HTML
 * and 404s for styles.css / script.js.
 *
 * THE FIX: change "assets/..." to "../assets/..." in the two tags
 * below (see the commented-out corrected versions).
 * ------------------------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Peddleflash - Shell</title>

    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>

    <header class="shell-header">
        <h1>Peddleflash</h1>
        <p>Scenario 1: Asset Trap Test Page</p>
    </header>

    <main class="shell-content">
        <p>If this box below has a colored background and border, your
           CSS loaded correctly. If the page looks like plain black
           text on a white background, the CSS 404'd (the Asset Trap).</p>
        <div class="status-box" id="status-box">
            Checking JS status...
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
</body>
</html>
