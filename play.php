<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$featuredTournaments = [];
try {
    $stmt = db()->prepare("
        SELECT t.id, t.name, t.category, t.status, t.start_date, t.end_date, t.venue, t.max_players,
               (SELECT COUNT(*) FROM tournament_players tp WHERE tp.tournament_id = t.id) as registered_count
        FROM tournaments t
        WHERE t.status IN ('upcoming', 'ongoing')
        ORDER BY t.start_date ASC
        LIMIT 6
    ");
    $stmt->execute();
    $featuredTournaments = $stmt->fetchAll();
} catch (PDOException $e) {
    $featuredTournaments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play | TournamentHQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/style.css">
    <style>
        :root {
            --primary: #6c63ff;
            --primary-light: #8b85ff;
            --accent: #00d4aa;
            --bg-900: #0d0e1a;
            --bg-800: #12131f;
            --bg-700: #1a1b2e;
            --border: rgba(255,255,255,0.07);
            --text-100: #f0f2ff;
            --text-200: #c5c8e8;
            --text-300: #9094c0;
            --text-400: #6065a0;
            --radius-md: 14px;
            --radius-sm: 8px;
            --radius-lg: 20px;
        }
        body {
            background-color: var(--bg-900); color: var(--text-100); margin: 0;
            font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column;
        }
        .site-header {
            position: sticky; top: 0; z-index: 100;
            background: #0f111a; border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 0 24px; height: 72px; display: flex; align-items: center;
        }
        .nav-container {
            width: 100%; max-width: 1200px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center; height: 100%;
        }
        .brand-logo {
            display: flex; align-items: center; gap: 8px; text-decoration: none;
            color: #fff; font-family: 'Outfit', sans-serif; font-weight: 800;
            font-size: 22px; letter-spacing: -0.5px; text-transform: uppercase;
        }
        .brand-logo em { font-style: normal; color: var(--accent); }
        .nav-links { display: flex; align-items: center; gap: 12px; }
        .nav-link-item {
            color: #a3a7c2; text-decoration: none; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; text-transform: uppercase; transition: all 0.25s ease;
            display: flex; align-items: center; gap: 6px; background: var(--bg-700);
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            cursor: pointer; padding: 10px 22px;
        }
        .nav-link-item:hover { color: #fff; border-color: rgba(108,99,255,0.4); background: rgba(108,99,255,0.1); }
        .nav-link-item.active { color: #fff; background: rgba(108,99,255,0.2); border-color: rgba(108,99,255,0.5); }
        .nav-login {
            color: var(--accent); text-decoration: none; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; text-transform: uppercase; transition: all 0.25s ease;
            display: flex; align-items: center; gap: 6px; background: rgba(0,212,170,0.1);
            border: 1px solid rgba(0,212,170,0.3); border-radius: var(--radius-sm);
            padding: 10px 22px;
        }
        .nav-login:hover { background: rgba(0,212,170,0.2); border-color: rgba(0,212,170,0.5); color: #fff; }
        .login-page {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 60px 20px;
            background: radial-gradient(ellipse at 10% 20%, rgba(108,99,255,0.12) 0%, transparent 60%),
                        radial-gradient(ellipse at 90% 80%, rgba(0,212,170,0.08) 0%, transparent 60%),
                        var(--bg-900);
        }
        .home-container {
            display: flex; width: 100%; max-width: 800px;
            align-items: center; justify-content: center;
        }
        .landing-panel { max-width: 700px; display: flex; flex-direction: column; gap: 28px; align-items: center; text-align: center; }
        .landing-headline {
            font-family: 'Outfit', sans-serif; font-size: clamp(28px, 4vw, 40px);
            font-weight: 800; line-height: 1.15; color: var(--text-100); letter-spacing: -0.3px;
        }
        .landing-headline em {
            font-style: normal; background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .landing-lead { font-size: 15px; color: var(--text-300); line-height: 1.65; max-width: 520px; }

        /* How It Works */
        .how-section {
            width: 100%; max-width: 900px; margin: 0 auto;
            padding: 50px 20px 40px; box-sizing: border-box;
        }
        .how-section > h2 {
            font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700;
            color: var(--text-100); margin: 0 0 32px 0; text-align: center;
        }
        .how-steps {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
        }
        @media (max-width: 640px) { .how-steps { grid-template-columns: 1fr; } }
        .how-step {
            display: flex; flex-direction: column; align-items: center; text-align: center;
            gap: 14px; padding: 24px 16px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border); border-radius: var(--radius-md);
        }
        .how-step:hover { border-color: rgba(108,99,255,0.25); background: rgba(108,99,255,0.03); }
        .how-step-num {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(108,99,255,0.15); color: var(--primary-light);
            font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .how-step h3 {
            font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 600;
            color: var(--text-100); margin: 0;
        }
        .how-step p {
            font-size: 13px; color: var(--text-300); line-height: 1.6; margin: 0;
        }

        /* Stats Bar */
        .stats-section {
            width: 100%; max-width: 800px; margin: 0 auto;
            padding: 10px 20px 60px; box-sizing: border-box;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
            text-align: center;
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .stat-item { display: flex; flex-direction: column; gap: 4px; }
        .stat-num {
            font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 800;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            font-size: 12px; font-weight: 500; color: var(--text-400);
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Featured Tournaments */
        .tournament-section {
            width: 100%; max-width: 1000px; margin: 0 auto;
            padding: 0 20px 60px; box-sizing: border-box;
        }
        .tournament-section > h2 {
            font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700;
            color: var(--text-100); margin: 0 0 24px 0; text-align: center;
        }
        .tournament-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
        }
        @media (max-width: 768px) { .tournament-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .tournament-grid { grid-template-columns: 1fr; } }
        .tournament-card {
            background: var(--bg-800); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 20px;
            display: flex; flex-direction: column; gap: 12px;
            transition: border-color 0.2s ease, background 0.2s ease;
            text-decoration: none; color: inherit;
        }
        .tournament-card:hover {
            border-color: rgba(108,99,255,0.3);
            background: rgba(108,99,255,0.03);
        }
        .tournament-card-header {
            display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
        }
        .tournament-card-name {
            font-family: 'Outfit', sans-serif; font-size: 15px;
            font-weight: 600; color: var(--text-100); line-height: 1.3;
        }
        .tournament-badge {
            display: inline-flex; align-items: center; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;
        }
        .badge-upcoming { background: rgba(0,212,170,0.12); color: var(--accent); }
        .badge-ongoing { background: rgba(255,165,0,0.12); color: #ffa500; }
        .badge-category {
            background: rgba(108,99,255,0.12); color: var(--primary-light);
        }
        .tournament-card-meta {
            display: flex; flex-direction: column; gap: 6px;
        }
        .tournament-card-meta span {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--text-400);
        }
        .tournament-card-meta i { width: 14px; height: 14px; flex-shrink: 0; }
        .tournament-card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 10px; border-top: 1px solid var(--border);
        }
        .tournament-card-footer .spots {
            font-size: 12px; font-weight: 500; color: var(--text-300);
        }
        .tournament-card-footer .spots strong { color: var(--text-100); }
        .tournament-card-footer .register-btn {
            font-size: 11px; font-weight: 700; color: var(--accent);
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-400); font-size: 14px;
        }
        .empty-state i { width: 40px; height: 40px; color: var(--text-400); margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }

        /* Footer */
        .site-footer {
            border-top: 1px solid var(--border);
            padding: 24px 20px; text-align: center;
            font-size: 12px; color: var(--text-400);
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav-container">
        <a href="/TournamentHQ/index.php" class="brand-logo">
            <i data-lucide="trophy"></i>
            <span>TournamentHQ<em>.</em></span>
        </a>
        <nav class="nav-links">
            <a href="/TournamentHQ/login.php" class="nav-login">
                <i data-lucide="log-in" style="width:16px;height:16px;"></i> Login
            </a>
        </nav>
    </div>
</header>

<div class="login-page">
    <div class="home-container">
        <aside class="landing-panel">
            <h1 class="landing-headline">
                Find your sport.<br><em>Compete</em> in tournaments.
            </h1>
            <p class="landing-lead">
                Browse upcoming tournaments across multiple sports. Create your player profile and register for events.
            </p>
        </aside>
    </div>
</div>



<div class="how-section">
    <h2>How It Works</h2>
    <div class="how-steps">
        <div class="how-step">
            <span class="how-step-num">1</span>
            <h3>Browse Sports</h3>
            <p>Explore upcoming tournaments across table tennis, tennis, badminton, and more.</p>
        </div>
        <div class="how-step">
            <span class="how-step-num">2</span>
            <h3>Register</h3>
            <p>Create your player profile and sign up for events that match your skill level.</p>
        </div>
        <div class="how-step">
            <span class="how-step-num">3</span>
            <h3>Compete</h3>
            <p>Show up, play your matches, and track your results on the leaderboard.</p>
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-num">500+</span>
            <span class="stat-label">Players</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">50+</span>
            <span class="stat-label">Tournaments</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">10K</span>
            <span class="stat-label">Matches Played</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">100%</span>
            <span class="stat-label">Free</span>
        </div>
    </div>
</div>

<div class="tournament-section">
    <h2>Featured Tournaments</h2>
    <?php if (empty($featuredTournaments)): ?>
        <div class="empty-state">
            <i data-lucide="calendar-x"></i>
            <p>No upcoming tournaments yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="tournament-grid">
            <?php foreach ($featuredTournaments as $t): ?>
                <div class="tournament-card">
                    <div class="tournament-card-header">
                        <span class="tournament-card-name"><?= e($t['name']) ?></span>
                        <span class="tournament-badge badge-<?= $t['status'] ?>"><?= e($t['status']) ?></span>
                    </div>
                    <span class="tournament-badge badge-category"><?= e($t['sport'] ?? 'Table Tennis') ?></span>
                    <span class="tournament-badge badge-category"><?= e($t['category']) ?></span>
                    <div class="tournament-card-meta">
                        <span><i data-lucide="calendar"></i> <?= date('M j, Y', strtotime($t['start_date'])) ?></span>
                        <?php if ($t['venue']): ?>
                            <span><i data-lucide="map-pin"></i> <?= e($t['venue']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tournament-card-footer">
                        <span class="spots"><strong><?= (int)$t['registered_count'] ?></strong> / <?= (int)$t['max_players'] ?> players</span>
                        <span class="register-btn">View &rarr;</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="site-footer">
    TournamentHQ
</footer>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>
</body>
</html>
