<?php
/**
 * Player — Profile
 */
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth.php';
requireRole('player');
require_once __DIR__ . '/../modules/players/player_functions.php';

$userId = (int)$_SESSION['user_id'];
$player = getPlayerByUserId($userId);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile' && $player) {
        $data = [
            'first_name'    => trim($_POST['first_name'] ?? ''),
            'last_name'     => trim($_POST['last_name'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'gender'        => $_POST['gender'] ?? 'male',
            'club'          => trim($_POST['club'] ?? ''),
            'nationality'   => trim($_POST['nationality'] ?? ''),
        ];
        if (!$data['first_name'] || !$data['last_name']) {
            $errors[] = 'First name and last name are required.';
        } else {
            updatePlayer($player['id'], $data);
            setFlash('success', 'Profile updated successfully.');
            header('Location: profile.php'); exit;
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt    = db()->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $userId]);
            setFlash('success', 'Password changed successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>My Profile</h1>
        <p>Update your personal information and account settings</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="flash-message flash-error mb-24">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php foreach ($errors as $e): ?><?= e($e) ?><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="content-grid">
    <!-- Profile Card -->
    <div>
        <!-- Avatar / Summary -->
        <div class="card mb-24" style="text-align:center;padding:32px">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto 16px">
                <?= strtoupper(substr($player['first_name'] ?? $_SESSION['username'],0,1)) ?>
            </div>
            <div style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;color:var(--text-100)">
                <?= e(($player['first_name']??'').' '.($player['last_name']??'')) ?: e($_SESSION['username']) ?>
            </div>
            <div style="font-size:13px;color:var(--text-400);margin-top:4px"><?= e($player['club'] ?? '') ?></div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Change Password
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                    </div>
                    <button type="submit" class="btn btn-outline w-full" style="justify-content:center">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Personal Information
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="profile">
            <div class="card-body">
                <?php if (!$player): ?>
                <div class="flash-message flash-info mb-16">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12.01" y2="16"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                    Player profile not yet set up. Contact an admin.
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= e($player['username']) ?>" disabled style="opacity:.5">
                    <span class="form-hint">Username cannot be changed.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" value="<?= e($player['email']) ?>" disabled style="opacity:.5">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($player['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?= e($player['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?= e($player['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="male"   <?= ($player['gender']??'')==='male'?'selected':'' ?>>Male</option>
                            <option value="female" <?= ($player['gender']??'')==='female'?'selected':'' ?>>Female</option>
                            <option value="other"  <?= ($player['gender']??'')==='other'?'selected':'' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Club</label>
                        <input type="text" name="club" class="form-control" value="<?= e($player['club'] ?? '') ?>" placeholder="Your club name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Place</label>
                        <input type="text" name="nationality" class="form-control" value="<?= e($player['nationality'] ?? '') ?>" placeholder="e.g. Manila, Philippines">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:8px">Save Profile</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
