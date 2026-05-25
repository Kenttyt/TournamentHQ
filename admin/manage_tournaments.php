<?php
/**
 * Admin — Manage Tournaments
 */
$pageTitle = 'Manage Tournaments';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
require_once __DIR__ . '/../modules/players/player_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $baseName    = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $organizerId = (int)($_POST['organizer_id'] ?? $_SESSION['user_id']);
        
        $startDate   = $_POST['start_date'] ?? '';
        $endDate     = $_POST['end_date'] ?? '';
        $catNames      = $_POST['cat_name'] ?? [];
        $catMaxPlayers = $_POST['cat_max_players'] ?? [];
        $catPrizeChampion = $_POST['cat_prize_champion'] ?? [];
        $catPrize2nd      = $_POST['cat_prize_2nd'] ?? [];
        $catPrize3rd      = $_POST['cat_prize_3rd'] ?? [];
        $catPrize4th      = $_POST['cat_prize_4th'] ?? [];
        $catRegFees       = $_POST['cat_registration_fee'] ?? [];
        
        $createdCount = 0;
        foreach ($catNames as $i => $name) {
            $catName = trim($name);
            if (empty($catName)) continue;
            
            $maxPlayers = (int)($catMaxPlayers[$i] ?? 16);
            if ($maxPlayers < 2) $maxPlayers = 16;
            
            createTournament([
                'organizer_id'   => $organizerId,
                'name'           => $baseName . ' (' . $catName . ')',
                'category'       => $catName,
                'description'    => $description,
                'format'         => 'single_elimination',
                'status'         => 'upcoming',
                'max_players'    => $maxPlayers,
                'start_date'     => $startDate,
                'end_date'       => $endDate ?: null,
                'venue'          => null,
                'prize_champion' => trim($catPrizeChampion[$i] ?? ''),
                'prize_2nd'      => trim($catPrize2nd[$i] ?? ''),
                'prize_3rd'      => trim($catPrize3rd[$i] ?? ''),
                'prize_4th'      => trim($catPrize4th[$i] ?? ''),
                'registration_fee' => trim($catRegFees[$i] ?? ''),
            ]);
            $createdCount++;
        }
        
        if ($createdCount === 0) {
            createTournament([
                'organizer_id' => $organizerId,
                'name'         => $baseName . ' (Open Singles)',
                'category'     => 'Open Singles',
                'description'  => $description,
                'format'       => 'single_elimination',
                'status'       => 'upcoming',
                'max_players'  => 16,
                'start_date'   => $startDate ?: date('Y-m-d'),
                'end_date'     => $endDate ?: null,
                'venue'        => null,
                'prize_champion' => '',
                'prize_2nd'      => '',
                'prize_3rd'      => '',
                'prize_4th'      => '',
                'registration_fee' => '',
            ]);
        }
        
        setFlash('success', 'Tournament categories created successfully!');
        header('Location: manage_tournaments.php'); exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['tournament_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        if ($category === 'custom') {
            $category = trim($_POST['category_custom'] ?? 'Open Singles');
        }
        if (empty($category)) {
            $category = 'Open Singles';
        }
        updateTournament($id, [
            'name'        => trim($_POST['name'] ?? ''),
            'category'    => $category,
            'description' => trim($_POST['description'] ?? ''),
            'format'      => $_POST['format'] ?? 'single_elimination',
            'status'      => $_POST['status'] ?? 'upcoming',
            'max_players' => (int)($_POST['max_players'] ?? 16),
            'start_date'  => $_POST['start_date'] ?? '',
            'end_date'    => $_POST['end_date'] ?? '',
            'venue'       => trim($_POST['venue'] ?? ''),
            'prize_champion' => trim($_POST['prize_champion'] ?? ''),
            'prize_2nd'      => trim($_POST['prize_2nd'] ?? ''),
            'prize_3rd'      => trim($_POST['prize_3rd'] ?? ''),
            'prize_4th'      => trim($_POST['prize_4th'] ?? ''),
            'registration_fee' => trim($_POST['registration_fee'] ?? ''),
        ]);
        setFlash('success', 'Tournament updated.');
        header('Location: manage_tournaments.php'); exit;
    }

    if ($action === 'delete') {
        deleteTournament((int)($_POST['tournament_id'] ?? 0));
        setFlash('success', 'Tournament deleted.');
        header('Location: manage_tournaments.php'); exit;
    }

    if ($action === 'register') {
        $tid = (int)($_POST['tournament_id'] ?? 0);
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        
        if ($tid && $first !== '' && $last !== '') {
            $t = getTournamentById($tid);
            if ($t) {
                if ($t['registered_count'] >= $t['max_players']) {
                    setFlash('danger', 'This tournament is already full.');
                } else {
                    addTournamentGuest($tid, null, $first, $last, 'approved');
                    setFlash('success', 'Participant ' . $first . ' ' . $last . ' registered successfully!');
                }
            }
        } else {
            setFlash('danger', 'Please enter both first name and last name.');
        }
        header('Location: manage_tournaments.php'); exit;
    }
}

$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$tournaments  = getAllTournaments($search, $statusFilter);
$allPlayers   = getAllPlayers();
$organizers   = db()->query("SELECT id, username FROM users WHERE role IN ('admin','organizer') ORDER BY username")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Manage Tournaments</h1>
        <p>Create and manage all tournaments</p>
    </div>
    <button class="btn btn-primary" data-modal-open="createTournamentModal">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Tournament
    </button>
</div>

<!-- Filter Bar -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <div class="filter-bar">
            <div class="search-wrap">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="form-control" placeholder="Search tournaments…" data-search-table="tournamentsTable">
            </div>
            <select class="form-select" style="width:160px" data-filter-table="tournamentsTable" data-filter-col="3">
                <option value="">All Statuses</option>
                <option value="upcoming">Upcoming</option>
                <option value="ongoing">Ongoing</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>
</div>

<!-- Tournament Cards -->
<div class="tournament-grid mb-24">
<?php if (empty($tournaments)): ?>
    <div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">🏆</div><h3>No tournaments yet</h3><p>Create your first tournament above.</p></div>
<?php else: ?>
<?php foreach ($tournaments as $t): ?>
<div class="tournament-card">
    <div class="tournament-card-header">
        <div>
            <div class="tournament-name"><?= e($t['name']) ?></div>
            <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                <span style="background: rgba(0, 212, 170, 0.12); border: 1px solid rgba(0, 212, 170, 0.25); padding: 2px 8px; border-radius: 20px; color: var(--accent); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">
                    <?= e($t['category'] ?? 'Open Singles') ?>
                </span>
            </div>
        </div>
        <span class="badge badge-<?= e($t['status']) ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
    </div>
    <div class="tournament-meta">
        <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= date('M j, Y', strtotime($t['start_date'])) ?><?= $t['end_date'] ? ' — '.date('M j, Y', strtotime($t['end_date'])) : '' ?></span>
        <span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><?= $t['registered_count'] ?> / <?= $t['max_players'] ?> players</span>
        <?php if ($t['venue']): ?><span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><?= e($t['venue']) ?></span><?php endif; ?>
        <?php $places = getTournamentPrizePlaces($t); if (!empty($places)): ?>
        <span>🏅 <?php foreach ($places as $i => $p): ?><?= $i > 0 ? ' · ' : '' ?><?= e($p['label']) ?>: <?= e($p['value']) ?><?php endforeach; ?></span>
        <?php endif; ?>
        <?php if ($fee = formatRegistrationFee($t)): ?><span>💳 Registration: <?= e($fee) ?></span><?php endif; ?>
        <span>Format: <?= ucfirst(str_replace('_',' ',$t['format'])) ?></span>
    </div>
    <div class="tournament-footer">
        <div class="progress-bar" style="width:120px;flex-shrink:0">
            <div class="progress-fill" style="width:<?= $t['max_players']>0?min(100,round($t['registered_count']/$t['max_players']*100)):0 ?>%"></div>
        </div>
        <div class="btn-group">
            <button class="btn btn-ghost btn-sm" onclick="openEditTournament(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
            </button>
            <button class="btn btn-accent btn-sm" onclick="openRegisterModal(<?= $t['id'] ?>, '<?= e($t['name']) ?>')">
                Register Player
            </button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete '<?= e($t['name']) ?>'?">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Create Tournament Modal -->
<div class="modal-overlay" id="createTournamentModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <div class="modal-title">New Tournament</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tournament Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. National Open 2026">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief description…"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Organizer</label>
                    <select name="organizer_id" class="form-select">
                        <?php foreach ($organizers as $org): ?>
                        <option value="<?= $org['id'] ?>" <?= $org['id']==$_SESSION['user_id']?'selected':'' ?>><?= e($org['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Dynamic Categories Container -->
                <div style="margin-top: 18px; margin-bottom: 8px;">
                    <label class="form-label" style="font-weight: 700; color: var(--text-200); font-size: 14px; margin-bottom: 12px; display: block;">Tournament Categories *</label>
                    <div id="categoriesContainer">
                        
                        <!-- Initial Default Category Block -->
                        <div class="category-row-block" style="border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px; position: relative; background: rgba(255,255,255,0.01);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-size: 13px; font-weight: 700; color: var(--primary-light);">Category #1</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="cat_name[]" class="form-control" required placeholder="e.g. Men's Singles">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Max Players</label>
                                    <input type="number" name="cat_max_players[]" class="form-control" value="16" min="2" placeholder="e.g. 16">
                                </div>
                            </div>
                            <?php $namePrefix = 'cat_'; $values = []; include __DIR__ . '/../includes/tournament_prize_fields.php'; ?>
                        </div>

                    </div>
                    
                    <button type="button" class="btn btn-outline btn-sm" onclick="addCategoryRow()" style="width: 100%; justify-content: center; margin-top: 6px; height: 38px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Category
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Tournament</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tournament Modal -->
<div class="modal-overlay" id="editTournamentModal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <div class="modal-title">Edit Tournament</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tournament_id" id="etId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tournament Name *</label>
                    <input type="text" name="name" id="etName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="etDesc" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" id="etCategory" class="form-select" onchange="toggleEditCustomCategory(this.value)" required style="height: 44px; background: var(--bg-600); border: 1px solid var(--border); color: var(--text-100);">
                            <option value="Men's Singles">Men's Singles</option>
                            <option value="Women's Singles">Women's Singles</option>
                            <option value="Men's Doubles">Men's Doubles</option>
                            <option value="Women's Doubles">Women's Doubles</option>
                            <option value="Mixed Doubles">Mixed Doubles</option>
                            <option value="Juniors (Under-18)">Juniors (Under-18)</option>
                            <option value="Seniors (40+)">Seniors (40+)</option>
                            <option value="Open Singles">Open Singles</option>
                            <option value="custom">Custom Category...</option>
                        </select>
                    </div>
                    <div class="form-group" id="editCustomCategoryGroup" style="display: none;">
                        <label class="form-label">Custom Category Name *</label>
                        <input type="text" name="category_custom" id="editCustomCategoryInput" class="form-control" placeholder="e.g. Under-15 Boys">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Format</label>
                        <select name="format" id="etFormat" class="form-select">
                            <option value="single_elimination">Single Elimination</option>
                            <option value="round_robin">Round Robin</option>
                            <option value="double_elimination">Double Elimination</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="etStatus" class="form-select">
                            <option value="upcoming">Upcoming</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="etStart" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="etEnd" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Players</label>
                        <input type="number" name="max_players" id="etMax" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Venue</label>
                        <input type="text" name="venue" id="etVenue" class="form-control">
                    </div>
                </div>
                <div id="etPrizeFields">
                    <?php $namePrefix = ''; $values = []; include __DIR__ . '/../includes/tournament_prize_fields.php'; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Register Player Modal -->
<div class="modal-overlay" id="registerPlayerModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title">Register Participant</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="tournament_id" id="regTournamentId">
            <div class="modal-body">
                <p class="text-muted text-sm mb-16" id="regTournamentName"></p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required placeholder="e.g. John">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required placeholder="e.g. Doe">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-accent">Register</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTournament(t) {
    document.getElementById('etId').value     = t.id;
    document.getElementById('etName').value   = t.name;
    document.getElementById('etDesc').value   = t.description || '';
    
    // Handle category drop-down pre-selection
    const categories = ["Men's Singles", "Women's Singles", "Men's Doubles", "Women's Doubles", "Mixed Doubles", "Juniors (Under-18)", "Seniors (40+)", "Open Singles"];
    const catSelect = document.getElementById('etCategory');
    const customGroup = document.getElementById('editCustomCategoryGroup');
    const customInput = document.getElementById('editCustomCategoryInput');
    
    if (categories.includes(t.category)) {
        catSelect.value = t.category;
        customGroup.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    } else {
        catSelect.value = 'custom';
        customGroup.style.display = 'block';
        customInput.required = true;
        customInput.value = t.category || '';
    }

    document.getElementById('etFormat').value = t.format;
    document.getElementById('etStatus').value = t.status;
    document.getElementById('etStart').value  = t.start_date;
    document.getElementById('etEnd').value    = t.end_date || '';
    document.getElementById('etMax').value    = t.max_players;
    document.getElementById('etVenue').value  = t.venue || '';
    const setPrize = (id, val) => { const el = document.querySelector('#etPrizeFields [name="' + id + '"]'); if (el) el.value = val || ''; };
    setPrize('prize_champion', t.prize_champion);
    setPrize('prize_2nd', t.prize_2nd);
    setPrize('prize_3rd', t.prize_3rd);
    setPrize('prize_4th', t.prize_4th);
    setPrize('registration_fee', t.registration_fee);
    TTMS.openModal('editTournamentModal');
}

function openRegisterModal(tid, name) {
    document.getElementById('regTournamentId').value = tid;
    document.getElementById('regTournamentName').textContent = 'Adding player to: ' + name;
    TTMS.openModal('registerPlayerModal');
}

function addCategoryRow() {
    const container = document.getElementById('categoriesContainer');
    const rowCount = container.querySelectorAll('.category-row-block').length + 1;
    
    const newBlock = document.createElement('div');
    newBlock.className = 'category-row-block';
    newBlock.style = 'border: 1px solid var(--border); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 12px; position: relative; background: rgba(255,255,255,0.01);';
    newBlock.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="font-size: 13px; font-weight: 700; color: var(--primary-light);">Category #${rowCount}</span>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeCategoryRow(this)" style="padding: 4px 8px; font-size: 11px; height: auto;">Remove</button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="cat_name[]" class="form-control" required placeholder="e.g. Men's Singles">
            </div>
            <div class="form-group">
                <label class="form-label">Max Players</label>
                <input type="number" name="cat_max_players[]" class="form-control" value="16" min="2" placeholder="e.g. 16">
            </div>
        </div>
        <div class="prize-pool-fields">
            <label class="form-label" style="margin-bottom: 10px;">Prize Pool</label>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label text-xs">Champion</label>
                    <input type="text" name="cat_prize_champion[]" class="form-control" placeholder="e.g. ₱10,000">
                </div>
                <div class="form-group">
                    <label class="form-label text-xs">2nd Place</label>
                    <input type="text" name="cat_prize_2nd[]" class="form-control" placeholder="e.g. ₱5,000">
                </div>
            </div>
            <div class="form-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label class="form-label text-xs">3rd Place</label>
                    <input type="text" name="cat_prize_3rd[]" class="form-control" placeholder="e.g. ₱3,000">
                </div>
                <div class="form-group">
                    <label class="form-label text-xs">4th Place</label>
                    <input type="text" name="cat_prize_4th[]" class="form-control" placeholder="e.g. ₱1,500">
                </div>
            </div>
            <div class="form-group" style="margin-top: 14px; margin-bottom: 0;">
                <label class="form-label">Registration Fee</label>
                <input type="text" name="cat_registration_fee[]" class="form-control" placeholder="e.g. ₱500 or Free">
            </div>
        </div>
    `;
    container.appendChild(newBlock);
}

function removeCategoryRow(button) {
    const block = button.closest('.category-row-block');
    if (block) {
        block.remove();
        // Reindex labels
        const container = document.getElementById('categoriesContainer');
        const labels = container.querySelectorAll('.category-row-block span');
        labels.forEach((label, idx) => {
            label.textContent = 'Category #' + (idx + 1);
        });
    }
}

function toggleEditCustomCategory(val) {
    const group = document.getElementById('editCustomCategoryGroup');
    const input = document.getElementById('editCustomCategoryInput');
    if (group && input) {
        if (val === 'custom') {
            group.style.display = 'block';
            input.required = true;
            input.focus();
        } else {
            group.style.display = 'none';
            input.required = false;
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
