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
        .nav-link-item.active { color: #fff; background: rgba(0,212,170,0.15); border-color: rgba(0,212,170,0.5); }
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
            display: flex; width: 100%; max-width: 800px; gap: 56px;
            align-items: center; justify-content: center;
        }
        .landing-panel { max-width: 700px; display: flex; flex-direction: column; gap: 28px; align-items: center; text-align: center; }
        .landing-headline {
            font-family: 'Outfit', sans-serif; font-size: clamp(28px, 4vw, 40px);
            font-weight: 800; line-height: 1.15; color: var(--text-100); letter-spacing: -0.3px;
        }
        .landing-headline em {
            font-style: normal; background: linear-gradient(135deg, var(--accent), #00b894);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .landing-lead { font-size: 15px; color: var(--text-300); line-height: 1.65; max-width: 480px; }
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
<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>
</body>
</html>
