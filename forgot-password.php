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
    if (!validateCsrfToken()) {
        $messageType = 'danger';
        $message = 'Invalid request. Please try again.';
    } else {
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
}

$flash = getFlash();
$roleParam = trim($_GET['role'] ?? '');
$loginUrl = $roleParam === 'organizer' ? '/TournamentHQ/login.php?role=organizer' : '/TournamentHQ/login.php';
$backLink = '<a href="' . e($loginUrl) . '" style="color: var(--primary-light); font-size: 13px; display: inline-flex; align-items: center; gap: 4px; font-weight: 500; text-decoration: none;" class="hover-underline"><i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i> Back to Sign in</a>';
authPublicHeader('Forgot password', 'Enter your email or username and we will send reset instructions if an account exists.', $backLink);

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
<div class="dev-reset-link" style="background:rgba(255,255,255,0.02); border:1px dashed var(--border); padding:16px; border-radius:8px; margin-bottom:16px;">
    <strong style="color:var(--accent);display:block;margin-bottom:6px">Local development — email not sent</strong>
    Temporary password generated:<br>
    <code style="font-size:16px; color:#fff; font-weight:bold;"><?= e($devLink) ?></code>
</div>
<?php endif; ?>

<?php if ($messageType !== 'success'): ?>
<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="input-wrap">
            <i data-lucide="mail" class="input-icon"></i>
            <input type="text" id="email_or_username" name="email_or_username" class="form-control"
                   placeholder="you@example.com or your username" required
                   value="<?= e($_POST['email_or_username'] ?? '') ?>" autocomplete="username">
        </div>
    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;height:46px;margin-top:8px">
        Send reset link
    </button>
</form>
<?php endif; ?>

<?php
authPublicFooter();
