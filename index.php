<?php
/**
 * Root Entry Point — Login Page
 * Table Tennis Tournament Management System
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$error = '';
$flash = getFlash();
$googleEnabled = isGoogleOAuthConfigured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please enter both username and password.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            header('Location: ' . getDashboardUrl($result['role']));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register as a player or organize table tennis tournaments — registration and brackets in one system.">
    <title>Sign In | TT Tournament Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/table-tennis-system/assets/css/style.css">
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

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(108,99,255,0.18) 0%, transparent 60%),
                radial-gradient(ellipse at 90% 80%, rgba(0,212,170,0.12) 0%, transparent 60%),
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

        .login-section {
            flex: 0.85;
            display: flex;
            justify-content: flex-end;
            min-width: 380px;
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
            .login-card { padding: 28px 24px; }
            .landing-perks { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
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
                        <p>Organizers set up events, seed players, and publish brackets for everyone to follow.</p>
                    </div>
                </div>
            </div>

            <div class="landing-perks">
                <div class="perk-card">
                    <i data-lucide="calendar-plus"></i>
                    <span>Tournament setup &amp; scheduling</span>
                </div>
                <div class="perk-card">
                    <i data-lucide="git-merge"></i>
                    <span>Bracket generation</span>
                </div>
            </div>
        </aside>

        <div class="login-section">
            <div class="login-card">
                <div class="login-logo" style="margin-bottom: 24px; text-align: left;">
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700; color: var(--text-100);">Sign In</h2>
                    <p style="font-size: 13px; color: var(--text-400); margin-top: 4px;">Welcome back! Enter your credentials to access your dashboard.</p>
                </div>

                <?php if ($flash): ?>
                <div class="flash-message flash-<?= e($flash['type']) ?>" style="margin-bottom:16px; margin-left:0; margin-right:0;">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
                    <?= e($flash['message']) ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="flash-message flash-error" style="margin-bottom:16px; margin-left:0; margin-right:0;">
                    <i data-lucide="alert-circle"></i>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrap">
                            <i data-lucide="user" class="input-icon"></i>
                            <input type="text" id="username" name="username" class="form-control"
                                   placeholder="Enter your username"
                                   value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px">
                            <label class="form-label" for="password" style="margin:0">Password</label>
                            <a href="/table-tennis-system/forgot-password.php" class="text-xs" style="color:var(--primary-light);font-weight:600;text-decoration:none">Forgot password?</a>
                        </div>
                        <div class="input-wrap">
                            <i data-lucide="lock" class="input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Enter your password" autocomplete="current-password" required>
                            <button type="button" class="show-pw" id="togglePw" title="Show/hide password">
                                <i data-lucide="eye" id="pwIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login" id="loginBtn">
                        <i data-lucide="log-in" style="margin-right: 6px;"></i>
                        Sign In
                    </button>
                </form>

                <?php if ($googleEnabled): ?>
                <a href="/table-tennis-system/google-login.php?mode=login" class="btn" style="width: 100%; justify-content: center; height: 46px; font-size: 14px; background: #ffffff; color: #1f1f1f; margin-top: 12px; border: 1px solid #dadce0; font-family: 'Roboto', sans-serif; font-weight: 500;">
                    <svg viewBox="0 0 24 24" width="18" height="18" style="margin-right: 8px; vertical-align: middle;">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign in with Google
                </a>
                <?php else: ?>
                <p class="text-muted text-xs" style="margin-top:12px;text-align:center;line-height:1.45">
                    Google sign-in: copy <code>config/google.local.php.example</code> to <code>config/google.local.php</code> and add your OAuth Client ID &amp; Secret.
                </p>
                <?php endif; ?>

                <div class="login-sep">New to the platform?</div>

                <a href="/table-tennis-system/register.php" class="btn btn-outline" style="width: 100%; justify-content: center; height: 46px; font-size: 14px;">
                    <i data-lucide="user-plus" style="margin-right: 6px;"></i>
                    Create Account
                </a>
            </div>
        </div>

    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Toggle Password visibility
    const togglePw = document.getElementById('togglePw');
    const pwInput = document.getElementById('password');
    const pwIcon = document.getElementById('pwIcon');
    if (togglePw && pwInput) {
        togglePw.addEventListener('click', () => {
            if (pwInput.type === 'password') {
                pwInput.type = 'text';
                pwIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                pwInput.type = 'password';
                pwIcon.setAttribute('data-lucide', 'eye');
            }
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    }

});
</script>
</body>
</html>
