<?php
/**
 * Organizer Dashboard
 */
$pageTitle = 'Organizer Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/matches/match_functions.php';

$userId       = (int)$_SESSION['user_id'];
$myTournaments = getOrganizerTournaments($userId);
$upcomingMatches = getUpcomingMatches(6);
$recentResults   = getRecentMatches(5);

$totalTourneys  = count($myTournaments);
$activeTourneys = count(array_filter($myTournaments, fn($t) => $t['status'] === 'ongoing'));
$completedT     = count(array_filter($myTournaments, fn($t) => $t['status'] === 'completed'));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">🏆</div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalTourneys ?></div>
            <div class="stat-label">My Tournaments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">⚡</div>
        <div class="stat-info">
            <div class="stat-value"><?= $activeTourneys ?></div>
            <div class="stat-label">Active Now</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">✅</div>
        <div class="stat-info">
            <div class="stat-value"><?= $completedT ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📅</div>
        <div class="stat-info">
            <div class="stat-value"><?= count($upcomingMatches) ?></div>
            <div class="stat-label">Upcoming Matches</div>
        </div>
    </div>
</div>

<div class="content-grid">
    <!-- My Tournaments -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                My Tournaments
            </div>
            <a href="/table-tennis-system/organizer/tournaments.php" class="btn btn-primary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New
            </a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($myTournaments)): ?>
                <div class="empty-state"><div class="empty-icon">🏆</div><p>No tournaments yet. Create one!</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Tournament</th><th>Players</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($myTournaments as $t): ?>
                <tr>
                    <td>
                        <strong><?= e($t['name']) ?></strong>
                        <div class="text-muted text-xs"><?= date('M j, Y', strtotime($t['start_date'])) ?></div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="progress-bar" style="width:60px">
                                <div class="progress-fill" style="width:<?= $t['max_players']>0?min(100,round($t['registered_count']/$t['max_players']*100)):0 ?>%"></div>
                            </div>
                            <span class="text-sm"><?= $t['registered_count'] ?>/<?= $t['max_players'] ?></span>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= e($t['status']) ?>"><?= ucfirst($t['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Recent Results
            </div>
            <a href="/table-tennis-system/organizer/bracket_generator.php" class="btn btn-ghost btn-sm">Open Bracket</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($recentResults)): ?>
                <div class="empty-state"><div class="empty-icon">⚡</div><p>No results yet</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Match</th><th>Score</th></tr></thead>
                <tbody>
                <?php foreach ($recentResults as $m): ?>
                <tr>
                    <td style="font-size:13px">
                        <span style="color:<?= $m['winner_id']==$m['player1_id']?'var(--success)':'var(--text-300)' ?>;font-weight:600"><?= e($m['p1_first'].' '.$m['p1_last']) ?></span>
                        <span style="color:var(--text-400);padding:0 6px">vs</span>
                        <span style="color:<?= $m['winner_id']==$m['player2_id']?'var(--success)':'var(--text-300)' ?>;font-weight:600"><?= e($m['p2_first'].' '.$m['p2_last']) ?></span>
                    </td>
                    <td><strong><?= $m['player1_score'] ?> — <?= $m['player2_score'] ?></strong></td>
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Upcoming Matches
        </div>
        <a href="/table-tennis-system/organizer/bracket_generator.php" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Generate Bracket
        </a>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($upcomingMatches)): ?>
            <div class="empty-state"><div class="empty-icon">📅</div><p>No upcoming matches</p></div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Player 1</th><th>Player 2</th><th>Tournament</th><th>Date & Time</th><th>Table</th></tr></thead>
            <tbody>
            <?php foreach ($upcomingMatches as $m): ?>
            <tr>
                <td><strong><?= e($m['p1_first'].' '.$m['p1_last']) ?></strong></td>
                <td><strong><?= e($m['p2_first'].' '.$m['p2_last']) ?></strong></td>
                <td class="text-sm"><?= e($m['tournament_name']) ?></td>
                <td class="text-sm text-muted"><?= $m['match_date'] ? date('M j, Y g:i A', strtotime($m['match_date'])) : 'TBD' ?></td>
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
