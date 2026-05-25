<?php
/**
 * Payment proof uploads for tournament registration
 */
require_once __DIR__ . '/../../config/database.php';

function tournamentRequiresPaymentProof(?array $tournament): bool {
    if (!$tournament) {
        return false;
    }
    $fee = strtolower(trim($tournament['registration_fee'] ?? ''));
    if ($fee === '' || $fee === 'free' || $fee === '0' || $fee === '₱0' || $fee === 'php 0') {
        return false;
    }
    return true;
}

function paymentProofUploadDir(): string {
    return dirname(__DIR__, 2) . '/uploads/payment_proofs';
}

function savePaymentProofUpload(int $tournamentId, int $submitterPlayerId, array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Please upload a payment proof image or file.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 5 MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Allowed formats: JPG, PNG, WebP, or PDF.'];
    }

    $dir = paymentProofUploadDir() . '/t' . $tournamentId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create upload folder.'];
    }

    $ext = $allowed[$mime];
    $basename = 'p' . $submitterPlayerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $fullPath = $dir . '/' . $basename;
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['ok' => false, 'error' => 'Could not save uploaded file.'];
    }

    $relative = 't' . $tournamentId . '/' . $basename;
    $original = trim($file['name'] ?? 'payment-proof.' . $ext);
    if (strlen($original) > 200) {
        $original = substr($original, 0, 200);
    }

    return [
        'ok' => true,
        'path' => $relative,
        'original_name' => $original,
        'mime' => $mime,
    ];
}

function deletePaymentProofFile(?string $relativePath): void {
    if ($relativePath === null || $relativePath === '') {
        return;
    }
    $relativePath = str_replace(['\\', '..'], ['/', ''], $relativePath);
    $full = paymentProofUploadDir() . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function paymentProofPublicUrl(?string $relativePath): ?string {
    if ($relativePath === null || $relativePath === '') {
        return null;
    }
    return '/table-tennis-system/payment-proof.php?f=' . rawurlencode($relativePath);
}

function userCanViewPaymentProof(int $userId, string $role, string $relativePath): bool {
    $relativePath = str_replace(['\\', '..'], ['/', ''], $relativePath);
    $stmt = db()->prepare(
        "SELECT tg.tournament_id, tg.registered_by_player_id, t.organizer_id
         FROM tournament_guests tg
         JOIN tournaments t ON t.id = tg.tournament_id
         WHERE tg.payment_proof_path = ?
         LIMIT 1"
    );
    $stmt->execute([$relativePath]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if ($role === 'admin') {
        return true;
    }
    if ($role === 'organizer' && (int) $row['organizer_id'] === $userId) {
        return true;
    }
    if ($role === 'player') {
        require_once __DIR__ . '/../players/player_functions.php';
        $player = getPlayerByUserId($userId);
        return $player && (int) $row['registered_by_player_id'] === (int) $player['id'];
    }
    return false;
}
