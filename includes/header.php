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

// Get user's display name (first name and last name for players)
$displayName = $user['username'] ?? 'User';
$genderEmoji = '👤'; // Default emoji
if ($role === 'player') {
    $stmt = db()->prepare("SELECT first_name, last_name, gender FROM players WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user['id']]);
    $headerPlayer = $stmt->fetch();
    if ($headerPlayer && !empty($headerPlayer['first_name'])) {
        $displayName = trim(($headerPlayer['first_name'] ?? '') . ' ' . ($headerPlayer['last_name'] ?? ''));
    }
    // Set gender-based emoji
    if ($headerPlayer && !empty($headerPlayer['gender'])) {
        $gender = strtolower($headerPlayer['gender']);
        if ($gender === 'male') {
            $genderEmoji = '👨';
        } elseif ($gender === 'female') {
            $genderEmoji = '👩';
        } else {
            $genderEmoji = '🏳️‍🌈'; // LGBT flag for other
        }
    }
}

// Build nav links based on role
$navLinks = [];
if ($role === 'admin') {
    $navLinks = [
        ['href' => '/TournamentHQ/admin/index.php',                'icon' => 'grid',        'label' => 'Dashboard'],
        ['href' => '/TournamentHQ/admin/manage_users.php',         'icon' => 'users',       'label' => 'Users'],
        ['href' => '/TournamentHQ/admin/manage_players.php',       'icon' => 'user-check',  'label' => 'Players'],
        ['href' => '/TournamentHQ/admin/manage_tournaments.php',   'icon' => 'trophy',      'label' => 'Tournaments'],
        ['href' => '/TournamentHQ/admin/bracket_generator.php',   'icon' => 'git-branch',  'label' => 'Auto Bracket'],
        ['href' => '/TournamentHQ/admin/reports.php',              'icon' => 'bar-chart-2', 'label' => 'Reports'],
    ];
} elseif ($role === 'organizer') {
    $navLinks = [
        ['href' => '/TournamentHQ/organizer/index.php',       'icon' => 'grid',   'label' => 'Dashboard'],
        ['href' => '/TournamentHQ/organizer/tournaments.php', 'icon' => 'trophy', 'label' => 'Tournaments'],
        ['href' => '/TournamentHQ/organizer/umpire_access.php', 'icon' => 'key', 'label' => 'Umpire Access'],
        ['href' => '/TournamentHQ/organizer/bracket_generator.php', 'icon' => 'git-branch', 'label' => 'Auto Bracket'],
        ['href' => '/TournamentHQ/organizer/players.php',     'icon' => 'users',  'label' => 'Players'],
        ['href' => '/TournamentHQ/organizer/notifications.php', 'icon' => 'bell', 'label' => 'Notifications', 'badge' => $notificationCount],
        ['href' => '/TournamentHQ/organizer/profile.php', 'icon' => 'user', 'label' => 'Profile'],
    ];
} elseif ($role === 'player') {
    $navLinks = [
        ['href' => '/TournamentHQ/player/index.php',    'icon' => 'grid',       'label' => 'Dashboard'],
        ['href' => '/TournamentHQ/player/notifications.php', 'icon' => 'bell', 'label' => 'Notifications', 'badge' => $notificationCount],
        ['href' => '/TournamentHQ/player/profile.php',  'icon' => 'user',       'label' => 'Profile'],
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
    <link rel="stylesheet" href="/TournamentHQ/assets/css/style.css">
    <?= isset($extraCss) ? $extraCss : '' ?>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to content</a>

<?php if ($user): ?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-container">
            <div class="logo-icon"><?= $genderEmoji ?></div>
            <div class="logo-glow"></div>
        </div>
        <div class="logo-info">
            <div class="logo-title"><?= e($displayName) ?></div>
            <div class="logo-badge <?= e($role) ?>"><?= ucfirst(e($role)) ?></div>
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
            <a href="/TournamentHQ/<?= ($role === 'player' ? 'player/profile.php' : ($role === 'organizer' ? 'organizer/profile.php' : 'admin/index.php')) ?>" class="user-avatar-link" title="View profile">
                <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            </a>
            <div class="user-details">
                <span class="user-name"><?= e($user['username']) ?></span>
            </div>
        </div>
        <a href="/TournamentHQ/includes/logout.php" class="logout-btn logout-btn-text" title="Logout" onclick="return confirm('Are you sure you want to log out?');">
            Logout
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="false">
            <i data-lucide="menu"></i>
        </button>
        <div class="topbar-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></div>
        <?php if ($user && in_array($role, ['organizer', 'player'], true)): ?>
        <div class="topbar-right">
            <a href="/TournamentHQ/<?= $role ?>/notifications.php" class="notification-wrap" title="Notifications">
                <button type="button" class="notification-bell" aria-label="Open notifications">
                    <i data-lucide="bell"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?= (int) $notificationCount > 9 ? '9+' : (int) $notificationCount ?></span>
                    <?php endif; ?>
                </button>
            </a>
        </div>
        <?php endif; ?>
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
    <main class="page-content" id="main-content">
<?php endif; ?>
