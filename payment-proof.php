<?php
/**
 * Serve payment proof files (organizer / submitter only)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/modules/uploads/payment_proof.php';

requireLogin();

$relative = $_GET['f'] ?? '';
$relative = str_replace(['\\', '..'], ['/', ''], $relative);
if ($relative === '' || !preg_match('#^t\d+/[a-zA-Z0-9._-]+$#', $relative)) {
    http_response_code(404);
    exit('Not found');
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
if (!userCanViewPaymentProof($userId, $role, $relative)) {
    http_response_code(403);
    exit('Access denied');
}

$fullPath = paymentProofUploadDir() . '/' . $relative;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Not found');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($fullPath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="payment-proof"');
header('Content-Length: ' . (string) filesize($fullPath));
readfile($fullPath);
exit;
