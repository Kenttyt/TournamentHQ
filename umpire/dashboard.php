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

// Get the tournament linked to this umpire as default (if umpire)
$stmtUser = $pdo->prepare("SELECT tournament_id FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$uData = $stmtUser->fetch();
$linkedTournamentId = $uData ? (int)$uData['tournament_id'] : 0;

$linkedTourneyName = '';
if ($linkedTournamentId) {
    $stmtT = $pdo->prepare("SELECT name FROM tournaments WHERE id = ?");
    $stmtT->execute([$linkedTournamentId]);
    $linkedTourneyName = $stmtT->fetchColumn() ?: '';
}
$baseName = preg_replace('/\s*\([^)]+\)$/', '', $linkedTourneyName);

// Retrieve tournaments (filtered by base name if umpire, global if admin)
if ($user['role'] === 'umpire') {
    if ($baseName !== '') {
        $stmtAll = $pdo->prepare("SELECT id, name, sport, category, format, status FROM tournaments WHERE status IN ('upcoming','ongoing') AND name LIKE ? ORDER BY name");
        $stmtAll->execute([$baseName . '%']);
        $allTournaments = $stmtAll->fetchAll();
    } else {
        $allTournaments = [];
    }
} else {
    $stmtAll = $pdo->query("SELECT id, name, sport, category, format, status FROM tournaments WHERE status IN ('upcoming','ongoing') ORDER BY name");
    $allTournaments = $stmtAll->fetchAll();
}

$tid = (int)($_GET['tournament_id'] ?? $_POST['tournament_id'] ?? 0);
if (!$tid) {
    if ($linkedTournamentId) {
        $tid = $linkedTournamentId;
    } elseif (!empty($allTournaments)) {
        $tid = (int)$allTournaments[0]['id'];
    }
}

// Ensure the requested tournament ID is allowed for this umpire
if ($user['role'] === 'umpire' && $tid) {
    $allowed = false;
    foreach ($allTournaments as $tItem) {
        if ((int)$tItem['id'] === $tid) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        $tid = $linkedTournamentId ? $linkedTournamentId : (!empty($allTournaments) ? (int)$allTournaments[0]['id'] : 0);
    }
}

$tournament = null;
if ($tid) {
    require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
    $tournament = getTournamentById($tid);
}

require_once __DIR__ . '/../modules/matches/match_functions.php';
require_once __DIR__ . '/../modules/tournaments/bracket_functions.php';

// Handle recording scores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'result') {
    $matchId = (int) ($_POST['match_id'] ?? 0);
    $winnerKey = trim($_POST['winner_key'] ?? '');
    if ($matchId && $winnerKey) {
        $setScores = !empty($_POST['set_scores']) ? trim($_POST['set_scores']) : null;
        recordBracketMatchResult($matchId, $winnerKey, (int) ($_POST['player1_score'] ?? 0), (int) ($_POST['player2_score'] ?? 0), $setScores);
        setFlash('success', 'Match result saved successfully.');
    }
    header('Location: /TournamentHQ/umpire/dashboard?tournament_id=' . $tid);
    exit;
}

$bracketGroups = $tid ? buildBracketGroups($tid) : [];
$recordResultUrl = '/TournamentHQ/umpire/dashboard?tournament_id=' . $tid;
$bracketAllowSwap = false;

$flash = getFlash();
$formAction = '/TournamentHQ/umpire/dashboard?tournament_id=' . $tid;

$bracketIsTeamEvent = $tournament ? !empty($tournament['is_team_event']) : false;
$bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
$bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';

$pageTitle = 'Umpire Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <div class="page-heading">
        <h1>Umpire Portal</h1>
        <p>Record results and manage brackets for active tournaments</p>
    </div>
    
    <div style="display:flex; align-items:center; gap:12px; margin-left:auto;">
        <label for="tournamentSelect" style="font-size:13px; font-weight:600; color:var(--text-300); white-space:nowrap;">Select Tournament:</label>
        <select id="tournamentSelect" class="form-select" style="min-width: 250px; background: var(--bg-800); border: 1px solid var(--border); color: var(--text-100); height: 42px;" onchange="window.location='dashboard?tournament_id='+this.value">
            <?php foreach ($allTournaments as $tItem): ?>
                <option value="<?= $tItem['id'] ?>" <?= $tid == $tItem['id'] ? 'selected' : '' ?>>
                    <?= e($tItem['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($tournament): ?>
    <div class="card mb-24">
        <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <h2 style="margin:0 0 6px 0; font-family:'Outfit', sans-serif; font-size:18px; font-weight:700; color:var(--text-100);"><?= e($tournament['name']) ?></h2>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap;">
                    <span style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--primary-light); font-size: 10px; font-weight: 700; text-transform: uppercase;">
                        <?= e($tournament['sport'] ?? 'Table Tennis') ?>
                    </span>
                    <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase;">
                        <?= e($tournament['category'] ?? 'Open Singles') ?>
                    </span>
                    <span style="font-size:12px; color:var(--text-400);">
                        Format: <?= e(ucwords(str_replace('_', ' ', $tournament['format']))) ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="badge badge-<?= e($tournament['status']) ?>" style="text-transform: capitalize; font-size: 11px; padding: 4px 12px;">
                    <?= e($tournament['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card mb-24">
        <div class="card-header" id="umpireGroupHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="card-title" style="margin: 0;">Group Stage Brackets</div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                    <span id="umpireGroupToggleText">Collapse</span>
                    <svg id="umpireGroupToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </div>
            </div>
        </div>
        <div id="umpireGroupBody" style="transition: max-height 0.4s ease-in-out, opacity 0.2s; max-height: 5000px; overflow: hidden; opacity: 1;">
            <div class="card-body" id="umpire-group-view" style="padding: 20px 10px;">
                <?php
                $recordResultUrl = '/TournamentHQ/umpire/dashboard?tournament_id=' . $tid;
                $showOnlyPhase = 'group';
                include __DIR__ . '/../includes/bracket_view.php';
                ?>
            </div>
        </div>
    </div>

    <?php
    $hasKnockoutMatches = false;
    if (!empty($bracketGroups)) {
        foreach ($bracketGroups as $group) {
            if (!preg_match('/^Group [A-Z]/i', $group['label'])) {
                $hasKnockoutMatches = true;
                break;
            }
        }
    }
    ?>

    <?php if ($hasKnockoutMatches): ?>
    <div class="card">
        <div class="card-header" id="umpireKnockoutHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="card-title" style="margin: 0;">Knockout Stage Brackets</div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                    <span id="umpireKnockoutToggleText">Collapse</span>
                    <svg id="umpireKnockoutToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </div>
            </div>
        </div>
        <div id="umpireKnockoutBody" style="transition: max-height 0.4s ease-in-out, opacity 0.2s; max-height: 5000px; overflow: hidden; opacity: 1;">
            <div class="card-body" id="umpire-knockout-view" style="padding: 20px 10px;">
                <?php
                $recordResultUrl = '/TournamentHQ/umpire/dashboard?tournament_id=' . $tid;
                $showOnlyPhase = 'knockout';
                include __DIR__ . '/../includes/bracket_view.php';
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">🏆</div>
        <h3>No tournament selected</h3>
        <p>Please select an active tournament from the dropdown to view and score matches.</p>
    </div>
<?php endif; ?>

<!-- Record result modal -->
<div class="modal-overlay" id="bracketResultModal">
    <div class="modal" style="max-width: <?= $bracketIsTeamEvent ? '640px' : '420px' ?>;">
        <div class="modal-header">
            <div class="modal-title" id="bracketModalTitle">Record Match Result</div>
            <button type="button" class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= e($formAction) ?>">
            <input type="hidden" name="action" value="result">
            <input type="hidden" name="tournament_id" value="<?= $tid ?>">
            <input type="hidden" name="match_id" id="bracketMatchId">
            <input type="hidden" name="winner_key" id="bracketWinnerKey">
            <div class="modal-body">
                <p id="bracketMatchLabel" style="font-weight: 600; margin-bottom: 16px;"></p>
                <div class="form-group">
                    <label class="form-label" id="bracketWinnerLabel"><?= $bracketIsTeamEvent ? 'Winning Team' : 'Winner' ?></label>
                    <select id="bracketWinnerSelect" class="form-select" required>
                        <option value="">— Select winner —</option>
                    </select>
                </div>
                <input type="hidden" name="player1_score" id="bracketP1Score" value="0">
                <input type="hidden" name="player2_score" id="bracketP2Score" value="0">
                <div class="form-group" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <?php if ($bracketIsTeamEvent): ?>
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

<script>
document.addEventListener('DOMContentLoaded', () => { 
    // Modal close hooks
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('bracketResultModal').classList.remove('open');
        });
    });
});

window.BRACKET_IS_TEAM_EVENT = <?= $bracketIsTeamEvent ? 'true' : 'false' ?>;
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

// Bind buttons (delegated or direct)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.js-bracket-result-btn');
    if (btn) {
        openBracketResultModal(btn);
    }
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
<script>
(function() {
    function setupCollapse(headerId, bodyId, textId, iconId, storageKey) {
        var header = document.getElementById(headerId);
        var body = document.getElementById(bodyId);
        var text = document.getElementById(textId);
        var icon = document.getElementById(iconId);
        if (!header || !body) return;

        var isCollapsed = localStorage.getItem(storageKey) === 'true';
        if (isCollapsed) {
            body.style.maxHeight = '0';
            body.style.opacity = '0';
            body.style.display = 'none';
            if (text) text.textContent = 'Expand';
            if (icon) icon.style.transform = 'rotate(-180deg)';
        }

        header.addEventListener('mouseenter', function() { header.style.background = 'rgba(255,255,255,0.03)'; });
        header.addEventListener('mouseleave', function() { header.style.background = ''; });

        header.addEventListener('click', function() {
            var collapsed = body.style.maxHeight === '0px' || body.style.display === 'none';
            if (collapsed) {
                body.style.display = '';
                body.style.maxHeight = '5000px';
                body.style.opacity = '1';
                if (text) text.textContent = 'Collapse';
                if (icon) icon.style.transform = 'rotate(0deg)';
                localStorage.setItem(storageKey, 'false');
            } else {
                body.style.maxHeight = '0';
                body.style.opacity = '0';
                setTimeout(function() { body.style.display = 'none'; }, 400);
                if (text) text.textContent = 'Expand';
                if (icon) icon.style.transform = 'rotate(-180deg)';
                localStorage.setItem(storageKey, 'true');
            }
        });
    }

    setupCollapse('umpireGroupHeader', 'umpireGroupBody', 'umpireGroupToggleText', 'umpireGroupToggleIcon', 'umpire_group_collapsed');
    setupCollapse('umpireKnockoutHeader', 'umpireKnockoutBody', 'umpireKnockoutToggleText', 'umpireKnockoutToggleIcon', 'umpire_knockout_collapsed');

    var TOURNAMENT_ID = <?= (int)$tid ?>;
    if (!TOURNAMENT_ID || window.__umpireSse) return;

    var POLL_INTERVAL = 2000;
    var pollTimer = null;
    var lastTs = -1;
    var isRefreshing = false;

    function createStatusDot() {
        var header = document.getElementById('umpireGroupHeader');
        if (!header || document.getElementById('ws-status-dot')) return;
        var dot = document.createElement('span');
        dot.id = 'ws-status-dot';
        dot.title = 'Live updates active';
        dot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;background:#00d4aa;margin-left:8px;vertical-align:middle;transition:background 0.3s;';
        var wrapper = header.querySelector('div');
        if (wrapper) wrapper.appendChild(dot);
    }

    function setStatus(color, title) {
        var dot = document.getElementById('ws-status-dot');
        if (dot) { dot.style.background = color; dot.title = title; }
    }

    function refreshBrackets() {
        if (isRefreshing) return;
        isRefreshing = true;
        setStatus('#f0ad4e', 'Updating...');

        var baseUrl = '/TournamentHQ/includes/bracket_view_ajax.php?tournament_id=' + TOURNAMENT_ID + '&record_result_url=' + encodeURIComponent('/TournamentHQ/umpire/dashboard?tournament_id=' + TOURNAMENT_ID) + '&_=';
        var ts = Date.now();
        var pending = 0;
        var updated = false;

        var groupContainer = document.getElementById('umpire-group-view');
        var knockoutContainer = document.getElementById('umpire-knockout-view');

        function onPhaseDone() {
            pending--;
            if (pending <= 0) {
                isRefreshing = false;
                if (typeof window.drawBracketLines === 'function') window.drawBracketLines();
                if (typeof adjustGridColumns === 'function') adjustGridColumns();
                setStatus(updated ? '#00d4aa' : '#f0ad4e', updated ? 'Live updates active' : 'Update failed');
            }
        }

        function replaceHTML(container, html) {
            if (!container || !html.trim()) { onPhaseDone(); return; }
            container.innerHTML = html;
            var scripts = container.querySelectorAll('script');
            scripts.forEach(function(old) {
                var s = document.createElement('script');
                if (old.src) s.src = old.src; else s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
            updated = true;
            onPhaseDone();
        }

        pending++;
        var xhrGroup = new XMLHttpRequest();
        xhrGroup.open('GET', baseUrl + ts + '&phase=group', true);
        xhrGroup.onload = function() {
            replaceHTML(groupContainer, xhrGroup.status === 200 ? xhrGroup.responseText : '');
        };
        xhrGroup.onerror = function() { replaceHTML(groupContainer, ''); };
        xhrGroup.send();

        if (knockoutContainer) {
            pending++;
            var xhrKO = new XMLHttpRequest();
            xhrKO.open('GET', baseUrl + ts + '&phase=knockout', true);
            xhrKO.onload = function() {
                replaceHTML(knockoutContainer, xhrKO.status === 200 ? xhrKO.responseText : '');
            };
            xhrKO.onerror = function() { replaceHTML(knockoutContainer, ''); };
            xhrKO.send();
        }
    }

    function poll() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/TournamentHQ/includes/match_timestamp.php?tournament_id=' + TOURNAMENT_ID + '&_=' + Date.now(), true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var ts = parseFloat(data.timestamp) || 0;
                    if (lastTs === -1) {
                        lastTs = ts;
                        createStatusDot();
                        setStatus('#00d4aa', 'Live updates active');
                        return;
                    }
                    if (ts > lastTs) {
                        lastTs = ts;
                        refreshBrackets();
                    }
                } catch(e) {}
            }
        };
        xhr.send();
    }

    poll();
    pollTimer = setInterval(poll, POLL_INTERVAL);

    window.__umpireSse = true;
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
