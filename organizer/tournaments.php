<?php
/**
 * Organizer — Manage Tournaments
 */
$pageTitle = 'My Tournaments';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/players/player_functions.php';
require_once __DIR__ . '/../modules/uploads/payment_proof.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $baseName    = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $startDate   = $_POST['start_date'] ?? '';
        $endDate     = $_POST['end_date'] ?? '';
        $catNames      = $_POST['cat_name'] ?? [];
        $catMaxPlayers = $_POST['cat_max_players'] ?? [];
        $catPrizeChampion = $_POST['cat_prize_champion'] ?? [];
        $catPrize2nd      = $_POST['cat_prize_2nd'] ?? [];
        $catPrize3rd      = $_POST['cat_prize_3rd'] ?? [];
        $catPrize4th      = $_POST['cat_prize_4th'] ?? [];
        $catRegFees       = $_POST['cat_registration_fee'] ?? [];
        
        $createdCount = 0;
        foreach ($catNames as $i => $name) {
            $catName = trim($name);
            if (empty($catName)) continue;
            
            $maxPlayers = (int)($catMaxPlayers[$i] ?? 16);
            if ($maxPlayers < 2) $maxPlayers = 16;
            
            createTournament([
                'organizer_id'   => $userId,
                'name'           => $baseName . ' (' . $catName . ')',
                'category'       => $catName,
                'description'    => $description,
                'format'         => 'single_elimination',
                'status'         => 'upcoming',
                'max_players'    => $maxPlayers,
                'start_date'     => $startDate,
                'end_date'       => $endDate ?: null,
                'venue'          => null,
                'prize_champion' => trim($catPrizeChampion[$i] ?? ''),
                'prize_2nd'      => trim($catPrize2nd[$i] ?? ''),
                'prize_3rd'      => trim($catPrize3rd[$i] ?? ''),
                'prize_4th'      => trim($catPrize4th[$i] ?? ''),
                'registration_fee' => trim($catRegFees[$i] ?? ''),
            ]);
            $createdCount++;
        }
        
        if ($createdCount === 0) {
            createTournament([
                'organizer_id' => $userId,
                'name'         => $baseName . ' (Open Singles)',
                'category'     => 'Open Singles',
                'description'  => $description,
                'format'       => 'single_elimination',
                'status'       => 'upcoming',
                'max_players'  => 16,
                'start_date'   => $startDate ?: date('Y-m-d'),
                'end_date'     => $endDate ?: null,
                'venue'        => null,
                'prize_champion' => '',
                'prize_2nd'      => '',
                'prize_3rd'      => '',
                'prize_4th'      => '',
                'registration_fee' => '',
            ]);
        }
        
        setFlash('success', 'Tournament categories created successfully!');
        header('Location: tournaments.php'); exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['tournament_id'] ?? 0);
        if ($id && isTournamentOwnedBy($id, $userId)) {
            $existing = getTournamentById($id);
            if ($existing) {
                $category = trim($_POST['category'] ?? '');
                if (empty($category)) {
                    $category = 'Open Singles';
                }
                updateTournament($id, [
                    'name'        => trim($_POST['name'] ?? $existing['name']),
                    'category'    => $category,
                    'description' => trim($_POST['description'] ?? $existing['description']),
                    'format'      => $existing['format'],
                    'status'      => $existing['status'],
                    'max_players' => (int)($_POST['max_players'] ?? $existing['max_players']),
                    'start_date'  => $_POST['start_date'] ?? $existing['start_date'],
                    'end_date'    => $_POST['end_date'] ?? $existing['end_date'],
                    'venue'       => $existing['venue'],
                    'prize_champion' => trim($_POST['prize_champion'] ?? $existing['prize_champion']),
                    'prize_2nd'      => trim($_POST['prize_2nd'] ?? $existing['prize_2nd']),
                    'prize_3rd'      => trim($_POST['prize_3rd'] ?? $existing['prize_3rd']),
                    'prize_4th'      => trim($_POST['prize_4th'] ?? $existing['prize_4th']),
                    'registration_fee' => trim($_POST['registration_fee'] ?? $existing['registration_fee']),
                ]);
                setFlash('success', 'Tournament updated.');
            }
        } else {
            setFlash('danger', 'You can only edit tournaments you created.');
        }
        header('Location: tournaments.php'); exit;
    }

    if ($action === 'status') {
        $id = (int)($_POST['tournament_id'] ?? 0);
        $st = $_POST['status'] ?? '';
        if ($id && in_array($st, ['upcoming','ongoing','completed','cancelled'])) {
            $t = getTournamentById($id);
            if ($t && $t['organizer_id'] == $userId) {
                updateTournament($id, array_merge($t, ['status' => $st]));
                setFlash('success', 'Tournament status updated.');
            }
        }
        header('Location: tournaments.php'); exit;
    }

    if ($action === 'start_bracketing') {
        $id = (int)($_POST['tournament_id'] ?? 0);
        if ($id && isTournamentOwnedBy($id, $userId)) {
            $t = getTournamentById($id);
            if ($t) {
                // Move to ongoing status and redirect to bracket generator
                updateTournament($id, array_merge($t, ['status' => 'ongoing']));
                setFlash('success', 'Tournament moved to bracketing.');
                header('Location: /TournamentHQ/organizer/bracket_generator.php?tournament_id=' . $id);
                exit;
            }
        }
        header('Location: tournaments.php'); exit;
    }

    if ($action === 'register') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        $firstNames = $_POST['first_name'] ?? [];
        $lastNames  = $_POST['last_name'] ?? [];
        if (!is_array($firstNames)) {
            $firstNames = [$firstNames];
        }
        if (!is_array($lastNames)) {
            $lastNames = [$lastNames];
        }

        $participants = [];
        $count = max(count($firstNames), count($lastNames));
        for ($i = 0; $i < $count; $i++) {
            $first = trim($firstNames[$i] ?? '');
            $last  = trim($lastNames[$i] ?? '');
            if ($first === '' && $last === '') {
                continue;
            }
            if ($first === '' || $last === '') {
                $participants = [];
                setFlash('danger', 'Please enter both first name and last name for every participant.');
                header('Location: tournaments.php'); exit;
            }
            $participants[] = ['first' => $first, 'last' => $last];
        }

        if ($tid && !empty($participants)) {
            $t = getTournamentById($tid);
            if ($t) {
                $available = $t['max_players'] - $t['registered_count'];
                if ($available <= 0) {
                    setFlash('danger', 'This tournament is already full.');
                } elseif (count($participants) > $available) {
                    setFlash('danger', 'You can only register up to ' . $available . ' more participant(s) for this tournament.');
                } else {
                    $addedCount = 0;
                    foreach ($participants as $participant) {
                        if (!isTournamentGuestRegistered($tid, $participant['first'], $participant['last'])) {
                            if (addTournamentGuest($tid, null, $participant['first'], $participant['last'], 'approved')) {
                                $addedCount++;
                            }
                        }
                    }
                    if ($addedCount > 0) {
                        if ($addedCount === count($participants)) {
                            $message = $addedCount === 1
                                ? 'Participant ' . $participants[0]['first'] . ' ' . $participants[0]['last'] . ' registered successfully!'
                                : $addedCount . ' participant(s) registered successfully!';
                            setFlash('success', $message);
                        } else {
                            setFlash('warning', $addedCount . ' participant(s) registered successfully. Duplicate entry(ies) were skipped.');
                        }
                    } else {
                        setFlash('danger', 'These participant(s) are already registered for this tournament.');
                    }
                }
            }
        } else {
            if (empty($_SESSION['flash'] ?? [])) {
                setFlash('danger', 'Please enter at least one participant with both first name and last name.');
            }
        }
        header('Location: tournaments.php'); exit;
    }

    if ($action === 'approve_registration') {
        $tid = (int) ($_POST['tournament_id'] ?? 0);
        $regType = $_POST['reg_type'] ?? '';
        $regId = (int) ($_POST['reg_id'] ?? 0);
        if ($tid && $regId && isTournamentOwnedBy($tid, $userId)) {
            if (approveTournamentRegistration($tid, $regType, $regId, true)) {
                setFlash('success', 'Registration approved.');
            } else {
                setFlash('danger', 'Could not approve registration.');
            }
        }
        header('Location: tournaments.php');
        exit;
    }

    if ($action === 'reject_registration') {
        $tid = (int) ($_POST['tournament_id'] ?? 0);
        $regType = $_POST['reg_type'] ?? '';
        $regId = (int) ($_POST['reg_id'] ?? 0);
        if ($tid && $regId && isTournamentOwnedBy($tid, $userId)) {
            if (rejectTournamentRegistration($tid, $regType, $regId)) {
                setFlash('success', 'Registration declined and removed.');
            } else {
                setFlash('danger', 'Could not decline registration.');
            }
        }
        header('Location: tournaments.php');
        exit;
    }

    if ($action === 'remove_registration') {
        $tid = (int) ($_POST['tournament_id'] ?? 0);
        $regType = $_POST['reg_type'] ?? '';
        $regId = (int) ($_POST['reg_id'] ?? 0);
        if ($tid && $regId && isTournamentOwnedBy($tid, $userId)) {
            $t = getTournamentById($tid);
            if ($t && ($t['status'] ?? '') === 'completed') {
                setFlash('danger', 'Cannot remove participants from a completed tournament.');
            } elseif (removeTournamentRegistration($tid, $regType, $regId)) {
                setFlash('success', 'Participant removed from the tournament.');
            } else {
                setFlash('danger', 'Could not remove the participant.');
            }
        }
        header('Location: tournaments.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['tournament_id'] ?? 0);
        if ($id && isTournamentOwnedBy($id, $userId)) {
            deleteTournament($id);
            setFlash('success', 'Tournament removed successfully.');
        } else {
            setFlash('danger', 'You can only delete tournaments you created.');
        }
        header('Location: tournaments.php');
        exit;
    }
}

$tournaments = getOrganizerTournaments($userId);
$allPlayers  = getAllPlayers();

$activeTournaments = array_values(array_filter($tournaments, static fn($t) => ($t['status'] ?? '') !== 'completed'));
$completedTournaments = array_values(array_filter($tournaments, static fn($t) => ($t['status'] ?? '') === 'completed'));

function renderTournamentCard(array $t, int $userId): void {
    $tPlayers = getTournamentPlayers($t['id'], 'approved');
    $tGuests  = getTournamentGuests($t['id'], 'approved');
    $pendingGroups = getPendingRegistrationGroups($t['id']);
    $pendingLegacy = array_values(array_filter(
        getPendingTournamentRegistrations($t['id']),
        static fn($pr) => ($pr['reg_type'] ?? '') === 'player'
    ));
    $hasPending = !empty($pendingGroups) || !empty($pendingLegacy);
    $isCompletedTournament = ($t['status'] ?? '') === 'completed';
    ?>
    <div class="tournament-card">
        <div class="tournament-card-header">
            <div style="flex:1">
                <div class="tournament-name"><?= e($t['name']) ?></div>
                <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                    <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                        <?= e($t['category'] ?? 'Open Singles') ?>
                    </span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                <form method="POST" style="display: inline; margin: 0;">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                    <select name="status" class="badge badge-<?= e($t['status']) ?>" style="border: 1px solid rgba(255,255,255,0.15); font-family: inherit; font-size: 11px; font-weight: 600; cursor: pointer; outline: none; padding: 4px 10px; height: auto; border-radius: 20px; text-transform: capitalize;" onchange="this.form.submit()">
                        <option value="upcoming"  <?= $t['status']==='upcoming'?'selected':''  ?> style="background: var(--bg-700); color: var(--text-100);">Upcoming</option>
                        <option value="ongoing"   <?= $t['status']==='ongoing'?'selected':''   ?> style="background: var(--bg-700); color: var(--text-100);">Ongoing</option>
                        <option value="completed" <?= $t['status']==='completed'?'selected':'' ?> style="background: var(--bg-700); color: var(--text-100);">Completed</option>
                        <option value="cancelled" <?= $t['status']==='cancelled'?'selected':'' ?> style="background: var(--bg-700); color: var(--text-100);">Cancelled</option>
                    </select>
                </form>
                <form method="POST" style="display: inline; margin: 0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 6px 8px; min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;" title="Remove tournament" data-confirm="Remove tournament &quot;<?= e($t['name']) ?>&quot;? This cannot be undone. All registrations and matches will be deleted.">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </button>
                </form>
            </div>
        </div>
        <div class="tournament-meta">
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= date('M j, Y', strtotime($t['start_date'])) ?><?= $t['end_date'] ? ' — '.date('M j, Y', strtotime($t['end_date'])) : '' ?></span>
            <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><?= $t['registered_count'] ?>/<?= $t['max_players'] ?> players</span>
            <?php if ($t['venue']): ?><span>📍 <?= e($t['venue']) ?></span><?php endif; ?>
            <?php if ($fee = formatRegistrationFee($t)): ?><span>💳 Reg. fee: <?= e($fee) ?></span><?php endif; ?>
        </div>

        <?php if ($hasPending): ?>
        <div style="margin-bottom:14px;padding:12px;background:rgba(255,200,87,0.08);border:1px solid rgba(255,200,87,0.25);border-radius:var(--radius-sm)">
            <div class="text-xs text-muted mb-8" style="text-transform:uppercase;letter-spacing:.5px;color:var(--warning)">Pending approval</div>
            <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($pendingGroups as $group):
                $proofUrl = paymentProofPublicUrl($group['payment_proof_path'] ?? null);
                $isPdf = $proofUrl && str_ends_with(strtolower($group['payment_proof_path'] ?? ''), '.pdf');
            ?>
                <div style="padding:10px;background:var(--bg-700);border-radius:var(--radius-sm)">
                    <div style="font-size:11px;font-weight:700;color:var(--text-300);margin-bottom:8px">
                        Submitted by <?= e($group['submitter_name']) ?>
                    </div>
                    <?php if ($proofUrl): ?>
                    <div style="margin-bottom:10px">
                        <div class="text-xs text-muted mb-6" style="font-weight:600">Payment proof</div>
                        <?php if ($isPdf): ?>
                        <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm" style="font-size:11px">View PDF — <?= e($group['payment_proof_original_name'] ?? 'receipt') ?></a>
                        <?php else: ?>
                        <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($proofUrl) ?>" alt="Payment proof" style="max-width:100%;max-height:160px;border-radius:var(--radius-sm);border:1px solid var(--border);display:block">
                        </a>
                        <div class="text-xs text-muted" style="margin-top:4px"><?= e($group['payment_proof_original_name'] ?? '') ?></div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-muted" style="margin:0 0 10px">No payment proof uploaded.</p>
                    <?php endif; ?>
                    <div style="display:flex;flex-direction:column;gap:6px">
                    <?php foreach ($group['players'] as $pl):
                        $displayName = trim($pl['first_name'] . ' ' . $pl['last_name']);
                    ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:6px 8px;background:var(--bg-800);border-radius:var(--radius-sm)">
                            <span style="font-size:12px;font-weight:600;color:var(--text-200)">
                                <?= e($displayName) ?>
                                <?php if (!empty($pl['club'])): ?>
                                <span class="text-muted" style="font-weight:500"> · <?= e($pl['club']) ?></span>
                                <?php endif; ?>
                            </span>
                            <div style="display:flex;gap:6px">
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve_registration">
                                    <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="reg_type" value="guest">
                                    <input type="hidden" name="reg_id" value="<?= (int) $pl['reg_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px;font-size:11px;height:auto">Approve</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="reject_registration">
                                    <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="reg_type" value="guest">
                                    <input type="hidden" name="reg_id" value="<?= (int) $pl['reg_id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:11px;height:auto" data-confirm="Decline this player?">Decline</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php foreach ($pendingLegacy as $pr):
                $displayName = trim($pr['first_name'] . ' ' . $pr['last_name']);
            ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;padding:8px 10px;background:var(--bg-700);border-radius:var(--radius-sm)">
                    <div style="font-size:12px;font-weight:600;color:var(--text-200)"><?= e($displayName) ?></div>
                    <div style="display:flex;gap:6px">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_registration">
                            <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                            <input type="hidden" name="reg_type" value="player">
                            <input type="hidden" name="reg_id" value="<?= (int) $pr['reg_id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 10px;font-size:11px;height:auto">Approve</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="reject_registration">
                            <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                            <input type="hidden" name="reg_type" value="player">
                            <input type="hidden" name="reg_id" value="<?= (int) $pr['reg_id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:11px;height:auto" data-confirm="Decline this registration request?">Decline</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($tPlayers) || !empty($tGuests)): ?>
        <details style="margin-bottom:14px;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:var(--bg-900)">
            <summary style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;cursor:pointer;font-weight:700;color:var(--text-100);background:var(--bg-800)">
                <span>Approved players (<?= count($tPlayers) + count($tGuests) ?>)</span>
                <span style="font-size:12px;color:var(--text-400)">Click to expand</span>
            </summary>
            <div style="padding:12px;display:grid;gap:10px">
                <?php foreach ($tPlayers as $tp): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--bg-700);border:1px solid var(--border);border-radius:var(--radius-sm)">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--text-100);"><?= e($tp['first_name'].' '.$tp['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-400)">Registered player</div>
                    </div>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="remove_registration">
                        <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="reg_type" value="player">
                        <input type="hidden" name="reg_id" value="<?= (int) $tp['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-xs" style="padding:5px 9px;font-size:11px;height:auto;line-height:1" data-confirm="Remove <?= e($tp['first_name'].' '.$tp['last_name']) ?> from this tournament?" <?= $isCompletedTournament ? 'disabled title="Cannot remove players from a completed tournament." style="opacity:0.5;cursor:not-allowed;"' : '' ?>>Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php foreach ($tGuests as $tg): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:var(--bg-700);border:1px solid var(--border);border-radius:var(--radius-sm)">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--text-100);"><?= e($tg['first_name'].' '.$tg['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-400)">
                            <?= e($tg['club'] ?: 'No club') ?><?= !empty($tg['nationality']) ? ' · ' . e($tg['nationality']) : '' ?>
                        </div>
                    </div>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="remove_registration">
                        <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="reg_type" value="guest">
                        <input type="hidden" name="reg_id" value="<?= (int) $tg['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-xs" style="padding:5px 9px;font-size:11px;height:auto;line-height:1" data-confirm="Remove <?= e($tg['first_name'].' '.$tg['last_name']) ?> from this tournament?" <?= $isCompletedTournament ? 'disabled title="Cannot remove players from a completed tournament." style="opacity:0.5;cursor:not-allowed;"' : '' ?>>Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>

        <div class="tournament-footer">
            <div class="progress-bar" style="width:100px">
                <div class="progress-fill" style="width:<?= $t['max_players']>0?min(100,round($t['registered_count']/$t['max_players']*100)):0 ?>%"></div>
            </div>
            <div class="btn-group">
                <button class="btn btn-ghost btn-sm" onclick="openEditTournament(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </button>
                
                <?php if ($t['status'] === 'upcoming'): ?>
                    <form method="POST" style="display:inline; margin: 0;">
                        <input type="hidden" name="action" value="start_bracketing">
                        <input type="hidden" name="tournament_id" value="<?= (int) $t['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm" title="Start bracketing and view bracket">Check Bracket</button>
                    </form>
                <?php else: ?>
                    <a href="/TournamentHQ/organizer/bracket_generator.php?tournament_id=<?= (int) $t['id'] ?>" class="btn btn-outline btn-sm" title="View tournament bracket">Check Bracket</a>
                <?php endif; ?>
                
                <?php 
                $isFull = $t['registered_count'] >= $t['max_players'];
                ?>
                <button
                    type="button"
                    class="btn btn-accent btn-sm js-org-register-player"
                    data-tid="<?= (int) $t['id'] ?>"
                    data-tname="<?= e($t['name']) ?>"
                    data-available="<?= max(0, $t['max_players'] - $t['registered_count']) ?>"
                    <?= $isFull ? 'disabled style="opacity: 0.5; cursor: not-allowed; background: var(--bg-600); color: var(--text-400);"' : '' ?>
                ><?= $isFull ? 'Full' : '+ Player' ?></button>
            </div>
        </div>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>My Tournaments</h1>
        <p>Create and manage your assigned tournaments</p>
    </div>
    <button class="btn btn-primary" data-modal-open="createTModal">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Tournament
    </button>
</div>

<?php if (empty($tournaments)): ?>
    <div class="empty-state"><div class="empty-icon">🏆</div><h3>No tournaments yet</h3><p>Create your first tournament to get started.</p></div>
<?php else: ?>
    <?php if (!empty($activeTournaments)): ?>
    <div style="margin-bottom: 28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
            <div>
                <div style="font-size:18px;font-weight:700;color:var(--text-100)">Active Tournaments</div>
                <div style="font-size:13px;color:var(--text-400);">Upcoming, ongoing, and cancelled tournaments appear here.</div>
            </div>
        </div>
        <div class="tournament-grid">
            <?php foreach ($activeTournaments as $t): renderTournamentCard($t, $userId); endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($completedTournaments)): ?>
    <div style="margin-bottom: 28px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
            <div>
                <div style="font-size:18px;font-weight:700;color:var(--text-100)">Completed Tournaments</div>
                <div style="font-size:13px;color:var(--text-400);">Finished tournaments are shown separately below.</div>
            </div>
        </div>
        <div class="tournament-grid">
            <?php foreach ($completedTournaments as $t): renderTournamentCard($t, $userId); endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Create Tournament Modal -->
<div class="modal-overlay" id="createTModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <div class="modal-title">New Tournament</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tournament Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Tournament name">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" id="createStart" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="createEnd" class="form-control">
                    </div>
                </div>
                <!-- Dynamic Categories Container -->
                <div style="margin-top: 18px; margin-bottom: 8px;">
                    <label class="form-label" style="font-weight: 700; color: var(--text-200); font-size: 14px; margin-bottom: 12px; display: block;">Tournament Categories *</label>
                    <div id="categoriesContainer">
                        
                        <!-- Initial Default Category Block -->
                        <div class="category-row-block" style="border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px; position: relative; background: rgba(255,255,255,0.01);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-size: 13px; font-weight: 700; color: var(--primary-light);">Category #1</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="cat_name[]" class="form-control" required placeholder="e.g. Men's Singles">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Max Players</label>
                                    <input type="number" name="cat_max_players[]" class="form-control" value="16" min="2" placeholder="e.g. 16">
                                </div>
                            </div>
                            <?php $namePrefix = 'cat_'; $values = []; include __DIR__ . '/../includes/tournament_prize_fields.php'; ?>
                        </div>

                    </div>
                    
                    <button type="button" class="btn btn-outline btn-sm" onclick="addCategoryRow()" style="width: 100%; justify-content: center; margin-top: 6px; height: 38px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Category
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Register Player Modal -->
<div class="modal-overlay" id="regModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title">Register Participant</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" id="orgRegisterForm">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="tournament_id" id="regTId">
            <div class="modal-body">
                <p class="text-muted text-sm mb-16" id="regTName"></p>
                <p class="text-xs" style="margin-bottom: 12px; color: var(--accent); font-weight: 600;" id="regSlotsInfo"></p>
                <div id="participantRows">
                    <div class="participant-row" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:10px; align-items:center; margin-bottom:10px; padding:10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm);">
                        <div class="form-group" style="margin:0;">
                            <input type="text" name="first_name[]" class="form-control" required placeholder="First Name">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <input type="text" name="last_name[]" class="form-control" required placeholder="Last Name">
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeParticipantRow(this)" style="padding:0; width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </div>
                </div>
                <button type="button" id="addParticipantBtn" class="btn btn-outline btn-sm" onclick="addParticipantRow()" style="width:100%; justify-content:center; margin-top:10px; height:38px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add another participant
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-accent">Register</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tournament Modal -->
<div class="modal-overlay" id="editTournamentModal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <div class="modal-title">Edit Tournament</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tournament_id" id="etId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tournament Name *</label>
                    <input type="text" name="name" id="etName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="etDesc" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" id="etCategory" class="form-control" required placeholder="e.g. Open Singles">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="etStart" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="etEnd" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Players</label>
                        <input type="number" name="max_players" id="etMax" class="form-control">
                    </div>
                </div>
                <div id="etPrizeFields">
                    <?php $namePrefix = ''; $values = []; include __DIR__ . '/../includes/tournament_prize_fields.php'; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
var regAvailableSlots = 999;

function openRegModal(tid, name, available) {
    regAvailableSlots = available;
    document.getElementById('regTId').value = tid;
    document.getElementById('regTName').textContent = 'Tournament: ' + name;
    document.getElementById('regSlotsInfo').textContent = available + ' slot' + (available !== 1 ? 's' : '') + ' available';

    // Reset to a single row
    var container = document.getElementById('participantRows');
    var rows = container.querySelectorAll('.participant-row');
    for (var i = rows.length - 1; i > 0; i--) rows[i].remove();
    // Clear the first row inputs
    var firstRow = container.querySelector('.participant-row');
    if (firstRow) {
        firstRow.querySelectorAll('input').forEach(function(inp) { inp.value = ''; });
    }

    updateAddBtnState();

    var overlay = document.getElementById('regModal');
    if (window.TTMS && typeof TTMS.openModal === 'function') {
        TTMS.openModal('regModal');
    } else if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function updateAddBtnState() {
    var container = document.getElementById('participantRows');
    var currentRows = container.querySelectorAll('.participant-row').length;
    var addBtn = document.getElementById('addParticipantBtn');
    if (currentRows >= regAvailableSlots) {
        addBtn.disabled = true;
        addBtn.style.opacity = '0.4';
        addBtn.style.cursor = 'not-allowed';
    } else {
        addBtn.disabled = false;
        addBtn.style.opacity = '1';
        addBtn.style.cursor = 'pointer';
    }
}

function addParticipantRow() {
    var container = document.getElementById('participantRows');
    var currentRows = container.querySelectorAll('.participant-row').length;
    if (currentRows >= regAvailableSlots) {
        alert('No more slots available. Maximum ' + regAvailableSlots + ' participant(s) can be added.');
        return;
    }
    var row = document.createElement('div');
    row.className = 'participant-row';
    row.style = 'display:grid; grid-template-columns: 1fr 1fr auto; gap:10px; align-items:center; margin-bottom:10px; padding:10px; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm);';
    row.innerHTML = `
        <div class="form-group" style="margin:0;">
            <input type="text" name="first_name[]" class="form-control" required placeholder="First Name">
        </div>
        <div class="form-group" style="margin:0;">
            <input type="text" name="last_name[]" class="form-control" required placeholder="Last Name">
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeParticipantRow(this)" style="padding:0; width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
        </button>
    `;
    container.appendChild(row);
    updateAddBtnState();
}

function removeParticipantRow(button) {
    var container = document.getElementById('participantRows');
    if (!container) return;
    var rows = container.querySelectorAll('.participant-row');
    if (rows.length <= 1) {
        alert('At least one participant row is required.');
        return;
    }
    var row = button.closest('.participant-row');
    if (row) {
        row.remove();
        updateAddBtnState();
    }
}

function addCategoryRow() {
    const container = document.getElementById('categoriesContainer');
    const rowCount = container.querySelectorAll('.category-row-block').length + 1;
    
    const newBlock = document.createElement('div');
    newBlock.className = 'category-row-block';
    newBlock.style = 'border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px; position: relative; background: rgba(255,255,255,0.01);';
    newBlock.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="font-size: 13px; font-weight: 700; color: var(--primary-light);">Category #${rowCount}</span>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeCategoryRow(this)" style="padding: 4px 8px; font-size: 11px; height: auto;">Remove</button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="cat_name[]" class="form-control" required placeholder="e.g. Men's Singles">
            </div>
            <div class="form-group">
                <label class="form-label">Max Players</label>
                <input type="number" name="cat_max_players[]" class="form-control" value="16" min="2" placeholder="e.g. 16">
            </div>
        </div>
        <div class="prize-pool-fields">
            <label class="form-label" style="margin-bottom: 10px;">Prize Pool</label>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label text-xs">Champion</label>
                    <input type="text" name="cat_prize_champion[]" class="form-control" placeholder="e.g. ₱10,000">
                </div>
                <div class="form-group">
                    <label class="form-label text-xs">2nd Place</label>
                    <input type="text" name="cat_prize_2nd[]" class="form-control" placeholder="e.g. ₱5,000">
                </div>
            </div>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label class="form-label text-xs">3rd Place</label>
                    <input type="text" name="cat_prize_3rd[]" class="form-control" placeholder="e.g. ₱3,000">
                </div>
                <div class="form-group">
                    <label class="form-label text-xs">4th Place</label>
                    <input type="text" name="cat_prize_4th[]" class="form-control" placeholder="e.g. ₱1,500">
                </div>
            </div>
            <div class="form-group" style="margin-top: 14px; margin-bottom: 0;">
                <label class="form-label">Registration Fee</label>
                <input type="text" name="cat_registration_fee[]" class="form-control" placeholder="e.g. ₱500 or Free">
            </div>
        </div>
    `;
    container.appendChild(newBlock);
}

function removeCategoryRow(button) {
    const block = button.closest('.category-row-block');
    if (block) {
        block.remove();
        // Reindex labels
        const container = document.getElementById('categoriesContainer');
        const labels = container.querySelectorAll('.category-row-block span');
        labels.forEach((label, idx) => {
            label.textContent = 'Category #' + (idx + 1);
        });
    }
}

// Set min dates and bind change listeners to keep end date >= start date
(function() {
    var today = new Date();
    var yyyy = today.getFullYear();
    var mm = String(today.getMonth() + 1).padStart(2, '0');
    var dd = String(today.getDate()).padStart(2, '0');
    var minDate = yyyy + '-' + mm + '-' + dd;

    var createStart = document.getElementById('createStart');
    var createEnd = document.getElementById('createEnd');
    var etStart = document.getElementById('etStart');
    var etEnd = document.getElementById('etEnd');

    if (createStart && createEnd) {
        createStart.setAttribute('min', minDate);
        createEnd.setAttribute('min', minDate);

        createStart.addEventListener('change', function() {
            var val = createStart.value;
            if (val) {
                createEnd.setAttribute('min', val);
                if (createEnd.value && createEnd.value < val) {
                    createEnd.value = val;
                }
            } else {
                createEnd.setAttribute('min', minDate);
            }
        });
    }

    if (etStart && etEnd) {
        etStart.addEventListener('change', function() {
            var val = etStart.value;
            if (val) {
                etEnd.setAttribute('min', val);
                if (etEnd.value && etEnd.value < val) {
                    etEnd.value = val;
                }
            }
        });
    }
})();

function openEditTournament(t) {
    document.getElementById('etId').value     = t.id;
    document.getElementById('etName').value   = t.name;
    document.getElementById('etDesc').value   = t.description || '';
    document.getElementById('etCategory').value = t.category || '';

    document.getElementById('etStart').value  = t.start_date;
    document.getElementById('etEnd').value    = t.end_date || '';
    document.getElementById('etMax').value    = t.max_players;

    var etStartInput = document.getElementById('etStart');
    var etEndInput = document.getElementById('etEnd');
    if (etStartInput && etEndInput) {
        var todayVal = new Date().toISOString().split('T')[0];
        var minStart = t.start_date && t.start_date < todayVal ? t.start_date : todayVal;
        etStartInput.setAttribute('min', minStart);
        etEndInput.setAttribute('min', t.start_date || todayVal);
    }

    var setPrize = function(id, val) { var el = document.querySelector('#etPrizeFields [name="' + id + '"]'); if (el) el.value = val || ''; };
    setPrize('prize_champion', t.prize_champion);
    setPrize('prize_2nd', t.prize_2nd);
    setPrize('prize_3rd', t.prize_3rd);
    setPrize('prize_4th', t.prize_4th);
    setPrize('registration_fee', t.registration_fee);
    TTMS.openModal('editTournamentModal');
}

function toggleEditCustomCategory(val) {
    var group = document.getElementById('editCustomCategoryGroup');
    var input = document.getElementById('editCustomCategoryInput');
    if (group && input) {
        if (val === 'custom') {
            group.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            group.style.display = 'none';
            input.required = false;
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
var orgRegisterForm = document.getElementById('orgRegisterForm');
if (orgRegisterForm) {
    orgRegisterForm.addEventListener('submit', function (event) {
        var submitButton = this.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.style.opacity = '0.6';
            submitButton.style.cursor = 'not-allowed';
        }
    });
}

document.querySelectorAll('.js-org-register-player').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openRegModal(
            parseInt(btn.getAttribute('data-tid'), 10),
            btn.getAttribute('data-tname') || '',
            parseInt(btn.getAttribute('data-available'), 10) || 0
        );
    });
});
</script>
