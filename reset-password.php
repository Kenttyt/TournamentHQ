<?php
/**
 * Reset password — set new password using token from email
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_public_shell.php';
require_once __DIR__ . '/modules/auth/password_reset.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;
$validToken = $token !== '' && getPasswordResetUserId($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $result = completePasswordReset($token, $password);
            if ($result['ok']) {
                setFlash('success', $result['message']);
                header('Location: ' . url('/index.php'));
                exit;
            }
            $error = $result['message'];
            $validToken = getPasswordResetUserId($token) !== null;
        }
    }
}

if ($token === '') {
    authPublicHeader('Reset password', '');
    echo '<div class="flash-message flash-error" style="margin-bottom:16px">Missing reset link. Request a new one from the forgot password page.</div>';
    echo '<a href="' . url('/forgot-password.php') . '" class="auth-back-link">Request reset link</a>';
    authPublicFooter();
    exit;
}

if (!$validToken && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    authPublicHeader('Reset password', '');
    echo '<div class="flash-message flash-error" style="margin-bottom:16px">This reset link is invalid or has expired. Please request a new one.</div>';
    echo '<a href="' . url('/forgot-password.php') . '" class="auth-back-link">Request new link</a>';
    authPublicFooter();
    exit;
}

authPublicHeader('Set new password', 'Choose a new password for your account (at least 6 characters).');

if ($error): ?>
<div class="flash-message flash-error" style="margin-bottom:16px"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <div class="form-group">
        <label class="form-label" for="password">New password</label>
        <input type="password" id="password" name="password" class="form-control" minlength="6" required placeholder="Min. 6 characters">
    </div>
    <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
    </div>
    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;height:46px;margin-top:8px">
        Update password
    </button>
</form>

<a href="<?= url('/index.php') ?>" class="auth-back-link">
    <i data-lucide="arrow-left" style="width:14px;height:14px"></i> Back to sign in
</a>

<?php
authPublicFooter();
