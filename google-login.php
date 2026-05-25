<?php
/**
 * Start Google OAuth (sign in / sign up)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
startGoogleOAuth($mode);
