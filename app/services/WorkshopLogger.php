<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — WORKSHOP LOGGER
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Persistent log for all Workshop activity (installs, Apache start/stop,
 *  errors, warnings). Replaces the useless 3-second toast for errors.
 *
 *  Log file: app/.workshop/logs/workshop.log
 *  Format:   [2025-01-15 14:30:22] [ERROR] [APACHE] message here
 *
 *  Capped at 500 lines / 200 KB so it never grows unbounded on a phone.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeWorkshopLogger
{
  /** @var string Absolute path to the log file */
  private string $logFile;

  /** @var string Absolute path to the log directory */
  private string $logDir;

  /** Maximum number of lines to keep */
  private const MAX_LINES = 500;

  /** Maximum file size in bytes (200 KB) */
  private const MAX_SIZE = 200 * 1024;

  public function __construct()
  {
    $this->logDir = IDE_APP . '/.workshop/logs';
    $this->logFile = $this->logDir . '/workshop.log';

    if (!is_dir($this->logDir)) {
      @mkdir($this->logDir, 0777, true);
    }
  }

    /* ═══════════════════════════════════════════════════════════
    PUBLIC LOGGING METHODS
    ═══════════════════════════════════════════════════════════ */

  /**
   * Write a log entry.
   *
   * @param string $level   INFO | WARN | ERROR | SUCCESS
   * @param string $source  APACHE | PKG | WORKSHOP | EXTENSION
   * @param string $message The log message
   */
  public function log(string $level, string $source, string $message): void
  {
    $level = strtoupper($level);
    $source = strtoupper($source);
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] [$source] $message\n";

    @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    $this->trimIfNeeded();
  }

  public function info(string $source, string $message): void
  {
    $this->log('INFO', $source, $message);
  }

  public function warn(string $source, string $message): void
  {
    $this->log('WARN', $source, $message);
  }

  public function error(string $source, string $message): void
  {
    $this->log('ERROR', $source, $message);
  }

  public function success(string $source, string $message): void
  {
    $this->log('SUCCESS', $source, $message);
  }

    /* ═══════════════════════════════════════════════════════════
    READ / CLEAR
    ═══════════════════════════════════════════════════════════ */

  /**
   * Get all log lines as an array (newest last).
   *
   * @return array{lines: array<int, array{time: string, level: string, source: string, message: string}>, total: int}
   */
  public function getLogs(): array
  {
    if (!is_file($this->logFile)) {
      return ['lines' => [], 'total' => 0];
    }

    $raw = @file_get_contents($this->logFile);
    if ($raw === false || $raw === '') {
      return ['lines' => [], 'total' => 0];
    }

    $rawLines = explode("\n", trim($raw));
    $parsed = [];

    foreach ($rawLines as $line) {
      $line = trim($line);
      if ($line === '') continue;

      // Parse: [2025-01-15 14:30:22] [ERROR] [APACHE] message
      if (preg_match(
        '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\[(\w+)\]\s+\[(\w+)\]\s+(.*)$/',
        $line,
        $m
      )) {
        $parsed[] = [
          'time'    => $m[1],
          'level'   => $m[2],
          'source'  => $m[3],
          'message' => $m[4],
        ];
      } else {
        // Unparseable line — include as-is
        $parsed[] = [
          'time'    => '',
          'level'   => 'RAW',
          'source'  => '',
          'message' => $line,
        ];
      }
    }

    return ['lines' => $parsed, 'total' => count($parsed)];
  }

  /**
   * Get the raw log text (for the copy button).
   */
  public function getRawLogs(): string
  {
    if (!is_file($this->logFile)) {
      return '';
    }
    return (string) @file_get_contents($this->logFile);
  }

  /**
   * Clear all logs.
   */
  public function clearLogs(): void
  {
    @file_put_contents($this->logFile, '', LOCK_EX);
  }

    /* ═══════════════════════════════════════════════════════════
    INTERNAL
    ═══════════════════════════════════════════════════════════ */

  /**
   * Trim the log file if it exceeds MAX_LINES or MAX_SIZE.
   * Keeps the most recent entries.
   */
  private function trimIfNeeded(): void
  {
    if (!is_file($this->logFile)) return;

    $size = filesize($this->logFile);
    if ($size === false || $size < self::MAX_SIZE) return;

    $content = @file_get_contents($this->logFile);
    if ($content === false) return;

    $lines = explode("\n", $content);

    // Keep only the last MAX_LINES lines
    if (count($lines) > self::MAX_LINES) {
      $lines = array_slice($lines, -self::MAX_LINES);
    }

    @file_put_contents($this->logFile, implode("\n", $lines), LOCK_EX);
  }
}
