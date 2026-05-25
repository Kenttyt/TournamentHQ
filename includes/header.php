<?php
/**
 * Shared Header Component
 */
require_once __DIR__ . '/auth.php';
$user  = getCurrentUser();
$role  = $user['role'] ?? '';
$flash = getFlash();

$notificationCount = 0;
if ($user && in_array($role, ['organizer', 'player'], true)) {
    require_once __DIR__ . '/../modules/notifications/notification_functions.php';
    $notificationCount = getNotificationBadgeCount((int) $user['id'], $role);
}

// Build nav links based on role
$navLinks = [];
if ($role === 'admin') {
    $navLinks = [
        ['href' => '/table-tennis-system/admin/index.php',                'icon' => 'grid',        'label' => 'Dashboard'],
        ['href' => '/table-tennis-system/admin/manage_users.php',         'icon' => 'users',       'label' => 'Users'],
        ['href' => '/table-tennis-system/admin/manage_players.php',       'icon' => 'user-check',  'label' => 'Players'],
        ['href' => '/table-tennis-system/admin/manage_tournaments.php',   'icon' => 'trophy',      'label' => 'Tournaments'],
        ['href' => '/table-tennis-system/admin/bracket_generator.php',   'icon' => 'git-branch',  'label' => 'Auto Bracket'],
        ['href' => '/table-tennis-system/admin/reports.php',              'icon' => 'bar-chart-2', 'label' => 'Reports'],
    ];
} elseif ($role === 'organizer') {
    $navLinks = [
        ['href' => '/table-tennis-system/organizer/index.php',       'icon' => 'grid',   'label' => 'Dashboard'],
        ['href' => '/table-tennis-system/organizer/tournaments.php', 'icon' => 'trophy', 'label' => 'Tournaments'],
        ['href' => '/table-tennis-system/organizer/bracket_generator.php', 'icon' => 'git-branch', 'label' => 'Auto Bracket'],
        ['href' => '/table-tennis-system/organizer/players.php',     'icon' => 'users',  'label' => 'Players'],
        ['href' => '/table-tennis-system/organizer/notifications.php', 'icon' => 'bell', 'label' => 'Notifications', 'badge' => $notificationCount],
    ];
} elseif ($role === 'player') {
    $navLinks = [
        ['href' => '/table-tennis-system/player/index.php',    'icon' => 'grid',       'label' => 'Dashboard'],
        ['href' => '/table-tennis-system/player/notifications.php', 'icon' => 'bell', 'label' => 'Notifications', 'badge' => $notificationCount],
        ['href' => '/table-tennis-system/player/profile.php',  'icon' => 'user',       'label' => 'Profile'],
    ];
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Table Tennis Tournament Management System — manage players, tournaments, matches and rankings.">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>TT Tournament Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/table-tennis-system/assets/css/style.css">
    <?= isset($extraCss) ? $extraCss : '' ?>
</head>
<body>

<?php if ($user): ?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">🏓</div>
        <div class="logo-text">
            <span class="logo-title">TT Manager</span>
            <span class="logo-badge <?= e($role) ?>"><?= ucfirst(e($role)) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navLinks as $link): 
            $isActive = (basename($link['href']) === $currentPage) ? 'active' : '';
        ?>
        <a href="<?= e($link['href']) ?>" class="nav-link <?= $isActive ?>">
            <i data-lucide="<?= e($link['icon']) ?>"></i>
            <span><?= e($link['label']) ?></span>
            <?php if (!empty($link['badge'])): ?>
            <span class="nav-link-badge"><?= (int) $link['badge'] > 9 ? '9+' : (int) $link['badge'] ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div class="user-details">
                <span class="user-name"><?= e($user['username']) ?></span>
                <span class="user-email"><?= e($user['email']) ?></span>
            </div>
        </div>
        <a href="/table-tennis-system/includes/logout.php" class="logout-btn" title="Logout">
            <i data-lucide="log-out"></i>
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i data-lucide="menu"></i>
        </button>
        <div class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></div>
    </header>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div class="flash-message flash-<?= e($flash['type']) ?>" id="flashMessage">
        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
        <?= e($flash['message']) ?>
        <button onclick="document.getElementById('flashMessage').remove()" class="flash-close">×</button>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="page-content">
<?php endif; ?>
