<?php
/**
 * Admin — Reports
 */
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/matches/match_functions.php';
require_once __DIR__ . '/../modules/players/player_functions.php';

$playersList   = getAllPlayers();
$tournaments   = getAllTournaments();
$recentResults = getRecentMatches(10);
$totalPlayers  = getPlayerCount();
$totalMatches  = getMatchCount();

// Compute totals
$totalWins = 0; $totalLosses = 0;
foreach ($playersList as $p) { $totalWins += $p['wins']; $totalLosses += $p['losses']; }

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Reports & Analytics</h1>
        <p>System-wide statistics, global rankings, and match history</p>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
    <div class="stat-card">
        <div class="stat-icon purple">👤</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalPlayers ?></div>
            <div class="stat-label">Registered Players</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">🏆</div>
        <div class="stat-info">
            <div class="stat-value"><?= count($tournaments) ?></div>
            <div class="stat-label">Total Tournaments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">⚡</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalMatches ?></div>
            <div class="stat-label">Total Matches</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🎯</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalWins ?></div>
            <div class="stat-label">Total Sets Won</div>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- Registered Players -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Registered Players
            </div>
        </div>
        <div class="card-body" style="padding:0">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Player</th><th>Gender</th><th>Place</th><th>W</th><th>L</th></tr>
                </thead>
                <tbody>
                <?php foreach ($playersList as $i => $p): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $i+1 ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="p-avatar" style="width:30px;height:30px;font-size:11px"><?= strtoupper(substr($p['first_name'],0,1)) ?></div>
                            <div>
                                <div class="p-name"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
                                <div class="p-club"><?= e($p['club'] ?: '—') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= e($p['gender']) ?>"><?= ucfirst(e($p['gender'])) ?></span></td>
                    <td class="text-sm"><?= e($p['nationality'] ?: '—') ?></td>
                    <td class="text-success font-bold"><?= $p['wins'] ?></td>
                    <td class="text-muted"><?= $p['losses'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tournament Summary -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Tournament Summary
            </div>
        </div>
        <div class="card-body" style="padding:0">
            <table class="data-table">
                <thead><tr><th>Tournament</th><th>Format</th><th>Players</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($tournaments as $t): ?>
                <tr>
                    <td>
                        <strong><?= e($t['name']) ?></strong>
                        <div class="text-muted text-xs"><?= date('M Y', strtotime($t['start_date'])) ?></div>
                    </td>
                    <td class="text-sm"><?= ucfirst(str_replace('_',' ',$t['format'])) ?></td>
                    <td class="text-sm"><?= $t['registered_count'] ?>/<?= $t['max_players'] ?></td>
                    <td><span class="badge badge-<?= e($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Match Results -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Recent Match Results
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Winner</th><th>Loser</th><th>Score</th><th>Round</th><th>Tournament</th></tr></thead>
            <tbody>
            <?php if (empty($recentResults)): ?>
                <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">⚡</div><p>No completed matches yet</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($recentResults as $m):
                $winFirst = $m['winner_id']==$m['player1_id'] ? $m['p1_first'] : $m['p2_first'];
                $winLast  = $m['winner_id']==$m['player1_id'] ? $m['p1_last']  : $m['p2_last'];
                $loseFirst= $m['winner_id']==$m['player1_id'] ? $m['p2_first'] : $m['p1_first'];
                $loseLast = $m['winner_id']==$m['player1_id'] ? $m['p2_last']  : $m['p1_last'];
                $winScore = $m['winner_id']==$m['player1_id'] ? $m['player1_score'] : $m['player2_score'];
                $loseScore= $m['winner_id']==$m['player1_id'] ? $m['player2_score'] : $m['player1_score'];
            ?>
            <tr>
                <td><strong class="text-success">🏆 <?= e($winFirst.' '.$winLast) ?></strong></td>
                <td class="text-muted"><?= e($loseFirst.' '.$loseLast) ?></td>
                <td><strong><?= $winScore ?> — <?= $loseScore ?></strong></td>
                <td class="text-sm"><?= e($m['round_name']) ?></td>
                <td class="text-sm text-muted"><?= e($m['tournament_name']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
