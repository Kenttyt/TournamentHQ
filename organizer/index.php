<?php
/**
 * Organizer Dashboard
 */
$pageTitle = 'Organizer Dashboard';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/uploads/payment_proof.php';
require_once __DIR__ . '/../modules/matches/match_functions.php';

$userId       = (int)$_SESSION['user_id'];
$myTournaments = getOrganizerTournaments($userId);
$approvalHistory = getApprovedRegistrationHistory();
$recentResults   = getRecentMatches();

$totalTourneys  = count($myTournaments);
$activeTourneys = count(array_filter($myTournaments, fn($t) => $t['status'] === 'ongoing'));
$completedT     = count(array_filter($myTournaments, fn($t) => $t['status'] === 'completed'));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple"><i data-lucide="trophy" style="color: var(--primary-light)"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalTourneys ?></div>
            <div class="stat-label">My Tournaments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i data-lucide="zap" style="color: var(--accent)"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $activeTourneys ?></div>
            <div class="stat-label">Active Now</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i data-lucide="check-circle" style="color: var(--warning)"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $completedT ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i data-lucide="history" style="color: var(--info)"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= count($approvalHistory) ?></div>
            <div class="stat-label">History</div>
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
            <a href="/TournamentHQ/organizer/tournaments.php" class="btn btn-primary btn-sm">
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
            <a href="/TournamentHQ/organizer/bracket_generator.php" class="btn btn-ghost btn-sm">Open Bracket</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($recentResults)): ?>
                <div class="empty-state"><div class="empty-icon">⚡</div><p>No results yet</p></div>
            <?php else: ?>
            <div class="table-responsive" style="<?= count($recentResults) > 8 ? 'max-height: 420px; overflow-y: auto;' : '' ?>">
            <table class="data-table">
                <thead><tr><th>Match</th><th>Category</th><th>Sets</th><th>Winner</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentResults as $m): ?>
                <tr>
                    <td style="font-size:13px">
                        <span style="color:<?= $m['winner_id']==$m['player1_id']?'var(--success)':'var(--text-300)' ?>;font-weight:600"><?= e($m['p1_first'].' '.$m['p1_last']) ?></span>
                        <span style="color:var(--text-400);padding:0 6px">vs</span>
                        <span style="color:<?= $m['winner_id']==$m['player2_id']?'var(--success)':'var(--text-300)' ?>;font-weight:600"><?= e($m['p2_first'].' '.$m['p2_last']) ?></span>
                    </td>
                    <td class="text-sm"><?= e($m['tournament_category'] ?? '') ?></td>
                    <td>
                        <strong><?= $m['player1_score'] ?> — <?= $m['player2_score'] ?></strong>
                        <?php if (!empty($m['set_scores'])): ?>
                            <div class="text-muted text-xs" style="margin-top: 2px; font-family: monospace;"><?= e(str_replace(',', '  ', $m['set_scores'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm"><?= e(trim($m['winner_first'].' '.$m['winner_last'])) ?: 'TBD' ?></td>
                    <td class="text-sm"><?= e(date('M j, Y H:i', strtotime($m['updated_at'] ?? $m['match_date'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approval History (no proof) -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            History
        </div>
        
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($approvalHistory)): ?>
            <div class="empty-state"><div class="empty-icon">📂</div><p>No approvals without proof</p></div>
        <?php else: ?>
        <div class="table-responsive" style="<?= count($approvalHistory) > 8 ? 'max-height: 420px; overflow-y: auto;' : '' ?>">
        <table class="data-table">
            <thead><tr><th>Participant</th><th>Tournament</th><th>Submitted By</th><th>Payment Proof</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($approvalHistory as $r):
                $isOrganizerRegistered = $r['type'] === 'player' || empty(trim(($r['submitter_first'] ?? '') . ' ' . ($r['submitter_last'] ?? '')));
                $rowStyle = $isOrganizerRegistered ? 'background: rgba(16, 185, 129, 0.08);' : 'background: rgba(59, 130, 246, 0.08);';
                $sourceLabel = $isOrganizerRegistered
                    ? '<span class="badge badge-success" style="margin-left:6px;">Organizer</span>'
                    : '<span class="badge badge-info" style="margin-left:6px;">Player</span>';
            ?>
            <tr style="<?= $rowStyle ?>">
                <td><strong><?= e(trim($r['first_name'].' '.$r['last_name'])) ?></strong>
                    <?= $sourceLabel ?>
                    <div class="text-xs text-muted"><?= $r['type'] === 'player' ? 'Account player' : 'Player' ?></div>
                </td>
                <td class="text-sm"><a href="/TournamentHQ/organizer/tournaments.php?tournament_id=<?= (int)$r['tournament_id'] ?>"><?= e($r['tournament_name'] ?? 'Unknown') ?></a></td>
                <td class="text-sm"><?= e(trim(($r['submitter_first'] ?? '') . ' ' . ($r['submitter_last'] ?? ''))) ?></td>
                <td class="text-sm">
                    <?php if (!empty($r['payment_proof_path'])): ?>
                        <a href="<?= e(paymentProofPublicUrl($r['payment_proof_path'])) ?>" target="_blank" class="btn btn-outline btn-xs">View Proof</a>
                    <?php else: ?>
                        Without proof
                    <?php endif; ?>
                </td>
                <td class="text-sm"><?= e(date('M j, Y H:i', strtotime($r['ts'] ?? ''))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
