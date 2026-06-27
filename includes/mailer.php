<?php
/**
 * Mailer — n8n / Gmail
 * Table Tennis Tournament Management System
 *
 * Sends verification emails via an n8n webhook (Gmail node).
 * Configure the webhook URL in config/n8n.local.php
 */

/**
 * Load the n8n configuration from config/n8n.local.php
 */
function load_n8n_config(): ?array {
    $path = __DIR__ . '/../config/n8n.local.php';
    if (file_exists($path)) {
        $cfg = include $path;
        if (is_array($cfg) && !empty($cfg['webhook_url'])) {
            return $cfg;
        }
    }
    return null;
}

/**
 * Send the account verification email via n8n webhook.
 *
 * @param string $email     Recipient email address
 * @param string $username  Recipient username
 * @param int    $userId    User ID (used to build the verify link)
 * @param string $token     Verification token
 * @return array ['success' => bool, ...]
 */
function send_verification_email(string $email, string $username, int $userId, string $token): array {
    $scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $verifyUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('/verify.php') . '?uid=' . $userId . '&token=' . $token;

    $n8n = load_n8n_config();
    if (!$n8n) {
        return ['success' => false, 'error' => 'n8n webhook not configured. Add your webhook URL to config/n8n.local.php'];
    }

    $payload = json_encode([
        'email'      => $email,
        'username'   => $username,
        'verify_url' => $verifyUrl,
        'action'     => 'email_verification',
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
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['success' => false, 'error' => 'Webhook request failed: ' . $err];
    }

    if ($code >= 200 && $code < 300) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => "n8n returned HTTP $code", 'body' => $resp];
}
