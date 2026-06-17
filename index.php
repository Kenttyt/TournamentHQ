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
    <style>
        :root {
            --primary:        #6c63ff;
            --primary-light:  #8b85ff;
            --accent:         #00d4aa;
            --bg-900:         #0d0e1a;
            --bg-800:         #12131f;
            --bg-700:         #1a1b2e;
            --border:         rgba(255, 255, 255, 0.07);
            --text-100:       #f0f2ff;
            --text-200:       #c5c8e8;
            --text-300:       #9094c0;
            --text-400:       #6065a0;
            --radius-md:      14px;
            --radius-sm:      8px;
            --radius-lg:      20px;
        }

        body {
            background-color: var(--bg-900);
            color: var(--text-100);
            margin: 0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Toornament.com Style Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f111a;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0 24px;
            height: 72px;
            display: flex;
            align-items: center;
        }

        .nav-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 22px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .brand-logo em {
            font-style: normal;
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
            height: 100%;
            margin-left: auto;
        }

        .nav-link-item {
            color: #a3a7c2;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            position: relative;
            background: var(--bg-700);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            padding: 10px 22px;
            height: auto;
        }

        .nav-link-item:hover {
            color: #ffffff;
            border-color: rgba(108, 99, 255, 0.4);
            background: rgba(108, 99, 255, 0.1);
        }

        .nav-link-item.active {
            color: #ffffff;
            background: rgba(108, 99, 255, 0.2);
            border-color: rgba(108, 99, 255, 0.5);
        }

        .header-cta {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.25s ease, transform 0.2s ease;
        }

        .header-cta:hover {
            background-color: var(--primary-light);
            transform: translateY(-1px);
        }

        .login-page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(108,99,255,0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 90% 80%, rgba(0,212,170,0.08) 0%, transparent 60%),
                var(--bg-900);
            overflow-x: hidden;
        }

        .home-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            gap: 56px;
            align-items: center;
            justify-content: space-between;
        }

        /* Left landing — register & organize */
        .landing-panel {
            flex: 1.2;
            max-width: 560px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .landing-headline {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 800;
            line-height: 1.15;
            color: var(--text-100);
            letter-spacing: -0.3px;
        }

        .landing-headline em {
            font-style: normal;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .landing-lead {
            font-size: 15px;
            color: var(--text-300);
            line-height: 1.65;
            max-width: 480px;
        }

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

        /* Right Side / Flow Panel container */
        .login-section {
            flex: 0.85;
            display: flex;
            justify-content: flex-end;
            min-width: 380px;
        }

        .landing-cta-box {
            background: var(--bg-800);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .login-card {
            background: var(--bg-800);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg), var(--shadow-glow);
            backdrop-filter: blur(16px);
        }

        .login-sep {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-400);
            font-size: 11px;
            margin: 22px 0 14px;
        }

        .login-sep::before, .login-sep::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--text-400);
            pointer-events: none;
            transition: color 0.25s ease;
        }

        .form-control:focus + .input-icon,
        .form-control:focus ~ .input-icon {
            color: var(--primary-light);
        }

        .input-wrap .form-control {
            padding-left: 42px;
            background: var(--bg-700);
            border-color: rgba(255,255,255,0.05);
            font-weight: 500;
            height: 46px;
        }

        .input-wrap .form-control:focus {
            background: var(--bg-600);
            border-color: var(--primary);
        }

        .show-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-400);
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .show-pw:hover {
            color: var(--text-200);
        }

        .show-pw i {
            width: 16px;
            height: 16px;
        }

        .btn-login {
            width: 100%;
            height: 46px;
            font-size: 15px;
            margin-top: 12px;
            border-radius: var(--radius-sm);
            justify-content: center;
            font-weight: 600;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 4px 12px rgba(108,99,255,0.25);
        }

        .btn-login:hover {
            box-shadow: 0 6px 18px rgba(108,99,255,0.4);
            transform: translateY(-1px);
        }

        @media (max-width: 900px) {
            .home-container {
                flex-direction: column;
                gap: 40px;
                max-width: 420px;
            }
            .landing-panel {
                max-width: 100%;
                text-align: center;
                align-items: center;
            }
            .landing-lead { margin-left: auto; margin-right: auto; }
            .landing-steps { width: 100%; }
            .landing-perks { width: 100%; }
            .login-section {
                width: 100%;
                min-width: 0;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .login-page { padding: 24px 16px; }
            .login-card, .landing-cta-box { padding: 28px 24px; }
            .landing-perks { grid-template-columns: 1fr; }
        }

        /* Feature Highlights */
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
            <a href="/TournamentHQ/play.php" class="nav-link-item">
                <i data-lucide="gamepad-2" style="width:16px;height:16px;"></i> Play
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
<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>
</body>
</html>
