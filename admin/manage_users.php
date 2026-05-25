<?php
/**
 * Admin — Manage Users
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/database.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $un = trim($_POST['username'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $pw = $_POST['password'] ?? '';
        $rl = $_POST['role'] ?? 'player';
        if ($un && $em && $pw && in_array($rl, ['admin','organizer','player'])) {
            try {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $stmt = db()->prepare("INSERT INTO users (username,password,email,role) VALUES (?,?,?,?)");
                $stmt->execute([$un, $hash, $em, $rl]);
                setFlash('success', "User '$un' created successfully.");
            } catch (PDOException $e) {
                setFlash('error', 'Username or email already exists.');
            }
        } else {
            setFlash('error', 'All fields are required.');
        }
        header('Location: manage_users.php'); exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['user_id'] ?? 0);
        db()->prepare("UPDATE users SET is_active = NOT is_active WHERE id=? AND id != ?")->execute([$id, $_SESSION['user_id']]);
        setFlash('success', 'User status updated.');
        header('Location: manage_users.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== (int)$_SESSION['user_id']) {
            db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            setFlash('success', 'User deleted.');
        } else {
            setFlash('error', 'Cannot delete your own account.');
        }
        header('Location: manage_users.php'); exit;
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['user_id'] ?? 0);
        $em   = trim($_POST['email'] ?? '');
        $rl   = $_POST['role'] ?? 'player';
        $pw   = $_POST['password'] ?? '';
        if ($id && $em && in_array($rl, ['admin','organizer','player'])) {
            if ($pw) {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                db()->prepare("UPDATE users SET email=?, role=?, password=? WHERE id=?")->execute([$em, $rl, $hash, $id]);
            } else {
                db()->prepare("UPDATE users SET email=?, role=? WHERE id=?")->execute([$em, $rl, $id]);
            }
            setFlash('success', 'User updated.');
        }
        header('Location: manage_users.php'); exit;
    }
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (username LIKE ? OR email LIKE ?)"; $like="%$search%"; $params=[$like,$like]; }
$sql .= " ORDER BY created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>Manage Users</h1>
        <p>Create, edit, and manage system user accounts</p>
    </div>
    <button class="btn btn-primary" data-modal-open="createUserModal">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add User
    </button>
</div>

<!-- Filter Bar -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <div class="filter-bar">
            <div class="search-wrap">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="form-control" placeholder="Search users…" data-search-table="usersTable" value="<?= e($search) ?>">
            </div>
            <select class="form-select" style="width:160px" data-filter-table="usersTable" data-filter-col="2">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="organizer">Organizer</option>
                <option value="player">Player</option>
            </select>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-responsive">
            <table class="data-table" id="usersTable">
                <thead>
                    <tr><th>#</th><th>Username</th><th>Role</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">👤</div><p>No users found</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-muted text-sm"><?= $u['id'] ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="p-avatar" style="width:30px;height:30px;font-size:11px"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                            <strong><?= e($u['username']) ?></strong>
                        </div>
                    </td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= ucfirst(e($u['role'])) ?></span></td>
                    <td class="text-sm"><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $u['is_active']?'badge-active':'badge-inactive' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                    <td class="text-sm text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-ghost btn-sm"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </button>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm"><?= $u['is_active']?'Disable':'Enable' ?></button>
                            </form>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete user '<?= e($u['username']) ?>'? This cannot be undone.">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
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

<!-- Create User Modal -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Add New User</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. johndoe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select">
                            <option value="player">Player</option>
                            <option value="organizer">Organizer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="user@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit User</div>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" id="editUsername" class="form-control" disabled style="opacity:0.5">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" id="editRole" class="form-select">
                            <option value="player">Player</option>
                            <option value="organizer">Organizer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                    <input type="password" name="password" class="form-control" minlength="6" placeholder="Enter new password or leave blank">
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
function openEditModal(user) {
    document.getElementById('editUserId').value  = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value   = user.email;
    document.getElementById('editRole').value    = user.role;
    TTMS.openModal('editUserModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
