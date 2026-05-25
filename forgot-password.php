<?php
/**
 * Forgot password — request reset link
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_public_shell.php';
require_once __DIR__ . '/modules/auth/password_reset.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$message = '';
$messageType = 'info';
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['email_or_username'] ?? '');
    if ($input === '') {
        $message = 'Please enter your email or username.';
        $messageType = 'danger';
    } else {
        $result = requestPasswordReset($input);
        $message = $result['message'];
        $messageType = 'success';
        $devLink = $result['dev_link'] ?? null;
    }
}

$flash = getFlash();
authPublicHeader('Forgot password', 'Enter your email or username and we will send reset instructions if an account exists.');

if ($flash): ?>
<div class="flash-message flash-<?= e($flash['type']) ?>" style="margin-bottom:16px">
    <?= e($flash['message']) ?>
</div>
<?php endif;

if ($message): ?>
<div class="flash-message flash-<?= e($messageType) ?>" style="margin-bottom:16px">
    <?= e($message) ?>
</div>
<?php endif; ?>

<?php if ($devLink): ?>
<div class="dev-reset-link">
    <strong style="color:var(--info);display:block;margin-bottom:6px">Local development — email not sent</strong>
    Use this link to reset your password (expires in 1 hour):<br>
    <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
</div>
<?php endif; ?>

<?php if ($messageType !== 'success'): ?>
<form method="POST" action="">
    <div class="form-group">
        <label class="form-label" for="email_or_username">Email or username</label>
        <div class="input-wrap">
            <i data-lucide="mail" class="input-icon"></i>
            <input type="text" id="email_or_username" name="email_or_username" class="form-control"
                   placeholder="you@example.com or your username" required
                   value="<?= e($_POST['email_or_username'] ?? '') ?>" autocomplete="username">
        </div>
    </div>
    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;height:46px;margin-top:8px">
        Send reset link
    </button>
</form>
<?php endif; ?>

<a href="/table-tennis-system/index.php" class="auth-back-link">
    <i data-lucide="arrow-left" style="width:14px;height:14px"></i> Back to sign in
</a>

<?php
authPublicFooter();
