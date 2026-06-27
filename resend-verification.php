<?php
/**
 * Resend Email Verification
 * Table Tennis Tournament Management System
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $messageType = 'danger';
        $message = 'Invalid request. Please try again.';
    } else {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $messageType = 'danger';
        $message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messageType = 'danger';
        $message = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = db()->prepare("SELECT id, username, email, is_verified, verification_token, token_expires FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal if email exists (security)
                $messageType = 'success';
                $message = 'If an account exists with this email, a verification link will be sent.';
            } elseif ($user['is_verified']) {
                // Email already verified
                $messageType = 'info';
                $message = 'Your email is already verified. You can <a href="index.php">log in here</a>.';
            } else {
                // Generate new token and send email
                $token = bin2hex(random_bytes(32));
                $expires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
                
                db()->prepare("UPDATE users SET verification_token = ?, token_expires = ? WHERE id = ?")
                    ->execute([$token, $expires, $user['id']]);

                // Send verification email
                $sendRes = send_verification_email($user['email'], $user['username'], $user['id'], $token);

                if ($sendRes['success']) {
                    $messageType = 'success';
                    $message = 'Verification email sent to ' . htmlspecialchars($email) . '. Please check your inbox and spam folder.';
                } else {
                    $messageType = 'warning';
                    $message = 'Verification email sent to ' . htmlspecialchars($email) . '. (Note: Email delivery may not be configured. Please contact support.)';
                }
            }
        } catch (Exception $e) {
            error_log('TournamentHQ Resend Verification Error: ' . $e->getMessage());
            $messageType = 'danger';
            $message = 'An error occurred. Please try again.';
        }
    }
    } // end CSRF validation
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Resend verification email — Pingpong Tournament Manager">
    <title>Resend Verification Email | Pingpong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/public.css') ?>">
    <style>
        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background:
                radial-gradient(ellipse at 10% 20%, rgba(108,99,255,0.18) 0%, transparent 60%),
                radial-gradient(ellipse at 90% 80%, rgba(0,212,170,0.12) 0%, transparent 60%),
                var(--bg-900);
        }

        .rv-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--bg-800);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .rv-card h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 12px 0;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .rv-card .lead {
            font-size: 14px;
            color: var(--text-300);
            margin: 0 0 30px 0;
            line-height: 1.6;
        }

        .rv-alert {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .rv-alert-success {
            background-color: rgba(0, 212, 170, 0.15);
            color: var(--accent);
            border: 1px solid rgba(0, 212, 170, 0.3);
        }

        .rv-alert-danger {
            background-color: rgba(255, 77, 77, 0.15);
            color: #ff4d4d;
            border: 1px solid rgba(255, 77, 77, 0.3);
        }

        .rv-alert-warning {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .rv-alert-info {
            background-color: rgba(33, 150, 243, 0.15);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }

        .rv-alert a {
            color: inherit;
            text-decoration: underline;
        }

        .rv-form-group {
            margin-bottom: 20px;
        }

        .rv-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-200);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rv-form-group input {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            color: var(--text-100);
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .rv-form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.1);
        }

        .rv-btn {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rv-btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            margin-bottom: 16px;
        }

        .rv-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(108, 99, 255, 0.3);
        }

        .rv-btn-secondary {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .rv-btn-secondary:hover {
            background-color: rgba(108, 99, 255, 0.1);
        }

        .rv-divider {
            text-align: center;
            margin: 20px 0;
            font-size: 12px;
            color: var(--text-400);
        }

        .rv-links {
            text-align: center;
            font-size: 12px;
        }

        .rv-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 4px;
        }

        .rv-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="rv-card">
            <h1>Resend Verification</h1>
            <p class="lead">Didn't receive your verification email? We'll send a new one.</p>

            <?php if ($message): ?>
                <div class="rv-alert rv-alert-<?php echo htmlspecialchars($messageType); ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="rv-form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="your@email.com"
                        required
                    >
                </div>

                <button type="submit" class="rv-btn rv-btn-primary">
                    Resend Verification Email
                </button>
            </form>

            <div class="rv-divider">OR</div>

            <button onclick="location.href='<?= url('/index.php') ?>'" class="rv-btn rv-btn-secondary">
                Back to Sign In
            </button>

            <div class="rv-links">
                <p style="margin: 20px 0 10px 0; color: var(--text-300);">
                    Don't have an account? <a href="<?= url('/register.php') ?>">Sign up</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
