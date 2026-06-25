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
                $minutes = ceil($rateLimit['retry_after'] / 60);
                $loginError = 'Too many failed login attempts. Please try again in ' . $minutes . ' minute(s).';
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
    <link rel="stylesheet" href="/TournamentHQ/assets/css/public.css">
    <style>
        .login-card {
            padding: 0;
            overflow: hidden;
        }

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

        @media (max-width: 480px) {
            .login-card { margin: 0 8px; }
            .auth-form { padding: 24px 20px 28px; }
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
                    <a href="/TournamentHQ/login.php?role=organizer" style="color: var(--primary-light); font-size: 13px; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;" class="hover-underline">
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
                        <button type="button" class="show-pw">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                        </button>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                        <?php if ($roleParam === 'organizer'): ?>
                            <a href="/TournamentHQ/login.php?role=umpire" style="color: var(--primary-light); font-size: 12px; font-weight: 500;" class="hover-underline">Are you an Umpire? Log in here</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        <a href="/TournamentHQ/forgot-password.php<?= $roleParam === 'organizer' ? '?role=organizer' : '' ?>" style="color: var(--primary-light); font-size: 12px; font-weight: 500;" class="hover-underline">Forgot password?</a>
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
                        <button type="button" class="show-pw">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
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
<script src="/TournamentHQ/assets/js/public.js"></script>
<script>
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
