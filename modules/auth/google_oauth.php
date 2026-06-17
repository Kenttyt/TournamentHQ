<?php
/**
 * Google OAuth helpers
 */
require_once __DIR__ . '/../../config/google.php';

function isGoogleOAuthConfigured(): bool {
    if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET')) {
        return false;
    }
    $id = trim(GOOGLE_CLIENT_ID);
    $secret = trim(GOOGLE_CLIENT_SECRET);
    if ($id === '' || $secret === '') {
        return false;
    }
    if (str_contains($id, 'YOUR_CLIENT_ID') || str_contains($secret, 'YOUR_CLIENT_SECRET')) {
        return false;
    }
    return true;
}

function googleOAuthRedirectUri(): string {
    if (defined('GOOGLE_REDIRECT_URI') && GOOGLE_REDIRECT_URI !== '') {
        return GOOGLE_REDIRECT_URI;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/TournamentHQ'), '/\\');
    if (str_ends_with($base, '/modules/auth')) {
        $base = dirname(dirname($base));
    }
    return $scheme . '://' . $host . $base . '/google-callback.php';
}

function startGoogleOAuth(string $mode = 'login', string $role = ''): void {
    if (!isGoogleOAuthConfigured()) {
        setFlash('danger', 'Google sign-in is not configured. Add your Client ID and Secret in config/google.local.php (see config/google.local.php.example).');
        header('Location: ' . ($mode === 'register' ? '/TournamentHQ/register.php' : '/TournamentHQ/index.php'));
        exit;
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_mode'] = $mode;
    $_SESSION['google_oauth_role'] = $role;

    $params = [
        'response_type' => 'code',
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => googleOAuthRedirectUri(),
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

function googleHttpPost(string $url, array $data): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL is required for Google sign-in.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Google request failed: ' . $err);
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid response from Google.');
    }
    if ($code >= 400) {
        $msg = $json['error_description'] ?? $json['error'] ?? 'Google OAuth error';
        throw new RuntimeException((string) $msg);
    }
    return $json;
}

function googleHttpGet(string $url, array $headers = []): array {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL is required for Google sign-in.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('Google request failed: ' . $err);
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid response from Google.');
    }
    if ($code >= 400) {
        throw new RuntimeException('Failed to load Google profile.');
    }
    return $json;
}

function exchangeGoogleAuthCode(string $code): array {
    return googleHttpPost('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => googleOAuthRedirectUri(),
        'grant_type'    => 'authorization_code',
    ]);
}

function fetchGoogleUserProfile(string $accessToken): array {
    return googleHttpGet(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );
}

function validateGoogleOAuthState(?string $state): bool {
    $expected = $_SESSION['google_oauth_state'] ?? '';
    unset($_SESSION['google_oauth_state']);
    return $expected !== '' && $state !== null && hash_equals($expected, $state);
}

function getGoogleOAuthMode(): string {
    $mode = $_SESSION['google_oauth_mode'] ?? 'login';
    return $mode === 'register' ? 'register' : 'login';
}

function getGoogleOAuthRole(): string {
    $role = $_SESSION['google_oauth_role'] ?? '';
    return $role === 'organizer' ? 'organizer' : '';
}

function googleOAuthReturnUrl(?string $mode = null): string {
    $mode = $mode ?? getGoogleOAuthMode();
    return $mode === 'register' ? '/TournamentHQ/register.php' : '/TournamentHQ/index.php';
}

function clearGoogleOAuthSession(): void {
    unset($_SESSION['google_oauth_mode'], $_SESSION['google_oauth_state'], $_SESSION['google_oauth_role']);
}
