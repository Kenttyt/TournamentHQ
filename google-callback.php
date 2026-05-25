<?php
/**
 * Google OAuth callback
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/auth/google_oauth.php';

$oauthMode = getGoogleOAuthMode();
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
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['email']    = $user['email'];
        clearGoogleOAuthSession();
        header('Location: ' . getDashboardUrl($user['role']));
        exit;
    }

    // No account for this Google email
    if ($oauthMode === 'login') {
        setFlash(
            'danger',
            'No account found for this Google email. Please create an account first using Sign up with Google on the registration page.'
        );
        clearGoogleOAuthSession();
        header('Location: /table-tennis-system/register.php');
        exit;
    }

    // Register mode — create new player account
    $firstName = trim($profile['given_name'] ?? '');
    $lastName = trim($profile['family_name'] ?? '');
    if ($firstName === '' && $lastName === '') {
        $parts = explode(' ', trim($profile['name'] ?? 'Google User'), 2);
        $firstName = $parts[0] ?? 'Google';
        $lastName = $parts[1] ?? 'User';
    }

    $pdo->beginTransaction();

    $generatedUsername = generateUniqueUsername($firstName, $lastName);
    $randomPwHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, 'player', 1)"
    );
    $stmt->execute([$generatedUsername, $randomPwHash, $email]);
    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO players (user_id, first_name, last_name, gender, points, wins, losses) VALUES (?, ?, ?, 'male', 0, 0, 0)"
    );
    $stmt->execute([$userId, $firstName, $lastName]);

    $playerId = (int) $pdo->lastInsertId();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO rankings (player_id, total_points, tournaments_played, matches_played, matches_won, matches_lost, win_rate)
             VALUES (?, 0, 0, 0, 0, 0, 0.00)"
        );
        $stmt->execute([$playerId]);
    } catch (PDOException $rankEx) {
        // rankings table optional on some installs
    }

    $pdo->commit();

    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $generatedUsername;
    $_SESSION['role']     = 'player';
    $_SESSION['email']    = $email;

    clearGoogleOAuthSession();
    setFlash('success', 'Welcome! Your account was created with Google. You can sign in with Google next time.');
    header('Location: ' . getDashboardUrl('player'));
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
