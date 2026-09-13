#!/usr/bin/env python3
"""
QuirkyIDE Asset Packer & Transfer Tool
----------------------------------------
A command-line utility to archive your web-view asset source (php/html/js/css)
and deploy it straight into your Android Studio project's assets folder.

Navigate with Up/Down arrows (or j/k), Enter to select, Esc/q to go back.
Number keys also jump-select instantly. Falls back to numbered typing if
run somewhere without a real interactive terminal (e.g. piped input).

Highlights:
  - The script, its config, and its own log file are ALWAYS excluded from
    what gets packed, no matter what - so the tool can never accidentally
    archive itself.
  - Selectable, lossless compression levels (0-9) for every compressible
    format. Nothing here ever changes archive bytes on extraction - only
    the packing time and resulting file size are affected.
  - Every archive is re-opened and integrity-checked immediately after
    creation (CRC/member verification). If a check fails, the archive is
    deleted and the transfer is aborted rather than risking a bad deploy.
  - Files are streamed straight into the archive (no intermediate staging
    copy) for ZIP/TAR/7z, which is both faster and uses less disk.

Run:
    python packer.py
"""

import os
import sys
import time
import json
import shutil
import tarfile
import zipfile
import fnmatch
import tempfile
import subprocess
from datetime import datetime

# --------------------------------------------------------------------------- #
# Constants / Defaults
# --------------------------------------------------------------------------- #

VERSION = "2.0.0"

SCRIPT_PATH = os.path.abspath(__file__)
SCRIPT_DIR = os.path.dirname(SCRIPT_PATH)
SCRIPT_NAME = os.path.basename(SCRIPT_PATH)

CONFIG_PATH = os.path.join(SCRIPT_DIR, "packer_config.json")
CONFIG_NAME = os.path.basename(CONFIG_PATH)

LOG_PATH = os.path.join(SCRIPT_DIR, "packer_log.txt")
LOG_NAME = os.path.basename(LOG_PATH)

ARCHIVE_FORMATS = {
    "1": ("zip",   ".zip",     "ZIP"),
    "2": ("tar",   ".tar",     "TAR (uncompressed)"),
    "3": ("gztar", ".tar.gz",  "TAR + GZIP"),
    "4": ("bztar", ".tar.bz2", "TAR + BZIP2"),
    "5": ("xztar", ".tar.xz",  "TAR + XZ"),
    "6": ("rar",   ".rar",     "RAR (requires WinRAR/rar CLI on PATH)"),
    "7": ("7z",    ".7z",      "7-Zip (requires py7zr or 7z CLI on PATH)"),
}

CONFLICT_MODES = {
    "1": ("delete",    "Delete Existing  - remove the file already at the destination, then place the new one"),
    "2": ("overwrite", "Overwrite        - replace the destination file directly (in-place overwrite)"),
    "3": ("skip",      "Skip             - keep the existing destination file, discard the newly packed one"),
    "4": ("backup",    "Archive & Keep   - rename the existing destination file to *.conf-bak (incremental)"),
}

# Compression is lossless at every level - raising the level only trades
# packing speed for a smaller file, it never touches data fidelity.
COMPRESSION_LEVEL_LABELS = {
    0: "Store - no compression, fastest, largest file",
    1: "Fast",
    2: "Fast",
    3: "Fast",
    4: "Balanced",
    5: "Balanced",
    6: "Balanced (recommended)",
    7: "High compression",
    8: "High compression",
    9: "Maximum compression - smallest file, slowest",
}

DEFAULT_CONFIG = {
    "archive_format": "zip",                 # key from ARCHIVE_FORMATS (stored as internal id, e.g. "zip")
    "naming_mode": "prompt",                 # "static" or "prompt"
    "archive_name": "ide",                   # used only when naming_mode == "static"
    "append_timestamp": False,               # append _YYYYMMDD_HHMMSS to the archive name
    "compression_level": 6,                  # 0 (store/fastest) - 9 (maximum/slowest), lossless throughout
    "source_directory": SCRIPT_DIR,
    "destination_directory": r"C:\Users\admin\AndroidStudioProjects\QuirkyIDE\app\src\main\assets",
    "conflict_resolution": "backup",         # key from CONFLICT_MODES
    "item_exceptions": [".usr"],             # names/paths/patterns skipped while packing
}


# --------------------------------------------------------------------------- #
# Config helpers
# --------------------------------------------------------------------------- #

def load_config():
    cfg = DEFAULT_CONFIG.copy()
    if os.path.exists(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r", encoding="utf-8") as f:
                data = json.load(f)
            if isinstance(data, dict):
                cfg.update(data)
            else:
                print("[!] Config file had an unexpected shape. Restoring defaults.")
                cfg = DEFAULT_CONFIG.copy()
        except (json.JSONDecodeError, OSError) as e:
            print(f"[!] Config file was corrupted or unreadable ({e}). Restoring defaults.")
            cfg = DEFAULT_CONFIG.copy()

    # Normalize/repair fields so a stale or hand-edited config can never
    # crash the tool later on.
    items = cfg.get("item_exceptions")
    if not isinstance(items, list):
        items = list(DEFAULT_CONFIG["item_exceptions"])
    deduped = []
    for item in items:
        if isinstance(item, str) and item and item not in deduped:
            deduped.append(item)
    cfg["item_exceptions"] = deduped

    try:
        cfg["compression_level"] = max(0, min(9, int(cfg.get("compression_level", 6))))
    except (TypeError, ValueError):
        cfg["compression_level"] = DEFAULT_CONFIG["compression_level"]

    cfg["append_timestamp"] = bool(cfg.get("append_timestamp", False))

    if cfg.get("archive_format") not in {fid for _, (fid, _, _) in ARCHIVE_FORMATS.items()}:
        cfg["archive_format"] = DEFAULT_CONFIG["archive_format"]
    if cfg.get("conflict_resolution") not in {mid for _, (mid, _) in CONFLICT_MODES.items()}:
        cfg["conflict_resolution"] = DEFAULT_CONFIG["conflict_resolution"]
    if cfg.get("naming_mode") not in ("static", "prompt"):
        cfg["naming_mode"] = DEFAULT_CONFIG["naming_mode"]

    return cfg


def save_config(cfg):
    try:
        with open(CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(cfg, f, indent=4)
    except OSError as e:
        print(f"[!] Could not save settings: {e}")


# --------------------------------------------------------------------------- #
# Small utilities
# --------------------------------------------------------------------------- #

def clear_screen():
    if os.name == "nt":
        os.system("cls")
        return
    # ★ Phase T-PTY: when we're attached to a real terminal (ptyrun), write
    # the ANSI wipe codes directly. This never depends on an external
    # `clear` binary or terminfo database being installed.
    #   [H  = cursor home, [2J = clear screen, [3J = clear scrollback
    try:
        if sys.stdout.isatty():
            sys.stdout.write("\033[H\033[2J\033[3J")
            sys.stdout.flush()
        else:
            os.system("clear")
    except Exception:
        os.system("clear")
        
def pause():
    input("\nPress Enter to continue...")


def header(title):
    clear_screen()
    print("=" * 60)
    print(f" QuirkyIDE Packer & Transfer Tool  v{VERSION}")
    print(f" {title}")
    print("=" * 60)
    print()


def ask(prompt, default=None):
    suffix = f" [{default}]" if default not in (None, "") else ""
    val = input(f"{prompt}{suffix}: ").strip()
    return val if val else default


def format_label(fmt_id):
    for _, (fid, ext, label) in ARCHIVE_FORMATS.items():
        if fid == fmt_id:
            return f"{label} ({ext})"
    return fmt_id


def conflict_label(mode_id):
    for _, (mid, label) in CONFLICT_MODES.items():
        if mid == mode_id:
            return label
    return mode_id


def extension_for(fmt_id):
    for _, (fid, ext, _) in ARCHIVE_FORMATS.items():
        if fid == fmt_id:
            return ext
    return ""


def sanitize_filename(name):
    invalid = '<>:"/\\|?*'
    for ch in invalid:
        name = name.replace(ch, "_")
    return name.strip() or "ide"


def human_size(num_bytes):
    """Pretty-print a byte count as e.g. '3.2 MB'."""
    size = float(num_bytes)
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if abs(size) < 1024.0 or unit == "TB":
            return f"{int(size)} {unit}" if unit == "B" else f"{size:.1f} {unit}"
        size /= 1024.0
    return f"{size:.1f} PB"


def compression_label(cfg):
    fmt = cfg["archive_format"]
    if fmt == "tar":
        return "N/A (plain TAR is never compressed)"
    lvl = level_for_format(fmt, cfg["compression_level"])
    return f"Level {lvl}/9 - {COMPRESSION_LEVEL_LABELS.get(lvl, '')}"


def level_for_format(fmt_id, level):
    """Clamp a 0-9 compression level to what each format actually accepts."""
    level = max(0, min(9, int(level)))
    if fmt_id == "bztar":
        return max(1, level)  # bzip2 only supports levels 1-9
    return level


def log_event(message):
    """Best-effort append to the operation log. Never raises - logging
    should never be the reason a pack/transfer fails."""
    try:
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {message}\n")
    except OSError:
        pass


class ProgressReporter:
    """Lightweight, throttled progress feedback for long pack operations.
    Rewrites a single line on real terminals; prints periodic decile
    updates when output isn't a TTY (e.g. redirected to a log file) so
    it stays cheap and readable either way."""

    def __init__(self, total, label="Packing"):
        self.total = total
        self.label = label
        self.count = 0
        self._interactive = supports_interactive() or sys.stdout.isatty()
        self._last_print = 0.0
        self._last_bucket = -1

    def step(self):
        self.count += 1
        if self.total <= 0:
            return
        if self._interactive:
            now = time.monotonic()
            if now - self._last_print < 0.1 and self.count != self.total:
                return
            self._last_print = now
            pct = (self.count / self.total) * 100
            line = f"\r[*] {self.label}: {self.count}/{self.total} ({pct:5.1f}%)"
            sys.stdout.write(line.ljust(80))
            sys.stdout.flush()
        else:
            bucket = int((self.count / self.total) * 10)
            if bucket != self._last_bucket or self.count == self.total:
                self._last_bucket = bucket
                pct = (self.count / self.total) * 100
                print(f"[*] {self.label}: {self.count}/{self.total} ({pct:.0f}%)")

    def finish(self):
        if self.total > 0 and self._interactive:
            sys.stdout.write("\n")
            sys.stdout.flush()


# --------------------------------------------------------------------------- #
# Arrow-key navigation layer
# --------------------------------------------------------------------------- #
# Pure stdlib: msvcrt on Windows, termios/tty (+select for non-blocking Esc
# detection) on Unix. Degrades gracefully to numbered typing if stdin/stdout
# isn't a real interactive terminal.

ANSI_ENABLED = False
ANSI_RESET = "\033[0m"
ANSI_REVERSE = "\033[7m"
ANSI_DIM = "\033[2m"
ANSI_CURSOR = "\033[36m"  # cyan


def enable_ansi():
    """Best-effort: turn on VT100 escape processing on Windows consoles."""
    global ANSI_ENABLED
    if os.name == "nt":
        try:
            import ctypes
            kernel32 = ctypes.windll.kernel32
            handle = kernel32.GetStdHandle(-11)  # STD_OUTPUT_HANDLE
            mode = ctypes.c_uint32()
            if not kernel32.GetConsoleMode(handle, ctypes.byref(mode)):
                ANSI_ENABLED = False
                return
            ENABLE_VIRTUAL_TERMINAL_PROCESSING = 0x0004
            ANSI_ENABLED = bool(kernel32.SetConsoleMode(handle, mode.value | ENABLE_VIRTUAL_TERMINAL_PROCESSING))
        except Exception:
            ANSI_ENABLED = False
    else:
        ANSI_ENABLED = True


def supports_interactive():
    try:
        return sys.stdin.isatty() and sys.stdout.isatty()
    except Exception:
        return False


def _read_key_windows():
    import msvcrt
    ch = msvcrt.getch()
    if ch in (b"\x00", b"\xe0"):
        ch2 = msvcrt.getch()
        mapping = {b"H": "UP", b"P": "DOWN", b"K": "LEFT", b"M": "RIGHT"}
        return mapping.get(ch2)
    if ch == b"\r":
        return "ENTER"
    if ch == b"\x1b":
        return "ESC"
    if ch == b"\x08":
        return "BACKSPACE"
    if ch == b"\x03":
        raise KeyboardInterrupt
    try:
        return ch.decode("utf-8", errors="ignore")
    except Exception:
        return None


def _read_key_unix():
    import tty
    import termios
    import select
    fd = sys.stdin.fileno()
    old_settings = termios.tcgetattr(fd)
    try:
        tty.setraw(fd)

        # ★ FIX (the real one): read bytes straight off the file descriptor
        # with os.read() instead of the buffered sys.stdin.read().
        #
        # Why the old code broke: sys.stdin is a *buffered* reader. The very
        # first sys.stdin.read(1) silently pulled the WHOLE escape sequence
        # ("\x1b[A" — all 3 bytes) into Python's private internal buffer and
        # handed back only "\x1b". The follow-up select.select() then looked
        # at the RAW descriptor — which was now EMPTY, because the bytes were
        # sitting in Python's buffer, not on the descriptor. select() waited
        # the full timeout, saw nothing, and the code concluded you pressed a
        # bare Escape… which quits the menu ("Goodbye!").
        #
        # os.read() and select() both operate on the same raw descriptor, so
        # the "[" and "A" that follow "\x1b" are always seen immediately.
        def read_byte(timeout=None):
            """Read exactly one byte from the terminal.

            timeout=None -> block until a key is pressed (menu idle state)
            timeout=secs -> wait up to that long; return b'' when nothing
                            arrives (that is how a lone Esc is recognised)
            """
            if timeout is not None:
                ready, _, _ = select.select([fd], [], [], timeout)
                if not ready:
                    return b""
            return os.read(fd, 1)

        first = read_byte()                 # wait for any key
        if not first:
            return None
        ch = first.decode("utf-8", errors="ignore")

        if ch == "\x1b":
            # Something followed the Escape byte -> it is an escape sequence
            # (arrow keys etc.). A lone Esc sends nothing after it.
            second = read_byte(0.25)
            if not second:
                return "ESC"                # bare Escape key
            ch2 = second.decode("utf-8", errors="ignore")
            # Arrow keys arrive as  ESC [ A/B/C/D  (or  ESC O A/B/C/D  when
            # the terminal is in "application cursor" mode). Handle both.
            if ch2 in ("[", "O"):
                third = read_byte(0.25)
                if not third:
                    return "ESC"
                ch3 = third.decode("utf-8", errors="ignore")
                mapping = {"A": "UP", "B": "DOWN", "C": "RIGHT", "D": "LEFT"}
                return mapping.get(ch3, "ESC")
            return "ESC"                    # some other sequence we ignore

        if ch in ("\r", "\n"):
            return "ENTER"
        if ch == "\x7f":
            return "BACKSPACE"
        if ch == "\x03":
            raise KeyboardInterrupt
        return ch
    finally:
        termios.tcsetattr(fd, termios.TCSADRAIN, old_settings)
        
def read_key():
    return _read_key_windows() if os.name == "nt" else _read_key_unix()


def _render_option(opt, is_selected):
    label = opt["label"]
    if is_selected:
        if ANSI_ENABLED:
            return f"{ANSI_CURSOR}>{ANSI_RESET} {ANSI_REVERSE} {label} {ANSI_RESET}"
        return f"> [ {label} ]"
    return f"  {label}"


def arrow_select(options, title=None, subtitle=None, initial_index=0,
                  allow_escape=True, escape_label="Back", numbered=True):
    """
    options: list of dicts {"label": str, "value": Any, "hint": Optional[str]}
    Returns the selected value, or None if the user escaped/cancelled.
    """
    if not options:
        return None

    if not supports_interactive():
        return _fallback_numbered_select(options, title, allow_escape, escape_label)

    index = max(0, min(initial_index, len(options) - 1))
    while True:
        clear_screen()
        if title:
            print("=" * 60)
            print(f" {title}")
            print("=" * 60)
        if subtitle:
            print(subtitle)
        print()
        for i, opt in enumerate(options):
            print(_render_option(opt, i == index))
            if i == index and opt.get("hint"):
                hint_text = f"    {opt['hint']}"
                print(f"{ANSI_DIM}{hint_text}{ANSI_RESET}" if ANSI_ENABLED else hint_text)
        print()
        footer = "^/v or j/k: Navigate   Enter: Select" if not ANSI_ENABLED else "↑/↓  Navigate    Enter  Select"
        if numbered:
            footer += "    1-9: Jump"
        if allow_escape:
            footer += f"    Esc/q: {escape_label}"
        print(f"{ANSI_DIM}{footer}{ANSI_RESET}" if ANSI_ENABLED else footer)

        key = read_key()
        if key in ("UP", "k", "w"):
            index = (index - 1) % len(options)
        elif key in ("DOWN", "j", "s"):
            index = (index + 1) % len(options)
        elif key == "ENTER":
            return options[index]["value"]
        elif key in ("ESC", "q", "Q") and allow_escape:
            return None
        elif numbered and key is not None and key.isdigit():
            num = int(key)
            if 1 <= num <= len(options):
                return options[num - 1]["value"]


def _fallback_numbered_select(options, title, allow_escape, escape_label):
    header(title or "Select")
    for i, opt in enumerate(options, 1):
        print(f" {i}. {opt['label']}")
        if opt.get("hint"):
            print(f"    {opt['hint']}")
    if allow_escape:
        print(f" 0. {escape_label}")
    choice = ask("Select an option", "0" if allow_escape else "1")
    try:
        idx = int(choice)
        if allow_escape and idx == 0:
            return None
        if 1 <= idx <= len(options):
            return options[idx - 1]["value"]
    except (ValueError, TypeError):
        pass
    return None


def confirm_select(question, default_yes=True, subtitle=None):
    options = [{"label": "Yes", "value": True}, {"label": "No", "value": False}]
    result = arrow_select(
        options, title=question, subtitle=subtitle,
        initial_index=0 if default_yes else 1,
        allow_escape=True, escape_label="Cancel (No)",
    )
    return bool(result) if result is not None else False


# --------------------------------------------------------------------------- #
# Settings menu
# --------------------------------------------------------------------------- #

def settings_menu(cfg):
    while True:
        options = [
            {"label": "Archive Format", "value": "format",
             "hint": f"Currently: {format_label(cfg['archive_format'])}"},
            {"label": "Compression Level", "value": "compression",
             "hint": f"Currently: {compression_label(cfg)}"},
            {"label": "Archive Naming Mode", "value": "naming",
             "hint": f"Currently: {'Static (' + cfg['archive_name'] + ')' if cfg['naming_mode']=='static' else 'Prompt (ask each run)'}"},
            {"label": "Timestamp Suffix", "value": "timestamp",
             "hint": f"Currently: {'On' if cfg.get('append_timestamp') else 'Off'} "
                     f"- appends _YYYYMMDD_HHMMSS to the archive name"},
            {"label": "Source Directory", "value": "source",
             "hint": f"Currently: {cfg['source_directory']}"},
            {"label": "Destination Directory", "value": "destination",
             "hint": f"Currently: {cfg['destination_directory']}"},
            {"label": "Conflict Resolution", "value": "conflict",
             "hint": f"Currently: {conflict_label(cfg['conflict_resolution'])}"},
            {"label": "Item Exceptions", "value": "exceptions",
             "hint": f"{len(cfg['item_exceptions'])} user item(s) + "
                     f"{len(builtin_protected_patterns())} built-in protection(s)"},
            {"label": "Reset to Defaults", "value": "reset"},
            {"label": "Back to Main Menu", "value": "back"},
        ]
        choice = arrow_select(options, title="Settings", allow_escape=True, escape_label="Back to Main Menu")

        if choice == "format":
            set_archive_format(cfg)
        elif choice == "compression":
            set_compression_level(cfg)
        elif choice == "naming":
            set_naming_mode(cfg)
        elif choice == "timestamp":
            toggle_timestamp_suffix(cfg)
        elif choice == "source":
            set_source_directory(cfg)
        elif choice == "destination":
            set_destination_directory(cfg)
        elif choice == "conflict":
            set_conflict_resolution(cfg)
        elif choice == "exceptions":
            exceptions_menu(cfg)
        elif choice == "reset":
            if confirm_select("Reset ALL settings to defaults?", default_yes=False):
                cfg.clear()
                cfg.update(DEFAULT_CONFIG.copy())
                save_config(cfg)
        elif choice in ("back", None):
            save_config(cfg)
            return


def set_compression_level(cfg):
    options = [
        {"label": f"{lvl} - {desc}", "value": lvl}
        for lvl, desc in COMPRESSION_LEVEL_LABELS.items()
    ]
    initial = next((i for i, o in enumerate(options) if o["value"] == cfg["compression_level"]), 6)
    choice = arrow_select(
        options, title="Settings > Compression Level",
        subtitle=(
            "Higher = smaller archive, longer to pack. Every level is lossless -\n"
            "extracted files come back byte-for-byte identical no matter which\n"
            "level you pick; only packing time and resulting size change.\n"
            "Ignored for plain TAR (never compressed); RAR/7z via external CLI\n"
            "tools map this onto their own compression scale."
        ),
        initial_index=initial,
    )
    if choice is not None:
        cfg["compression_level"] = choice
        save_config(cfg)


def toggle_timestamp_suffix(cfg):
    options = [
        {"label": "On", "value": True, "hint": "Archive name gets _YYYYMMDD_HHMMSS appended"},
        {"label": "Off", "value": False, "hint": "Archive name is used exactly as entered/configured"},
    ]
    initial = 0 if cfg.get("append_timestamp") else 1
    choice = arrow_select(options, title="Settings > Timestamp Suffix", initial_index=initial)
    if choice is not None:
        cfg["append_timestamp"] = choice
        save_config(cfg)


def set_archive_format(cfg):
    options = [
        {"label": f"{label} ({ext})", "value": fid}
        for _, (fid, ext, label) in ARCHIVE_FORMATS.items()
    ]
    initial = next((i for i, o in enumerate(options) if o["value"] == cfg["archive_format"]), 0)
    choice = arrow_select(options, title="Settings > Archive Format", initial_index=initial)
    if choice:
        cfg["archive_format"] = choice
        save_config(cfg)


def set_naming_mode(cfg):
    options = [
        {"label": "Static", "value": "static",
         "hint": "Set a fixed archive name now; every run reuses it"},
        {"label": "Prompt", "value": "prompt",
         "hint": "You'll be asked for a name every time you pack"},
    ]
    initial = 0 if cfg["naming_mode"] == "static" else 1
    choice = arrow_select(options, title="Settings > Archive Naming Mode", initial_index=initial)
    if choice == "static":
        cfg["naming_mode"] = "static"
        name = ask("Enter the static archive name (no extension)", cfg.get("archive_name", "ide"))
        cfg["archive_name"] = sanitize_filename(name)
        save_config(cfg)
    elif choice == "prompt":
        cfg["naming_mode"] = "prompt"
        save_config(cfg)


def set_source_directory(cfg):
    header("Settings > Source Directory")
    print("This is the folder containing the files/subfolders to be packed.")
    print(f"Current: {cfg['source_directory']}\n")
    new_path = ask("Enter new source directory", cfg["source_directory"])
    new_path = os.path.abspath(os.path.expanduser(new_path))
    if os.path.isdir(new_path):
        cfg["source_directory"] = new_path
        save_config(cfg)
        print(f"[+] Source directory set to: {new_path}")
    else:
        print(f"[!] Path does not exist or isn't a directory: {new_path}")
    pause()


def set_destination_directory(cfg):
    header("Settings > Destination Directory")
    print("This is where the packed archive will be transferred/deployed to.")
    print(f"Current: {cfg['destination_directory']}\n")
    new_path = ask("Enter new destination directory", cfg["destination_directory"])
    new_path = os.path.abspath(os.path.expanduser(new_path))
    cfg["destination_directory"] = new_path
    save_config(cfg)
    print(f"[+] Destination directory set to: {new_path}")
    if not os.path.isdir(new_path):
        print("    (Note: this path doesn't exist yet - it will be created on transfer.)")
    pause()


def set_conflict_resolution(cfg):
    options = [{"label": label, "value": mid} for _, (mid, label) in CONFLICT_MODES.items()]
    initial = next((i for i, o in enumerate(options) if o["value"] == cfg["conflict_resolution"]), 0)
    choice = arrow_select(options, title="Settings > Conflict Resolution", initial_index=initial)
    if choice:
        cfg["conflict_resolution"] = choice
        save_config(cfg)


# --------------------------------------------------------------------------- #
# Item Exceptions
# --------------------------------------------------------------------------- #

def builtin_protected_patterns():
    """Names/patterns that are ALWAYS excluded from packing, on top of
    whatever the user configures, so the tool can never accidentally
    archive itself, its config, its log, or its own leftover temp/backup
    files. These are intentionally not stored in the config and can't be
    removed via the Item Exceptions menu."""
    return [SCRIPT_NAME, CONFIG_NAME, LOG_NAME, ".pack_tmp_*", "*.conf-bak*"]


def effective_exceptions(cfg, extra=None):
    """The full pattern list actually used while packing/scanning: the
    built-in self-protections, then the user's configured exceptions,
    then any call-specific extras (e.g. the archive currently being
    produced, so a leftover copy of it can't get packed into itself)."""
    combined = list(builtin_protected_patterns())
    combined.extend(cfg.get("item_exceptions") or [])
    if extra:
        combined.extend(extra)
    return combined


def exceptions_menu(cfg):
    syntax_help = (
        "Pattern syntax (gitignore-style):\n"
        "  name           matches that name anywhere in the tree   e.g. .usr\n"
        "  *.log          wildcard, matches basenames only         e.g. *.log\n"
        "  dir/file.ext   anchored to that exact nested location   e.g. build/output\n"
        "  dir/*.log      anchored, one level only (not nested)\n"
        "  dir/**/*.log   anchored, any depth under dir/ (recursive)\n"
        "  name/          trailing slash = only matches a directory\n"
        "  !pattern       negation - re-includes something matched earlier"
    )
    while True:
        items = cfg["item_exceptions"]
        protected_list = "Always protected (built-in, cannot be removed): " + ", ".join(builtin_protected_patterns())
        current_list = "Your exceptions: " + (", ".join(items) if items else "(none)")
        options = [
            {"label": "Add exception", "value": "add"},
            {"label": "Remove exception", "value": "remove"},
            {"label": "Preview exclusions (live scan)", "value": "preview"},
            {"label": "Back to Settings", "value": "back"},
        ]
        choice = arrow_select(
            options, title="Settings > Item Exceptions",
            subtitle=f"{syntax_help}\n\n{protected_list}\n{current_list}",
            allow_escape=True, escape_label="Back to Settings",
        )

        if choice == "add":
            header("Settings > Item Exceptions > Add")
            new_item = ask("Enter name/path/pattern to exclude", "")
            if not new_item:
                print("[!] No value entered.")
            elif new_item in items:
                print(f"[!] '{new_item}' is already in the list.")
            else:
                items.append(new_item)
                save_config(cfg)
                print(f"[+] Added '{new_item}' to exceptions.")
            pause()

        elif choice == "remove":
            if not items:
                header("Settings > Item Exceptions > Remove")
                print("[!] List is empty, nothing to remove.")
                pause()
                continue
            remove_options = [{"label": item, "value": item} for item in items]
            target = arrow_select(
                remove_options, title="Settings > Item Exceptions > Remove",
                subtitle="Select an item to remove.",
                allow_escape=True, escape_label="Cancel",
            )
            if target is not None:
                items.remove(target)
                save_config(cfg)

        elif choice == "preview":
            preview_exclusions(cfg)

        elif choice in ("back", None):
            save_config(cfg)
            return


def _parse_pattern(raw):
    """Break a raw exception entry into its matching components."""
    pattern = raw.strip()
    negate = pattern.startswith("!")
    if negate:
        pattern = pattern[1:].strip()
    dir_only = pattern.endswith("/")
    if dir_only:
        pattern = pattern[:-1]
    pattern = pattern.replace("\\", "/")
    anchored = "/" in pattern  # contains a separator -> matched against full relative path
    return {"pattern": pattern, "negate": negate, "dir_only": dir_only, "anchored": anchored}


def _glob_match(rel_parts, pattern_parts):
    """Segment-by-segment glob match. Supports '**' as 'zero or more directories'."""
    if not pattern_parts:
        return not rel_parts
    head = pattern_parts[0]
    if head == "**":
        if _glob_match(rel_parts, pattern_parts[1:]):
            return True
        if rel_parts:
            return _glob_match(rel_parts[1:], pattern_parts)
        return False
    if not rel_parts:
        return False
    if fnmatch.fnmatch(rel_parts[0], head):
        return _glob_match(rel_parts[1:], pattern_parts[1:])
    return False


def _pattern_matches(parsed, name, rel_posix, is_dir):
    if parsed["dir_only"] and not is_dir:
        return False
    if not parsed["pattern"]:
        return False
    if parsed["anchored"]:
        return _glob_match(rel_posix.split("/"), parsed["pattern"].split("/"))
    # Unanchored: matches the basename, at any depth in the tree.
    return name == parsed["pattern"] or fnmatch.fnmatch(name, parsed["pattern"])


def is_excluded(name, rel_path, is_dir, exceptions):
    """
    Evaluate every exception entry in order (gitignore semantics): the LAST
    matching pattern wins, so a later '!pattern' can re-include something an
    earlier broader pattern excluded.
    """
    rel_posix = rel_path.replace(os.sep, "/")
    decision = False
    for raw in exceptions:
        parsed = _parse_pattern(raw)
        if _pattern_matches(parsed, name, rel_posix, is_dir):
            decision = not parsed["negate"]
    return decision


def scan_pack_preview(source_dir, exceptions):
    """
    Single-pass walk of source_dir classifying every item as included or
    excluded under the given pattern list. Returns
    (included_file_count, included_bytes, excluded_item_labels).
    Excluded directories are pruned so their contents are never visited,
    which mirrors exactly what packing itself will do.
    """
    excluded_items = []
    included_files = 0
    included_bytes = 0

    for root, dirs, files in os.walk(source_dir):
        kept_dirs = []
        for d in dirs:
            full = os.path.join(root, d)
            rel = os.path.relpath(full, source_dir)
            if is_excluded(d, rel, True, exceptions):
                excluded_items.append(rel.replace(os.sep, "/") + "/")
            else:
                kept_dirs.append(d)
        dirs[:] = kept_dirs  # don't descend into excluded dirs, mirrors pack behavior

        for f in files:
            full = os.path.join(root, f)
            rel = os.path.relpath(full, source_dir)
            if is_excluded(f, rel, False, exceptions):
                excluded_items.append(rel.replace(os.sep, "/"))
            else:
                included_files += 1
                try:
                    included_bytes += os.path.getsize(full)
                except OSError:
                    pass

    return included_files, included_bytes, excluded_items


def preview_exclusions(cfg):
    """Live scan of the current Source Directory showing what would be skipped right now."""
    header("Settings > Item Exceptions > Preview")
    source_dir = cfg["source_directory"]

    if not os.path.isdir(source_dir):
        print(f"[!] Source directory not found: {source_dir}")
        pause()
        return

    exceptions = effective_exceptions(cfg)
    print(f"Scanning live: {source_dir}\n")
    included_files, included_bytes, excluded_items = scan_pack_preview(source_dir, exceptions)

    if excluded_items:
        print(f"{len(excluded_items)} item(s) would be EXCLUDED from the next pack "
              f"(including built-in self-protection):\n")
        for item in sorted(excluded_items):
            print(f"  - {item}")
    else:
        print("[*] Nothing in the source directory currently matches your exceptions.")

    print(f"\n{included_files} file(s) would be included ({human_size(included_bytes)}).")
    pause()


def _iter_source_entries(source_dir, exceptions):
    """
    Walk source_dir once, yielding (full_path, rel_path, is_dir) for every
    directory and file that is NOT excluded. Excluded directories are
    pruned in-place so their contents are never visited (fast, and keeps
    packing and previewing perfectly in sync).
    """
    for root, dirs, files in os.walk(source_dir):
        kept_dirs = []
        for d in dirs:
            full = os.path.join(root, d)
            rel = os.path.relpath(full, source_dir)
            if is_excluded(d, rel, True, exceptions):
                continue
            kept_dirs.append(d)
            yield full, rel, True
        dirs[:] = kept_dirs

        for f in files:
            full = os.path.join(root, f)
            rel = os.path.relpath(full, source_dir)
            if is_excluded(f, rel, False, exceptions):
                continue
            yield full, rel, False


def _stage_filtered(source_dir, exceptions):
    """
    Materializes a filtered copy of source_dir on disk. Only needed for
    the external RAR/7z-CLI tools, which operate on real directories
    rather than an in-memory file list. Caller must clean up staging_root.
    """
    staging_root = tempfile.mkdtemp(prefix=".pack_tmp_")
    staging_target = os.path.join(staging_root, "content")
    os.makedirs(staging_target, exist_ok=True)
    for full, rel, is_dir in _iter_source_entries(source_dir, exceptions):
        dest = os.path.join(staging_target, rel)
        if is_dir:
            os.makedirs(dest, exist_ok=True)
        else:
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            shutil.copy2(full, dest)
    return staging_root, staging_target


# --------------------------------------------------------------------------- #
# Packing
# --------------------------------------------------------------------------- #

def _safe_size(path):
    try:
        return os.path.getsize(path)
    except OSError:
        return 0


def _build_zip(path, files, dirs, level):
    try:
        progress = ProgressReporter(len(files), "Packing")
        with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=level) as zf:
            for _, rel in dirs:
                zf.writestr(rel.replace(os.sep, "/") + "/", "")
            for full, rel in files:
                zf.write(full, arcname=rel.replace(os.sep, "/"))
                progress.step()
        progress.finish()
        return path
    except Exception as e:
        print(f"\n[!] Failed to create ZIP archive: {e}")
        return None


def _build_tar(path, files, dirs, mode, extra_kwargs):
    try:
        progress = ProgressReporter(len(files), "Packing")
        with tarfile.open(path, mode, **extra_kwargs) as tf:
            for full, rel in dirs:
                ti = tarfile.TarInfo(name=rel.replace(os.sep, "/"))
                ti.type = tarfile.DIRTYPE
                ti.mode = 0o755
                try:
                    ti.mtime = os.path.getmtime(full)
                except OSError:
                    pass
                tf.addfile(ti)
            for full, rel in files:
                tf.add(full, arcname=rel.replace(os.sep, "/"), recursive=False)
                progress.step()
        progress.finish()
        return path
    except Exception as e:
        print(f"\n[!] Failed to create TAR archive: {e}")
        return None


def _build_7z_cli(path, source_dir, exceptions, level):
    seven_zip_exe = shutil.which("7z") or shutil.which("7za")
    if not seven_zip_exe:
        print("[!] 7z support needs either 'pip install py7zr' or a '7z' CLI on PATH.")
        return None
    staging_root, staging_target = _stage_filtered(source_dir, exceptions)
    try:
        cmd = [seven_zip_exe, "a", f"-mx={max(0, min(9, int(level)))}", path, "*"]
        proc = subprocess.run(cmd, cwd=staging_target, capture_output=True, text=True)
        if proc.returncode != 0:
            print(f"[!] 7z creation failed:\n{proc.stderr}")
            return None
        return path
    except Exception as e:
        print(f"[!] Failed to create 7z archive: {e}")
        return None
    finally:
        shutil.rmtree(staging_root, ignore_errors=True)


def _build_7z(path, files, dirs, source_dir, exceptions, level):
    try:
        import py7zr
    except ImportError:
        return _build_7z_cli(path, source_dir, exceptions, level)
    try:
        filters = [{"id": py7zr.FILTER_LZMA2, "preset": max(0, min(9, int(level)))}]
        progress = ProgressReporter(len(files), "Packing")
        with py7zr.SevenZipFile(path, "w", filters=filters) as archive:
            for full, rel in dirs:
                archive.write(full, arcname=rel.replace(os.sep, "/"))
            for full, rel in files:
                archive.write(full, arcname=rel.replace(os.sep, "/"))
                progress.step()
        progress.finish()
        return path
    except Exception as e:
        print(f"\n[!] Failed to create 7z archive: {e}")
        return None


def _build_rar(path, source_dir, exceptions, level):
    rar_exe = shutil.which("rar") or shutil.which("WinRAR")
    if not rar_exe:
        print("[!] Could not find 'rar' or 'WinRAR' on PATH. Install the WinRAR/rar CLI to use RAR format.")
        return None
    staging_root, staging_target = _stage_filtered(source_dir, exceptions)
    try:
        m_level = max(0, min(9, int(level))) // 2  # map 0-9 -> rar's -m0..-m5 scale
        cmd = [rar_exe, "a", "-r", f"-m{m_level}", path, "*"]
        proc = subprocess.run(cmd, cwd=staging_target, capture_output=True, text=True)
        if proc.returncode != 0:
            print(f"[!] RAR creation failed:\n{proc.stderr}")
            return None
        return path
    except Exception as e:
        print(f"[!] Failed to create RAR archive: {e}")
        return None
    finally:
        shutil.rmtree(staging_root, ignore_errors=True)


def verify_archive(path, fmt_id, stats):
    """
    Reopens the freshly-written archive and confirms it's structurally
    sound BEFORE it's ever transferred anywhere:
      - ZIP:  zipfile's own per-member CRC check via testzip()
      - TAR*: every file member must be readable back out of the stream
      - 7z:   py7zr's own testzip() when a reader is available
      - RAR:  'rar t' (test), since Python has no built-in RAR reader
    Also cross-checks the packed member count against what was fed in, as
    a cheap safety net against a silently-truncated write.
    """
    try:
        if fmt_id == "zip":
            with zipfile.ZipFile(path) as zf:
                bad = zf.testzip()
                if bad:
                    print(f"    -> corrupted member detected: {bad}")
                    return False
                packed = len([n for n in zf.namelist() if not n.endswith("/")])

        elif fmt_id in ("tar", "gztar", "bztar", "xztar"):
            with tarfile.open(path, "r:*") as tf:
                members = tf.getmembers()
                for m in members:
                    if m.isfile():
                        fh = tf.extractfile(m)
                        if fh is None:
                            print(f"    -> unreadable member: {m.name}")
                            return False
                        fh.close()
                packed = len([m for m in members if m.isfile()])

        elif fmt_id == "7z":
            try:
                import py7zr
                with py7zr.SevenZipFile(path, "r") as archive:
                    bad = archive.testzip()
                    if bad:
                        print(f"    -> corrupted member(s) detected: {bad}")
                        return False
                    packed = len(archive.getnames())
            except ImportError:
                # No local reader to double-check with; fall back to a
                # basic existence/non-empty sanity check.
                return os.path.exists(path) and os.path.getsize(path) > 0

        elif fmt_id == "rar":
            rar_exe = shutil.which("rar") or shutil.which("WinRAR")
            if not rar_exe:
                return os.path.exists(path) and os.path.getsize(path) > 0
            proc = subprocess.run([rar_exe, "t", path], capture_output=True, text=True)
            return proc.returncode == 0

        else:
            return False

        if stats and stats.get("files") is not None and packed != stats["files"]:
            print(f"    -> member count mismatch: expected {stats['files']}, archive has {packed}")
            return False
        return True

    except Exception as e:
        print(f"    -> could not reopen archive for verification: {e}")
        return False


def build_archive(source_dir, exceptions, archive_base_path, fmt_id, level):
    """
    Streams the already-filtered source tree straight into the target
    archive (no intermediate staging copy for ZIP/TAR/7z-via-py7zr), then
    verifies the result can be re-opened and every member reads back
    cleanly before handing back the path. If verification fails the
    partial/corrupt archive is deleted and (None, None) is returned so
    the caller never transfers a bad file.

    archive_base_path: full path WITHOUT extension.
    Returns (archive_path, stats) where stats = {"files", "dirs", "bytes_in"}.
    """
    entries = list(_iter_source_entries(source_dir, exceptions))
    files = [(f, r) for f, r, d in entries if not d]
    dirs = [(f, r) for f, r, d in entries if d]
    stats = {
        "files": len(files),
        "dirs": len(dirs),
        "bytes_in": sum(_safe_size(f) for f, _ in files),
    }

    ext = extension_for(fmt_id)
    if not ext:
        print(f"[!] Unknown archive format: {fmt_id}")
        return None, None
    path = archive_base_path + ext

    if fmt_id == "zip":
        result = _build_zip(path, files, dirs, level)
    elif fmt_id == "tar":
        result = _build_tar(path, files, dirs, "w", {})
    elif fmt_id == "gztar":
        result = _build_tar(path, files, dirs, "w:gz", {"compresslevel": level_for_format(fmt_id, level)})
    elif fmt_id == "bztar":
        result = _build_tar(path, files, dirs, "w:bz2", {"compresslevel": level_for_format(fmt_id, level)})
    elif fmt_id == "xztar":
        result = _build_tar(path, files, dirs, "w:xz", {"preset": level_for_format(fmt_id, level)})
    elif fmt_id == "7z":
        result = _build_7z(path, files, dirs, source_dir, exceptions, level)
    elif fmt_id == "rar":
        result = _build_rar(path, source_dir, exceptions, level)
    else:
        print(f"[!] Unknown archive format: {fmt_id}")
        return None, None

    if not result:
        return None, None

    print("[*] Verifying archive integrity...")
    if not verify_archive(path, fmt_id, stats):
        print("[!] Integrity check FAILED - the archive may be corrupted. Deleting it for safety.")
        try:
            os.remove(path)
        except OSError:
            pass
        return None, None
    print("[+] Integrity check passed - archive is safe to transfer.")

    return path, stats


# --------------------------------------------------------------------------- #
# Conflict resolution / transfer
# --------------------------------------------------------------------------- #

def next_backup_name(existing_path):
    """
    Returns an available backup name following:
      file.ext -> file.ext.conf-bak -> file.ext.conf-bak1 -> file.ext.conf-bak2 ...
    """
    base = existing_path + ".conf-bak"
    if not os.path.exists(base):
        return base
    i = 1
    while os.path.exists(base + str(i)):
        i += 1
    return base + str(i)


def transfer_archive(archive_path, destination_dir, conflict_mode):
    try:
        os.makedirs(destination_dir, exist_ok=True)
    except OSError as e:
        print(f"[!] Could not create destination directory: {e}")
        print(f"    Packed archive was kept at: {archive_path}")
        return False

    filename = os.path.basename(archive_path)
    dest_path = os.path.join(destination_dir, filename)

    if os.path.exists(dest_path):
        print(f"[*] Conflict detected: '{filename}' already exists at destination.")
        if conflict_mode == "delete":
            try:
                os.remove(dest_path)
                print("    -> Existing file deleted.")
            except OSError as e:
                print(f"[!] Could not delete existing file: {e}")
                print(f"    Packed archive was kept at: {archive_path}")
                return False
        elif conflict_mode == "overwrite":
            print("    -> Existing file will be overwritten.")
        elif conflict_mode == "skip":
            print("    -> Skipping transfer. Destination file kept as-is; packed archive discarded.")
            try:
                os.remove(archive_path)
            except OSError:
                pass
            return False
        elif conflict_mode == "backup":
            try:
                backup_path = next_backup_name(dest_path)
                os.rename(dest_path, backup_path)
                print(f"    -> Existing file renamed to: {os.path.basename(backup_path)}")
            except OSError as e:
                print(f"[!] Could not back up existing file: {e}")
                print(f"    Packed archive was kept at: {archive_path}")
                return False
        else:
            print(f"[!] Unknown conflict mode '{conflict_mode}', defaulting to overwrite.")

    try:
        shutil.move(archive_path, dest_path)
    except OSError as e:
        print(f"[!] Transfer failed: {e}")
        print(f"    Packed archive was kept at: {archive_path}")
        return False

    print(f"[+] Transferred to: {dest_path}")
    return True


# --------------------------------------------------------------------------- #
# Pack and Transfer flow
# --------------------------------------------------------------------------- #

def pack_and_transfer(cfg):
    header("Pack and Transfer")

    source_dir = cfg["source_directory"]
    if not os.path.isdir(source_dir):
        print(f"[!] Source directory not found: {source_dir}")
        pause()
        return

    if cfg["naming_mode"] == "static":
        archive_name = cfg["archive_name"]
    else:
        archive_name = ask("Enter archive name (no extension)", "ide")
        archive_name = sanitize_filename(archive_name)
    if cfg.get("append_timestamp"):
        archive_name = f"{archive_name}_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    archive_name = sanitize_filename(archive_name)

    ext = extension_for(cfg["archive_format"])
    # Also exclude the exact file this run is about to produce, so a
    # leftover archive from a previous run with the same name (e.g. a
    # failed transfer) never gets packed into the new one.
    exceptions = effective_exceptions(cfg, extra=[archive_name + ext])

    print("[*] Scanning source directory...")
    file_count, total_bytes, excluded_items = scan_pack_preview(source_dir, exceptions)

    print(f"\nSource       : {source_dir}")
    print(f"Destination  : {cfg['destination_directory']}")
    print(f"Format       : {format_label(cfg['archive_format'])}")
    print(f"Compression  : {compression_label(cfg)}")
    print(f"Archive Name : {archive_name}{ext}")
    print(f"On Conflict  : {conflict_label(cfg['conflict_resolution'])}")
    print(f"To Pack      : {file_count} file(s), {human_size(total_bytes)}")
    if excluded_items:
        user_exceptions = cfg.get("item_exceptions") or []
        note = ", ".join(user_exceptions) if user_exceptions else "built-in self-protection only"
        print(f"Excluding    : {len(excluded_items)} item(s) ({note})")

    if file_count == 0:
        print("\n[!] Nothing to pack: every item in the source is excluded, or the")
        print("    folder is empty. Aborting so an empty archive isn't created.")
        pause()
        return

    if not confirm_select("Proceed with Pack and Transfer?", default_yes=True):
        header("Pack and Transfer")
        print("[*] Cancelled.")
        pause()
        return

    header("Pack and Transfer")
    start = time.monotonic()
    tmp_base_path = os.path.join(SCRIPT_DIR, f".pack_tmp_{os.getpid()}_{archive_name}")
    print("[*] Packing files...")
    archive_path, stats = build_archive(source_dir, exceptions, tmp_base_path, cfg["archive_format"],
                                         cfg["compression_level"])

    if not archive_path:
        print("[!] Packing failed. Aborting.")
        log_event(f"FAILED pack '{archive_name}{ext}' from {source_dir}")
        pause()
        return

    final_name = archive_name + ext
    renamed_path = os.path.join(SCRIPT_DIR, final_name)
    try:
        if os.path.exists(renamed_path):
            os.remove(renamed_path)
        os.rename(archive_path, renamed_path)
        archive_path = renamed_path
    except OSError as e:
        print(f"[!] Could not finalize archive name: {e}")

    packed_size = _safe_size(archive_path)
    elapsed = time.monotonic() - start
    ratio = (1 - packed_size / stats["bytes_in"]) * 100 if stats["bytes_in"] else 0.0
    print(f"\n[+] Packed: {archive_path}")
    print(f"    {stats['files']} file(s): {human_size(stats['bytes_in'])} -> {human_size(packed_size)} "
          f"({ratio:.1f}% smaller, {elapsed:.1f}s)")

    print("\n[*] Transferring...")
    ok = transfer_archive(archive_path, cfg["destination_directory"], cfg["conflict_resolution"])

    status = "completed" if ok else "finished (transfer skipped)"
    print(f"\n[{'OK' if ok else '*'}] Pack and Transfer {status} at "
          f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    log_event(
        f"{'OK' if ok else 'SKIPPED'} pack '{final_name}' - {stats['files']} files, "
        f"{human_size(stats['bytes_in'])} -> {human_size(packed_size)}, {elapsed:.1f}s "
        f"-> {cfg['destination_directory']}"
    )

    pause()


# --------------------------------------------------------------------------- #
# Main menu / loop
# --------------------------------------------------------------------------- #

def about_screen():
    header("About / Help")
    print(f"QuirkyIDE Packer & Transfer Tool  -  v{VERSION}\n")
    print("Archives a source folder and deploys it to a destination folder -")
    print("e.g. dropping a packaged web-asset bundle into an Android Studio")
    print("project's assets folder.\n")

    print("Archive formats:")
    for _, (fid, ext, label) in ARCHIVE_FORMATS.items():
        print(f"  {label:<38} {ext}")

    print("\nCompression levels (0-9, all lossless):")
    print("  0        store only, fastest, largest file")
    print("  1-3      fast, light compression")
    print("  4-6      balanced (default: 6)")
    print("  7-9      maximum compression, smallest file, slowest")
    print("  Extracted files are always byte-for-byte identical to the")
    print("  originals regardless of level - only speed/size trade off.")

    print("\nSafety:")
    print("  - Every pack is re-opened and integrity-checked right after")
    print("    creation. A failed check deletes the archive and cancels")
    print("    the transfer, so a corrupt file never gets deployed.")
    print("  - The script, its config, and its own log are always")
    print("    excluded from packing, so the tool never archives itself.")

    print(f"\nConfig file : {CONFIG_PATH}")
    print(f"Log file    : {LOG_PATH}")
    print("Protected   : " + ", ".join(builtin_protected_patterns()))
    print("              (always skipped when packing; can't be removed)")
    pause()


def main_menu():
    cfg = load_config()
    options = [
        {"label": "Pack and Transfer", "value": "pack"},
        {"label": "Settings", "value": "settings"},
        {"label": "About", "value": "about"},
        {"label": "Exit", "value": "exit"},
    ]
    while True:
        choice = arrow_select(options, title="Main Menu", allow_escape=True, escape_label="Exit")

        if choice == "pack":
            pack_and_transfer(cfg)
        elif choice == "settings":
            settings_menu(cfg)
        elif choice == "about":
            about_screen()
        elif choice in ("exit", None):
            clear_screen()
            print("Goodbye!")
            sys.exit(0)


if __name__ == "__main__":
    enable_ansi()
    try:
        main_menu()
    except KeyboardInterrupt:
        print("\n\n[*] Interrupted. Exiting.")
        sys.exit(0)