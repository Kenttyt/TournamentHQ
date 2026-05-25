<?php
/** @var string $authPageTitle */
/** @var string $authPageSubtitle */
function authPublicHeader(string $title, string $subtitle = ''): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | TT Tournament Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/table-tennis-system/assets/css/style.css">
    <style>
        .auth-public-page {
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
        .auth-public-card {
            background: var(--bg-800);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg), var(--shadow-glow);
        }
        .auth-public-card h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-100);
            margin: 0 0 6px;
        }
        .auth-public-card .lead {
            font-size: 13px;
            color: var(--text-400);
            margin: 0 0 22px;
            line-height: 1.5;
        }
        .auth-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 18px;
            font-size: 13px;
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-back-link:hover { text-decoration: underline; }
        .dev-reset-link {
            margin-top: 14px;
            padding: 12px;
            background: rgba(56,189,248,0.08);
            border: 1px solid rgba(56,189,248,0.25);
            border-radius: var(--radius-sm);
            font-size: 12px;
            word-break: break-all;
        }
        .dev-reset-link a { color: var(--info); font-weight: 600; }
    </style>
</head>
<body>
<div class="auth-public-page">
    <div class="auth-public-card">
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><p class="lead"><?= e($subtitle) ?></p><?php endif; ?>
    <?php
}

function authPublicFooter(): void {
    ?>
    </div>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>
</html>
    <?php
}
