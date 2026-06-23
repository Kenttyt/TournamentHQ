<?php
/**
 * Mark notifications read (AJAX / form POST)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../modules/notifications/notification_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (!validateCsrfToken()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'mark_read') {
    $id = (int) ($_POST['notification_id'] ?? 0);
    if ($id > 0 && markNotificationRead($id, $userId)) {
        echo json_encode(['ok' => true]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if ($action === 'mark_all_read') {
    markAllNotificationsRead($userId);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['notification_id'] ?? 0);
    if ($id > 0 && deleteNotification($id, $userId)) {
        echo json_encode(['ok' => true]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Invalid action']);
