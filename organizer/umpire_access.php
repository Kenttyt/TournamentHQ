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
        // Fetch the tournament to get its base name
        $targetTournament = getTournamentById($tournamentId);
        if ($targetTournament) {
            $baseName = preg_replace('/\s*\([^)]+\)$/', '', $targetTournament['name']);
            
            // Find all tournament IDs under the same organizer with this base name
            $pdo = db();
            $stmtGroup = $pdo->prepare("SELECT id FROM tournaments WHERE organizer_id = ? AND name LIKE ?");
            $stmtGroup->execute([$userId, $baseName . '%']);
            $groupTournaments = $stmtGroup->fetchAll();
            $tIds = array_column($groupTournaments, 'id');
            
            if (!empty($tIds)) {
                $inClause = implode(',', array_map('intval', $tIds));
                
                if ($action === 'generate') {
                    $pdo->beginTransaction();
                    try {
                        // Delete existing umpire accounts for all categories in this group
                        $pdo->exec("DELETE FROM users WHERE role = 'umpire' AND tournament_id IN ($inClause)");

                        // Generate random unique code: UMP- followed by 4 random uppercase chars
                        $code = 'UMP-' . strtoupper(bin2hex(random_bytes(2)));
                        $email = $code . '@umpire.tournamenthq.com';
                        $hashed = password_hash($code, PASSWORD_DEFAULT);

                        // Link it to the tournamentId that was submitted (the first tournament of the group)
                        $stmtIns = $pdo->prepare("INSERT INTO users (username, password, email, role, tournament_id, is_active, auth_method, is_verified) VALUES (?, ?, ?, 'umpire', ?, 1, 'local', 1)");
                        $stmtIns->execute([$code, $hashed, $email, $tournamentId]);
                        
                        $pdo->commit();
                        setFlash('success', 'Umpire Access Code generated: ' . $code);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        setFlash('danger', 'Failed to generate access code.');
                    }
                } elseif ($action === 'revoke') {
                    $stmtDel = $pdo->prepare("DELETE FROM users WHERE role = 'umpire' AND tournament_id IN ($inClause)");
                    if ($stmtDel->execute()) {
                        setFlash('success', 'Umpire Access Code revoked.');
                    } else {
                        setFlash('danger', 'Failed to revoke access.');
                    }
                }
            }
        }
    } else {
        setFlash('danger', 'You do not have permission to manage this tournament.');
    }
    header('Location: umpire_access.php');
    exit;
}

$myTourneys = getOrganizerTournaments($userId);

// Group tournaments by their base name
$groupedTourneys = [];
foreach ($myTourneys as $t) {
    $baseName = preg_replace('/\s*\([^)]+\)$/', '', $t['name']);
    if (!isset($groupedTourneys[$baseName])) {
        $groupedTourneys[$baseName] = [
            'base_name' => $baseName,
            'start_date' => $t['start_date'],
            'sport' => $t['sport'] ?? 'Table Tennis',
            'status' => $t['status'],
            'first_id' => $t['id'], // represent this group with the first ID
            'categories' => [],
            'umpire_code' => null,
        ];
    }
    $groupedTourneys[$baseName]['categories'][] = $t['category'];
    if ($t['status'] === 'ongoing') {
        $groupedTourneys[$baseName]['status'] = 'ongoing';
    }
}

// Fetch umpire codes for each group
foreach ($groupedTourneys as $baseName => &$group) {
    $stmtCode = db()->prepare("
        SELECT u.username 
        FROM users u 
        JOIN tournaments t ON u.tournament_id = t.id 
        WHERE u.role = 'umpire' 
          AND t.organizer_id = ? 
          AND t.name LIKE ? 
        LIMIT 1
    ");
    $stmtCode->execute([$userId, $baseName . '%']);
    $umpire = $stmtCode->fetch();
    if ($umpire) {
        $group['umpire_code'] = $umpire['username'];
    }
}
unset($group);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Umpire Access Codes</h1>
        <p>Generate one access code per tournament name. Umpires can log in and select any category within the tournament.</p>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tournament</th>
                        <th>Categories</th>
                        <th>Status</th>
                        <th>Umpire Access Code</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($groupedTourneys)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <h3>No tournaments yet</h3>
                                <p>Create a tournament to generate umpire access codes.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($groupedTourneys as $g): 
                        $currentCode = $g['umpire_code'];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600; color:var(--text-100);"><?= e($g['base_name']) ?></div>
                            <div class="text-xs text-muted" style="margin-top:2px;">Starts: <?= date('M j, Y', strtotime($g['start_date'])) ?></div>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px; flex-wrap:wrap; max-width: 400px;">
                                <?php foreach ($g['categories'] as $cat): ?>
                                    <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                        <?= e($cat) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= e($g['status']) ?>" style="text-transform: capitalize;">
                                <?= e($g['status']) ?>
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
                                    <input type="hidden" name="tournament_id" value="<?= $g['first_id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <?= $currentCode ? 'Regenerate Code' : 'Generate Code' ?>
                                    </button>
                                </form>
                                <?php if ($currentCode): ?>
                                    <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to revoke umpire access for this tournament?');">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="tournament_id" value="<?= $g['first_id'] ?>">
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
