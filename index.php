<?php
/**
 * Root Entry Point — Landing Page
 */
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
    <meta name="description" content="Register as a player or organize table tennis tournaments — registration and brackets in one system.">
    <title>TournamentHQ | Table Tennis Tournament Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/style.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/public.css">
    <style>
        .landing-steps {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .landing-step {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 16px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .landing-step:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(108,99,255,0.2);
        }

        .step-num {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            border-radius: 8px;
            background: rgba(108,99,255,0.15);
            color: var(--primary-light);
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step-body h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-100);
            margin-bottom: 4px;
        }

        .step-body p {
            font-size: 13px;
            color: var(--text-400);
            line-height: 1.5;
        }

        .landing-perks {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .perk-card {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }

        .perk-card i {
            width: 18px;
            height: 18px;
            color: var(--accent);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .perk-card span {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-200);
            line-height: 1.4;
        }

        @media (max-width: 480px) {
            .landing-perks { grid-template-columns: 1fr; }
        }

        .features-section {
            width: 100%; max-width: 900px; margin: 0 auto;
            padding: 50px 20px 60px; box-sizing: border-box;
        }
        .features-section > h2 {
            font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700;
            color: var(--text-100); margin: 0 0 32px 0; text-align: center;
        }
        .features-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
        }
        @media (max-width: 640px) { .features-grid { grid-template-columns: 1fr; } }
        .feature-card {
            display: flex; flex-direction: column; gap: 14px;
            padding: 24px 20px;
            background: var(--bg-800); border: 1px solid var(--border);
            border-radius: var(--radius-md);
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .feature-card:hover {
            border-color: rgba(108,99,255,0.3); background: rgba(108,99,255,0.03);
        }
        .feat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(108,99,255,0.12); display: flex;
            align-items: center; justify-content: center;
        }
        .feat-icon i { width: 20px; height: 20px; color: var(--primary-light); }
        .feature-card h3 {
            font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 600;
            color: var(--text-100); margin: 0;
        }
        .feature-card p {
            font-size: 13px; color: var(--text-300); line-height: 1.6; margin: 0;
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
            <a href="/TournamentHQ/play.php" class="nav-link-item">
                <i data-lucide="trophy" style="width:16px;height:16px;"></i> Play
            </a>
            <a href="/TournamentHQ/login.php?role=organizer" class="nav-link-item">
                <i data-lucide="layout-grid" style="width:16px;height:16px;"></i> Organize
            </a>
        </nav>
    </div>
</header>

<div class="login-page">
    <div class="home-container">

        <aside class="landing-panel">
            <h1 class="landing-headline">
                Register players.<br><em>Organize</em> your tournaments.
            </h1>
            <p class="landing-lead">
                Built for clubs and organizers who need a simple way to sign up competitors, run events, and manage tournament brackets in one place.
            </p>

            <div class="landing-steps">
                <div class="landing-step">
                    <span class="step-num">1</span>
                    <div class="step-body">
                        <h3>Create your account</h3>
                        <p>Register as a player in minutes. Your profile is ready from day one.</p>
                    </div>
                </div>
                <div class="landing-step">
                    <span class="step-num">2</span>
                    <div class="step-body">
                        <h3>Join or host a tournament</h3>
                        <p>Organizers set up events, players, and publish brackets for everyone to follow.</p>
                    </div>
                </div>
            </div>

        </aside>

    </div>
</div>

<div class="features-section">
    <h2>Built for Competition</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feat-icon"><i data-lucide="git-merge"></i></div>
            <h3>Bracket Generation</h3>
            <p>Auto-generate single or double elimination brackets with one click.</p>
        </div>
        <div class="feature-card">
            <div class="feat-icon"><i data-lucide="calendar-clock"></i></div>
            <h3>Smart Scheduling</h3>
            <p>Set match dates, assign tables, and manage the full tournament timeline.</p>
        </div>
        <div class="feature-card">
            <div class="feat-icon"><i data-lucide="zap"></i></div>
            <h3>Live Results</h3>
            <p>Submit match outcomes and watch brackets update in real time.</p>
        </div>
    </div>
</div>

<footer class="site-footer">
    TournamentHQ
</footer>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="/TournamentHQ/assets/js/public.js"></script>
</body>
</html>
