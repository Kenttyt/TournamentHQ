<?php
/**
 * Password reset (forgot password)
 */
require_once __DIR__ . '/../../config/database.php';

function appBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/table-tennis-system';
}

function isLocalAppHost(): bool {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    return $host === 'localhost'
        || str_starts_with($host, '127.0.0.1')
        || str_starts_with($host, 'localhost:');
}

function findUserForPasswordReset(string $input): ?array {
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, username, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$input]);
    } else {
        $stmt = db()->prepare('SELECT id, username, email FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$input]);
    }
    $user = $stmt->fetch();
    return $user ?: null;
}

function createPasswordResetToken(int $userId): string {
    db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 3600);

    db()->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $hash, $expires]);

    return $token;
}

function buildPasswordResetUrl(string $token): string {
    return appBaseUrl() . '/reset-password.php?token=' . urlencode($token);
}

function sendPasswordResetEmail(string $toEmail, string $username, string $resetUrl): bool {
    $subject = 'Reset your password — TT Tournament Manager';
    $body = "Hello " . $username . ",\r\n\r\n"
        . "We received a request to reset your password.\r\n\r\n"
        . "Open this link (valid for 1 hour):\r\n"
        . $resetUrl . "\r\n\r\n"
        . "If you did not request this, you can ignore this email.\r\n\r\n"
        . "— TT Tournament Manager";

    $from = 'noreply@localhost';
    $headers = "From: TT Tournament Manager <{$from}>\r\n"
        . "Reply-To: {$from}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "X-Mailer: PHP/" . phpversion();

    return @mail($toEmail, $subject, $body, $headers);
}

/**
 * Request reset. Always returns generic success message (no account enumeration).
 */
function requestPasswordReset(string $emailOrUsername): array {
    $generic = 'If an account exists for that email or username, password reset instructions have been sent.';

    $user = findUserForPasswordReset($emailOrUsername);
    if (!$user) {
        return ['ok' => true, 'message' => $generic, 'dev_link' => null];
    }

    $token = createPasswordResetToken((int) $user['id']);
    $resetUrl = buildPasswordResetUrl($token);
    $mailed = sendPasswordResetEmail($user['email'], $user['username'], $resetUrl);

    $devLink = null;
    if (!$mailed && isLocalAppHost()) {
        $devLink = $resetUrl;
    }

    return ['ok' => true, 'message' => $generic, 'dev_link' => $devLink];
}

function getPasswordResetUserId(string $token): ?int {
    $token = trim($token);
    if ($token === '' || strlen($token) !== 64) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        "SELECT user_id FROM password_reset_tokens
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? (int) $row['user_id'] : null;
}

function completePasswordReset(string $token, string $newPassword): array {
    if (strlen($newPassword) < 6) {
        return ['ok' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    $userId = getPasswordResetUserId($token);
    if (!$userId) {
        return ['ok' => false, 'message' => 'This reset link is invalid or has expired. Request a new one.'];
    }

    $hash = hash('sha256', $token);
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$newHash, $userId]);
        $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?'
        )->execute([$hash]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Your password has been reset. You can sign in now.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => 'Could not update password. Please try again.'];
    }
}
