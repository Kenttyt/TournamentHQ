<?php
/**
 * Organizer — View Players
 */
$pageTitle = 'Players';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../modules/players/player_functions.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';

$userId = (int)$_SESSION['user_id'];
$myTourneys = getOrganizerTournaments($userId);
$tidFilter  = (int)($_GET['tournament_id'] ?? 0);

$pagination = paginate(0, 20);

if ($tidFilter) {
    $allPlayers = getTournamentPlayers($tidFilter);
    $guests = getTournamentGuests($tidFilter);
    $totalPlayers = count($allPlayers) + count($guests);
    $pagination = paginate($totalPlayers, 20);

    $players = array_slice($allPlayers, $pagination['offset'], $pagination['perPage']);
    foreach ($players as &$p) {
        $p['is_guest'] = false;
    }
    unset($p);
    
    $guestsPage = array_slice($guests, max(0, $pagination['offset'] - count($allPlayers)), $pagination['perPage']);
    foreach ($guestsPage as $g) {
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
    $countStmt = db()->query("SELECT COUNT(*) FROM players");
    $totalPlayers = (int) $countStmt->fetchColumn();
    $pagination = paginate($totalPlayers, 20);

    $stmt = db()->prepare("SELECT p.*, u.username, u.email, u.is_active FROM players p JOIN users u ON p.user_id = u.id ORDER BY p.first_name ASC, p.last_name ASC LIMIT {$pagination['perPage']} OFFSET {$pagination['offset']}");
    $stmt->execute();
    $players = $stmt->fetchAll();
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
                <input type="text" class="form-control" placeholder="Search by name, club, or place…" data-search-table="orgPlayersTable">
            </div>
            <select class="form-select" style="width:220px" onchange="window.location='players.php?tournament_id='+this.value<?= isset($_GET['type']) ? '&type='+ urlencode($_GET['type']) : '' ?>">
                <option value="">All Tournaments</option>
                <?php foreach ($myTourneys as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $tidFilter==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" style="width:160px" id="playerTypeFilter" onchange="filterPlayerType(this.value)">
                <option value="">All Types</option>
                <option value="account" <?= ($_GET['type'] ?? '') === 'account' ? 'selected' : '' ?>>Account Players</option>
                <option value="guest" <?= ($_GET['type'] ?? '') === 'guest' ? 'selected' : '' ?>>Guests</option>
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
                <tr data-type="<?= $isGuest ? 'guest' : 'account' ?>">
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
    <?php
    $baseUrl = '/TournamentHQ/organizer/players.php' . ($tidFilter ? '?tournament_id=' . $tidFilter : '');
    require_once __DIR__ . '/../includes/pagination.php';
    ?>
</div>

<script>
function filterPlayerType(type) {
    var rows = document.querySelectorAll('#orgPlayersTable tbody tr[data-type]');
    rows.forEach(function(row) {
        if (!type || row.getAttribute('data-type') === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var saved = document.getElementById('playerTypeFilter');
    if (saved && saved.value) filterPlayerType(saved.value);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
