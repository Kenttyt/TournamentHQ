<?php
header('Content-Type: text/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$tid = (int) ($_GET['tournament_id'] ?? 0);
if (!$tid) {
    http_response_code(400);
    echo json_encode(['error' => 'missing tournament_id']);
    exit;
}

$stmt = db()->prepare("SELECT UNIX_TIMESTAMP(MAX(updated_at)) AS ts FROM matches WHERE tournament_id = ?");
$stmt->execute([$tid]);
$ts = (float) ($stmt->fetchColumn() ?: 0);

echo json_encode(['timestamp' => $ts]);
