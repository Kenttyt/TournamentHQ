<?php
/**
 * Umpire Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
if ($user['role'] !== 'umpire' && $user['role'] !== 'admin') {
    header('Location: ' . getDashboardUrl($user['role']));
    exit;
}

$pdo = db();
$userId = (int)$_SESSION['user_id'];

// Get the tournament linked to this umpire
$stmtUser = $pdo->prepare("SELECT tournament_id, username FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$uData = $stmtUser->fetch();
$tournamentId = $uData ? (int)$uData['tournament_id'] : 0;

if ($user['role'] === 'admin') {
    $tournamentId = (int)($_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0);
    if (!$tournamentId) {
        $tournamentId = (int)$pdo->query("SELECT id FROM tournaments LIMIT 1")->fetchColumn();
    }
}

if (!$tournamentId) {
    die("No tournament linked to this umpire account.");
}

$stmtTourney = $pdo->prepare("SELECT * FROM tournaments WHERE id = ? LIMIT 1");
$stmtTourney->execute([$tournamentId]);
$tournament = $stmtTourney->fetch();
if (!$tournament) {
    die("Tournament not found.");
}

require_once __DIR__ . '/../modules/matches/match_functions.php';

// Handle recording scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'result') {
    $matchId = (int) ($_POST['match_id'] ?? 0);
    $winnerKey = trim($_POST['winner_key'] ?? '');
    if ($matchId && $winnerKey) {
        $setScores = !empty($_POST['set_scores']) ? trim($_POST['set_scores']) : null;
        recordBracketMatchResult($matchId, $winnerKey, (int) ($_POST['player1_score'] ?? 0), (int) ($_POST['player2_score'] ?? 0), $setScores);
        setFlash('success', 'Match result saved successfully.');
    }
    header('Location: /TournamentHQ/umpire/dashboard');
    exit;
}

$matches = getTournamentMatches($tournamentId);
$flash = getFlash();
$formAction = '/TournamentHQ/umpire/dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umpire Dashboard | TournamentHQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="/TournamentHQ/assets/css/style.css">
    <style>
        :root {
            --primary: #6c63ff;
            --primary-light: #8b85ff;
            --accent: #00d4aa;
            --bg-900: #0d0e1a;
            --bg-800: #12131f;
            --bg-700: #1a1b2e;
            --border: rgba(255,255,255,0.07);
            --text-100: #f0f2ff;
            --text-200: #c5c8e8;
            --text-300: #9094c0;
            --text-400: #6065a0;
            --radius-md: 14px;
            --radius-sm: 8px;
            --radius-lg: 20px;
        }
        body {
            background-color: var(--bg-900);
            color: var(--text-100);
            margin: 0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .umpire-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f111a;
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .umpire-title-block {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .tournament-name {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }
        .umpire-badge-block {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-300);
        }
        .umpire-badge {
            background: rgba(0, 212, 170, 0.12);
            border: 1px solid rgba(0, 212, 170, 0.25);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--accent);
            font-weight: 700;
            font-family: monospace;
        }
        .logout-btn-mob {
            color: #ff6b6b;
            border: 1px solid rgba(255,107,107,0.3);
            background: none;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .logout-btn-mob:hover {
            background: rgba(255,107,107,0.1);
        }
        .dashboard-container {
            padding: 16px;
            flex: 1;
            max-width: 600px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        .match-card {
            background: var(--bg-800);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .match-meta-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }
        .match-round-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary-light);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .match-status-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        .badge-scheduled {
            background: rgba(255,165,0,0.12);
            border: 1px solid rgba(255,165,0,0.3);
            color: #ffb74d;
        }
        .badge-ongoing {
            background: rgba(108,99,255,0.12);
            border: 1px solid rgba(108,99,255,0.3);
            color: var(--primary-light);
        }
        .badge-completed {
            background: rgba(0,212,170,0.12);
            border: 1px solid rgba(0,212,170,0.3);
            color: var(--accent);
        }
        .match-row-mob {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
        }
        .entrant-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-200);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .entrant-winner {
            color: #fff;
            font-weight: 700;
        }
        .entrant-winner-dot {
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            display: inline-block;
        }
        .entrant-score {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-100);
            font-family: 'Outfit', sans-serif;
            background: var(--bg-700);
            border: 1px solid var(--border);
            padding: 2px 8px;
            border-radius: 4px;
            min-width: 24px;
            text-align: center;
        }
        .entrant-score-winner {
            color: var(--accent);
            border-color: rgba(0,212,170,0.3);
            background: rgba(0,212,170,0.05);
        }
        .sets-display {
            font-size: 11px;
            color: var(--text-400);
            background: var(--bg-700);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            text-align: center;
        }
    </style>
</head>
<body>

<header class="umpire-header">
    <div class="umpire-title-block">
        <div class="tournament-name"><?= e($tournament['name']) ?></div>
        <div class="umpire-badge-block">
            <span>Umpire:</span>
            <span class="umpire-badge"><?= e($user['username']) ?></span>
        </div>
    </div>
    <a href="/TournamentHQ/includes/logout.php" class="logout-btn-mob" onclick="return confirm('Are you sure you want to log out?');">
        <i data-lucide="log-out" style="width:14px; height:14px;"></i>
        <span>Logout</span>
    </a>
</header>

<div class="dashboard-container">
    <?php if ($flash): ?>
    <div class="flash-message flash-<?= e($flash['type']) ?>" id="flashMessage" style="margin-bottom: 16px;">
        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
        <?= e($flash['message']) ?>
        <button onclick="document.getElementById('flashMessage').remove()" class="flash-close">×</button>
    </div>
    <?php endif; ?>

    <h2 style="font-family: 'Outfit', sans-serif; font-size: 18px; font-weight: 700; margin: 0 0 16px 0;">Tournament Matches</h2>

    <?php if (empty($matches)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏆</div>
            <h3>No matches scheduled</h3>
            <p>Matches will appear here once the bracket has been generated.</p>
        </div>
    <?php else: ?>
        <?php foreach ($matches as $m): 
            $p1Name = trim(($m['p1_first'] ?? '') . ' ' . ($m['p1_last'] ?? ''));
            $p2Name = trim(($m['p2_first'] ?? '') . ' ' . ($m['p2_last'] ?? ''));
            
            // Skip bye matches where both are empty
            if (empty($p1Name) && empty($p2Name)) {
                continue;
            }
            if (empty($p1Name)) { $p1Name = 'TBD'; }
            if (empty($p2Name)) { $p2Name = 'TBD'; }
            
            $p1Winner = matchWinnerIsSlot($m, 1);
            $p2Winner = matchWinnerIsSlot($m, 2);
            
            $isCompleted = $m['status'] === 'completed';
            $isActive = !$isCompleted && $p1Name !== 'TBD' && $p2Name !== 'TBD';
        ?>
        <div class="match-card">
            <div class="match-meta-line">
                <span class="match-round-label"><?= e($m['round_name']) ?><?= $m['table_number'] ? ' · Table ' . $m['table_number'] : '' ?></span>
                <span class="match-status-badge badge-<?= e($m['status']) ?>"><?= e($m['status']) ?></span>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div class="match-row-mob">
                    <span class="entrant-name <?= $p1Winner ? 'entrant-winner' : '' ?>">
                        <?php if ($p1Winner): ?><span class="entrant-winner-dot"></span><?php endif; ?>
                        <?= e($p1Name) ?>
                    </span>
                    <span class="entrant-score <?= $p1Winner ? 'entrant-score-winner' : '' ?>"><?= (int)$m['player1_score'] ?></span>
                </div>
                <div class="match-row-mob">
                    <span class="entrant-name <?= $p2Winner ? 'entrant-winner' : '' ?>">
                        <?php if ($p2Winner): ?><span class="entrant-winner-dot"></span><?php endif; ?>
                        <?= e($p2Name) ?>
                    </span>
                    <span class="entrant-score <?= $p2Winner ? 'entrant-score-winner' : '' ?>"><?= (int)$m['player2_score'] ?></span>
                </div>
            </div>

            <?php if (!empty($m['set_scores'])): ?>
                <div class="sets-display">
                    Sets: <?= e($m['set_scores']) ?>
                </div>
            <?php endif; ?>

            <?php if ($isActive): ?>
                <button type="button" class="btn btn-primary btn-sm js-bracket-result-btn" style="width:100%; justify-content:center; height:36px; font-size:13px;"
                        data-match-id="<?= $m['id'] ?>"
                        data-p1-key="<?= !empty($m['player1_id']) ? 'player:' . $m['player1_id'] : 'guest:' . $m['player1_guest_id'] ?>"
                        data-p2-key="<?= !empty($m['player2_id']) ? 'player:' . $m['player2_id'] : 'guest:' . $m['player2_guest_id'] ?>"
                        data-p1-name="<?= e($p1Name) ?>"
                        data-p2-name="<?= e($p2Name) ?>"
                        data-winner-key="<?= matchWinnerKey($m) ?>"
                        data-p1-sets="<?= (int)$m['player1_score'] ?>"
                        data-p2-sets="<?= (int)$m['player2_score'] ?>"
                        data-set-scores="<?= e($m['set_scores']) ?>"
                        data-edit="<?= $isCompleted ? '1' : '0' ?>">
                    Record Score
                </button>
            <?php elseif ($isCompleted): ?>
                <button type="button" class="btn btn-outline btn-sm js-bracket-result-btn" style="width:100%; justify-content:center; height:36px; font-size:13px;"
                        data-match-id="<?= $m['id'] ?>"
                        data-p1-key="<?= !empty($m['player1_id']) ? 'player:' . $m['player1_id'] : 'guest:' . $m['player1_guest_id'] ?>"
                        data-p2-key="<?= !empty($m['player2_id']) ? 'player:' . $m['player2_id'] : 'guest:' . $m['player2_guest_id'] ?>"
                        data-p1-name="<?= e($p1Name) ?>"
                        data-p2-name="<?= e($p2Name) ?>"
                        data-winner-key="<?= matchWinnerKey($m) ?>"
                        data-p1-sets="<?= (int)$m['player1_score'] ?>"
                        data-p2-sets="<?= (int)$m['player2_score'] ?>"
                        data-set-scores="<?= e($m['set_scores']) ?>"
                        data-edit="1">
                    Edit Result
                </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Record result modal -->
<div class="modal-overlay" id="bracketResultModal">
    <div class="modal" style="max-width: <?= !empty($tournament['is_team_event']) ? '640px' : '420px' ?>;">
        <div class="modal-header">
            <div class="modal-title" id="bracketModalTitle">Record Match Result</div>
            <button type="button" class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= e($formAction) ?>">
            <input type="hidden" name="action" value="result">
            <input type="hidden" name="tournament_id" value="<?= $tournamentId ?>">
            <input type="hidden" name="match_id" id="bracketMatchId">
            <input type="hidden" name="winner_key" id="bracketWinnerKey">
            <div class="modal-body">
                <p id="bracketMatchLabel" style="font-weight: 600; margin-bottom: 16px;"></p>
                <div class="form-group">
                    <label class="form-label" id="bracketWinnerLabel"><?= !empty($tournament['is_team_event']) ? 'Winning Team' : 'Winner' ?></label>
                    <select id="bracketWinnerSelect" class="form-select" required>
                        <option value="">— Select winner —</option>
                    </select>
                </div>
                <input type="hidden" name="player1_score" id="bracketP1Score" value="0">
                <input type="hidden" name="player2_score" id="bracketP2Score" value="0">
                <div class="form-group" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <?php if (!empty($tournament['is_team_event'])): ?>
                    <label class="form-label" style="font-weight: 600; margin-bottom: 8px;">Game Results</label>
                    <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 4px; padding: 0 2px;">
                        <span></span>
                        <span id="bracketSetP1Header" style="font-size: 11px; font-weight: 700; color: var(--accent); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.3px;">Team 1</span>
                        <span id="bracketSetP2Header" style="font-size: 11px; font-weight: 700; color: var(--accent); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.3px;">Team 2</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="setScoresContainer">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <div style="background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.06); border-radius: var(--radius-sm); padding: 10px 12px;" class="game-row">
                                <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 6px;">
                                    <span style="font-size: 12px; font-weight: 700; color: var(--accent); letter-spacing: 0.3px;">Game <?= $s ?></span>
                                    <input type="text" class="form-control js-game-p1name" data-set="<?= $s ?>" placeholder="Type Player Name" style="padding: 5px 8px; font-size: 11px; height: 32px;">
                                    <input type="text" class="form-control js-game-p2name" data-set="<?= $s ?>" placeholder="Type Player Name" style="padding: 5px 8px; font-size: 11px; height: 32px;">
                                </div>
                                <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center;">
                                    <span></span>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <input type="number" class="form-control js-set-p1" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 6px; font-size: 15px; font-weight: 700; text-align: center; height: 36px; width: 60px; flex-shrink: 0;">
                                        <span style="font-size: 11px; color: var(--text-400); white-space: nowrap;">sets</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <input type="number" class="form-control js-set-p2" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 6px; font-size: 15px; font-weight: 700; text-align: center; height: 36px; width: 60px; flex-shrink: 0;">
                                        <span style="font-size: 11px; color: var(--text-400); white-space: nowrap;">sets</span>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php else: ?>
                    <label class="form-label" style="font-weight: 600; margin-bottom: 8px;">Set Scores (Points)</label>
                    <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 12px; align-items: center; margin-bottom: 8px; text-align: center;">
                        <span></span>
                        <span id="bracketSetP1Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;">Player 1</span>
                        <span id="bracketSetP2Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;">Player 2</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="setScoresContainer">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 12px; align-items: center;">
                                <span style="font-size: 11px; font-weight: 600; color: var(--text-300);">Set <?= $s ?></span>
                                <input type="number" class="form-control js-set-p1" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 8px; font-size: 12px;">
                                <input type="number" class="form-control js-set-p2" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 8px; font-size: 12px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" name="set_scores" id="bracketSetScoresInput">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="bracketSaveBtn">Save Result</button>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => { 
    lucide.createIcons(); 
    
    // Modal close hooks
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('bracketResultModal').classList.remove('open');
        });
    });
});

window.BRACKET_IS_TEAM_EVENT = <?= !empty($tournament['is_team_event']) ? 'true' : 'false' ?>;
window._bracketTeam1 = '';
window._bracketTeam2 = '';

function calculateSetsFromSetInputs() {
    let p1Games = 0;
    let p2Games = 0;
    const setScores = [];

    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        if (!p1Input || !p2Input) continue;

        if (window.BRACKET_IS_TEAM_EVENT) {
            const gameBlock = p1Input.closest('.game-row');
            const gameType = gameBlock ? (gameBlock.querySelector('.js-game-type')?.value || 'Singles') : 'Singles';
            const p1NameInput = document.querySelector(`.js-game-p1name[data-set="${s}"]`);
            const p2NameInput = document.querySelector(`.js-game-p2name[data-set="${s}"]`);
            const p1name = p1NameInput ? p1NameInput.value.trim() : '';
            const p2name = p2NameInput ? p2NameInput.value.trim() : '';
            const p1Val = p1Input.value;
            const p2Val = p2Input.value;

            if (p1Val !== '' && p2Val !== '') {
                const p1 = parseInt(p1Val, 10);
                const p2 = parseInt(p2Val, 10);
                setScores.push(gameType + '|' + p1name + '|' + p2name + '|' + p1 + '-' + p2);
                if (p1 > p2) p1Games++;
                else if (p2 > p1) p2Games++;
            } else if (p1name || p2name) {
                setScores.push(gameType + '|' + p1name + '|' + p2name + '|');
            }
        } else {
            if (p1Games >= 3 || p2Games >= 3) {
                p1Input.disabled = true;
                p2Input.disabled = true;
                p1Input.value = '';
                p2Input.value = '';
                continue;
            } else {
                p1Input.disabled = false;
                p2Input.disabled = false;
            }

            const p1Val = p1Input.value;
            const p2Val = p2Input.value;

            if (p1Val !== '' && p2Val !== '') {
                const p1 = parseInt(p1Val, 10);
                const p2 = parseInt(p2Val, 10);
                setScores.push(p1 + '-' + p2);
                if (p1 > p2) p1Games++;
                else if (p2 > p1) p2Games++;
            }
        }
    }

    document.getElementById('bracketP1Score').value = p1Games;
    document.getElementById('bracketP2Score').value = p2Games;

    const sel = document.getElementById('bracketWinnerSelect');
    if (sel && sel.options.length > 2) {
        const p1Key = sel.options[1].value;
        const p2Key = sel.options[2].value;
        const currentWinner = sel.value;
        
        if (!currentWinner || currentWinner === p1Key || currentWinner === p2Key) {
            if (p1Games > p2Games) {
                sel.value = p1Key;
                document.getElementById('bracketWinnerKey').value = p1Key;
            } else if (p2Games > p1Games) {
                sel.value = p2Key;
                document.getElementById('bracketWinnerKey').value = p2Key;
            }
        }
    }

    document.getElementById('bracketSetScoresInput').value = setScores.join(',');

    if (window.BRACKET_IS_TEAM_EVENT) {
        const p1hdr = document.getElementById('bracketSetP1Header');
        const p2hdr = document.getElementById('bracketSetP2Header');
        if (p1hdr && window._bracketTeam1) p1hdr.textContent = window._bracketTeam1;
        if (p2hdr && window._bracketTeam2) p2hdr.textContent = window._bracketTeam2;
    }
}

document.querySelectorAll('.js-set-p1, .js-set-p2, .js-game-p1name, .js-game-p2name').forEach(function(input) {
    input.addEventListener('input', calculateSetsFromSetInputs);
});

function openBracketResultModal(btn) {
    const isEdit = btn.getAttribute('data-edit') === '1';
    const p1Key = btn.getAttribute('data-p1-key');
    const p2Key = btn.getAttribute('data-p2-key');
    const p1Name = btn.getAttribute('data-p1-name');
    const p2Name = btn.getAttribute('data-p2-name');
    const winnerKey = btn.getAttribute('data-winner-key') || '';

    document.getElementById('bracketModalTitle').textContent = isEdit ? 'Edit Match Result' : 'Record Match Result';
    document.getElementById('bracketSaveBtn').textContent = isEdit ? 'Update Result' : 'Save Result';
    document.getElementById('bracketMatchId').value = btn.getAttribute('data-match-id');

    const p1DisplayName = p1Name;
    const p2DisplayName = p2Name;

    if (window.BRACKET_IS_TEAM_EVENT) {
        document.getElementById('bracketMatchLabel').textContent = p1DisplayName + ' vs ' + p2DisplayName;
    } else {
        document.getElementById('bracketMatchLabel').textContent = p1Name + ' vs ' + p2Name;
    }

    window._bracketTeam1 = p1DisplayName;
    window._bracketTeam2 = p2DisplayName;

    const p1hdr = document.getElementById('bracketSetP1Header');
    const p2hdr = document.getElementById('bracketSetP2Header');
    if (p1hdr) p1hdr.textContent = p1DisplayName;
    if (p2hdr) p2hdr.textContent = p2DisplayName;

    const form = document.querySelector('#bracketResultModal form');
    const p1ScoreInput = document.getElementById('bracketP1Score');
    const p2ScoreInput = document.getElementById('bracketP2Score');
    if (p1ScoreInput) p1ScoreInput.value = btn.getAttribute('data-p1-sets') || '0';
    if (p2ScoreInput) p2ScoreInput.value = btn.getAttribute('data-p2-sets') || '0';

    const setScoresRaw = btn.getAttribute('data-set-scores') || '';
    document.getElementById('bracketSetScoresInput').value = setScoresRaw;
    const setsArray = setScoresRaw ? setScoresRaw.split(',') : [];

    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        if (!p1Input || !p2Input) continue;

        if (window.BRACKET_IS_TEAM_EVENT) {
            const p1NameInput = document.querySelector(`.js-game-p1name[data-set="${s}"]`);
            const p2NameInput = document.querySelector(`.js-game-p2name[data-set="${s}"]`);
            if (setsArray[s - 1]) {
                const parts = setsArray[s - 1].split('|');
                if (p1NameInput) p1NameInput.value = parts[1] || '';
                if (p2NameInput) p2NameInput.value = parts[2] || '';
                const scores = (parts[3] || '').split('-');
                p1Input.value = scores[0] || '';
                p2Input.value = scores[1] || '';
            } else {
                if (p1NameInput) p1NameInput.value = '';
                if (p2NameInput) p2NameInput.value = '';
                p1Input.value = '';
                p2Input.value = '';
            }
        } else {
            if (setsArray[s - 1]) {
                const parts = setsArray[s - 1].split('-');
                p1Input.value = parts[0] || '';
                p2Input.value = parts[1] || '';
            } else {
                p1Input.value = '';
                p2Input.value = '';
            }
        }
    }

    const sel = document.getElementById('bracketWinnerSelect');
    if (sel) {
        sel.innerHTML = '<option value="">— Select winner —</option>'
            + '<option value="' + p1Key + '">' + p1DisplayName + '</option>'
            + '<option value="' + p2Key + '">' + p2DisplayName + '</option>';
        sel.value = winnerKey;
        document.getElementById('bracketWinnerKey').value = winnerKey;
        sel.onchange = function () {
            document.getElementById('bracketWinnerKey').value = sel.value;
        };
    }
    calculateSetsFromSetInputs();

    if (winnerKey) {
        sel.value = winnerKey;
        document.getElementById('bracketWinnerKey').value = winnerKey;
    }

    document.getElementById('bracketResultModal').classList.add('open');
}

document.querySelectorAll('.js-bracket-result-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openBracketResultModal(btn);
    });
});

document.querySelector('#bracketResultModal form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const sel = document.getElementById('bracketWinnerSelect');
    document.getElementById('bracketWinnerKey').value = sel.value;
    if (!sel.value) {
        alert('Please select the winner.');
        return;
    }
    e.target.submit();
});
</script>
</body>
</html>
