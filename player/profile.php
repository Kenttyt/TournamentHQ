<?php
/**
 * Player — Profile
 */
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth.php';
requireRole('player');
require_once __DIR__ . '/../modules/players/player_functions.php';

$userId = (int)$_SESSION['user_id'];
$player = getPlayerByUserId($userId);
$errors = [];

// Get user's auth method
$stmt = db()->prepare("SELECT auth_method FROM users WHERE id = ?");
$stmt->execute([$userId]);
$authMethod = $stmt->fetchColumn() ?: 'local';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        setFlash('error', 'Invalid request. Please try again.');
        header('Location: profile.php');
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        if (!$player) {
            $firstName = trim($_POST['first_name'] ?? '');
            if ($firstName === '') {
                $errors[] = 'First name is required.';
            } else {
                $playerId = createPlayer(array_merge(
                    ['user_id' => $userId],
                    normalizePlayerProfileData($_POST)
                ));
                $player = getPlayerById($playerId);
                setFlash('success', 'Profile created successfully.');
                header('Location: profile.php');
                exit;
            }
        } else {
            $data = normalizePlayerProfileData($_POST);
            if ($data['first_name'] === '') {
                $errors[] = 'First name is required.';
            } else {
                $playerId = (int) ($player['id'] ?? 0);
                if ($playerId <= 0) {
                    $errors[] = 'Player profile is invalid. Please contact an admin.';
                } elseif (!updatePlayer($playerId, $data)) {
                    $errors[] = 'Profile could not be saved. Please try again or contact an admin.';
                } else {
                    setFlash('success', 'Profile updated successfully.');
                    header('Location: profile.php');
                    exit;
                }
            }
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt    = db()->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        // For Google users, skip current password verification
        if ($authMethod !== 'google' && !password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password=?, auth_method='local' WHERE id=?")->execute([$newHash, $userId]);
            setFlash('success', 'Password set successfully.');
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
            <div style="font-size:13px;color:var(--text-400);margin-top:4px">
                <?php
                if ($player) {
                    $dobSummary = playerDateInputValue($player['date_of_birth'] ?? null);
                    $summaryParts = array_filter([
                        $player['club'] ?? '',
                        $player['nationality'] ?? '',
                        $dobSummary ? 'Born ' . $dobSummary : '',
                    ]);
                    echo $summaryParts ? e(implode(' · ', $summaryParts)) : 'Complete your profile below';
                } else {
                    echo 'Complete your profile below';
                }
                ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Change Password
                </div>
            </div>
            <?php if ($authMethod === 'google'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div class="password-wrapper">
                            <input type="password" name="new_password" class="form-control password-field" required minlength="6" placeholder="Min 6 characters">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" class="form-control password-field" required placeholder="Repeat password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline w-full" style="justify-content:center">Set Password</button>
                </div>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div class="password-wrapper">
                            <input type="password" name="current_password" class="form-control password-field" required placeholder="Enter current password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" class="form-control password-field" required minlength="6" placeholder="Min 6 characters">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" class="form-control password-field" required placeholder="Repeat new password">
                            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="eye-off-icon" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline w-full" style="justify-content:center">Update Password</button>
                </div>
            </form>
            <?php endif; ?>
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
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="card-body">
                <?php if (!$player): ?>
                <div class="flash-message flash-info mb-16">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12.01" y2="16"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                    Your player profile is not set up yet. Fill in the form below and save to create it.
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($_SESSION['username'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="male" selected>Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Club</label>
                        <input type="text" name="club" class="form-control" value="" placeholder="Your club name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Place</label>
                        <input type="text" name="nationality" class="form-control" value="" placeholder="e.g. Manila, Philippines">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:8px">Create Profile</button>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= e($player['username'] ?? $_SESSION['username'] ?? '') ?>" disabled style="opacity:.5">
                    <span class="form-hint">Username cannot be changed.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" value="<?= e($player['email'] ?? $_SESSION['email'] ?? '') ?>" disabled style="opacity:.5">
                    <span class="form-hint">Email cannot be changed.</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($player['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= e($player['last_name']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?= e(playerDateInputValue($player['date_of_birth'] ?? null)) ?>">
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

<style>
    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-wrapper .form-control {
        padding-right: 40px;
    }

    .toggle-password {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-400);
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
    }

    .toggle-password:hover {
        color: var(--text-200);
    }

    .toggle-password svg {
        width: 20px;
        height: 20px;
    }
</style>

<script>
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const wrapper = button.closest('.password-wrapper');
            const input = wrapper.querySelector('.password-field');
            const eyeIcon = button.querySelector('.eye-icon');
            const eyeOffIcon = button.querySelector('.eye-off-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
