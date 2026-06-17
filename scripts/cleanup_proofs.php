<?php
/**
 * CLI Cleanup Script — Delete payment proof files for completed tournaments
 * Run daily via Windows Task Scheduler
 * Usage: php c:\xampp\htdocs\TournamentHQ\scripts\cleanup_proofs.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';

$results = cleanupExpiredProofs();

$now = date('Y-m-d H:i:s');
if (empty($results)) {
    echo "[{$now}] No expired proofs to clean up.\n";
} else {
    foreach ($results as $r) {
        echo "[{$now}] Tournament #{$r['tournament_id']} \"{$r['tournament_name']}\": deleted {$r['files_deleted']} proof file(s).\n";
    }
}
