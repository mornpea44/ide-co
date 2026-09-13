/**
* ═══════════════════════════════════════════════════════════════════════════
*  QUIRKY IDE — API CLIENT (File 14 of 20)
* ═══════════════════════════════════════════════════════════════════════════
*
*  The single source of truth for all backend communication.
*  Instead of using fetch() directly, other modules call methods here.
*
*  Handles:
*  - URL construction (index.php?api=...)
*  - JSON serialization
*  - Error catching and formatting
*  - Request cancellation via AbortController (Task 5.1)
*
* ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';
    const api = {};
    // The backend router. Since shell.php is loaded via index.php,
    // we can just use 'index.php' as the relative base.
    const BASE_URL = 'index.php';
    /**
    * Create an AbortController — a tiny object that lets the caller cancel
    * an in-flight request. Pass its `.signal` into a request, then call
    * `.abort()` the moment the answer is no longer needed (e.g. the user
    * typed more and the old response is now stale).
    * @returns {AbortController|null} null on very old browsers (the request
    *                                 still works — it just can't be cancelled)
    */
    api.createController = () => (typeof AbortController === 'undefined' ? null : new AbortController());
    /**
    * True when an error is the result of a request being aborted ON PURPOSE
    * (not a real failure). Callers use this to ignore cancelled requests
    * instead of showing a scary error toast.
    * @param {Error} e
    * @returns {boolean}
    */
    api.isAbortError = (e) => !!(e && e.name === 'AbortError');
    /**
    * Core request handler.
    * @param {string} action - The API endpoint (e.g., 'file', 'search')
    * @param {object} options - { method, body, params, signal }
    * @param {AbortSignal} [options.signal] - optional cancellation signal (Task 5.1)
    * @returns {Promise<any>} The 'data' payload from the server
    */
    async function request(action, options = {}) {
        const { method = 'GET', body = null, params = {}, signal = null } = options;
        // 1. Build the URL
        const url = new URL(BASE_URL, window.location.href);
        url.searchParams.set('api', action);
        // 2. Append query parameters for GET and DELETE requests
        // (Our backend reads $_GET['path'] for these methods)
        if ((method === 'GET' || method === 'DELETE') && params) {
            Object.keys(params).forEach(key => {
                if (params[key] !== undefined && params[key] !== null) {
                    url.searchParams.set(key, params[key]);
                }
            });
        }
        // 3. Configure fetch options
        const fetchOptions = {
            method: method,
            headers: {
                'Accept': 'application/json',
            },
        };
        // Optional cancellation (Task 5.1): if the caller handed us an
        // AbortSignal, wire it into fetch so the request can be killed
        // mid-flight. A null/undefined signal simply means "not cancellable".
        if (signal) {
            fetchOptions.signal = signal;
        }
        // Add JSON body for POST/PUT requests
        if (body && method !== 'GET' && method !== 'DELETE') {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(body);
        }
        // 4. Execute and handle response
        try {
            const response = await fetch(url.toString(), fetchOptions);
            // Read the body as text FIRST, then parse — so when it isn't
            // JSON we can show a snippet of what the server actually said.
            const rawText = await response.text();
            let data = null;
            try {
                data = rawText === '' ? null : JSON.parse(rawText);
            } catch (e) {
                // If it's not JSON, it's likely a PHP fatal error or HTML 404
                const snippet = rawText.replace(/\s+/g, ' ').trim().slice(0, 140);
                throw new Error(
                    `Server returned invalid response (${response.status})` +
                    (snippet ? `: ${snippet}` : '') +
                    `. Check PHP error logs.`
                );
            }
            if (data === null) {
                throw new Error(`Server returned an empty response (${response.status}). Check PHP error logs.`);
            }
            // Check for application-level errors (our backend returns { ok: false, error: "..." })
            if (!response.ok || (data && data.ok === false)) {
                const errorMsg = (data && data.error) ? data.error : `HTTP Error ${response.status}`;
                const err = new Error(errorMsg);
                err.status = response.status;
                throw err;
            }
            // Success! Return the 'data' payload directly
            return data.data;
        } catch (error) {
            // Aborted requests (Task 5.1) reject with an 'AbortError'. Rethrow
            // them untouched so callers can recognise them via api.isAbortError()
            // and drop them quietly — they are NOT real failures.
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Network error. Is the PHP server running?');
            }
            throw error;
        }
    }
    /* ═══════════════════════════════════════════════════════════════════════
       AUTHENTICATION
       ═══════════════════════════════════════════════════════════════════════ */

    /** @namespace api.auth */
    api.auth = {
        /**
         * Check whether the user is currently authenticated.
         * @returns {Promise<{authenticated: boolean}>} Auth status
         */
        check: () => request('auth-check'),

        /**
         * Attempt to log in with a password.
         * @param {string} password - The user's password
         * @returns {Promise<{authenticated: boolean}>} Login result
         */
        login: (password) => request('auth', { method: 'POST', body: { password } }),

        /**
         * Log out and destroy the session.
         * @returns {Promise<{authenticated: boolean}>} Always returns authenticated: false
         */
        logout: () => request('logout', { method: 'POST' })
    };

    /* ═══════════════════════════════════════════════════════════════════════
       FILE SYSTEM
       ════════════════════════════════════════════════════════════════════════ */

    /** @namespace api.files */
    api.files = {
        /**
         * Get the full directory tree of the workspace.
         * @returns {Promise<{tree: Array<Object>}>} Nested folder/file structure
         */
        getTree: () => request('tree'),

        /**
         * Get the directory tree with lazy-loading / ordering options.
         * Without params this behaves exactly like getTree().
         * @param {Object} [params={}] - Query options
         * @param {string} [params.path] - Scan ONE sub-folder instead of the
         *        whole workspace (lazy load; jail-resolved server-side)
         * @param {number} [params.depth] - Folder levels to descend (1..6)
         * @param {string} [params.sort] - 'name' | 'size' | 'mtime'
         * @param {string} [params.hidden] - '1' reveals dotfiles
         * @returns {Promise<{tree: Array<Object>}>} Nested folder/file
         *          structure; folders cut off by the depth budget carry
         *          unloaded:true so the client can fetch them on demand
         */
        tree: (params) => request('tree', { params: params || {} }),

        /**
         * Get a lightweight fingerprint + mtime map for change detection polling.
         * @returns {Promise<{fingerprint: string, mtimes: Object<string, number>}>}
         */
        watch: () => request('watch'),

        /**
         * Read a single file's content and metadata.
         * @param {string} path - Workspace-relative file path (e.g. 'project/index.php')
         * @returns {Promise<{path: string, name: string, content: string|null, binary: boolean, size: number, sizeHuman: string, modified: string, extension: string}|null>}
         *          Returns null if path is empty.
         */
        read: (path) => path
            ? request('file', { params: { path } })
            : Promise.resolve(null),

        /**
         * Read multiple files in a single request (batch session restore).
         * @param {string[]} paths - Array of workspace-relative file paths
         * @returns {Promise<{files: Array<{ok: boolean, path: string, content?: string, error?: string}>, count: number}>}
         */
        readBatch: (paths) => request('files-batch', {
            method: 'POST',
            body: { paths: paths }
        }),

        /**
         * Save (create or overwrite) a file with the given content.
         * @param {string} path - Workspace-relative file path
         * @param {string} content - The full file content to write
         * @returns {Promise<{path: string, name: string, isNew: boolean, size: number, sizeHuman: string, modified: string}>}
         */
        save: (path, content) => request('file', {
            method: 'POST',
            body: { path, content }
        }),

        /**
         * Delete a single file.
         * @param {string} path - Workspace-relative file path
         * @returns {Promise<{path: string, deleted: boolean}>}
         */
        delete: (path) => request('file', {
            method: 'DELETE',
            params: { path }
        }),

        /**
         * Get metadata about a file or folder (without reading content).
         * @param {string} path - Workspace-relative path
         * @returns {Promise<{path: string, name: string, type: string, modified: string, size?: number, sizeHuman?: string, extension?: string, childCount?: number}|null>}
         *          Returns null if path is empty.
         */
        getInfo: (path) => {
            if (!path) return Promise.resolve(null);
            return request('file-info', { params: { path } }).then(function (data) {
                // Handle the 200 OK "notFound" response from the server
                if (data && data.notFound) return null;
                return data;
            }).catch(function (err) {
                // Fallback silent catch just in case
                if (err && err.status === 404) return null;
                throw err;
            });
        },

        /**
         * Recursively copy a file or folder to a new location.
         * @param {string} from - Source path (workspace-relative)
         * @param {string} to - Destination path (workspace-relative)
         * @returns {Promise<{source: string, dest: string, copied: boolean, files: number, folders: number}>}
         */
        copy: (from, to) => request('copy', {
            method: 'POST',
            body: { from, to }
        }),

        /**
         * Rename or move a file/folder.
         * @param {string} from - Current path (workspace-relative)
         * @param {string} to - New path (workspace-relative)
         * @returns {Promise<{from: string, to: string, renamed: boolean}>}
         */
        rename: (from, to) => request('rename', {
            method: 'POST',
            body: { from, to }
        }),

        /**
         * Create a new folder (parent folders are auto-created).
         * @param {string} path - Workspace-relative folder path
         * @returns {Promise<{path: string, name: string, created: boolean}>}
         */
        createFolder: (path) => request('folder', {
            method: 'POST',
            body: { path }
        }),

        /**
         * Delete a folder and all its contents recursively.
         * @param {string} path - Workspace-relative folder path
         * @returns {Promise<{path: string, deleted: boolean}>}
         */
        deleteFolder: (path) => request('folder', {
            method: 'DELETE',
            params: { path }
        }),

        /**
         * ★ Explorer Upgrade: create a short-lived download token for exporting
         * a file or folder (folders are zipped server-side). The actual download
         * happens via index.php?export-token=… so the Android DownloadListener
         * can fetch it without session cookies.
         * @param {string} path - Workspace-relative path ('' = whole workspace)
         */
        exportToken: (path) => request('files-export-token', {
            method: 'POST',
            body: { path: path || '' }
        }),

        /**
         * ★ Explorer Upgrade: server-side import — copy an absolute source path
         * (chosen via the Android picker / staged into the app cache) into the
         * workspace.
         * @param {string} from - Absolute source path reported by the Android bridge
         * @param {string} dest - Workspace-relative destination (including the name)
         * @param {boolean} [overwrite=false]
         */
        importPath: (from, dest, overwrite) => request('files-import-path', {
            method: 'POST',
            body: { from, dest, overwrite: !!overwrite }
        })
    };

    /* ═══════════════════════════════════════════════════════════════════════
    WORKSPACE (★ Explorer Upgrade — dynamic workspace root)
    ════════════════════════════════════════════════════════════════════════ */
    /** @namespace api.workspace */
    api.workspace = {
        /** Current workspace info: path, stats, disk free, default flag. */
        info: () => request('workspace-info'),

        /** Available preset locations with exists/writable flags. */
        presets: () => request('workspace-presets'),

        /**
         * Switch the workspace root. path='__default__' resets to the
         * app-private default. copyFiles copies the current files across.
         * The IDE should be reloaded after a successful switch.
         */
        switch: (path, copyFiles) => request('workspace-switch', {
            method: 'POST',
            body: { path, copyFiles: !!copyFiles }
        })
    };

    /* ═══════════════════════════════════════════════════════════════════════
       SEARCH
       ════════════════════════════════════════════════════════════════════════ */

    /** @namespace api.search */
    api.search = {
        /**
         * Search all files in the workspace for a text or regex pattern.
         * @param {string} query - The search query or regex pattern
         * @param {Object} [options={}] - Search options
         * @param {boolean} [options.caseSensitive=false] - Match exact case
         * @param {boolean} [options.useRegex=false] - Treat query as regex
         * @param {string} [options.fileFilter=''] - Glob filter (e.g. '*.php')
         * @param {number} [options.maxResults=500] - Max total matches to return
         * @returns {Promise<{query: string, totalMatches: number, totalFiles: number, filesScanned: number, truncated: boolean, results: Array<{file: string, matchCount: number, matches: Array<{line: number, content: string, matchStart: number, matchLength: number}>}>}>}
         */
        query: (query, options = {}) => request('search', {
            method: 'POST',
            body: { query, ...options }
        }),

        /**
         * Replace all occurrences across workspace files.
         * @param {string} query - The search query or regex pattern to find
         * @param {string} replacement - The replacement string (supports $1 backrefs in regex mode)
         * @param {Object} [options={}] - Same options as query(), plus optional `files` array
         * @param {string[]} [options.files] - Restrict replacement to these specific file paths
         * @returns {Promise<{query: string, replacement: string, filesChanged: number, totalReplaced: number, files: Array<{file: string, replacements: number}>}>}
         */
        replace: (query, replacement, options = {}) => request('search-replace', {
            method: 'POST',
            body: { query, replacement, ...options }
        }),

        /**
         * Get quick stats about searchable files in the workspace.
         * @returns {Promise<{totalFiles: number, totalSize: number, totalSizeHuman: string}>}
         */
        getStats: () => request('search-stats')
    };

    /* ══════════════════════════════════════════════════════════════════════
       TERMINAL
       ════════════════════════════════════════════════════════════════════════ */

    /** @namespace api.terminal */
    api.terminal = {
        /**
         * Run a shell command inside the workspace (one-shot, waits for completion).
         * @param {string} command - The command to execute (e.g. 'php -v', 'dir')
         * @param {string} [stdin=''] - Optional stdin input to feed to the command
         * @returns {Promise<{command: string, output: string, error: string, exitCode: number, timedOut: boolean, duration: number, cwd: string, shell: string}>}
         */
        run: (command, stdin = '') => request('terminal', {
            method: 'POST',
            body: { command, stdin }
        }),

        /**
         * Get information about the terminal environment (shell, PHP version, OS).
         * @returns {Promise<{enabled: boolean, shell: string, shellLabel: string, cwd: string, phpVersion: string, os: string, osFamily: string, isWindows: boolean, timeout: number, procOpenOk: boolean}>}
         */
        getInfo: () => request('terminal-info'),

        /**
         * Cancel (kill) a currently running command.
         * @returns {Promise<{cancelled: boolean, pid?: number, reason?: string}>}
         */
        cancel: () => request('terminal-cancel', { method: 'POST' }),

        /**
         * Send typed input (stdin) to a currently running command.
         * This is what makes interactive programs (scanf, input(), readLine) work.
         * @param {string} input - The text to feed to the running program (include "\n")
         * @returns {Promise<{sent: boolean, reason?: string}>}
         */
        sendStdin: (input) => request('terminal-stdin', {
            method: 'POST',
            body: { input }
        }),
        /**
         * Live-resize the running PTY (T-PTY-7). The server forwards the new
         * size to ptyrun, which fires SIGWINCH so running TUIs redraw.
         * @param {number} cols - New column count
         * @param {number} rows - New row count
         * @returns {Promise<{live: boolean, cols?: number, rows?: number}>}
         */
        resize: (cols, rows) => request('terminal-resize', {
            method: 'POST',
            body: { cols, rows }
        })
    };
    /* ═══════════════════════════════════════════════════════════════════════
       CODE TOOLS (Format & Minify)
       ════════════════════════════════════════════════════════════════════════ */

    /** @namespace api.code */
    api.code = {
        /**
         * Format code server-side (currently only PHP; other languages format client-side).
         * @param {string} code - The source code to format
         * @param {string} language - The language identifier (e.g. 'php')
         * @param {string} [formatter='builtin'] - Formatter to use ('builtin' or 'php-cs-fixer')
         * @returns {Promise<{code: string, language: string, formatter: string, note?: string}>}
         */
        format: (code, language, formatter) => request('format', {
            method: 'POST',
            body: { code, language, formatter }
        }),

        /**
         * Minify code server-side (currently only PHP; other languages minify client-side).
         * @param {string} code - The source code to minify
         * @param {string} language - The language identifier (e.g. 'php')
         * @returns {Promise<{code: string, language: string, note?: string}>}
         */
        minify: (code, language) => request('minify', {
            method: 'POST',
            body: { code, language }
        })
    };

    /* ═══════════════════════════════════════════════════════════════════
       LINT (error-lens backend)
       ════════════════════════════════════════════════════════════════════ */

    /** @namespace api.lint */
    api.lint = {
        /**
         * Lint code content for syntax errors. Multi-language:
         * php → `php -l`; python → `python -m py_compile`; js/mjs →
         * `node --check`; ts/jsx → ok with a note (no compiler on device).
         * Missing toolchains never fail — they return zero markers + note.
         * @param {string} content - The source code to lint
         * @param {string} language - The language identifier (e.g. 'php', 'python', 'js')
         * @param {AbortSignal} [signal=null] - Optional cancellation signal (Task 5.1).
         *        Pass a signal to allow stale lint requests to be cancelled mid-flight.
         * @returns {Promise<{markers: Array<{line: number, severity: string, message: string, source: string}>, language: string, ok: boolean, note?: string}>}
         */
        check: (content, language, signal) => request('lint', {
            method: 'POST',
            body: { content, language },
            signal: signal
        })
    };

    /* ═══════════════════════════════════════════════════════════════════════
       SYMBOLS (document outline indexer)
       ═══════════════════════════════════════════════════════════════════════ */

    /** @namespace api.symbols */
    api.symbols = {
        /**
         * Index the symbols of a workspace file (functions, methods,
         * classes, …). Regex-based per language; unsupported/binary/oversized
         * files return an empty list.
         * @param {string} path - Workspace-relative file path
         * @returns {Promise<{symbols: Array<{line: number, name: string, kind: string, signature: string, owner?: string}>}>}
         *          kind ∈ function|method|class|interface|trait|struct|constant|variable-export
         */
        index: (path) => request('symbols-index', { params: { path } })
    };

    /* ═══════════════════════════════════════════════════════════════════════
    SNIPPET STUDIO (custom boilerplates & snippets)
    ═══════════════════════════════════════════════════════════════════════ */
    /** @namespace api.snippets */
    api.snippets = {
        /**
         * List all custom snippets, optionally filtered by language.
         * @param {string} [lang=null] - Filter by language key (e.g. 'php')
         * @returns {Promise<{snippets: Array, count: number, total: number}>}
         */
        list: (lang) => request('snippets-list', { params: lang ? { lang } : {} }),

        /**
         * Create or update a snippet.
         * @param {Object} snippet - {id?, lang, label, type, detail, body}
         * @returns {Promise<{saved: boolean, id: string, lang: string, label: string}>}
         */
        save: (snippet) => request('snippets-save', { method: 'POST', body: snippet }),

        /**
         * Delete a snippet by ID.
         * @param {string} id
         * @returns {Promise<{deleted: boolean, id: string}>}
         */
        delete: (id) => request('snippets-delete', { method: 'POST', body: { id } }),

        /**
         * Bulk import snippets from a parsed JSON structure.
         * @param {Object} data - {snippets: [{lang, label, type, detail, body}]}
         * @returns {Promise<{imported: number, skipped: number, errors: Array}>}
         */
        import: (data) => request('snippets-import', { method: 'POST', body: data }),

        /**
         * Export all snippets as a portable JSON structure.
         * @returns {Promise<{app: string, version: number, exportedAt: string, snippets: Array}>}
         */
        export: () => request('snippets-export'),

        /**
         * Get snippet counts per language (for coverage overview).
         * @returns {Promise<{coverage: Object}>}
         */
        coverage: () => request('snippets-coverage')
    };

    /* ═══════════════════════════════════════════════════════════════════════
       GIT SOURCE CONTROL (write side; read side consumed via raw fetch in
       mobile-git.js today)
       ═══════════════════════════════════════════════════════════════════════ */

    /** @namespace api.git */
    api.git = {
        /**
         * Stage files (git add --).
         * @param {string[]} paths - Workspace-relative file paths
         * @returns {Promise<{ok: boolean, output: string, error: null, staged: string[], count: number}>}
         */
        stage: (paths) => request('git-stage', { method: 'POST', body: { paths } }),

        /**
         * Unstage files (git restore --staged, falls back to git reset HEAD --).
         * @param {string[]} paths - Workspace-relative file paths
         * @returns {Promise<{ok: boolean, output: string, error: null, unstaged: string[], count: number}>}
         */
        unstage: (paths) => request('git-unstage', { method: 'POST', body: { paths } }),

        /**
         * ★ DESTRUCTIVE — revert UNCOMMITTED changes in the given files.
         * The UI MUST show a confirmation dialog before calling this.
         * @param {string[]} paths - Workspace-relative file paths
         * @returns {Promise<{ok: boolean, output: string, error: null, discarded: string[], count: number}>}
         */
        discard: (paths) => request('git-discard', { method: 'POST', body: { paths } }),

        /**
         * Commit staged changes with an identity from server config
         * (defaults: Quirky <dev@quirky.local>).
         * @param {string} message - Commit message (empty is refused server-side)
         * @param {boolean} [amend=false] - Amend the previous commit instead
         * @returns {Promise<{ok: boolean, output: string, error: null, amended: boolean, branch: string|null}>}
         */
        commit: (message, amend = false) => request('git-commit', { method: 'POST', body: { message, amend } }),

        /**
         * Initialize a repository in workspace/.
         * @returns {Promise<{ok: boolean, output: string, error: null, created: boolean, alreadyRepo: boolean, branch: string|null}>}
         */
        init: () => request('git-init', { method: 'POST' }),

        /**
         * List all branches (local + remote), star marks the current one.
         * @returns {Promise<{ok: boolean, output: string, error: null, branches: Array<{name: string, current: boolean, remote: boolean}>, current: string|null}>}
         */
        branchList: () => request('git-branch-list'),

        /**
         * Create and switch to a new branch (git checkout -b).
         * @param {string} name - Branch name ([A-Za-z0-9._-/]{1,80}, no "..", no leading "-")
         * @returns {Promise<{ok: boolean, output: string, error: null, branch: string, created: boolean}>}
         */
        branchCreate: (name) => request('git-branch-create', { method: 'POST', body: { name } }),

        /**
         * Switch branches (git checkout). Dirty-tree refusals come back as
         * readable errors in Error.message (client throws on ok:false).
         * @param {string} name - Existing branch name
         * @returns {Promise<{ok: boolean, output: string, error: null, branch: string}>}
         */
        branchSwitch: (name) => request('git-branch-switch', { method: 'POST', body: { name } }),

        /**
         * Pull from upstream (120s timeout; auth failures give guidance text).
         * @returns {Promise<{ok: boolean, output: string, error: null, branch: string|null}>}
         */
        pull: () => request('git-pull', { method: 'POST' }),

        /**
         * Push to upstream (120s timeout; auth failures give guidance text).
         * @returns {Promise<{ok: boolean, output: string, error: null, branch: string|null}>}
         */
        push: () => request('git-push', { method: 'POST' })
    };

    /* ═══════════════════════════════════════════════════════════════
    FILE HISTORY (Task 5.2)
    ═══════════════════════════════════════════════════════════════ */
    /** @namespace api.fileHistory */
    api.fileHistory = {
        /**
        * List all versions for a file (newest first).
        * @param {string} path - Workspace-relative file path
        * @returns {Promise<Array<{id: string, time: number, timeHuman: string, size: number, sizeHuman: string}>>}
        */
        list: (path) => request('file-history', { params: { path } }),

        /**
        * Get the content of a specific version.
        * @param {string} path - Workspace-relative file path
        * @param {string} version - The version ID (filename)
        * @returns {Promise<{path: string, version: string, content: string, size: number, time: number}>}
        */
        getVersion: (path, version) => request('file-history-version', { params: { path, version } }),

        /**
        * Create a snapshot of the file content.
        * @param {string} path - Workspace-relative file path
        * @param {string} content - The file content to snapshot
        * @returns {Promise<{snapshot: boolean, version?: string, reason?: string}>}
        */
        snapshot: (path, content) => request('file-history-snapshot', {
            method: 'POST',
            body: { path, content }
        }),

        /**
        * Delete a specific version.
        * @param {string} path - Workspace-relative file path
        * @param {string} version - The version ID to delete
        * @returns {Promise<{deleted: boolean}>}
        */
        deleteVersion: (path, version) => request('file-history-version', {
            method: 'DELETE',
            params: { path, version }
        }),

        /**
        * Clear all versions for a file.
        * @param {string} path - Workspace-relative file path
        * @returns {Promise<{cleared: boolean, count: number}>}
        */
        clear: (path) => request('file-history-clear', {
            method: 'POST',
            body: { path }
        })
    };
    /* ═══════════════════════════════════════════════════════════════════════
    EXPOSE TO GLOBAL NAMESPACE
    ═══════════════════════════════════════════════════════════════════════ */
    window.IDE.api = api;
})();