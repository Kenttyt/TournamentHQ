<?php
/**
 * Organizer — View Players
 */
$pageTitle = 'Players';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);
require_once __DIR__ . '/../modules/players/player_functions.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';

$userId = (int)$_SESSION['user_id'];
$myTourneys = getOrganizerTournaments($userId);
$tidFilter  = (int)($_GET['tournament_id'] ?? 0);

if ($tidFilter) {
    $players = getTournamentPlayers($tidFilter);
    foreach ($players as &$p) {
        $p['is_guest'] = false;
    }
    unset($p);
    
    $guests = getTournamentGuests($tidFilter);
    foreach ($guests as $g) {
        $regName = trim(($g['reg_first'] ?? '') . ' ' . ($g['reg_last'] ?? ''));
        $players[] = [
            'id' => 0,
            'first_name' => $g['first_name'],
            'last_name' => $g['last_name'],
            'club' => $g['club'] ?? '',
            'nationality' => $g['nationality'] ?? '',
            'wins' => 0,
            'losses' => 0,
            'is_guest' => true,
            'registered_by_name' => $regName ?: 'Organizer/Admin',
        ];
    }
} else {
    $players = getAllPlayers();
    foreach ($players as &$p) {
        $p['is_guest'] = false;
    }
    unset($p);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Players</h1>
        <p>View players registered across your tournaments</p>
    </div>
</div>

<!-- Tournament Filter -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <div class="filter-bar">
            <div class="search-wrap">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="form-control" placeholder="Search players…" data-search-table="orgPlayersTable">
            </div>
            <select class="form-select" style="width:220px" onchange="window.location='players.php?tournament_id='+this.value">
                <option value="">All Players</option>
                <?php foreach ($myTourneys as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tidFilter==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="data-table" id="orgPlayersTable">
                <thead>
                    <tr><th>#</th><th>Player</th><th>Club</th><th>Place</th></tr>
                </thead>
                <tbody>
                <?php if (empty($players)): ?>
                    <tr><td colspan="4"><div class="empty-state"><h3>No players</h3><p>No players found for this filter.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($players as $i => $p): 
                    $isGuest = $p['is_guest'] ?? false;
                ?>
                <tr>
                    <td class="text-muted text-sm"><?= $i+1 ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="p-avatar" style="font-size: 18px; <?= $isGuest ? 'background: linear-gradient(135deg, var(--primary), var(--accent)); border: 1px dashed var(--border);' : '' ?>"><?= getPlayerGenderAvatar($p['gender'] ?? null, $isGuest) ?></div>
                            <div>
                                <div class="p-name">
                                    <?= e($p['first_name'].' '.$p['last_name']) ?>
                                    <?php if ($isGuest): ?>
                                        <span class="badge badge-ongoing" style="font-size: 9px; padding: 2px 6px; margin-left: 6px; background: rgba(108,99,255,0.15); color: var(--primary-light); border: 1px solid rgba(108,99,255,0.3);">Guest</span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-club">
                                    <?php if ($isGuest && !empty($p['registered_by_name'])): ?>
                                        Registered by <?= e($p['registered_by_name']) ?>
                                    <?php elseif (isset($p['username'])): ?>
                                        <?= e($p['username']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm"><?= e($p['club'] ?: '—') ?></td>
                    <td class="text-sm"><?= e($p['nationality'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
