<?php
/**
 * Password reset (forgot password)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/mailer.php';

function appBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/TournamentHQ';
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

/**
 * Generate a random readable temporary password
 */
function generateTemporaryPassword(int $length = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

function sendPasswordResetEmail(string $toEmail, string $username, string $tempPassword): bool {
    $n8n = load_n8n_config();
    if (!$n8n) {
        return false;
    }

    $payload = json_encode([
        'email'      => $toEmail,
        'username'   => $username,
        'temp_password' => $tempPassword,
        'action'     => 'password_reset',
    ]);

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if (!empty($n8n['secret'])) {
        $headers[] = 'X-Webhook-Secret: ' . $n8n['secret'];
    }

    $ch = curl_init($n8n['webhook_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($resp !== false && $code >= 200 && $code < 300);
}

/**
 * Request reset. Automatically updates user's password to a temporary one and sends it.
 */
function requestPasswordReset(string $emailOrUsername): array {
    $generic = 'If an account exists for that email or username, a temporary password has been sent to the registered email.';

    $user = findUserForPasswordReset($emailOrUsername);
    if (!$user) {
        return ['ok' => true, 'message' => $generic, 'dev_link' => null];
    }

    // Generate a temporary password
    $tempPassword = generateTemporaryPassword();
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Save temporary password directly to the database
    $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, (int)$user['id']]);

    // Send the temporary password via n8n
    $mailed = sendPasswordResetEmail($user['email'], $user['username'], $tempPassword);

    $devLink = null;
    if (!$mailed && isLocalAppHost()) {
        $devLink = $tempPassword;
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
