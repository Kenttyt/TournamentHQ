<?php
/**
 * Authentication Helper
 * Table Tennis Tournament Management System
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
        'email'    => $_SESSION['email'],
    ];
}

/**
 * Require user to be logged in; redirect to login if not
 */
function requireLogin(string $redirectTo = '/table-tennis-system/index.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Require a specific role; redirect to dashboard if insufficient
 */
function requireRole($roles) {
    requireLogin();
    $current = $_SESSION['role'] ?? '';
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($current, $allowed, true)) {
        $dashboard = getDashboardUrl($current);
        header("Location: $dashboard");
        exit;
    }
}

/**
 * Get dashboard URL by role
 */
function getDashboardUrl($role) {
    switch ($role) {
        case 'admin':
            return '/table-tennis-system/admin/index.php';
        case 'organizer':
            return '/table-tennis-system/organizer/index.php';
        case 'player':
            return '/table-tennis-system/player/index.php';
        default:
            return '/table-tennis-system/index.php';
    }
}

/**
 * Login a user by credentials
 */
function loginUser(string $username, string $password): array {
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['email']    = $user['email'];
            return ['success' => true, 'role' => $user['role']];
        }
        return ['success' => false, 'message' => 'Invalid username or password.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error. Please try again.'];
    }
}

/**
 * Logout current user
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: /table-tennis-system/index.php");
    exit;
}

/**
 * Sanitize output for HTML display
 */
function e($val) {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Flash message helpers
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
