<?php

/**
 * Shared admin shell: navigation + page chrome.
 */

require_once __DIR__ . '/status.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/site_footer.php';

function adminNavItems(): array
{
    return [
        'dashboard' => [
            'label' => 'Home',
            'short' => 'Home',
            'href' => BASE_URL . '/admin/dashboard.php',
            'desc' => 'Overview & shortcuts',
        ],
        'shipments' => [
            'label' => 'Inbound Shipments',
            'short' => 'Shipments',
            'href' => BASE_URL . '/admin/shipments.php',
            'desc' => 'Add, edit & search shipments',
        ],
        'overview' => [
            'label' => 'User Counting Records',
            'short' => 'Records',
            'href' => BASE_URL . '/admin/overview.php',
            'desc' => 'View, search & filter user submissions',
        ],
        'users' => [
            'label' => 'User Management',
            'short' => 'Users',
            'href' => BASE_URL . '/admin/users.php',
            'desc' => 'Warehouse accounts',
        ],
    ];
}

function adminPageStart(string $title, string $activeNav, array $user): void
{
    $nav = adminNavItems();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> - Admin</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(stylesheetHref()) ?>">
</head>
<body class="admin-portal">
  <header class="app-header">
    <h1 class="app-header-title">Inbound Counting</h1>
    <nav class="admin-nav" aria-label="Admin sections">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="admin-nav-link<?= $key === $activeNav ? ' is-active' : '' ?>">
          <?= htmlspecialchars($item['short']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="user-info">
      <span><?= htmlspecialchars($user['username']) ?></span>
      <a href="<?= BASE_URL ?>/logout.php">Logout</a>
    </div>
  </header>
  <div id="timeout-warning"></div>
  <main class="main-container admin-main">
    <?php
}

function adminRenderFlash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $type = $flash['type'] === 'error' ? 'error' : 'success';
    ?>
    <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php
}

function adminPageEnd(array $scripts = []): void
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

function adminShortcutCards(): void
{
    $nav = adminNavItems();
    unset($nav['dashboard']);
    ?>
    <div class="admin-shortcuts">
      <?php foreach ($nav as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>" class="admin-shortcut-card">
          <span class="admin-shortcut-title"><?= htmlspecialchars($item['label']) ?></span>
          <span class="admin-shortcut-desc"><?= htmlspecialchars($item['desc']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
}
