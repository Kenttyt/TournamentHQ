<?php
/**
 * Player Dashboard
 */
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireRole('player');
require_once __DIR__ . '/../modules/players/player_functions.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/notifications/notification_functions.php';
require_once __DIR__ . '/../modules/uploads/payment_proof.php';

$userId  = (int)$_SESSION['user_id'];
$player  = getPlayerByUserId($userId);

if (!$player) {
    setFlash('info', 'Your player profile has not been set up yet. Please contact an admin.');
    $player = ['id'=>0,'first_name'=>$_SESSION['username'],'last_name'=>'','wins'=>0,'losses'=>0,'points'=>0,'club'=>'','nationality'=>''];
}

$playerId   = (int)($player['id'] ?? 0);

$stmt = db()->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$userEmail = $stmt->fetchColumn();
$emailMissing = empty($userEmail);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        setFlash('error', 'Invalid request. Please try again.');
        header('Location: index.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['join', 'leave'], true) && $playerId <= 0) {
        setFlash('danger', 'Your player profile is not set up. Please contact an admin before joining tournaments.');
        header('Location: index.php');
        exit;
    }
    // Check if user has email when trying to join tournament
    if ($action === 'join' && $playerId > 0) {
        if ($emailMissing) {
            header('Location: profile.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $playerId > 0) {
    if (!validateCsrfToken()) {
        setFlash('error', 'Invalid request. Please try again.');
        header('Location: index.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'join') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        if ($tid) {
            $t = getTournamentById($tid);
            if ($t) {
                // Parse guests
                $guestFirsts = $_POST['guest_first'] ?? [];
                $guestLasts  = $_POST['guest_last'] ?? [];
                $isTeamEvent = !empty($t['is_team_event']);
                $guestsToRegister = [];
                for ($i = 0; $i < count($guestFirsts); $i++) {
                    $gFirst = trim($guestFirsts[$i] ?? '');
                    $gLast  = trim($guestLasts[$i] ?? '');
                    if ($isTeamEvent) {
                        if ($gFirst !== '') {
                            $guestsToRegister[] = ['first' => $gFirst, 'last' => ''];
                        }
                    } else {
                        if ($gFirst !== '' && $gLast !== '') {
                            $guestsToRegister[] = ['first' => $gFirst, 'last' => $gLast];
                        }
                    }
                }
                
                $totalSlotsNeeded = count($guestsToRegister);
                $availableSlots = $t['max_players'] - $t['registered_count'];
                
                if ($t['status'] !== 'upcoming') {
                    setFlash('danger', 'Registration is only open for upcoming tournaments.');
                } elseif ($totalSlotsNeeded < 1) {
                    setFlash('danger', 'Add at least one player to register. Your account profile is not included in the tournament.');
                } elseif ($totalSlotsNeeded > $availableSlots) {
                    setFlash('danger', 'Not enough slots available in this tournament. Only ' . $availableSlots . ' remaining.');
                } else {
                    $needsProof = tournamentRequiresPaymentProof($t);
                    $proofPath = null;
                    $proofOriginal = null;
                    if ($needsProof) {
                        $fileProvided = (isset($_FILES['payment_proof']) && ($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
                        $noProofChecked = isset($_POST['no_payment_proof']);
                        
                        if ($fileProvided) {
                            // User provided a file - upload it
                            $upload = savePaymentProofUpload($tid, $playerId, $_FILES['payment_proof'] ?? []);
                            if (!$upload['ok']) {
                                setFlash('danger', $upload['error']);
                                header('Location: index.php');
                                exit;
                            }
                            $proofPath = $upload['path'];
                            $proofOriginal = $upload['original_name'];
                        } elseif ($noProofChecked) {
                            // User checked "no proof" - allow submission without proof
                            setFlash('info', 'No payment proof was provided. The organizer will review and may follow up if needed.');
                        } else {
                            // No file and no checkbox - this shouldn't happen if frontend validation works
                            setFlash('danger', 'Please upload payment proof or check the box if you don\'t have it right now.');
                            header('Location: index.php');
                            exit;
                        }
                    }

                    $guestCount = 0;
                    $guestFailed = false;
                    foreach ($guestsToRegister as $g) {
                        if (addTournamentGuest($tid, $playerId, $g['first'], $g['last'], 'pending', $proofPath, $proofOriginal)) {
                            $guestCount++;
                        } else {
                            $guestFailed = true;
                        }
                    }
                    if ($guestCount > 0) {
                        if ($guestFailed) {
                            setFlash('warning', 'Some players were saved, but one or more could not be added. The organizer will review what was submitted.');
                        } else {
                            setFlash('success', 'Registration submitted for ' . $guestCount . ' player(s). Your profile is not in the tournament — the organizer will confirm payment/approval for ' . $t['name'] . '.');
                        }
                        notifyOrganizerRegistrationRequest($tid, $playerId, $guestCount);
                    } else {
                        if ($proofPath) {
                            deletePaymentProofFile($proofPath);
                        }
                        setFlash('danger', 'Failed to submit registration. Please try again or contact support.');
                    }
                }
            }
        }
        header('Location: index.php'); exit;
    }
    if ($action === 'leave') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        if ($tid) {
            $t = getTournamentById($tid);
            if ($t) {
                if ($t['status'] !== 'upcoming') {
                    setFlash('danger', 'You cannot leave a tournament that has already started.');
                } else {
                    removePlayerFromTournament($tid, $playerId);
                    $success = removePlayerGuestsFromTournament($tid, $playerId);
                    if ($success) {
                        setFlash('success', 'Your registered players have been removed from ' . $t['name'] . '.');
                    } else {
                        setFlash('danger', 'Failed to leave tournament.');
                    }
                }
            }
        }
        header('Location: index.php'); exit;
    }
}

$myTourneys = $playerId ? getPlayerTournaments($playerId) : [];
$myRegisteredByTournament = [];
foreach ($myTourneys as $mt) {
    $myRegisteredByTournament[(int) $mt['id']] = $playerId
        ? getSubmitterTournamentGuests((int) $mt['id'], $playerId)
        : [];
}

$tournamentRosters = [];
foreach (getAllTournaments() as $t) {
    if (in_array($t['status'], ['upcoming', 'ongoing'], true)) {
        $tournamentRosters[(int) $t['id']] = getTournamentApprovedEntrants((int) $t['id']);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,rgba(108,99,255,0.15),rgba(0,212,170,0.08));border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:28px">
    <h1 style="font-family:'Outfit',sans-serif;font-size:22px;font-weight:700;color:var(--text-100);margin:0">
        Welcome, <?= e($player['first_name']) ?>!
    </h1>
    <p style="font-size:13px;color:var(--text-400);margin-top:4px">
        <?= e(($player['club'] ?? '') ?: 'No club') ?><?= ($player['nationality'] ?? '') ? ' · '.e($player['nationality']) : '' ?>
    </p>
</div>

<!-- Explore Tournaments -->
<div class="card mb-24">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--accent);"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Explore & Register Players
        </div>
        <button type="button" onclick="location.reload()" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; height: auto; display: inline-flex; align-items: center; gap: 4px;" title="Refresh list">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
            Refresh
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php
        $allTournaments = getAllTournaments();
        // Filter out completed or cancelled from the exploration list
        $joinableTournaments = array_filter($allTournaments, function($t) {
            return in_array($t['status'], ['upcoming', 'ongoing']);
        });
        
        if (empty($joinableTournaments)):
        ?>
            <div class="empty-state" style="padding: 30px;">
                <div class="empty-icon">🏆</div>
                <h3>No active or upcoming tournaments</h3>
                <p>Check back later for new events!</p>
            </div>
        <?php else: ?>
            <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 12px; font-weight: 600; color: var(--text-300);">Filter:</span>
                <select id="filterSport" class="form-select" style="width: auto; min-width: 140px; height: 34px; font-size: 12px; padding: 4px 10px;">
                    <option value="">All Sports</option>
                    <option value="Table Tennis">Table Tennis</option>
                    <option value="Lawn Tennis">Lawn Tennis</option>
                    <option value="Badminton">Badminton</option>
                    <option value="Pickleball">Pickleball</option>
                </select>
                <select id="filterFormat" class="form-select" style="width: auto; min-width: 140px; height: 34px; font-size: 12px; padding: 4px 10px;">
                    <option value="">All Formats</option>
                    <option value="Singles">Singles</option>
                    <option value="Doubles">Doubles</option>
                    <option value="Team">Team Events</option>
                </select>
                <input type="text" id="filterSearch" placeholder="Search tournaments..." class="form-control" style="width: auto; min-width: 180px; height: 34px; font-size: 12px; padding: 4px 10px;">
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table" id="exploreTable">
                    <thead>
                        <tr>
                            <th>Tournament Name</th>
                            <th>Category</th>
                            <th>Start Date</th>
                            <th>Capacity</th>
                            <th>Reg. Fee</th>
                            <th>Prize Pool</th>
                            <th>Registered players</th>
                            <th style="text-align: right; padding-right: 24px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($joinableTournaments as $t): 
                            $regStatus = $playerId ? getPlayerRegistrationStatus($t['id'], $playerId) : null;
                            $isRegistered = $regStatus !== null;
                            $isApproved = $regStatus === 'approved';
                            $isPending = $regStatus === 'pending';
                            $isFull = $t['registered_count'] >= $t['max_players'];
                            $percent = $t['max_players'] > 0 ? min(100, round(($t['registered_count'] / $t['max_players']) * 100)) : 0;
                            $rowSport = e($t['sport'] ?? 'Table Tennis');
                            $rowCategory = e($t['category'] ?? 'Open Singles');
                            $rowCatLower = strtolower($t['category'] ?? '');
                            $rowFormat = !empty($t['is_team_event']) ? 'Team' : (strpos($rowCatLower, 'double') !== false ? 'Doubles' : 'Singles');
                        ?>
                            <tr data-sport="<?= $rowSport ?>" data-format="<?= $rowFormat ?>">
                                <td>
                                    <div style="font-weight: 700; color: var(--text-100);">
                                        <a href="tournament_bracket.php?tournament_id=<?= $t['id'] ?>" style="color: var(--primary-light); text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                            <?= e($t['name']) ?>
                                        </a>
                                    </div>
                                    <?php if ($t['description']): ?>
                                        <div class="text-muted text-xs" style="margin-top: 2px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($t['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--primary-light); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                                        <?= e($t['sport'] ?? 'Table Tennis') ?>
                                    </span>
                                    <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; margin-left: 4px;">
                                        <?= e($t['category'] ?? 'Open Singles') ?>
                                    </span>
                                </td>
                                <td class="text-sm text-muted">
                                    <?= date('M j, Y', strtotime($t['start_date'])) ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 12px; font-weight: 600; min-width: 42px;"><?= $t['registered_count'] ?> / <?= $t['max_players'] ?></span>
                                        <div class="progress-bar" style="width: 80px; height: 6px; margin: 0;">
                                            <div class="progress-fill" style="width: <?= $percent ?>%; background: <?= $isRegistered ? 'var(--accent)' : ($isFull ? 'var(--danger)' : 'var(--primary)') ?>;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-sm font-semibold" style="color: var(--text-200);">
                                    <?php $fee = formatRegistrationFee($t); ?>
                                    <?= $fee !== '' ? e($fee) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td>
                                    <?php $tournament = $t; include __DIR__ . '/../includes/tournament_prize_display.php'; ?>
                                </td>
                                <td>
                                    <?php
                                    $roster = $tournamentRosters[(int) $t['id']] ?? [];
                                    $rosterCount = count($roster);
                                    ?>
                                    <?php if ($rosterCount > 0): ?>
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm js-view-roster-btn"
                                        style="padding: 4px 10px; font-size: 11px; height: auto;"
                                        data-tid="<?= (int) $t['id'] ?>"
                                        data-tname="<?= e($t['name']) ?>"
                                        data-roster="<?= e(json_encode($roster)) ?>"
                                    >View (<?= $rosterCount ?>)</button>
                                    <?php else: ?>
                                    <span class="text-muted text-xs">None yet</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; padding-right: 24px;">
                                    <?php if ($isRegistered): ?>
                                        <div style="display: inline-flex; align-items: center; gap: 8px;">
                                            <?php if ($isPending): ?>
                                                <span class="badge badge-upcoming" style="padding: 6px 12px; font-weight: 700; border-radius: var(--radius-sm);">Players pending</span>
                                            <?php else: ?>
                                                <span class="badge badge-ongoing" style="padding: 6px 12px; font-weight: 700; border-radius: var(--radius-sm);">Registered ✓</span>
                                            <?php endif; ?>
                                            <?php if ($t['status'] === 'upcoming'): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm js-join-tournament-btn"
                                                    style="padding: 5px 10px; font-weight: 700; height: auto; font-size: 11px;"
                                                    data-tid="<?= (int)$t['id'] ?>"
                                                    data-tname="<?= e($t['name']) ?>"
                                                    data-pname="<?= e(trim($player['first_name'] . ' ' . $player['last_name'])) ?>"
                                                    data-description="<?= e($t['description'] ?? '') ?>"
                                                    data-slots="<?= (int)($t['max_players'] - $t['registered_count']) ?>"
                                                    data-fee="<?= e(formatRegistrationFee($t)) ?>"
                                                    data-requires-proof="<?= tournamentRequiresPaymentProof($t) ? '1' : '0' ?>"
                                                    data-email-missing="<?= $emailMissing ? '1' : '0' ?>"
                                                    data-team-event="<?= !empty($t['is_team_event']) ? '1' : '0' ?>"
                                                >+ Add</button>
                                            <?php endif; ?>
                                            <?php if ($t['status'] === 'upcoming' && ($isApproved || $isPending)): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to leave this tournament?');">
                                                    <input type="hidden" name="action" value="leave">
                                                    <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 5px 10px; height: auto; font-size: 11px;">Leave</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($t['status'] !== 'upcoming'): ?>
                                            <span class="badge badge-cancelled" style="padding: 6px 12px; border-radius: var(--radius-sm);">Closed</span>
                                        <?php elseif ($isFull): ?>
                                            <span class="badge badge-cancelled" style="padding: 6px 12px; border-radius: var(--radius-sm);">Full 🔒</span>
                                        <?php elseif ($playerId <= 0): ?>
                                            <span class="text-muted text-xs">Profile required</span>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm js-join-tournament-btn"
                                                style="padding: 6px 14px; font-weight: 700; height: auto;"
                                                data-tid="<?= (int)$t['id'] ?>"
                                                data-tname="<?= e($t['name']) ?>"
                                                data-pname="<?= e(trim($player['first_name'] . ' ' . $player['last_name'])) ?>"
                                                data-description="<?= e($t['description'] ?? '') ?>"
                                                data-slots="<?= (int)($t['max_players'] - $t['registered_count']) ?>"
                                                data-fee="<?= e(formatRegistrationFee($t)) ?>"
                                                data-requires-proof="<?= tournamentRequiresPaymentProof($t) ? '1' : '0' ?>"
                                                data-email-missing="<?= $emailMissing ? '1' : '0' ?>"
                                                data-team-event="<?= !empty($t['is_team_event']) ? '1' : '0' ?>"
                                            >Register Players</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="content-grid">
    <!-- My Tournaments -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                My Registrations & Teams
            </div>
            <span class="text-muted text-xs">Players you submitted, grouped by tournament</span>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($myTourneys)): ?>
                <div class="empty-state"><div class="empty-icon">🏆</div><p>No players registered in any tournament yet</p></div>
            <?php else: ?>
            <?php foreach ($myTourneys as $t):
                $tid = (int) $t['id'];
                $myPlayers = $myRegisteredByTournament[$tid] ?? [];
            ?>
            <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                    <div>
                        <strong style="color:var(--text-100)"><?= e($t['name']) ?></strong>
                        <?php if (!empty($t['sport'])): ?>
                        <span class="text-muted text-xs"> · <?= e($t['sport']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t['category'])): ?>
                        <span class="text-muted text-xs"> · <?= e($t['category']) ?></span>
                        <?php endif; ?>
                        <div class="text-muted text-xs" style="margin-top:4px">
                            <?= date('M j, Y', strtotime($t['start_date'])) ?>
                            <?= $t['venue'] ? ' · ' . e($t['venue']) : '' ?>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <?php if (($t['registration_status'] ?? '') === 'pending'): ?>
                            <span class="badge badge-upcoming">Awaiting approval</span>
                        <?php else: ?>
                            <span class="badge badge-ongoing">Approved</span>
                        <?php endif; ?>
                        <span class="badge badge-<?= e($t['status']) ?>"><?= ucfirst(e($t['status'])) ?></span>
                        <a href="tournament_bracket.php?tournament_id=<?= $t['id'] ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px; font-size: 11px; height: auto; display: inline-flex; align-items: center; gap: 4px;">
                            Bracket
                        </a>
                    </div>
                </div>
                <?php if (empty($myPlayers)): ?>
                <p class="text-muted text-xs" style="margin:0">No players listed for this tournament.</p>
                <?php else: ?>
                <div class="text-xs text-muted mb-8" style="text-transform:uppercase;letter-spacing:.4px;font-weight:600">Your registered players</div>
                <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
                    <?php
                    $myProofUrl = paymentProofPublicUrl($myPlayers[0]['payment_proof_path'] ?? null);
                    ?>
                    <?php if ($myProofUrl && ($myPlayers[0]['registration_status'] ?? '') === 'pending'): ?>
                    <p class="text-xs" style="margin:0 0 8px">
                        <a href="<?= e($myProofUrl) ?>" target="_blank" rel="noopener" style="color:var(--primary-light);font-weight:600">View payment proof submitted</a>
                    </p>
                    <?php endif; ?>
                    <?php foreach ($myPlayers as $mp): ?>
                    <li style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;background:var(--bg-700);border-radius:var(--radius-sm)">
                        <span style="font-size:13px;font-weight:600;color:var(--text-200)">
                            <?= e(trim($mp['first_name'] . ' ' . $mp['last_name'])) ?>
                            <?php if (!empty($mp['club'])): ?>
                            <span class="text-muted" style="font-weight:500;font-size:11px"> · <?= e($mp['club']) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (($mp['registration_status'] ?? '') === 'pending'): ?>
                            <span class="badge badge-upcoming" style="font-size:10px;padding:3px 8px">Pending</span>
                        <?php elseif (($mp['registration_status'] ?? '') === 'approved'): ?>
                            <span class="badge badge-ongoing" style="font-size:10px;padding:3px 8px">Approved</span>
                        <?php else: ?>
                            <span class="badge badge-cancelled" style="font-size:10px;padding:3px 8px"><?= e(ucfirst($mp['registration_status'] ?? '')) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tournament roster modal -->
<div class="modal-overlay" id="tournamentRosterModal">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title" id="rosterModalTitle">Registered players</div>
            <button type="button" class="modal-close" data-modal-close>×</button>
        </div>
        <div class="modal-body">
            <p class="text-muted text-xs" style="margin:0 0 12px">Approved players in this tournament</p>
            <ul id="rosterModalList" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;max-height:320px;overflow-y:auto"></ul>
            <p id="rosterModalEmpty" class="text-muted text-sm" style="display:none;margin:12px 0 0">No approved players yet.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-modal-close>Close</button>
        </div>
    </div>
</div>

<!-- Join Tournament with Guests Modal -->
<div class="modal-overlay" id="joinTournamentModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title">Register Players</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" id="joinForm" action="index.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="join">
            <input type="hidden" name="tournament_id" id="joinTId">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="modal-body">
                <div style="background: rgba(108, 99, 255, 0.08); border: 1px solid rgba(108, 99, 255, 0.2); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px;">
                    <h4 style="margin: 0; color: var(--primary-light); font-size: 15px; font-weight: 700;" id="joinTName">Tournament Name</h4>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-400);" id="joinTSlots">Slots remaining: </p>
                    <p style="margin: 6px 0 0 0; font-size: 12px; color: var(--accent); font-weight: 600; display: none;" id="joinTFee"></p>
                </div>

                <div id="joinTDescriptionWrapper" style="display:none;margin-bottom:14px;padding:12px;border-radius:var(--radius-sm);background:rgba(0,212,170,0.06);border:1px solid rgba(0,212,170,0.18);">
                    <div id="joinTDescription" style="margin:0;font-size:13px;color:var(--text-200);line-height:1.4"></div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <label class="form-label" style="font-weight: 700; margin: 0;">Players to register <span style="color: var(--danger);">*</span></label>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addGuestRow()" style="padding: 4px 10px; font-size: 11px; height: auto;">
                            + Add Player
                        </button>
                    </div>
                    <?php
                    $registrarClub = trim($player['club'] ?? '');
                    $registrarPlace = trim($player['nationality'] ?? '');
                    $inheritParts = array_filter([$registrarClub, $registrarPlace]);
                    ?>
                    <p style="font-size: 11px; color: var(--text-400); margin-top: -6px; margin-bottom: 12px;">
                        Enter each person who will play.
                        <?php if ($inheritParts): ?>
                        They will be registered with your club/place: <strong style="color:var(--text-300)"><?= e(implode(' · ', $inheritParts)) ?></strong>.
                        <?php else: ?>
                        Set your club on <a href="profile.php" style="color:var(--primary-light)">My Profile</a> so players you register share the same club.
                        <?php endif; ?>
                        The organizer will approve after payment is confirmed.
                    </p>
                    
                    <div id="guestsContainer">
                        <!-- Dynamic guest rows -->
                    </div>
                </div>

                <div id="paymentProofSection" style="display:none;margin-top:18px;padding-top:18px;border-top:1px solid var(--border)">
                    <label class="form-label" style="font-weight:700">Payment proof <span id="paymentProofRequired" style="color:var(--danger)">*</span></label>
                    <p class="text-muted text-xs" style="margin:0 0 10px;line-height:1.45">
                        Upload a screenshot or photo of your payment (receipt, GCash, bank transfer, etc.). The organizer will review this before approving your players.
                    </p>
                    <input type="file" name="payment_proof" id="paymentProofFile" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" style="height:auto;padding:10px">
                    <p class="text-muted text-xs" style="margin:8px 0 0">JPG, PNG, WebP, or PDF · Max 5 MB</p>
                    
                    <div style="margin-top:14px;padding:12px;background:rgba(255,200,87,0.08);border:1px solid rgba(255,200,87,0.2);border-radius:var(--radius-sm)">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0">
                            <input type="checkbox" id="noPaymentProofCheckbox" name="no_payment_proof" style="width:18px;height:18px;cursor:pointer">
                            <span style="font-size:13px;color:var(--text-300);font-weight:500">I don't have payment proof right now, I'll submit it later</span>
                        </label>
                        <p class="text-muted text-xs" style="margin:8px 0 0">The organizer can still approve your registration and may follow up if needed.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 700;">Submit Registration</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="emailUpdateModal">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title">Update your email</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <div class="modal-body">
            <p style="margin:0 0 12px; font-size:14px; color:var(--text-100);">You need to add an email address to your profile before registering for a tournament.</p>
            <p style="margin:0; font-size:13px; color:var(--text-400); line-height:1.5;">This email lets the organizer contact you about your registration and payment status.</p>
        </div>
        <div class="modal-footer">
            <a href="profile.php" class="btn btn-primary" style="font-weight:700;">Update Email</a>
            <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        </div>
    </div>
</div>

<script>
let currentSlotsRemaining = 16;

function openJoinModal(tid, tname, tdesc, pname, slotsRemaining, regFee, requiresProof, teamEvent) {
    document.getElementById('joinTId').value = tid;
    document.getElementById('joinTName').textContent = tname;
    const descWrapper = document.getElementById('joinTDescriptionWrapper');
    const descEl = document.getElementById('joinTDescription');
    if (descWrapper && descEl) {
        if (tdesc && tdesc.trim() !== '') {
            descEl.textContent = tdesc;
            descWrapper.style.display = 'block';
        } else {
            descEl.textContent = '';
            descWrapper.style.display = 'none';
        }
    }
    const submitterEl = document.getElementById('joinSubmitterName');
    if (submitterEl) submitterEl.textContent = pname;
    document.getElementById('joinTSlots').textContent = 'Slots remaining: ' + slotsRemaining;
    const feeEl = document.getElementById('joinTFee');
    if (regFee) {
        feeEl.textContent = 'Registration fee: ' + regFee;
        feeEl.style.display = 'block';
    } else {
        feeEl.style.display = 'none';
        feeEl.textContent = '';
    }

    const proofSection = document.getElementById('paymentProofSection');
    const proofInput = document.getElementById('paymentProofFile');
    const needsProof = requiresProof === '1' || requiresProof === true;
    if (proofSection) {
        proofSection.style.display = needsProof ? 'block' : 'none';
    }
    if (proofInput) {
        proofInput.required = needsProof;
        proofInput.value = '';
    }
    const noProofCheckbox = document.getElementById('noPaymentProofCheckbox');
    if (noProofCheckbox) {
        noProofCheckbox.checked = false;
    }

    window._isTeamEvent = teamEvent === '1' || teamEvent === 1;

    // Update labels for team events
    var addBtn = document.querySelector('#joinTournamentModal .btn-outline[onclick*="addGuestRow"]');
    var labelEl = document.querySelector('#joinTournamentModal .form-label[style*="font-weight: 700"]');
    var hintEl = document.querySelector('#joinTournamentModal p[style*="font-size: 11px"]');
    if (window._isTeamEvent) {
        if (addBtn) addBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> + Add Team';
        if (labelEl) labelEl.innerHTML = 'Teams to register <span style="color: var(--danger);">*</span>';
        if (hintEl) hintEl.textContent = 'Enter each team that will play. The organizer will approve after payment is confirmed.';
    } else {
        if (addBtn) addBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> + Add Player';
        if (labelEl) labelEl.innerHTML = 'Players to register <span style="color: var(--danger);">*</span>';
        if (hintEl) hintEl.innerHTML = 'Enter each person who will play.' + ('<?= $inheritParts ? e(implode(' · ', $inheritParts)) : '' ?>' ? ' They will be registered with your club/place: <strong style="color:var(--text-300)"><?= $inheritParts ? e(implode(' · ', $inheritParts)) : '' ?></strong>.' : ' Set your club on <a href="profile.php" style="color:var(--primary-light)">My Profile</a> so players you register share the same club.') + ' The organizer will approve after payment is confirmed.';
    }

    document.getElementById('guestsContainer').innerHTML = '';
    addGuestRow();
    
    currentSlotsRemaining = slotsRemaining;
    
    const overlay = document.getElementById('joinTournamentModal');
    if (window.TTMS && typeof TTMS.openModal === 'function') {
        TTMS.openModal('joinTournamentModal');
    } else if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

document.getElementById('joinForm')?.addEventListener('submit', function (e) {
    let filled = 0;
    const isTeamEvent = window._isTeamEvent;
    document.querySelectorAll('#guestsContainer .guest-row').forEach(function (row) {
        const first = row.querySelector('input[name="guest_first[]"]');
        const last = row.querySelector('input[name="guest_last[]"]');
        if (!first) return;
        const hasFirst = first.value.trim() !== '';
        const hasLast = last ? last.value.trim() !== '' : false;
        if (!hasFirst) {
            first.removeAttribute('name');
            if (last) last.removeAttribute('name');
            return;
        }
        if (!isTeamEvent && !hasLast) {
            e.preventDefault();
            alert('Please enter both first and last name for each player.');
            return;
        }
        if (last && !hasFirst) {
            last.removeAttribute('name');
        }
        filled++;
    });
    if (!e.defaultPrevented && filled < 1) {
        e.preventDefault();
        alert(isTeamEvent ? 'Add at least one team.' : 'Add at least one player.');
        return;
    }
    const proofInput = document.getElementById('paymentProofFile');
    const proofSection = document.getElementById('paymentProofSection');
    const noProofCheckbox = document.getElementById('noPaymentProofCheckbox');
    if (!e.defaultPrevented && proofSection && proofSection.style.display !== 'none' && proofInput && !proofInput.files.length && (!noProofCheckbox || !noProofCheckbox.checked)) {
        e.preventDefault();
        alert('Please upload payment proof or check the box if you\'ll submit it later.');
    }
});

// Handle "no proof" checkbox to toggle file input required attribute
(function () {
    const noProofCheckbox = document.getElementById('noPaymentProofCheckbox');
    const proofInput = document.getElementById('paymentProofFile');
    
    if (noProofCheckbox && proofInput) {
        noProofCheckbox.addEventListener('change', function () {
            if (this.checked) {
                // If checkbox is checked, remove required from file input
                proofInput.required = false;
            } else {
                // If unchecked, restore required if the proof section is visible
                const proofSection = document.getElementById('paymentProofSection');
                if (proofSection && proofSection.style.display !== 'none') {
                    proofInput.required = true;
                }
            }
        });
    }
})();

function addGuestRow() {
    const container = document.getElementById('guestsContainer');
    const guestRows = container.querySelectorAll('.guest-row');
    
    const currentGuestsCount = guestRows.length;
    if (currentGuestsCount + 1 > currentSlotsRemaining) {
        alert(window._isTeamEvent ? 'Cannot add more teams. The number of teams would exceed available tournament slots.' : 'Cannot add more players. The number of players would exceed available tournament slots.');
        return;
    }
    
    const rowId = 'guest_' + Date.now();
    const div = document.createElement('div');
    div.className = 'guest-row';
    div.id = rowId;
    
    if (window._isTeamEvent) {
        div.style = 'display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm);';
        div.innerHTML = `
            <div class="form-group" style="margin: 0;">
                <input type="text" name="guest_first[]" class="form-control" placeholder="Team Name" style="height: 38px;">
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeGuestRow('${rowId}')" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        `;
    } else {
        div.style = 'display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: center; margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm);';
        div.innerHTML = `
            <div class="form-group" style="margin: 0;">
                <input type="text" name="guest_first[]" class="form-control" placeholder="First Name" style="height: 38px;">
            </div>
            <div class="form-group" style="margin: 0;">
                <input type="text" name="guest_last[]" class="form-control" placeholder="Last Name" style="height: 38px;">
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeGuestRow('${rowId}')" style="padding: 0; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        `;
    }
    container.appendChild(div);
}

function removeGuestRow(id) {
    const container = document.getElementById('guestsContainer');
    if (container && container.querySelectorAll('.guest-row').length <= 1) {
        alert(window._isTeamEvent ? 'At least one team is required.' : 'At least one player is required. Your account profile is not registered for the tournament.');
        return;
    }
    const el = document.getElementById(id);
    if (el) {
        el.remove();
    }
}

document.querySelectorAll('.js-view-roster-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const tname = btn.getAttribute('data-tname') || 'Tournament';
        let roster = [];
        try {
            roster = JSON.parse(btn.getAttribute('data-roster') || '[]');
        } catch (_) { roster = []; }
        document.getElementById('rosterModalTitle').textContent = 'Registered players — ' + tname;
        const list = document.getElementById('rosterModalList');
        const empty = document.getElementById('rosterModalEmpty');
        list.innerHTML = '';
        if (!roster.length) {
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            roster.forEach(function (entry) {
                const li = document.createElement('li');
                li.style.cssText = 'padding:10px 12px;background:var(--bg-700);border-radius:var(--radius-sm);font-size:13px;font-weight:600;color:var(--text-200)';
                let label = entry.name || '';
                if (entry.club) {
                    label += ' · ' + entry.club;
                }
                li.textContent = label;
                list.appendChild(li);
            });
        }
        if (window.TTMS && typeof TTMS.openModal === 'function') {
            TTMS.openModal('tournamentRosterModal');
        } else {
            document.getElementById('tournamentRosterModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    });
});

function openEmailUpdateModal() {
    const overlay = document.getElementById('emailUpdateModal');
    if (!overlay) return;
    if (window.TTMS && typeof TTMS.openModal === 'function') {
        TTMS.openModal('emailUpdateModal');
    } else {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

document.querySelectorAll('.js-join-tournament-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (btn.getAttribute('data-email-missing') === '1') {
            openEmailUpdateModal();
            return;
        }
        openJoinModal(
            parseInt(btn.getAttribute('data-tid'), 10),
            btn.getAttribute('data-tname') || '',
            btn.getAttribute('data-description') || '',
            btn.getAttribute('data-pname') || '',
            parseInt(btn.getAttribute('data-slots'), 10) || 0,
            btn.getAttribute('data-fee') || '',
            btn.getAttribute('data-requires-proof') || '0',
            btn.getAttribute('data-team-event') || '0'
        );
    });
});

(function() {
    var sportSel = document.getElementById('filterSport');
    var formatSel = document.getElementById('filterFormat');
    var searchInput = document.getElementById('filterSearch');
    var table = document.getElementById('exploreTable');
    if (!sportSel || !formatSel || !searchInput || !table) return;

    function applyFilters() {
        var sport = sportSel.value.toLowerCase();
        var format = formatSel.value.toLowerCase();
        var search = searchInput.value.toLowerCase();
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            var rowSport = (row.getAttribute('data-sport') || '').toLowerCase();
            var rowFormat = (row.getAttribute('data-format') || '').toLowerCase();
            var rowText = row.textContent.toLowerCase();
            var show = true;
            if (sport && rowSport !== sport) show = false;
            if (format && rowFormat !== format) show = false;
            if (search && rowText.indexOf(search) === -1) show = false;
            row.style.display = show ? '' : 'none';
        });
    }

    sportSel.addEventListener('change', applyFilters);
    formatSel.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
