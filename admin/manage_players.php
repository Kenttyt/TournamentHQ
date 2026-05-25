<?php
/**
 * Admin — Manage Players
 */
$pageTitle = 'Manage Players';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../modules/players/player_functions.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $id = (int)($_POST['player_id'] ?? 0);
        if ($id) {
            updatePlayer($id, [
                'first_name'    => trim($_POST['first_name'] ?? ''),
                'last_name'     => trim($_POST['last_name'] ?? ''),
                'date_of_birth' => $_POST['date_of_birth'] ?? '',
                'gender'        => $_POST['gender'] ?? 'male',
                'club'          => trim($_POST['club'] ?? ''),
                'nationality'   => trim($_POST['nationality'] ?? ''),
            ]);
            setFlash('success', 'Player updated successfully.');
        }
        header('Location: manage_players.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['player_id'] ?? 0);
        if ($id) { deletePlayer($id); setFlash('success', 'Player deleted.'); }
        header('Location: manage_players.php'); exit;
    }
}

$search  = trim($_GET['search'] ?? '');
$players = getAllPlayers($search);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Manage Players</h1>
        <p>View and edit all registered player profiles</p>
    </div>
    <a href="/table-tennis-system/admin/manage_users.php" class="btn btn-outline">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Add via User Manager
    </a>
</div>

<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <div class="filter-bar">
            <div class="search-wrap">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="form-control" placeholder="Search players, clubs, places…" data-search-table="playersTable">
            </div>
            <select class="form-select" style="width:150px" data-filter-table="playersTable" data-filter-col="3">
                <option value="">All Genders</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="data-table" id="playersTable">
                <thead>
                    <tr><th>#</th><th>Player</th><th>Club</th><th>Gender</th><th>Place</th><th>W/L</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($players)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">🏓</div><p>No players found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($players as $i => $p): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $p['id'] ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="p-avatar"><?= strtoupper(substr($p['first_name'],0,1)) ?></div>
                            <div>
                                <div class="p-name"><?= e($p['first_name'].' '.$p['last_name']) ?></div>
                                <div class="p-club"><?= e($p['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm"><?= e($p['club'] ?: '—') ?></td>
                    <td><span class="badge badge-<?= e($p['gender']) ?>"><?= ucfirst(e($p['gender'])) ?></span></td>
                    <td class="text-sm"><?= e($p['nationality'] ?: '—') ?></td>
                    <td class="text-sm"><span class="text-success"><?= $p['wins'] ?>W</span> / <span class="text-muted"><?= $p['losses'] ?>L</span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-ghost btn-sm"
                                onclick="openEditPlayer(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </button>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="player_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                    data-confirm="Delete <?= e($p['first_name'].' '.$p['last_name']) ?>? This cannot be undone.">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
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

<!-- Edit Player Modal -->
<div class="modal-overlay" id="editPlayerModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Player Profile</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="player_id" id="editPlayerId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" id="editDob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="editGender" class="form-select">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Club</label>
                        <input type="text" name="club" id="editClub" class="form-control" placeholder="Club name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Place</label>
                        <input type="text" name="nationality" id="editNationality" class="form-control" placeholder="e.g. Manila, Philippines">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPlayer(p) {
    document.getElementById('editPlayerId').value   = p.id;
    document.getElementById('editFirstName').value  = p.first_name;
    document.getElementById('editLastName').value   = p.last_name;
    document.getElementById('editDob').value        = p.date_of_birth || '';
    document.getElementById('editGender').value     = p.gender;
    document.getElementById('editClub').value       = p.club || '';
    document.getElementById('editNationality').value = p.nationality || '';
    TTMS.openModal('editPlayerModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
