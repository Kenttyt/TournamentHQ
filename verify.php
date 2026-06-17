<?php
require_once __DIR__ . '/includes/auth.php';

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$token = $_GET['token'] ?? '';

if (!$uid || !$token) {
    setFlash('error', 'Invalid verification link.');
    header('Location: /TournamentHQ/login.php');
    exit;
}

try {
    $stmt = db()->prepare('SELECT id, email, verification_token, token_expires, is_verified FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) {
        setFlash('error', 'User not found.');
        header('Location: /TournamentHQ/login.php');
        exit;
    }

    if ($user['is_verified']) {
        setFlash('success', 'Email already verified. You can log in.');
        header('Location: /TournamentHQ/login.php');
        exit;
    }

    if (empty($user['verification_token']) || !hash_equals($user['verification_token'], $token)) {
        setFlash('error', 'Invalid or expired verification token.');
        header('Location: /TournamentHQ/login.php');
        exit;
    }

    $now = new DateTime();
    if (!empty($user['token_expires']) && $now > new DateTime($user['token_expires'])) {
        setFlash('error', 'Verification link has expired.');
        header('Location: /TournamentHQ/login.php');
        exit;
    }

    // Mark verified
    db()->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires = NULL WHERE id = ?')->execute([$uid]);
    setFlash('success', 'Email successfully verified. You can now log in.');
    header('Location: /TournamentHQ/login.php');
    exit;
} catch (Exception $e) {
    setFlash('error', 'Verification failed: ' . $e->getMessage());
    header('Location: /TournamentHQ/login.php');
    exit;
}
