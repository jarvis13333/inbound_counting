<?php

/**
 * Shared user portal shell: navigation + page chrome.
 */

require_once __DIR__ . '/status.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_footer.php';

function userNavItems(): array
{
    return [
        'dashboard' => [
            'label' => 'Counting',
            'href' => BASE_URL . '/user/dashboard.php',
        ],
        'profile' => [
            'label' => 'Profile',
            'href' => BASE_URL . '/user/profile.php',
        ],
    ];
}

function userPageStart(string $title, string $activeNav, array $user): void
{
    $nav = userNavItems();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - User</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body class="user-dashboard user-portal">
  <header class="app-header">
    <h1 class="app-header-title">Inbound Counting</h1>
    <nav class="user-nav" aria-label="User sections">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="user-nav-link<?= $key === $activeNav ? ' is-active' : '' ?>">
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="user-info">
      <span><?= htmlspecialchars($user['username']) ?></span>
      <a href="<?= BASE_URL ?>/logout.php">Logout</a>
    </div>
  </header>
  <div id="timeout-warning"></div>
  <main class="main-container user-main">
    <?php
}

function userRenderFlash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $type = $flash['type'] === 'error' ? 'error' : 'success';
    ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php
}

function userPageEnd(array $scripts = []): void
{
    ?>
  </main>
  <?php renderSiteFooter(); ?>
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
  <?php foreach ($scripts as $src): ?>
  <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>
  <script src="<?= BASE_URL ?>/assets/js/inactivity.js"></script>
</body>
</html>
    <?php
}
