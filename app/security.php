<?php

declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — SECURITY GUARD (File 3 of 20)
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 *  This is the Head of Security for the IDE. 
 *  Nothing happens in the IDE without passing through this file first.
 * 
 *  Its jobs:
 *  1. Password gate (Authentication & Session timeouts)
 *  2. "The Path Jail" (Making sure files can NEVER be accessed outside 
 *     the workspace folder)
 *  3. Rule enforcement (Allowed file types, blocked folders)
 *  4. Sending clean JSON messages back to the browser
 * 
 * ═══════════════════════════════════════════════════════════════════════════
 */

class IdeSecurity
{
    /**
     * The settings loaded from config.php
     */
    private array $config;

    /**
     * The absolute, real path to the workspace folder.
     * We calculate this once when the file loads.
     */
    private string $workspaceRoot;

    /**
     * Constructor: Runs automatically when this class is created.
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // Find the true, physical location of the workspace folder.
        // (str_replace fixes the slashes so they work perfectly on Windows/Laragon)
        $realRoot = realpath(IDE_WORKSPACE);

        if ($realRoot === false) {
            // If the workspace folder doesn't exist yet, create it!
            mkdir(IDE_WORKSPACE, 0777, true);
            $realRoot = realpath(IDE_WORKSPACE);
        }

        $this->workspaceRoot = str_replace('\\', '/', (string) $realRoot);
    }

    /* ═══════════════════════════════════════════════════════════════
       1. THE ID CHECK (Authentication)
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Starts the PHP session (how the server remembers you are logged in).
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('QUIRKY_IDE_SESSION');
            session_start();
        }
    }

    /**
     * Checks if the user is currently allowed inside.
     */
    public function isAuthenticated(): bool
    {
        // If the password gate is turned off in config.php, let everyone in.
        if (empty($this->config['security']['auth_enabled'])) {
            return true;
        }

        $this->startSession();

        // Check if the "logged in" flag exists in the session
        if (empty($_SESSION['quirky_ide_auth'])) {
            return false;
        }

        // Check if the login has expired (based on auth_lifetime in config.php)
        $loginTime = (int) ($_SESSION['quirky_ide_time'] ?? 0);
        $lifetime  = (int) $this->config['security']['auth_lifetime'];

        if ((time() - $loginTime) > $lifetime) {
            $this->logout(); // Time's up! Kick the session out.
            return false;
        }

        return true;
    }

    /**
     * Attempts to log the user in with a password.
     */
    public function login(string $password): bool
    {
        $correctPassword = (string) $this->config['security']['auth_password'];

        // hash_equals is a special PHP function that compares passwords 
        // in a way that prevents timing-based hacker tricks.
        if (hash_equals($correctPassword, $password)) {
            $this->startSession();

            // Stamp the session pass
            $_SESSION['quirky_ide_auth'] = true;
            $_SESSION['quirky_ide_time'] = time();

            return true;
        }

        return false;
    }

    /**
     * Logs the user out and destroys the session pass.
     */
    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION['quirky_ide_auth'], $_SESSION['quirky_ide_time']);
        session_destroy();
    }

    /**
     * The ultimate guard. If you aren't logged in, this stops everything 
     * immediately and sends an error to the browser.
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->fail('Authentication required. Please log in.', 401);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       2. THE FENCE (The Path Jail)
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Takes a path requested by the browser (like "my-project/index.php")
     * and proves it is safely inside the workspace. 
     * Returns the safe, full path. If it's dangerous, it kills the request.
     */
    public function resolvePath(string $userPath): string
    {
        // Rule 1: No "null bytes" (an old hacker trick to hide file extensions)
        if (strpos($userPath, "\0") !== false) {
            $this->fail('Access denied: Invalid path characters.');
        }

        // Rule 2: Make all slashes face the same way and remove leading slashes
        $userPath = str_replace('\\', '/', trim($userPath));
        $userPath = ltrim($userPath, '/');

        // Rule 3: You are not allowed to use ".." (the "go up one folder" trick)
        $segments = explode('/', $userPath);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                $this->fail('Access denied: You cannot navigate outside the workspace.');
            }
        }

        // Rule 4: Build the full path
        $fullPath = $this->workspaceRoot . '/' . $userPath;

        // Rule 5: Separate the folder part from the file name part.
        // (We do this because the file might not exist yet, but the folder MUST exist).
        $parentDir  = dirname($fullPath);
        $baseName   = basename($fullPath);
        $realParent = realpath($parentDir);

        if ($realParent === false) {
            $this->fail('The folder "' . dirname($userPath) . '" does not exist in the workspace. Create the folder first.');
        }

        $realParent = str_replace('\\', '/', $realParent);

        // Rule 6: THE JAIL CHECK. 
        // The real folder must be EXACTLY the workspace, or start with the workspace path.
        // If someone used a sneaky symlink trick to escape, this catches it.
        if ($realParent !== $this->workspaceRoot && strpos($realParent, $this->workspaceRoot . '/') !== 0) {
            $this->fail('Access denied: Path escapes the workspace.');
        }

        // If it passed all the rules, return the safe, verified path!
        return $realParent . '/' . $baseName;
    }

    /* ═══════════════════════════════════════════════════════════════
       3. THE DRESS CODE (File Rules)
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Checks if a path contains any blocked folder names (like .git)
     */
    public function checkBlockedSegments(string $path): void
    {
        $blocked = $this->config['security']['blocked_segments'] ?? [];

        foreach ($blocked as $badSegment) {
            // Check if the bad segment exists as a folder/file name in the path
            if (in_array($badSegment, explode('/', str_replace('\\', '/', $path)), true)) {
                $this->fail('Access denied: "' . $badSegment . '" is a protected/blocked name.');
            }
        }
    }

    /**
     * Checks if the file's extension is allowed by config.php
     */
    public function validateFileExtension(string $path): void
    {
        $allowed = $this->config['security']['allowed_extensions'] ?? [];

        // Get the extension (the part after the last dot) and make it lowercase
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Special files with no extension (like .gitignore or LICENSE) 
        // are checked by their full name.
        $baseName = strtolower(basename($path));

        $isAllowed = in_array($extension, $allowed, true) || in_array($baseName, $allowed, true);

        if (!$isAllowed) {
            $this->fail('Access denied: File type ".' . $extension . '" is not allowed in the IDE.');
        }
    }

    /* ═══════════════════════════════════════════════════════════════
        3b. NON-FATAL CHECKS (used by batch operations)
    ═══════════════════════════════════════════════════════════════
        The same rules as above, but these RETURN an answer instead of
        killing the request. A batch read (several files in one call)
        must not die just because ONE of the paths is bad.            */

    /**
     * Non-fatal blocked-segment check.
     *
     * @param string $path The path to inspect
     * @return bool True when the path contains a blocked segment
     */
    public function hasBlockedSegment(string $path): bool
    {
        $blocked = $this->config['security']['blocked_segments'] ?? [];
        foreach ($blocked as $badSegment) {
            if (in_array($badSegment, explode('/', str_replace('\\', '/', $path)), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Non-fatal extension whitelist check.
     *
     * @param string $path The path to inspect
     * @return bool True when the extension (or full file name) is allowed
     */
    public function isExtensionAllowed(string $path): bool
    {
        $allowed   = $this->config['security']['allowed_extensions'] ?? [];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $baseName  = strtolower(basename($path));
        return in_array($extension, $allowed, true) || in_array($baseName, $allowed, true);
    }

    /**
     * Non-fatal version of resolvePath().
     *
     * @param string $userPath Path relative to the workspace
     * @return string|null The safe absolute path, or null when invalid
     */
    public function tryResolvePath(string $userPath): ?string
    {
        if (strpos($userPath, "\0") !== false) {
            return null;
        }
        $userPath = str_replace('\\', '/', trim($userPath));
        $userPath = ltrim($userPath, '/');
        foreach (explode('/', $userPath) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }
        $fullPath   = $this->workspaceRoot . '/' . $userPath;
        $parentDir  = dirname($fullPath);
        $baseName   = basename($fullPath);
        $realParent = realpath($parentDir);
        if ($realParent === false) {
            return null;
        }
        $realParent = str_replace('\\', '/', $realParent);
        if ($realParent !== $this->workspaceRoot && strpos($realParent, $this->workspaceRoot . '/') !== 0) {
            return null;
        }
        return $realParent . '/' . $baseName;
    }

    /* ═══════════════════════════════════════════════════════════════
       4. THE MESSENGERS (JSON Responses)
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Sends a "Success" message back to the browser and stops.
     */
    public function respond(array $data = [], int $code = 200): void
    {
        // ★ HOTFIX (B-1.1): discard any stray output (BOM / warnings)
        // so the JSON envelope is always the ONLY thing we send.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'   => true,
            'data' => $data
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Sends a "Failure" message back to the browser and stops.
     */
    public function fail(string $message, int $code = 400): void
    {
        // ★ HOTFIX (B-1.1): same stray-output protection as respond().
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => $message
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
