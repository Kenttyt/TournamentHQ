<?php
/**
 * Player — View Tournament Bracket
 */
$pageTitle = 'Tournament Bracket';
require_once __DIR__ . '/../includes/auth.php';
requireRole('player');
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/tournaments/bracket_functions.php';

$tid = (int) ($_GET['tournament_id'] ?? 0);
$tournament = getTournamentById($tid);

if (!$tournament) {
    setFlash('danger', 'Tournament not found.');
    header('Location: index.php');
    exit;
}

// Fetch matches & build groups for this tournament
$bracketGroups = buildBracketGroups($tid);
$recordResultUrl = ''; // Empty so player cannot edit/record results

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 24px;">
    <a href="index.php" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Back to Dashboard
    </a>
</div>

<!-- Tournament Details Header Card -->
<div class="card mb-24" style="background: linear-gradient(135deg, rgba(108, 99, 255, 0.08) 0%, rgba(0, 212, 170, 0.04) 100%); border: 1px solid var(--border);">
    <div class="card-body" style="padding: 24px;">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div>
                <span style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25); padding: 3px 10px; border-radius: 20px; color: var(--primary-light); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; margin-bottom: 10px; margin-right: 6px;">
                    <?= e($tournament['sport'] ?? 'Table Tennis') ?>
                </span>
                <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 3px 10px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; margin-bottom: 10px;">
                    <?= e($tournament['category'] ?? 'Open Singles') ?>
                </span>
                <h1 style="font-family: 'Outfit', sans-serif; font-size: 26px; font-weight: 800; color: var(--text-100); margin: 0; line-height: 1.2;">
                    <?= e($tournament['name']) ?>
                </h1>
                <p style="font-size: 14px; color: var(--text-300); margin-top: 6px; line-height: 1.5; max-width: 650px;">
                    <?= e($tournament['description'] ?: 'No description provided.') ?>
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-top: 16px; font-size: 12px; color: var(--text-400); font-weight: 500;">
                    <span style="display: flex; align-items: center; gap: 6px;">
                        📅 <strong>Date:</strong> <?= date('F j, Y', strtotime($tournament['start_date'])) ?><?= $tournament['end_date'] ? ' — ' . date('F j, Y', strtotime($tournament['end_date'])) : '' ?>
                    </span>
                    <?php if ($tournament['venue']): ?>
                    <span style="display: flex; align-items: center; gap: 6px;">
                        📍 <strong>Venue:</strong> <?= e($tournament['venue']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <span class="badge badge-<?= e($tournament['status']) ?>" style="font-size: 11px; padding: 6px 12px; font-weight: 700;">
                    <?= ucfirst(e($tournament['status'])) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Bracket card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: var(--primary-light);"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Tournament Bracket & Matches
        </div>
    </div>
    <div class="card-body">
        <?php
        $showOnlyPhase = 'all';
        $bracketIsTeamEvent = !empty($tournament['is_team_event']);
        $bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
        $bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
        include __DIR__ . '/../includes/bracket_view.php';
        ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
