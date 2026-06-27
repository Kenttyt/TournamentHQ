<?php
/**
 * Player Registration Page
 * Table Tennis Tournament Management System
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';
require_once __DIR__ . '/includes/mailer.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$error = '';
$flash = getFlash();
$googleEnabled = isGoogleOAuthConfigured();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid request. Please try again.';
    } else {
    $password   = $_POST['password'] ?? '';
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    if (!$password || !$username || !$email) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[A-Za-z]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 letters only (A-Z)';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $pdo = db();
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            $existing = $stmt->fetch();
            if ($existing) {
                if ($existing['username'] === $username) {
                    $error = 'Username is already taken.';
                } else {
                    $error = 'Email address is already registered.';
                }
            } else {
                $pdo->beginTransaction();
                // Insert user with username and email
                $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, is_active, auth_method) VALUES (?, ?, ?, 'player', 1, 'local')");
                $stmt->execute([$username, $hashed_pw, $email]);
                $userId = $pdo->lastInsertId();

                // Insert player profile: store username as first_name and leave last_name empty
                $stmt = $pdo->prepare("INSERT INTO players (user_id, first_name, last_name, points, wins, losses) VALUES (?, ?, '', 0, 0, 0)");
                $stmt->execute([$userId, $username]);

                $pdo->commit();

                // Generate verification token and store it
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                try {
                    db()->prepare("UPDATE users SET verification_token = ?, token_expires = ?, is_verified = 0 WHERE id = ?")->execute([$token, $expires, $userId]);
                } catch (Exception $e) {
                    // non-fatal
                }

                // Send verification email (best-effort)
                $sendRes = send_verification_email($email, $username, $userId, $token);

                if ($sendRes['success']) {
                    setFlash('success', 'Account created! A verification email has been sent to ' . e($email) . '. Please verify your email to access your account (check your spam folder if you do not see it).');
                } else {
                    setFlash('warning', 'Account created! However, we could not send the verification email to ' . e($email) . ' automatically. Please try to log in to resend the verification email, or contact support.');
                }
                header('Location: ' . url('/index.php'));
                exit;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Registration failed. Please try again.';
        }
    }
    } // end CSRF validation
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register as a player or organize table tennis tournaments â€” registration and brackets in one system.">
    <title>Register | TournamentHQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/public.css') ?>">
    <style>
        .login-page {
            min-height: 100vh;
            overflow-x: hidden;
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
            padding: 30px;
        }

        .btn-login {
            width: 100%;
            height: 44px;
            font-size: 14px;
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

        @media (max-width: 480px) {
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
                        <p>Organizers set up events, players, and publish brackets for everyone to follow.</p>
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

        <!-- Registration form -->
        <div class="login-section">
            <div class="login-card">
                <div class="login-logo" style="margin-bottom: 20px; text-align: left;">
                    <h2 style="font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700; color: var(--text-100);">Create Account</h2>
                    <p style="font-size: 13px; color: var(--text-400); margin-top: 4px;">Join the community! Fill out the form to create your player profile.</p>
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

                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <div class="form-group">
                        <label class="form-label" for="username">Username *</label>
                        <div class="input-wrap">
                            <svg data-lucide="user" class="input-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required minlength="3" maxlength="30" pattern="[A-Za-z]{3,30}">
                        </div>
                        <p class="text-xs text-muted" style="margin-top:6px;">3-30 letters only (A–Z).</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <div class="input-wrap">
                            <svg data-lucide="mail" class="input-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <path d="M22 4L12 13 2 4"></path>
                            </svg>
                            <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password *</label>
                        <div class="input-wrap">
                            <svg data-lucide="lock" class="input-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                            <button type="button" class="show-pw" id="togglePw" title="Show/hide password">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>



                    <button type="submit" class="btn btn-primary btn-login" id="registerBtn">
                        <i data-lucide="user-plus" style="margin-right: 6px;"></i>
                        Create Account
                    </button>
                </form>

                <?php if ($googleEnabled): ?>
                <a href="<?= url('/google-login.php?mode=register') ?>" class="btn" style="width: 100%; justify-content: center; height: 44px; font-size: 14px; background: #ffffff; color: #1f1f1f; margin-top: 12px; border: 1px solid #dadce0; font-family: 'Roboto', sans-serif; font-weight: 500;">
                    <svg viewBox="0 0 24 24" width="18" height="18" style="margin-right: 8px; vertical-align: middle;">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Sign up with Google
                </a>
                <?php else: ?>
                <p class="text-muted text-xs" style="margin-top:12px;text-align:center;line-height:1.45">
                    Google sign-up: copy <code>config/google.local.php.example</code> to <code>config/google.local.php</code> and add your OAuth Client ID &amp; Secret.
                </p>
                <?php endif; ?>

                <div class="login-sep">Already have an account?</div>

                <a href="<?= url('/index.php') ?>" class="btn btn-outline" style="width: 100%; justify-content: center; height: 44px; font-size: 14px;">
                    <i data-lucide="log-in" style="margin-right: 6px;"></i>
                    Sign In
                </a>
            </div>
        </div>

    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="<?= url('/assets/js/public.js') ?>"></script>
<script>
const registerForm = document.getElementById('registerForm');
const usernameInput = document.getElementById('username');
const pwInput = document.getElementById('password');
if (registerForm && usernameInput && pwInput) {
    registerForm.addEventListener('submit', (event) => {
        const usernameVal = usernameInput.value.trim();
        if (!usernameVal) {
            event.preventDefault();
            alert('Please enter a username.');
            usernameInput.focus();
            return;
        }
        if (!/^[A-Za-z]{3,30}$/.test(usernameVal)) {
            event.preventDefault();
            alert('Username must be 3-30 letters only (A\u2013Z).');
            usernameInput.focus();
            return;
        }
        if (!pwInput.value || pwInput.value.length < 6) {
            event.preventDefault();
            alert('Please provide a password with at least 6 characters.');
            pwInput.focus();
            return;
        }
    });
}

document.querySelectorAll('.show-pw').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var wrapper = btn.closest('.input-wrap');
        if (!wrapper) return;
        var input = wrapper.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;
        var isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        var eyeIcon = btn.querySelector('.eye-icon');
        var eyeOffIcon = btn.querySelector('.eye-off-icon');
        if (eyeIcon) eyeIcon.style.display = isPassword ? 'none' : 'block';
        if (eyeOffIcon) eyeOffIcon.style.display = isPassword ? 'block' : 'none';
    });
});
</script>
</body>
</html>
