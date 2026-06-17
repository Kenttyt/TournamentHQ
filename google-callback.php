<?php
/**
 * Google OAuth callback
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';

$oauthMode = getGoogleOAuthMode();
$oauthRole = getGoogleOAuthRole();
$returnUrl = googleOAuthReturnUrl($oauthMode);

if (isLoggedIn()) {
    clearGoogleOAuthSession();
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

if (!isGoogleOAuthConfigured()) {
    setFlash('danger', 'Google sign-in is not configured.');
    clearGoogleOAuthSession();
    header('Location: ' . $returnUrl);
    exit;
}

if (isset($_GET['error'])) {
    setFlash('danger', 'Google sign-in was cancelled or denied.');
    clearGoogleOAuthSession();
    header('Location: ' . $returnUrl);
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !validateGoogleOAuthState($state)) {
    setFlash('danger', 'Invalid Google sign-in response. Please try again.');
    clearGoogleOAuthSession();
    header('Location: ' . $returnUrl);
    exit;
}

try {
    $tokenData = exchangeGoogleAuthCode($code);
    $accessToken = $tokenData['access_token'] ?? '';
    if (!$accessToken) {
        throw new RuntimeException('Google did not return an access token.');
    }

    $profile = fetchGoogleUserProfile($accessToken);
    $email = trim($profile['email'] ?? '');
    if ($email === '') {
        throw new RuntimeException('Your Google account did not provide an email address.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!(int) ($user['is_active'] ?? 0)) {
            setFlash('danger', 'This account is inactive. Please contact an administrator.');
            clearGoogleOAuthSession();
            header('Location: ' . $returnUrl);
            exit;
        }

        // If the user came from the SIGNUP page, don't auto-login — they already have an account
        if ($oauthMode === 'register') {
            $loginUrl = '/TournamentHQ/login.php' . ($oauthRole === 'organizer' ? '?role=organizer' : '');
            clearGoogleOAuthSession();
            // Special case: player Google account used on the organizer sign-up
            if ($oauthRole === 'organizer' && $user['role'] === 'player') {
                setFlash('warning', '⚠️ This Google account is already linked to a <strong>Player account</strong>. You cannot register it as an Organizer. Please use a different Google account, or <a href="/TournamentHQ/login.php">log in as a player</a> instead.');
            } else {
                setFlash('error', 'An account with this Google email already exists. Please log in instead.');
            }
            header('Location: ' . $loginUrl);
            exit;
        }

        // Do NOT automatically log the user in. Redirect them to the login page to type their credentials.
        $loginUrl = '/TournamentHQ/login.php' . ($oauthRole === 'organizer' ? '?role=organizer' : '');
        clearGoogleOAuthSession();
        setFlash('success', 'Google account linked! Please enter your username and password below to log in.');
        header('Location: ' . $loginUrl);
        exit;
    }

    // No account for this Google email
    if ($oauthMode === 'login') {
        setFlash(
            'error',
            'No account found for this Google email. Please create an account first using Sign up with Google.'
        );
        clearGoogleOAuthSession();
        header('Location: /TournamentHQ/login.php');
        exit;
    }

    // Register mode — redirect to signup form with email pre-filled and read-only
    $roleQuery = $oauthRole === 'organizer' ? '&role=organizer' : '';
    clearGoogleOAuthSession();
    header('Location: /TournamentHQ/login.php?google_email=' . urlencode($email) . $roleQuery);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('danger', 'Google sign-in failed: ' . $e->getMessage());
    clearGoogleOAuthSession();
    header('Location: ' . $returnUrl);
    exit;
}

function generateUniqueUsername(string $firstName, string $lastName): string {
    $pdo = db();
    $cleanFirst = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($firstName));
    $cleanLast  = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($lastName));
    $base = ($cleanFirst === '' && $cleanLast === '') ? 'player' : ($cleanFirst . $cleanLast);
    $username = $base;
    $counter = 1;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    while (true) {
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $base . $counter;
        $counter++;
    }
    return $username;
}
