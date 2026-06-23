<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$loginError = '';
$loginErrorIsHtml = false;
$regError = '';
$loginUsername = '';
$regUsername = '';
$regEmail = trim($_POST['email'] ?? $_GET['google_email'] ?? '');
$isGoogleSignup = (isset($_GET['google_email']) || (isset($_POST['form_type']) && $_POST['form_type'] === 'register' && isset($_POST['is_google_signup']) && $_POST['is_google_signup'] == '1'));
$activeTab = $isGoogleSignup ? 'register' : 'login';
$roleParam = $_GET['role'] ?? ($_POST['role_param'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $loginError = 'Invalid request. Please try again.';
    } elseif (isset($_POST['form_type'])) {
        if ($_POST['form_type'] === 'login') {
            $activeTab = 'login';

            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $rateLimit = checkRateLimit($clientIp);

            if (!$rateLimit['allowed']) {
                $loginError = 'Too many failed login attempts. Please try again in ' . ceil($rateLimit['retry_after'] / 60) . ' minute(s).';
            } elseif ($roleParam === 'umpire') {
                $accessCode = trim($_POST['access_code'] ?? '');
                if (empty($accessCode)) {
                    $loginError = 'Please enter the access code.';
                } else {
                    $pdo = db();
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'umpire' AND is_active = 1 LIMIT 1");
                    $stmt->execute([$accessCode]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $_SESSION['user_id']  = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role']     = $user['role'];
                        $_SESSION['email']    = $user['email'];
                        header('Location: ' . getDashboardUrl($user['role']));
                        exit;
                    } else {
                        $loginError = 'Invalid or expired access code.';
                        recordFailedLogin($clientIp);
                    }
                }
            } else {
                $loginUsername = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($loginUsername) || empty($password)) {
                    $loginError = 'Please enter both username and password.';
                } else {
                    $result = loginUser($loginUsername, $password);
                    if ($result['success']) {
                        clearRateLimit($clientIp);
                        if ($roleParam === 'organizer' && !in_array($result['role'], ['organizer', 'admin'], true)) {
                            $_SESSION = [];
                            if (ini_get('session.use_cookies')) {
                                $params = session_get_cookie_params();
                                setcookie(session_name(), '', time() - 42000,
                                    $params['path'], $params['domain'],
                                    $params['secure'], $params['httponly']
                                );
                            }
                            session_destroy();
                            session_start();
                            $loginError = 'This login is for Organizer accounts only. Please use the Player login.';
                        } elseif ($roleParam !== 'organizer' && in_array($result['role'], ['organizer', 'admin'])) {
                            $_SESSION = [];
                            if (ini_get('session.use_cookies')) {
                                $params = session_get_cookie_params();
                                setcookie(session_name(), '', time() - 42000,
                                    $params['path'], $params['domain'],
                                    $params['secure'], $params['httponly']
                                );
                            }
                            session_destroy();
                            session_start();
                            $loginError = 'This login is for Player accounts only. Please use the Organizer login.';
                        } elseif ($result['role'] === 'umpire') {
                            // strictly block umpires from logging in as players/organizers
                            $_SESSION = [];
                            if (ini_get('session.use_cookies')) {
                                $params = session_get_cookie_params();
                                setcookie(session_name(), '', time() - 42000,
                                    $params['path'], $params['domain'],
                                    $params['secure'], $params['httponly']
                                );
                            }
                            session_destroy();
                            session_start();
                            $loginError = 'Umpire accounts can only log in via the Umpire login portal.';
                        } else {
                            header('Location: ' . getDashboardUrl($result['role']));
                            exit;
                        }
                    } elseif ($result['message'] === 'email_not_verified') {
                        $loginError = 'Your email is not verified. Please check your inbox or <a href="/TournamentHQ/resend-verification.php">resend verification email</a>.';
                        $loginErrorIsHtml = true;
                    } else {
                        $loginError = $result['message'];
                        recordFailedLogin($clientIp);
                    }
                }
            }
        } elseif ($_POST['form_type'] === 'register') {
            $activeTab = 'register';
            $regUsername = trim($_POST['username'] ?? '');
            $regEmail = trim($_POST['email'] ?? '');
            $regPassword = $_POST['password'] ?? '';
            $gender = $_POST['gender'] ?? 'male';
            $nationality = trim($_POST['nationality'] ?? '');
            $club = trim($_POST['club'] ?? '');

            if (!$regPassword || !$regUsername || !$regEmail) {
                $regError = 'Please fill in all required fields.';
            } elseif (!preg_match('/^[A-Za-z]{3,30}$/', $regUsername)) {
                $regError = 'Username must be 3-30 letters only (A-Z).';
            } elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
                $regError = 'Please enter a valid email address.';
            } elseif (strlen($regPassword) < 6) {
                $regError = 'Password must be at least 6 characters.';
            } else {
                try {
                    $pdo = db();
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                    $stmt->execute([$regUsername, $regEmail]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        $stmt2 = $pdo->prepare("SELECT username FROM users WHERE username = ? LIMIT 1");
                        $stmt2->execute([$regUsername]);
                        if ($stmt2->fetch()) {
                            $regError = 'Username is already taken.';
                        } else {
                            $regError = 'Email address is already registered.';
                        }
                    } else {
                        $pdo->beginTransaction();
                        $hashed = password_hash($regPassword, PASSWORD_DEFAULT);
                        $authMethod = $isGoogleSignup ? 'google' : 'local';
                        $isVerified = $isGoogleSignup ? 1 : 0;
                        $roleVal = ($roleParam === 'organizer') ? 'organizer' : 'player';
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, is_active, auth_method, is_verified) VALUES (?, ?, ?, ?, 1, ?, ?)");
                        $stmt->execute([$regUsername, $hashed, $regEmail, $roleVal, $authMethod, $isVerified]);
                        $userId = $pdo->lastInsertId();
                        
                        if ($roleVal === 'player') {
                            $stmt = $pdo->prepare("INSERT INTO players (user_id, first_name, last_name, gender, club, nationality, points, wins, losses) VALUES (?, ?, '', ?, ?, ?, 0, 0, 0)");
                            $stmt->execute([$userId, $regUsername, $gender, $nationality ?: null, $club ?: null]);
                        }
                        $pdo->commit();

                        if ($isGoogleSignup) {
                            $redirectAfterReg = '/TournamentHQ/login.php' . ($roleParam === 'organizer' ? '?role=organizer' : '');
                            setFlash('success', 'Account created successfully using Google email! You can now log in.');
                            header('Location: ' . $redirectAfterReg);
                            exit;
                        } else {
                            $token = bin2hex(random_bytes(32));
                            $expires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                            try {
                                db()->prepare("UPDATE users SET verification_token = ?, token_expires = ?, is_verified = 0 WHERE id = ?")->execute([$token, $expires, $userId]);
                            } catch (Exception $e) {}

                            send_verification_email($regEmail, $regUsername, $userId, $token);
                            setFlash('success', 'Account created! A verification email has been sent to ' . e($regEmail) . '. Please verify your email to access your account.');
                            header('Location: /TournamentHQ/login.php');
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                    $regError = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

$flash = getFlash();
$googleEnabled = isGoogleOAuthConfigured();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TournamentHQ</title>
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
        .login-page {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 60px 20px;
            background: radial-gradient(ellipse at 10% 20%, rgba(108,99,255,0.12) 0%, transparent 60%),
                        radial-gradient(ellipse at 90% 80%, rgba(0,212,170,0.08) 0%, transparent 60%),
                        var(--bg-900);
        }
        .login-card {
            background: var(--bg-800); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 0; width: 100%;
            max-width: 420px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); overflow: hidden;
        }

        /* Tabs */
        .auth-tabs {
            display: flex; border-bottom: 1px solid var(--border);
        }
        .auth-tab {
            flex: 1; padding: 16px 0; text-align: center; cursor: pointer;
            font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600;
            color: var(--text-400); background: none; border: none;
            position: relative; transition: color 0.2s ease;
        }
        .auth-tab:hover { color: var(--text-200); }
        .auth-tab.active { color: var(--text-100); }
        .auth-tab.active::after {
            content: ''; position: absolute; bottom: -1px; left: 20%; right: 20%;
            height: 2px; background: var(--primary); border-radius: 2px;
        }

        .auth-form { padding: 28px 32px 32px; }
        .auth-form.hidden { display: none; }

        .auth-form h2 {
            font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700;
            color: var(--text-100); margin: 0 0 4px 0;
        }
        .auth-form .lead {
            font-size: 13px; color: var(--text-400); margin: 0 0 24px 0;
        }
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--text-200); margin-bottom: 5px; text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; color: var(--text-400); pointer-events: none;
            transition: color 0.25s ease;
        }
        .form-control:focus ~ .input-icon { color: var(--primary-light); }
        .input-wrap .form-control {
            width: 100%; padding: 11px 14px 11px 42px; box-sizing: border-box;
            background: var(--bg-700); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text-100);
            font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 500;
            height: 44px; outline: none; transition: border-color 0.2s ease, background 0.2s ease;
        }
        .form-control:focus { border-color: var(--primary); background: rgba(108,99,255,0.05); }
        .form-control::placeholder { color: var(--text-400); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-select {
            width: 100%; height: 44px; padding: 0 14px; box-sizing: border-box;
            background: var(--bg-700); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text-100);
            font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 500;
            outline: none; cursor: pointer;
        }
        .form-select:focus { border-color: var(--primary); }
        .show-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--text-400);
            padding: 4px; display: flex; align-items: center;
        }
        .show-pw:hover { color: var(--text-200); }
        .show-pw i { width: 16px; height: 16px; }
        .form-error {
            background: rgba(255,80,80,0.1); border: 1px solid rgba(255,80,80,0.3);
            border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 14px;
            font-size: 13px; color: #ff6b6b; line-height: 1.5;
        }
        .form-error a { color: #ff6b6b; text-decoration: underline; }
        .form-warning {
            background: rgba(255,165,0,0.1); border: 1px solid rgba(255,165,0,0.35);
            border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 14px;
            font-size: 13px; color: #ffb74d; line-height: 1.5;
        }
        .form-warning a { color: #ffb74d; text-decoration: underline; }
        .form-success {
            background: rgba(0,212,170,0.1); border: 1px solid rgba(0,212,170,0.3);
            border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 14px;
            font-size: 13px; color: var(--accent); line-height: 1.5;
        }
        .btn-submit {
            width: 100%; height: 44px; border: none; border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif; font-size: 14px; font-weight: 600;
            color: #fff; cursor: pointer; margin-top: 6px;
            background: linear-gradient(135deg, var(--primary), #5a52d5);
            box-shadow: 0 4px 12px rgba(108,99,255,0.25);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-submit:hover { box-shadow: 0 6px 18px rgba(108,99,255,0.4); transform: translateY(-1px); }
        .form-hint {
            font-size: 11px; color: var(--text-400); margin-top: 4px;
        }
        .login-sep {
            display: flex; align-items: center; gap: 10px;
            color: var(--text-400); font-size: 11px; margin: 18px 0 14px;
        }
        .login-sep::before, .login-sep::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .btn-google {
            width: 100%; height: 44px; border: 1px solid #dadce0; border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 500;
            color: #1f1f1f; cursor: pointer; margin-top: 10px;
            background: #fff; display: flex; align-items: center; justify-content: center;
            gap: 10px; text-decoration: none; transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-google:hover { background: #f8f9fa; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .btn-google svg { width: 18px; height: 18px; }
        .site-footer {
            border-top: 1px solid var(--border);
            padding: 24px 20px; text-align: center;
            font-size: 12px; color: var(--text-400);
        }
        @media (max-width: 480px) {
            .login-card { margin: 0 8px; }
            .auth-form { padding: 24px 20px 28px; }
            .form-row { grid-template-columns: 1fr; }
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
    </div>
</header>

<div class="login-page">
    <div class="login-card">
        <?php if ($roleParam !== 'umpire'): ?>
        <div class="auth-tabs">
            <button class="auth-tab <?= $activeTab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Log in</button>
            <button class="auth-tab <?= $activeTab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Sign up</button>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="auth-form <?= $activeTab !== 'login' ? 'hidden' : '' ?>" id="loginForm">
            <?php if ($roleParam === 'umpire'): ?>
                <div style="margin-bottom: 16px;">
                    <a href="/TournamentHQ/login.php?role=organizer" style="color: var(--primary-light); font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
            <h2><?= $roleParam === 'organizer' ? 'Log in to your organizer account' : ($roleParam === 'umpire' ? 'Umpire Login' : 'Welcome back') ?></h2>
            <p class="lead"><?= $roleParam === 'organizer' ? 'Sign in to manage your tournaments' : ($roleParam === 'umpire' ? 'Enter your tournament access code to start scoring' : 'Sign in to your TournamentHQ account') ?></p>

            <?php if ($flash): ?>
                <div class="<?= $flash['type'] === 'success' ? 'form-success' : ($flash['type'] === 'warning' ? 'form-warning' : 'form-error') ?>"><?= $flash['message'] ?></div>
            <?php endif; ?>
            <?php if ($loginError): ?>
                <div class="form-error"><?= $loginErrorIsHtml ? $loginError : e($loginError) ?></div>
            <?php endif; ?>

            <form method="POST" action="/TournamentHQ/login.php">
                <input type="hidden" name="form_type" value="login">
                <input type="hidden" name="role_param" value="<?= htmlspecialchars($roleParam) ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                
                <?php if ($roleParam === 'umpire'): ?>
                <div class="form-group">
                    <label for="login-access-code">Access Code</label>
                    <div class="input-wrap">
                        <input type="text" id="login-access-code" name="access_code" class="form-control"
                               placeholder="Enter Umpire Access Code" required autofocus style="padding-left: 14px;">
                    </div>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label for="login-username">Username or Email</label>
                    <div class="input-wrap">
                        <input type="text" id="login-username" name="username" class="form-control"
                               placeholder="Enter your username or email" value="<?= htmlspecialchars($loginUsername) ?>" required autofocus>
                        <i data-lucide="user" class="input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="login-password" name="password" class="form-control"
                               placeholder="Enter your password" required>
                        <i data-lucide="lock" class="input-icon"></i>
                        <button type="button" class="show-pw" onclick="togglePassword('login-password', 'login-pw-icon')">
                            <i data-lucide="eye" id="login-pw-icon"></i>
                        </button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                        <?php if ($roleParam === 'organizer'): ?>
                            <a href="/TournamentHQ/login.php?role=umpire" style="color: var(--primary-light); font-size: 12px; text-decoration: none; font-weight: 500;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Are you an Umpire? Log in here</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        <a href="/TournamentHQ/forgot-password.php" style="color: var(--primary-light); font-size: 12px; text-decoration: none; font-weight: 500;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">Forgot password?</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <?php if ($googleEnabled && $roleParam !== 'umpire'): ?>
            <div class="login-sep">or</div>
            <a href="/TournamentHQ/google-login.php?mode=login<?= $roleParam ? '&role=' . urlencode($roleParam) : '' ?>" class="btn-google">
                <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign in with Google
            </a>
            <?php endif; ?>
        </div>

        <!-- Register Form -->
        <div class="auth-form <?= $activeTab !== 'register' ? 'hidden' : '' ?>" id="registerForm">
            <h2><?= $roleParam === 'organizer' ? 'Create Organizer Account' : 'Create Account' ?></h2>
            <p class="lead"><?= $roleParam === 'organizer' ? 'Create events and manage your tournaments' : 'Join the community and start competing' ?></p>

            <?php if ($regError): ?>
                <div class="form-error"><?= e($regError) ?></div>
            <?php endif; ?>

            <form method="POST" action="/TournamentHQ/login.php">
                <input type="hidden" name="form_type" value="register">
                <input type="hidden" name="role_param" value="<?= htmlspecialchars($roleParam) ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <?php if ($isGoogleSignup): ?>
                    <input type="hidden" name="is_google_signup" value="1">
                <?php endif; ?>
                <div class="form-group">
                    <label for="reg-username">Username *</label>
                    <div class="input-wrap">
                        <input type="text" id="reg-username" name="username" class="form-control"
                               placeholder="3-30 letters only" value="<?= htmlspecialchars($regUsername) ?>" required
                               pattern="[A-Za-z]{3,30}" minlength="3" maxlength="30">
                        <i data-lucide="user" class="input-icon"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg-email">Email *</label>
                    <div class="input-wrap">
                        <input type="email" id="reg-email" name="email" class="form-control"
                               placeholder="your@email.com" value="<?= htmlspecialchars($regEmail) ?>" required
                               <?= $isGoogleSignup ? 'readonly style="opacity: 0.6; cursor: not-allowed;"' : '' ?>>
                        <i data-lucide="mail" class="input-icon"></i>
                    </div>
                    <?php if ($isGoogleSignup): ?>
                        <div class="form-hint" style="color: var(--accent);">Email filled automatically from Google.</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="reg-password">Password *</label>
                    <div class="input-wrap">
                        <input type="password" id="reg-password" name="password" class="form-control"
                               placeholder="Min. 6 characters" required minlength="6">
                        <i data-lucide="lock" class="input-icon"></i>
                        <button type="button" class="show-pw" onclick="togglePassword('reg-password', 'reg-pw-icon')">
                            <i data-lucide="eye" id="reg-pw-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="reg-gender">Gender</label>
                        <select id="reg-gender" name="gender" class="form-select">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reg-nationality">Place</label>
                        <div class="input-wrap">
                            <input type="text" id="reg-nationality" name="nationality" class="form-control"
                                   placeholder="e.g. Manila">
                            <i data-lucide="map-pin" class="input-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg-club">Club (Optional)</label>
                    <div class="input-wrap">
                        <input type="text" id="reg-club" name="club" class="form-control"
                               placeholder="e.g. Smashers Club">
                        <i data-lucide="shield" class="input-icon"></i>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Create Account</button>
            </form>

            <?php if ($googleEnabled): ?>
            <div class="login-sep">or</div>
            <a href="/TournamentHQ/google-login.php?mode=register<?= $roleParam ? '&role=' . urlencode($roleParam) : '' ?>" class="btn-google">
                <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign up with Google
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="site-footer">
    TournamentHQ
</footer>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });

function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.add('hidden'));
    if (tab === 'login') {
        document.querySelector('.auth-tab:nth-child(1)').classList.add('active');
        document.getElementById('loginForm').classList.remove('hidden');
    } else {
        document.querySelector('.auth-tab:nth-child(2)').classList.add('active');
        document.getElementById('registerForm').classList.remove('hidden');
    }
    lucide.createIcons();
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}
</script>
</body>
</html>
