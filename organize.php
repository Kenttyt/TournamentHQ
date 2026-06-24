<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organize | TournamentHQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/style.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/public.css">
    <style>
        .landing-panel {
            max-width: 700px;
            align-items: center;
            text-align: center;
        }
        .landing-headline em {
            background: linear-gradient(135deg, var(--accent), #00b894);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .feature-grid { display: flex; flex-direction: column; gap: 12px; }
        .feature-card {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 14px 16px; background: rgba(255,255,255,0.02);
            border: 1px solid var(--border); border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }
        .feature-card:hover { border-color: rgba(0,212,170,0.3); background: rgba(0,212,170,0.03); }
        .feature-card .feat-icon {
            width: 36px; height: 36px; flex-shrink: 0; border-radius: 8px;
            background: rgba(0,212,170,0.12); display: flex; align-items: center; justify-content: center;
        }
        .feature-card .feat-icon i { width: 18px; height: 18px; color: var(--accent); }
        .feature-card h3 { font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600; color: var(--text-100); margin: 0 0 4px 0; }
        .feature-card p { font-size: 12px; color: var(--text-400); line-height: 1.5; margin: 0; }
        @media (max-width: 600px) {
            .landing-panel { text-align: left; align-items: flex-start; }
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
            <a href="/TournamentHQ/login.php?role=organizer" class="nav-link-item active" style="color:#fff;background:rgba(0,212,170,0.15);border-color:rgba(0,212,170,0.5);">
                <i data-lucide="layout-grid" style="width:16px;height:16px;"></i> Organize
            </a>
            <a href="/TournamentHQ/login.php?role=organizer" class="nav-login">
                <i data-lucide="log-in" style="width:16px;height:16px;"></i> Login
            </a>
        </nav>
    </div>
</header>

<div class="login-page">
    <div class="home-container">

        <aside class="landing-panel">
            <h1 class="landing-headline">
                Run your tournaments.<br><em>Organize</em> like a pro.
            </h1>
            <p class="landing-lead">
                Full control over events, players, and brackets. Schedule matches, track results, and publish live brackets — all from one organizer dashboard.
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feat-icon"><i data-lucide="calendar-plus"></i></div>
                    <div>
                        <h3>Tournament Setup</h3>
                        <p>Create events, set dates, define rules, and configure entry requirements.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feat-icon"><i data-lucide="git-merge"></i></div>
                    <div>
                        <h3>Bracket Generation</h3>
                        <p>Auto-generate single or double elimination brackets with one click.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feat-icon"><i data-lucide="users"></i></div>
                    <div>
                        <h3>Player Management</h3>
                        <p>Approve registrations, manage rosters, and track competitor profiles.</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feat-icon"><i data-lucide="zap"></i></div>
                    <div>
                        <h3>Live Results</h3>
                        <p>Submit match outcomes and watch brackets update in real time.</p>
                    </div>
                </div>
            </div>
        </aside>

    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="/TournamentHQ/assets/js/public.js"></script>
</body>
</html>
