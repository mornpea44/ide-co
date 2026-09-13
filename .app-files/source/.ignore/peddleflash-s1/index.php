<?php
/**
 * PEDDLEFLASH - Scenario 1 Test File
 * ------------------------------------------------------------------
 * This file demonstrates TWO of the three "Scenario 1" problems from
 * the Quirky IDE cheat sheet:
 *
 *   1. The Redirect Issue  -> header('Location: ...')
 *   2. The Include Issue   -> include 'views/shell.php';
 *
 * HOW TO TEST:
 *   - As written below, Option A (redirect) is ACTIVE.
 *     Open this file in the IDE and click Preview. You should see a
 *     blank page / warning instead of being taken to shell.php.
 *
 *   - Comment out Option A and uncomment Option B to test the
 *     include() failure instead. You should see a
 *     "Failed to open stream: No such file or directory" error.
 * ------------------------------------------------------------------
 */

// ---------------------------------------------------------------
// OPTION A: The Redirect Issue (ACTIVE by default)
// ---------------------------------------------------------------
header("Location: views/shell.php");
exit;

// ---------------------------------------------------------------
// OPTION B: The Include Issue
// (comment out Option A above, then uncomment the two lines below)
// ---------------------------------------------------------------
// echo "<h1>Loading shell via include()...</h1>";
// include 'views/shell.php';
