<?php
/**
 * Admin — Auto Bracket Generator
 */
$pageTitle = 'Auto Bracket Generator';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/tournaments/bracket_functions.php';

$formAction = '/table-tennis-system/admin/bracket_generator.php';
$tid = (int) ($_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $postTid = (int) ($_POST['tournament_id'] ?? 0);

    if ($action === 'generate' && $postTid) {
        $rawSize = $_POST['group_size'] ?? 4;
        $groupSize = ($rawSize === 'all') ? 0 : normalizeGroupSize((int) $rawSize);
        $result = generateTournamentBracket($postTid, !empty($_POST['shuffle']), true, $groupSize);
        $sizeParam = ($rawSize === 'all') ? 'all' : $groupSize;
        if ($result['ok']) {
            $msg = 'Bracket generated: ' . $result['matches'] . ' match(es) across '
                . $result['groups'] . ' group(s) (' . $result['group_size'] . ' players per group).';
            if (!empty($result['merged'])) {
                $parts = array_map(fn($m) => $m['player'] . ' → ' . $m['group'], $result['merged']);
                $msg .= ' Extra player(s) placed randomly: ' . implode('; ', $parts) . '.';
            }
            setFlash('success', $msg);
            header('Location: bracket_generator.php?tournament_id=' . $postTid . '&group_size=' . $sizeParam);
            exit;
        } else {
            setFlash('danger', $result['message'] ?? 'Could not generate bracket.');
        }
        header('Location: bracket_generator.php?tournament_id=' . $postTid . '&group_size=' . $sizeParam);
        exit;
    }

    if ($action === 'generate_knockout' && $postTid) {
        $knockoutFormat = trim($_POST['knockout_format'] ?? 'single_elimination');
        $result = generateKnockoutStage($postTid, $knockoutFormat);
        if ($result['ok']) {
            $formatLabel = $knockoutFormat === 'single_elimination' ? 'Single Elimination' : 'Double Elimination';
            setFlash('success', 'Group stage finished! Generated ' . $formatLabel . ' knockout stage with ' . $result['matches'] . ' match(es) for ' . $result['round_name'] . '.');
        } else {
            setFlash('danger', $result['message'] ?? 'Could not generate knockout stage.');
        }
        header('Location: bracket_generator.php?tournament_id=' . $postTid);
        exit;
    }

    if ($action === 'swap_slots' && $postTid) {
        $match1Id = (int) ($_POST['match1_id'] ?? 0);
        $slot1 = (int) ($_POST['slot1'] ?? 1);
        $match2Id = (int) ($_POST['match2_id'] ?? 0);
        $slot2 = (int) ($_POST['slot2'] ?? 1);

        if ($match1Id && $match2Id) {
            swapBracketParticipants($postTid, $match1Id, $slot1, $match2Id, $slot2);
            setFlash('success', 'Bracket slots swapped successfully to balance the bracket!');
        }
        header('Location: bracket_generator.php?tournament_id=' . $postTid);
        exit;
    }

    if ($action === 'result' && $postTid) {
        $matchId = (int) ($_POST['match_id'] ?? 0);
        $winnerKey = trim($_POST['winner_key'] ?? '');
        if ($matchId && $winnerKey) {
            recordBracketMatchResult($matchId, $winnerKey, (int) ($_POST['player1_score'] ?? 0), (int) ($_POST['player2_score'] ?? 0));
            setFlash('success', 'Match result saved.');
        }
        header('Location: bracket_generator.php?tournament_id=' . $postTid);
        exit;
    }
}

$tournaments = getAllTournaments('', '');
$tournament = $tid ? getTournamentById($tid) : null;
$entrants = $tid ? getTournamentEntrants($tid) : [];
$rawGS = $_GET['group_size'] ?? $_POST['group_size'] ?? 4;
$selectedGroupSize = ($rawGS === 'all') ? 'all' : normalizeGroupSize((int) $rawGS);
$bracketGroups = $tid ? buildBracketGroups($tid) : [];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/bracket_generator_body.php';
require_once __DIR__ . '/../includes/footer.php';
