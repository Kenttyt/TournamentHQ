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

    if ($action === 'register') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        
        if ($tid && $first !== '' && $last !== '') {
            $t = getTournamentById($tid);
            if ($t) {
                if ($t['registered_count'] >= $t['max_players']) {
                    setFlash('danger', 'This tournament is already full.');
                } else {
                    addTournamentGuest($tid, null, $first, $last, 'approved');
                    setFlash('success', 'Participant ' . $first . ' ' . $last . ' registered successfully!');
                }
            }
        } else {
            setFlash('danger', 'Please enter both first name and last name.');
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
<div class="tournament-grid">
<?php foreach ($tournaments as $t):
    $tPlayers = getTournamentPlayers($t['id'], 'approved');
    $tGuests  = getTournamentGuests($t['id'], 'approved');
    $pendingGroups = getPendingRegistrationGroups($t['id']);
    $pendingLegacy = array_values(array_filter(
        getPendingTournamentRegistrations($t['id']),
        static fn($pr) => ($pr['reg_type'] ?? '') === 'player'
    ));
    $hasPending = !empty($pendingGroups) || !empty($pendingLegacy);
?>
<div class="tournament-card">
    <div class="tournament-card-header">
        <div>
            <div class="tournament-name"><?= e($t['name']) ?></div>
            <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                    <?= e($t['category'] ?? 'Open Singles') ?>
                </span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
            <span class="badge badge-<?= e($t['status']) ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
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
        <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= date('M j, Y', strtotime($t['start_date'])) ?></span>
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
                        <span style="font-size:12px;font-weight:600;color:var(--text-200)"><?= e($displayName) ?></span>
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
    <div style="margin-bottom:14px">
        <div class="text-xs text-muted mb-8" style="text-transform:uppercase;letter-spacing:.5px">Approved players</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($tPlayers as $tp): ?>
            <span style="background:var(--bg-600);border-radius:20px;padding:4px 10px;font-size:11px;font-weight:600;color:var(--text-300)"><?= e($tp['first_name'].' '.$tp['last_name']) ?></span>
        <?php endforeach; ?>
        <?php foreach ($tGuests as $tg): ?>
            <span style="background:var(--bg-600);border-radius:20px;padding:4px 10px;font-size:11px;font-weight:600;color:var(--text-300)"><?= e($tg['first_name'].' '.$tg['last_name']) ?></span>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="tournament-footer">
        <div class="progress-bar" style="width:100px">
            <div class="progress-fill" style="width:<?= $t['max_players']>0?min(100,round($t['registered_count']/$t['max_players']*100)):0 ?>%"></div>
        </div>
        <div class="btn-group">
            <!-- Quick status change -->
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                <select name="status" class="form-select" style="font-size:11px;padding:5px 8px;height:auto" onchange="this.form.submit()">
                    <option value="upcoming"  <?= $t['status']==='upcoming'?'selected':''  ?>>Upcoming</option>
                    <option value="ongoing"   <?= $t['status']==='ongoing'?'selected':''   ?>>Ongoing</option>
                    <option value="completed" <?= $t['status']==='completed'?'selected':'' ?>>Completed</option>
                    <option value="cancelled" <?= $t['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
            </form>
            <button
                type="button"
                class="btn btn-accent btn-sm js-org-register-player"
                data-tid="<?= (int) $t['id'] ?>"
                data-tname="<?= e($t['name']) ?>"
            >+ Player</button>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
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
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
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
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="tournament_id" id="regTId">
            <div class="modal-body">
                <p class="text-muted text-sm mb-16" id="regTName"></p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required placeholder="e.g. John">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required placeholder="e.g. Doe">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-accent">Register</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRegModal(tid, name) {
    document.getElementById('regTId').value = tid;
    document.getElementById('regTName').textContent = 'Tournament: ' + name;
    const overlay = document.getElementById('regModal');
    if (window.TTMS && typeof TTMS.openModal === 'function') {
        TTMS.openModal('regModal');
    } else if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.querySelectorAll('.js-org-register-player').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openRegModal(
            parseInt(btn.getAttribute('data-tid'), 10),
            btn.getAttribute('data-tname') || ''
        );
    });
});
</script>
