<?php
/**
 * Organizer — Manage Umpire Access Codes
 */
$pageTitle = 'Umpire Access';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'organizer']);
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tournamentId = (int)($_POST['tournament_id'] ?? 0);

    if ($tournamentId && isTournamentOwnedBy($tournamentId, $userId)) {
        if ($action === 'generate') {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                // Delete existing umpire account for this tournament
                $stmtDel = $pdo->prepare("DELETE FROM users WHERE tournament_id = ? AND role = 'umpire'");
                $stmtDel->execute([$tournamentId]);

                // Generate random unique code: UMP- followed by 4 random uppercase chars
                $code = 'UMP-' . strtoupper(bin2hex(random_bytes(2)));
                $email = $code . '@umpire.tournamenthq.com';
                $hashed = password_hash($code, PASSWORD_DEFAULT);

                $stmtIns = $pdo->prepare("INSERT INTO users (username, password, email, role, tournament_id, is_active, auth_method, is_verified) VALUES (?, ?, ?, 'umpire', ?, 1, 'local', 1)");
                $stmtIns->execute([$code, $hashed, $email, $tournamentId]);
                
                $pdo->commit();
                setFlash('success', 'Umpire Access Code generated: ' . $code);
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Failed to generate access code.');
            }
        } elseif ($action === 'revoke') {
            $pdo = db();
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE tournament_id = ? AND role = 'umpire'");
            if ($stmtDel->execute([$tournamentId])) {
                setFlash('success', 'Umpire Access Code revoked.');
            } else {
                setFlash('danger', 'Failed to revoke access.');
            }
        }
    } else {
        setFlash('danger', 'You do not have permission to manage this tournament.');
    }
    header('Location: umpire_access.php');
    exit;
}

$myTourneys = getOrganizerTournaments($userId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Umpire Access Codes</h1>
        <p>Generate and manage generic, single-field access codes for tournament umpires</p>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tournament</th>
                        <th>Sport / Category</th>
                        <th>Status</th>
                        <th>Umpire Access Code</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($myTourneys)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <h3>No tournaments yet</h3>
                                <p>Create a tournament to generate umpire access codes.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($myTourneys as $t): 
                        // Fetch the current umpire code if it exists
                        $stmtCode = db()->prepare("SELECT username FROM users WHERE role = 'umpire' AND tournament_id = ? LIMIT 1");
                        $stmtCode->execute([$t['id']]);
                        $umpire = $stmtCode->fetch();
                        $currentCode = $umpire ? $umpire['username'] : null;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600; color:var(--text-100);"><?= e($t['name']) ?></div>
                            <div class="text-xs text-muted" style="margin-top:2px;">Starts: <?= date('M j, Y', strtotime($t['start_date'])) ?></div>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <span style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--primary-light); font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                    <?= e($t['sport'] ?? 'Table Tennis') ?>
                                </span>
                                <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                    <?= e($t['category'] ?? 'Open Singles') ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= e($t['status']) ?>" style="text-transform: capitalize;">
                                <?= e($t['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($currentCode): ?>
                                <code style="font-family: monospace; font-size: 15px; font-weight: 700; color: var(--accent); background: rgba(0, 212, 170, 0.1); border: 1px solid rgba(0, 212, 170, 0.25); padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px;">
                                    <?= e($currentCode) ?>
                                </code>
                            <?php else: ?>
                                <span class="text-xs text-muted" style="font-style:italic;">No active access code</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display:flex; justify-content:flex-end; gap:8px;">
                                <form method="POST" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="action" value="generate">
                                    <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <?= $currentCode ? 'Regenerate Code' : 'Generate Code' ?>
                                    </button>
                                </form>
                                <?php if ($currentCode): ?>
                                    <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to revoke umpire access for this tournament?');">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color:#ff6b6b; border-color:rgba(255,107,107,0.3);">
                                            Revoke Access
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
