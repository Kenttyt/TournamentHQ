<?php
/**
 * Test Webhook Connection
 * Run this from the browser: http://localhost/TournamentHQ/test_n8n.php
 */
require_once __DIR__ . '/includes/mailer.php';

echo "<h2>Testing n8n Connection</h2>";

$n8n = load_n8n_config();
if (!$n8n) {
    echo "<p style='color:red;'>Error: n8n configuration not loaded! Make sure config/n8n.local.php exists and has webhook_url.</p>";
    exit;
}

echo "<p>Sending webhook test request to: <strong>" . htmlspecialchars($n8n['webhook_url']) . "</strong></p>";

$res = send_verification_email('sshesh430@gmail.com', 'TestUser', 9999, 'test_token_123456');

if ($res['success']) {
    echo "<p style='color:green; font-weight:bold;'>Success! n8n webhook accepted the request successfully.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>Failed to send webhook!</p>";
    echo "<pre>";
    print_r($res);
    echo "</pre>";
}
