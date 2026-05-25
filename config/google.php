<?php
/**
 * Google OAuth Configuration
 * Copy config/google.local.php.example to config/google.local.php and add your credentials.
 * https://console.cloud.google.com/ → APIs & Services → Credentials → OAuth 2.0 Client ID
 */

$googleLocal = __DIR__ . '/google.local.php';
if (is_readable($googleLocal)) {
    require_once $googleLocal;
} elseif (is_readable(__DIR__ . '/google.local.php.example')) {
    // Fallback if only the example file was edited (rename to google.local.php when possible)
    require_once __DIR__ . '/google.local.php.example';
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: '');
}
