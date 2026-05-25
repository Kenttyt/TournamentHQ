<?php
/**
 * Admin Dashboard
 */
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../modules/players/player_functions.php';
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/matches/match_functions.php';

$totalPlayers     = getPlayerCount();
$totalTournaments = getTournamentCount();
$activeTourneys   = getActiveTournamentCount();
$totalMatches     = getMatchCount();
$recentMatches    = getRecentMatches(5);
$upcomingMatches  = getUpcomingMatches(5);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">🏓</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalPlayers ?></div>
            <div class="stat-label">Total Players</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">🏆</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalTournaments ?></div>
            <div class="stat-label">Tournaments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">⚡</div>
        <div class="stat-info">
            <div class="stat-value"><?= $activeTourneys ?></div>
            <div class="stat-label">Active Now</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🎯</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalMatches ?></div>
            <div class="stat-label">Total Matches</div>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="content-grid">
    <!-- Recent Results -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Recent Results
            </div>
            <a href="/table-tennis-system/admin/bracket_generator.php" class="btn btn-ghost btn-sm">Open Bracket</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($recentMatches)): ?>
                <div class="empty-state"><div class="empty-icon">🎯</div><p>No completed matches yet</p></div>
            <?php else: ?>
            <table class="data-table" id="recentMatchesTable">
                <thead><tr><th>Match</th><th>Score</th><th>Tournament</th></tr></thead>
                <tbody>
                <?php foreach ($recentMatches as $m): ?>
                <tr>
                    <td>
                        <div style="font-size:13px;">
                            <span style="color:<?= $m['winner_id']==$m['player1_id']?'var(--success)':'var(--text-400)' ?>;font-weight:600;"><?= e($m['p1_first'].' '.$m['p1_last']) ?></span>
                            <span style="color:var(--text-400);padding:0 4px;">vs</span>
                            <span style="color:<?= $m['winner_id']==$m['player2_id']?'var(--success)':'var(--text-400)' ?>;font-weight:600;"><?= e($m['p2_first'].' '.$m['p2_last']) ?></span>
                        </div>
                    </td>
                    <td><strong><?= $m['player1_score'] ?> — <?= $m['player2_score'] ?></strong></td>
                    <td class="text-muted text-sm"><?= e($m['tournament_name']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Tournaments -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Upcoming Tournaments
            </div>
            <a href="/table-tennis-system/admin/manage_tournaments.php" class="btn btn-ghost btn-sm">Manage</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php 
            $upcomingT = array_slice(array_filter(getAllTournaments(), function($t) { return $t['status'] === 'upcoming'; }), 0, 5);
            if (empty($upcomingT)): 
            ?>
                <div class="empty-state"><div class="empty-icon">🏆</div><p>No upcoming tournaments</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Tournament</th><th>Start Date</th><th>Capacity</th></tr></thead>
                <tbody>
                <?php foreach ($upcomingT as $t): ?>
                <tr>
                    <td><strong><?= e($t['name']) ?></strong><div class="text-xs text-muted"><?= e($t['category']) ?></div></td>
                    <td class="text-muted text-sm"><?= date('M j, Y', strtotime($t['start_date'])) ?></td>
                    <td class="text-sm font-semibold"><?= $t['registered_count'] ?> / <?= $t['max_players'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Upcoming Matches -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Upcoming Matches
        </div>
        <a href="/table-tennis-system/admin/bracket_generator.php" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Generate Bracket
        </a>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($upcomingMatches)): ?>
            <div class="empty-state"><div class="empty-icon">📅</div><p>No upcoming matches scheduled</p></div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Player 1</th><th>Player 2</th><th>Tournament</th><th>Date</th><th>Table</th></tr></thead>
            <tbody>
            <?php foreach ($upcomingMatches as $m): ?>
            <tr>
                <td><strong><?= e($m['p1_first'].' '.$m['p1_last']) ?></strong></td>
                <td><strong><?= e($m['p2_first'].' '.$m['p2_last']) ?></strong></td>
                <td class="text-sm"><?= e($m['tournament_name']) ?></td>
                <td class="text-sm text-muted"><?= $m['match_date'] ? date('M j, Y g:i A', strtotime($m['match_date'])) : '—' ?></td>
                <td class="text-sm">Table <?= e($m['table_number']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
