<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'organizer']);
require_once __DIR__ . '/../modules/notifications/notification_functions.php';

$userId = (int) $_SESSION['user_id'];
$role = 'organizer';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    markAllNotificationsRead($userId);
    setFlash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit;
}

$notifications = getUserNotifications($userId, $role);
$notificationCount = getNotificationBadgeCount($userId, $role);

require_once __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/notifications_body.php';
require_once __DIR__ . '/../includes/footer.php';
