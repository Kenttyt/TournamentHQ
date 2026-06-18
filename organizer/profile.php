<?php
/**
 * Organizer — Profile
 */
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin','organizer']);

$userId = (int)$_SESSION['user_id'];
$errors = [];

$userStmt = db()->prepare("SELECT username, email, role, created_at, auth_method FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();

$authMethod = $userInfo['auth_method'] ?? 'local';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt    = db()->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if ($authMethod !== 'google' && !password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $newHash = password_hash($newPw, PASSWORD_DEFAULT);
            db()->prepare("UPDATE users SET password=?, auth_method='local' WHERE id=?")->execute([$newHash, $userId]);
            setFlash('success', 'Password updated successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-heading">
        <h1>My Profile</h1>
        <p>View your account information and update your password</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="flash-message flash-error mb-24">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php foreach ($errors as $err): ?><?= e($err) ?><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="content-grid">
    <div>
        <!-- Avatar / Summary -->
        <div class="card mb-24" style="text-align:center;padding:32px">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto 16px">
                <?= strtoupper(substr($userInfo['username'] ?? $_SESSION['username'], 0, 1)) ?>
            </div>
            <div style="font-family:'Outfit',sans-serif;font-size:20px;font-weight:700;color:var(--text-100)">
                <?= e($userInfo['username'] ?? $_SESSION['username']) ?>
            </div>
            <div style="font-size:13px;color:var(--text-400);margin-top:4px">
                <?= e($userInfo['email'] ?? '') ?> · <?= e(ucfirst($userInfo['role'] ?? '')) ?>
            </div>
            <div style="font-size:12px;color:var(--text-300);margin-top:4px">
                Member since <?= e(date('M j, Y', strtotime($userInfo['created_at'] ?? 'now'))) ?>
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
                <div class="card-body">
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
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
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

    <!-- Account Info -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Account Information
            </div>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?= e($userInfo['username'] ?? '') ?>" disabled style="opacity:.5">
                <span class="form-hint">Username cannot be changed.</span>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="text" class="form-control" value="<?= e($userInfo['email'] ?? '') ?>" disabled style="opacity:.5">
                <span class="form-hint">Email cannot be changed.</span>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= e(ucfirst($userInfo['role'] ?? '')) ?>" disabled style="opacity:.5">
            </div>
        </div>
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