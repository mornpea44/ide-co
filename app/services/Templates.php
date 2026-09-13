<?php

declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  QUIRKY IDE — PROJECT TEMPLATES (Phase 5 · Task 5.4)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  Scaffolds new projects inside workspace/ from predefined templates.
 *  Each template is a set of folders + starter files with sensible content.
 *
 *  Templates:
 *    blank      → empty folder
 *    html       → index.html + css/ + js/
 *    php-mvc    → simple PHP MVC structure
 *    python     → main.py + README + requirements.txt
 *
 *  EXPOSES: IdeTemplates class
 * ═══════════════════════════════════════════════════════════════════════════
 */
class IdeTemplates
{
  private IdeSecurity $security;
  private string $workspaceRoot;

  public function __construct(array $config, IdeSecurity $security)
  {
    $this->security = $security;
    $realRoot = realpath(IDE_WORKSPACE);
    if ($realRoot === false) {
      mkdir(IDE_WORKSPACE, 0777, true);
      $realRoot = realpath(IDE_WORKSPACE);
    }
    $this->workspaceRoot = str_replace('\\', '/', (string) $realRoot);
  }

    /* ═══════════════════════════════════════════════════════════════
    TEMPLATE DEFINITIONS
    ═══════════════════════════════════════════════════════════════ */

  /**
   * Returns the list of available templates (for the UI picker).
   */
  public function getAvailableTemplates(): array
  {
    return [
      [
        'id'          => 'blank',
        'name'        => 'Blank Project',
        'description' => 'An empty folder — start from scratch.',
        'icon'        => '📁',
      ],
      [
        'id'          => 'html',
        'name'        => 'HTML Starter',
        'description' => 'index.html + CSS + JS boilerplate.',
        'icon'        => '🌐',
      ],
      [
        'id'          => 'php-mvc',
        'name'        => 'PHP MVC',
        'description' => 'Simple router, config, includes, pages.',
        'icon'        => '🐘',
      ],
      [
        'id'          => 'python',
        'name'        => 'Python Script',
        'description' => 'main.py + README + requirements.txt.',
        'icon'        => '🐍',
      ],
    ];
  }

  /**
   * Create a project from a template.
   *
   * @param string $templateId  One of the template IDs above
   * @param string $projectName The folder name to create
   * @return array Result metadata
   */
  public function createProject(string $templateId, string $projectName): array
  {
    // Validate template exists
    $templates = $this->getAvailableTemplates();
    $found = null;
    foreach ($templates as $t) {
      if ($t['id'] === $templateId) {
        $found = $t;
        break;
      }
    }
    if ($found === null) {
      $this->security->fail('Unknown template: ' . $templateId);
    }

    // Validate project name
    $projectName = trim($projectName);
    if ($projectName === '') {
      $this->security->fail('Project name cannot be empty.');
    }
    if (!preg_match('/^[A-Za-z0-9_\-][A-Za-z0-9_\- .]*$/', $projectName)) {
      $this->security->fail('Project name contains invalid characters. Use letters, numbers, hyphens, underscores, spaces, and dots.');
    }
    // Sanitise for filesystem: replace spaces with hyphens
    $safeName = preg_replace('/\s+/', '-', $projectName);
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $safeName);
    if ($safeName === '') {
      $this->security->fail('Project name resolves to an empty folder name.');
    }

    $projectPath = $this->workspaceRoot . '/' . $safeName;
    if (is_dir($projectPath)) {
      $this->security->fail('A folder named "' . $safeName . '" already exists in the workspace.');
    }

    // Create the project root
    if (!mkdir($projectPath, 0777, true)) {
      $this->security->fail('Could not create project folder.');
    }

    // Scaffold based on template
    $filesCreated = 0;
    $foldersCreated = 1; // the root

    switch ($templateId) {
      case 'blank':
        // Nothing to create — just the empty folder
        break;

      case 'html':
        $result = $this->scaffoldHtml($projectPath);
        $filesCreated = $result['files'];
        $foldersCreated += $result['folders'];
        break;

      case 'php-mvc':
        $result = $this->scaffoldPhpMvc($projectPath);
        $filesCreated = $result['files'];
        $foldersCreated += $result['folders'];
        break;

      case 'python':
        $result = $this->scaffoldPython($projectPath);
        $filesCreated = $result['files'];
        $foldersCreated += $result['folders'];
        break;
    }

    return [
      'created'   => true,
      'template'  => $templateId,
      'name'      => $safeName,
      'path'      => $safeName,
      'files'     => $filesCreated,
      'folders'   => $foldersCreated,
    ];
  }

  /* ═══════════════════════════════════════════════════════════════
    SCAFFOLD BUILDERS
    ═══════════════════════════════════════════════════════════════ */

  private function scaffoldHtml(string $root): array
  {
    $files = 0;
    $folders = 0;

    // css/ folder
    mkdir($root . '/css', 0777, true);
    $folders++;

    // js/ folder
    mkdir($root . '/js', 0777, true);
    $folders++;

    // index.html
    $html = <<<'HTML'
      <!DOCTYPE html>
      <html lang="en">
      <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>My Project</title>
          <link rel="stylesheet" href="css/style.css">
      </head>
      <body>
          <header>
              <h1>Hello, World! 👋</h1>
              <p>Welcome to your new project. Start editing <code>index.html</code>.</p>
          </header>

          <main>
              <section id="content">
                  <h2>Getting Started</h2>
                  <p>Add your content here.</p>
              </section>
          </main>

          <footer>
              <p>&copy; 2025 My Project</p>
          </footer>

          <script src="js/app.js"></script>
      </body>
      </html>
      HTML;
    file_put_contents($root . '/index.html', $html);
    $files++;

    // css/style.css
    $css = <<<'CSS'
      /* ═══ Reset & Base ═══ */
      *, *::before, *::after {
          box-sizing: border-box;
          margin: 0;
          padding: 0;
      }

      body {
          font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
          line-height: 1.6;
          color: #1c2430;
          background: #f8fafc;
          padding: 2rem;
      }

      header {
          margin-bottom: 2rem;
      }

      h1 {
          font-size: 2rem;
          margin-bottom: 0.5rem;
      }

      main {
          max-width: 800px;
      }

      code {
          background: #e2e8f0;
          padding: 0.15em 0.4em;
          border-radius: 4px;
          font-size: 0.9em;
      }

      footer {
          margin-top: 3rem;
          padding-top: 1rem;
          border-top: 1px solid #e2e8f0;
          color: #64748b;
          font-size: 0.85rem;
      }
      CSS;
    file_put_contents($root . '/css/style.css', $css);
    $files++;

    // js/app.js
    $js = <<<'JS'
      // ═══ App Entry Point ═══
      document.addEventListener('DOMContentLoaded', function () {
          console.log('🚀 Project loaded!');

          // Your code here
      });
      JS;
    file_put_contents($root . '/js/app.js', $js);
    $files++;

    return ['files' => $files, 'folders' => $folders];
  }

  private function scaffoldPhpMvc(string $root): array
  {
    $files = 0;
    $folders = 0;

    // includes/ folder
    mkdir($root . '/includes', 0777, true);
    $folders++;

    // pages/ folder
    mkdir($root . '/pages', 0777, true);
    $folders++;

    // index.php — simple front controller / router
    $index = <<<'PHP'
      <?php
      declare(strict_types=1);
      /**
       * Simple front controller.
       * Routes ?page=... to files in pages/.
       */
      $page = $_GET['page'] ?? 'home';

      // Whitelist allowed pages (prevent directory traversal)
      $allowed = ['home', 'about'];
      if (!in_array($page, $allowed, true)) {
          $page = 'home';
      }

      $pageFile = __DIR__ . '/pages/' . $page . '.php';

      require __DIR__ . '/includes/header.php';

      if (file_exists($pageFile)) {
          require $pageFile;
      } else {
          echo '<h2>Page not found</h2>';
      }

      require __DIR__ . '/includes/footer.php';
      PHP;
    file_put_contents($root . '/index.php', $index);
    $files++;

    // config.php
    $config = <<<'PHP'
      <?php
      declare(strict_types=1);
      /**
       * Project configuration.
       */
      return [
          'app_name' => 'My PHP Project',
          'debug'    => true,
      ];
      PHP;
    file_put_contents($root . '/config.php', $config);
    $files++;

    // includes/header.php
    $header = <<<'PHP'
      <?php $config = require __DIR__ . '/../config.php'; ?>
      <!DOCTYPE html>
      <html lang="en">
      <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title><?php echo htmlspecialchars($config['app_name']); ?></title>
          <style>
              body { font-family: system-ui, sans-serif; margin: 2rem; line-height: 1.6; }
              nav a { margin-right: 1rem; }
          </style>
      </head>
      <body>
      <nav>
          <a href="?page=home">Home</a>
          <a href="?page=about">About</a>
      </nav>
      <hr>
      PHP;
    file_put_contents($root . '/includes/header.php', $header);
    $files++;

    // includes/footer.php
    $footer = <<<'PHP'
      <hr>
      <footer>
          <small>&copy; <?php echo date('Y'); ?> — Built with Quirky IDE</small>
      </footer>
      </body>
      </html>
      PHP;
    file_put_contents($root . '/includes/footer.php', $footer);
    $files++;

    // pages/home.php
    $home = <<<'PHP'
      <h1>Welcome Home 🏠</h1>
      <p>This is your home page. Edit <code>pages/home.php</code> to change it.</p>
      PHP;
          file_put_contents($root . '/pages/home.php', $home);
          $files++;

          // pages/about.php
          $about = <<<'PHP'
      <h1>About ℹ️</h1>
      <p>This project was scaffolded with the Quirky IDE PHP MVC template.</p>
      PHP;
    file_put_contents($root . '/pages/about.php', $about);
    $files++;

    return ['files' => $files, 'folders' => $folders];
  }

  private function scaffoldPython(string $root): array
  {
    $files = 0;
    $folders = 0;

    // main.py
    $main = <<<'PYTHON'
      #!/usr/bin/env python3
      """
      My Python Project
      =================
      A starter script scaffolded by Quirky IDE.
      """


      def main():
          """Entry point."""
          print("🐍 Hello from Python!")
          print("Edit main.py to get started.")


      if __name__ == "__main__":
          main()
      PYTHON;
    file_put_contents($root . '/main.py', $main);
    $files++;

    // README.md
    $readme = <<<'MD'
      # My Python Project

      A starter project scaffolded by **Quirky IDE**.

      ## Running

      ```bash
      python main.py
      ```

      ## Structure

      ```
      ├── main.py          # Entry point
      ├── README.md        # This file
      └── requirements.txt # Dependencies
      ```
      MD;
    file_put_contents($root . '/README.md', $readme);
    $files++;

    // requirements.txt
    $reqs = <<<'TXT'
      # Add your dependencies here, one per line.
      # Example:
      # requests>=2.28
      TXT;
    file_put_contents($root . '/requirements.txt', $reqs);
    $files++;

    return ['files' => $files, 'folders' => $folders];
  }
}
