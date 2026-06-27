<?php
/**
 * Public AJAX endpoint: returns bracket HTML for a tournament (read-only, no auth required)
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/tournaments/bracket_functions.php';

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
$recordResultUrl = ''; // Read-only — no record/edit buttons
$GLOBALS['swapModalRendered'] = true; // Prevent swap modal from rendering

$bracketIsTeamEvent = !empty($tournament['is_team_event']);
$bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
$bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
$showOnlyPhase = 'all';

include __DIR__ . '/bracket_view.php';
