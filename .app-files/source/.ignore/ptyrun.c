/* ═══════════════════════════════════════════════════════════════════════════
 *  ptyrun — run a command attached to a real pseudo-terminal (PTY).
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  This is the heart of Quirky IDE's "real terminal" mode. It creates a
 *  pseudo-terminal pair (master + slave), runs a command with its stdin,
 *  stdout, and stderr wired to the slave side, and shuttles bytes between
 *  the master side and the real stdin/stdout (which PHP's proc_open pipes
 *  connect to).
 *
 *  WHY THIS EXISTS:
 *    PHP's proc_open() gives a command plain PIPES, not a terminal.
 *    Interactive programs (scanf, input(), readline) behave differently
 *    on pipes vs terminals — prompts may not flush, echo is missing,
 *    and some programs refuse to run interactively at all. A PTY fixes
 *    all of this: the program thinks it's talking to a real terminal.
 *
 *  ★ ECHO IS INTENTIONALLY ENABLED ★
 *    The browser frontend (mobile-terminal.js) does NOT show typed input
 *    locally when a PTY is active. It expects the PTY to echo characters
 *    back through the output stream, exactly like a hardware terminal.
 *    A fresh PTY slave has ECHO on by default — we MUST NOT disable it.
 *
 *  ★ LIVE RESIZE (T-PTY-7) ★
 *    If the environment contains PTYRUN_WINSZ_FILE, ptyrun CREATES that
 *    file at startup (the act of creating it tells the IDE "I support
 *    live resize") and re-reads it a few times per second. When the IDE
 *    writes a new "cols rows" into it, ptyrun pushes TIOCSWINSZ into the
 *    master; the kernel then delivers SIGWINCH to the running program,
 *    which redraws itself for the new width — exactly what happens when
 *    you drag a desktop terminal window wider. Old IDE versions simply
 *    never set the variable, so this is backwards compatible.
 *
 *  USAGE:
 *    ptyrun [-w COLS] [-h ROWS] [-c] command [args...]
 *
 *    -c        Run via /bin/sh -c "command" (used by Quirky IDE)
 *    -w COLS   Terminal width  (default: 80)
 *    -h ROWS   Terminal height (default: 24)
 *
 *  BUILD (on-device with clang, or cross-compile):
 *    clang -O2 ptyrun.c -o "$PREFIX/bin/ptyrun"
 *
 * ═══════════════════════════════════════════════════════════════════════════ */

#include <errno.h>
#include <fcntl.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/ioctl.h>
#include <sys/select.h>
#include <sys/wait.h>
#include <termios.h>
#include <unistd.h>

/* Fallback in case the header doesn't define this ioctl. */
#ifndef TIOCSCTTY
#define TIOCSCTTY 0x540E
#endif

/* Default terminal size — the classic 80×24 VT100. */
static int termCols = 80;
static int termRows = 24;

/* Set to 1 when the child process exits (SIGCHLD handler). */
static volatile sig_atomic_t childDone = 0;
static void onChildExit(int sig) { (void)sig; childDone = 1; }

/* ── Live resize (T-PTY-7) ─────────────────────────────────────────
 * Re-read the winsize file written by the IDE. When the size inside
 * differs from the last one we applied, push it into the PTY. The
 * kernel automatically sends SIGWINCH to the child's foreground
 * process group as part of TIOCSWINSZ — no extra signalling needed.
 * Called ~5×/second from the parent's select() timeout loop, which
 * is a cheap small-file read and keeps resize latency under 0.2 s. */
static void maybeApplyWinsize(int m, const char *wsFile, int *haveC, int *haveR)
{
    if (wsFile == NULL) return;
    FILE *f = fopen(wsFile, "r");
    if (f == NULL) return;
    int c = 0, r = 0;
    int ok = (fscanf(f, "%d %d", &c, &r) == 2);
    fclose(f);
    if (!ok || c < 20 || c > 400 || r < 5 || r > 200) return; /* sanity */
    if (c == *haveC && r == *haveR) return;                  /* no change */
    *haveC = c;
    *haveR = r;
    struct winsize ws;
    memset(&ws, 0, sizeof(ws));
    ws.ws_col = (unsigned short)c;
    ws.ws_row = (unsigned short)r;
    ioctl(m, TIOCSWINSZ, &ws);   /* kernel sends SIGWINCH to the child */
}

int main(int argc, char **argv)
{
    const char *cmd = NULL;
    int useShell = 0;
    int argi = 1;

    /* ── Parse options ─────────────────────────────────────────── */
    while (argi < argc) {
        if (strcmp(argv[argi], "-c") == 0) {
            useShell = 1;
            argi++;
            break; /* next arg is the command string */
        } else if (strcmp(argv[argi], "-w") == 0 && argi + 1 < argc) {
            termCols = atoi(argv[++argi]);
            if (termCols < 1) termCols = 80;
            argi++;
        } else if (strcmp(argv[argi], "-h") == 0 && argi + 1 < argc) {
            termRows = atoi(argv[++argi]);
            if (termRows < 1) termRows = 24;
            argi++;
        } else {
            break; /* first non-option arg is the command */
        }
    }

    if (useShell) {
        if (argi >= argc) {
            fprintf(stderr, "ptyrun: -c requires a command string\n");
            return 2;
        }
        cmd = argv[argi];
    } else if (argi < argc) {
        cmd = argv[argi];
    } else {
        fprintf(stderr,
                "usage: ptyrun [-w cols] [-h rows] [-c] command [args...]\n");
        return 2;
    }

    /* ── Open the MASTER side of the PTY ───────────────────────── */
    int m = posix_openpt(O_RDWR | O_NOCTTY);
    if (m < 0) { perror("ptyrun: posix_openpt"); return 2; }

    if (grantpt(m) != 0 || unlockpt(m) != 0) {
        perror("ptyrun: grantpt/unlockpt");
        close(m);
        return 2;
    }

    char sname[128];
    if (ptsname_r(m, sname, sizeof(sname)) != 0) {
        perror("ptyrun: ptsname_r");
        close(m);
        return 2;
    }

    /* Set the terminal size BEFORE forking so the child inherits it.
       Programs that query terminal size (curses, readline, etc.)
       will see a sane size instead of 0×0. */
    struct winsize ws;
    memset(&ws, 0, sizeof(ws));
    ws.ws_row = (unsigned short)termRows;
    ws.ws_col = (unsigned short)termCols;
    ioctl(m, TIOCSWINSZ, &ws);

    /* ── Live resize hook (T-PTY-7) ────────────────────────────────
       Creating the file IS the capability signal: the PHP side only
       reports live:true when the file exists, and only ptyrun builds
       with this code create it. */
    const char *wsFile = getenv("PTYRUN_WINSZ_FILE");
    int liveC = termCols, liveR = termRows;
    if (wsFile != NULL) {
        FILE *wf = fopen(wsFile, "w");
        if (wf != NULL) {
            fprintf(wf, "%d %d", termCols, termRows);
            fclose(wf);
        }
    }

    /* Install a SIGCHLD handler so we notice the child exiting
       promptly, even if select() is sleeping. */
    struct sigaction sa;
    memset(&sa, 0, sizeof(sa));
    sa.sa_handler = onChildExit;
    sa.sa_flags   = SA_NOCLDSTOP; /* only fire on exit, not stop */
    sigaction(SIGCHLD, &sa, NULL);

    /* ── Fork the child ────────────────────────────────────────── */
    pid_t pid = fork();
    if (pid < 0) { perror("ptyrun: fork"); close(m); return 2; }

    if (pid == 0) {
        /* ── CHILD: attach to the SLAVE side of the PTY ───────── */
        close(m); /* don't let the child hold the master open */

        /* New session + controlling terminal — makes this PTY
           "our" terminal, just like a login shell gets. */
        setsid();

        int s = open(sname, O_RDWR);
        if (s < 0) _exit(127);
        ioctl(s, TIOCSCTTY, 0);

        /* ══════════════════════════════════════════════════════════
         *  ★ DO NOT TOUCH termios HERE ★
         *
         *  A freshly opened PTY slave comes with sensible defaults:
         *    • ECHO is ON  → typed characters appear in the output
         *    • Canonical mode → line buffering, backspace works
         *    • ISIG is ON  → Ctrl+C sends SIGINT, etc.
         *
         *  The browser frontend relies on the PTY echoing typed
         *  input back through the output stream. If we disable
         *  ECHO here, the user's keystrokes vanish.
         *
         *  Programs that need raw mode (vim, top, etc.) will
         *  switch the terminal to raw mode THEMSELVES via tcsetattr.
         *  We must not interfere with that.
         * ══════════════════════════════════════════════════════════ */

        /* Wire stdin / stdout / stderr to the PTY slave. */
        dup2(s, STDIN_FILENO);
        dup2(s, STDOUT_FILENO);
        dup2(s, STDERR_FILENO);
        if (s > STDERR_FILENO) close(s);

        if (useShell) {
            execl("/bin/sh", "/bin/sh", "-c", cmd, (char *)NULL);
            execl("/system/bin/sh", "/system/bin/sh", "-c", cmd, (char *)NULL);
        } else {
            execvp(cmd, &argv[argi]);
        }
        _exit(127); /* only reached if exec failed */
    }

    /* ── PARENT: shuttle bytes  stdin→PTY  and  PTY→stdout ────── */
    int stdinOpen = 1;

    for (;;) {
        fd_set rf;
        FD_ZERO(&rf);
        FD_SET(m, &rf);
        int maxfd = m;

        if (stdinOpen) {
            FD_SET(STDIN_FILENO, &rf);
            if (STDIN_FILENO > maxfd) maxfd = STDIN_FILENO;
        }

        /* 200 ms timeout: lets us notice childDone promptly
           without burning CPU in a tight loop. */
        struct timeval tv = { .tv_sec = 0, .tv_usec = 200000 };
        int ready = select(maxfd + 1, &rf, NULL, NULL, &tv);

        if (ready < 0) {
            if (errno == EINTR) continue; /* signal interrupted — retry */
            break;
        }

        /* ★ T-PTY-7: pick up size changes written by the IDE. */
        maybeApplyWinsize(m, wsFile, &liveC, &liveR);

        char buf[8192];

        /* ── PTY master → stdout (program output + echoed input) ── */
        if (FD_ISSET(m, &rf)) {
            ssize_t n = read(m, buf, sizeof(buf));
            if (n <= 0) break; /* EIO = child is gone */
            fwrite(buf, 1, (size_t)n, stdout);
            fflush(stdout);
        }

        /* ── stdin → PTY master (user's typed characters) ────────── */
        if (stdinOpen && FD_ISSET(STDIN_FILENO, &rf)) {
            ssize_t n = read(STDIN_FILENO, buf, sizeof(buf));
            if (n <= 0) {
                /* PHP closed the stdin pipe (command finished or
                   browser disconnected). Park stdin on /dev/null
                   so select() never fires on it again. */
                stdinOpen = 0;
                int dn = open("/dev/null", O_RDONLY);
                if (dn >= 0) { dup2(dn, STDIN_FILENO); close(dn); }
            } else {
                /* Write all bytes into the PTY master. The kernel
                   terminal driver will echo them back to the master
                   (because ECHO is on) and deliver them to the
                   child's stdin. */
                ssize_t off = 0;
                while (off < n) {
                    ssize_t w = write(m, buf + off, (size_t)(n - off));
                    if (w <= 0) break;
                    off += w;
                }
            }
        }

        /* If the child exited and select timed out with no data,
           we're done — the drain loop below picks up leftovers. */
        if (childDone && ready == 0) break;
    }

    /* ── Reap the child process ────────────────────────────────── */
    int status = 0;
    waitpid(pid, &status, 0);

    /* Drain any bytes the child wrote just before exiting.
       (e.g. a final "goodbye\n" that was still in the PTY buffer) */
    fcntl(m, F_SETFL, O_NONBLOCK);
    char tail[8192];
    ssize_t t;
    while ((t = read(m, tail, sizeof(tail))) > 0) {
        fwrite(tail, 1, (size_t)t, stdout);
    }
    fflush(stdout);
    close(m);

    /* ── Forward the child's exit code ─────────────────────────── */
    if (WIFEXITED(status))   return WEXITSTATUS(status);
    if (WIFSIGNALED(status)) return 128 + WTERMSIG(status);
    return 1;
}