<?php
/**
 * AJAX endpoint: returns bracket HTML for a tournament
 * Used by WebSocket client JS to refresh bracket after score updates
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/tournaments/bracket_functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$tid = (int) ($_GET['tournament_id'] ?? 0);
if (!$tid) {
    http_response_code(400);
    exit('Missing tournament_id');
}

$tournament = getTournamentById($tid);
if (!$tournament) {
    http_response_code(404);
    exit('Tournament not found');
}

$bracketGroups = buildBracketGroups($tid);
$recordResultUrl = $_GET['record_result_url'] ?? '';

$bracketIsTeamEvent = !empty($tournament['is_team_event']);
$bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
$bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
$phase = $_GET['phase'] ?? 'all';
$showOnlyPhase = in_array($phase, ['all', 'group', 'knockout']) ? $phase : 'all';

include __DIR__ . '/bracket_view.php';
