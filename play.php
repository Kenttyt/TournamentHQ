<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}

$featuredTournaments = [];
try {
    $stmt = db()->prepare("
        SELECT t.id, t.name, t.sport, t.category, t.status, t.start_date, t.end_date, t.venue, t.description, t.max_players,
               (SELECT COUNT(*) FROM tournament_players tp WHERE tp.tournament_id = t.id) as registered_count
        FROM tournaments t
        WHERE t.status IN ('upcoming', 'ongoing')
        ORDER BY t.start_date ASC
        LIMIT 6
    ");
    $stmt->execute();
    $featuredTournaments = $stmt->fetchAll();
} catch (PDOException $e) {
    $featuredTournaments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play | TournamentHQ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/lucide-static@latest/font/lucide.css">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/public.css') ?>">
    <style>
        .home-container {
            max-width: 800px;
            align-items: center;
            justify-content: center;
        }
        .landing-panel {
            flex: none;
            max-width: 700px;
            align-items: center;
            text-align: center;
        }

        .how-section {
            width: 100%; max-width: 900px; margin: 0 auto;
            padding: 50px 20px 40px; box-sizing: border-box;
        }
        .how-section > h2 {
            font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700;
            color: var(--text-100); margin: 0 0 32px 0; text-align: center;
        }
        .how-steps {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
        }
        @media (max-width: 640px) { .how-steps { grid-template-columns: 1fr; } }
        .how-step {
            display: flex; flex-direction: column; align-items: center; text-align: center;
            gap: 14px; padding: 24px 16px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border); border-radius: var(--radius-md);
        }
        .how-step:hover { border-color: rgba(108,99,255,0.25); background: rgba(108,99,255,0.03); }
        .how-step-num {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(108,99,255,0.15); color: var(--primary-light);
            font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .how-step h3 {
            font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 600;
            color: var(--text-100); margin: 0;
        }
        .how-step p {
            font-size: 13px; color: var(--text-300); line-height: 1.6; margin: 0;
        }

        .stats-section {
            width: 100%; max-width: 800px; margin: 0 auto;
            padding: 10px 20px 60px; box-sizing: border-box;
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
            text-align: center;
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        .stat-item { display: flex; flex-direction: column; gap: 4px; }
        .stat-num {
            font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 800;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-label {
            font-size: 12px; font-weight: 500; color: var(--text-400);
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .tournament-section {
            width: 100%; max-width: 1000px; margin: 0 auto;
            padding: 0 20px 60px; box-sizing: border-box;
        }
        .tournament-section > h2 {
            font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700;
            color: var(--text-100); margin: 0 0 24px 0; text-align: center;
        }
        .tournament-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px;
        }
        @media (max-width: 768px) { .tournament-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .tournament-grid { grid-template-columns: 1fr; } }
        .tournament-card {
            background: var(--bg-800); border: 1px solid var(--border);
            border-radius: var(--radius-md); padding: 20px;
            display: flex; flex-direction: column; gap: 12px;
            transition: border-color 0.2s ease, background 0.2s ease;
            text-decoration: none; color: inherit; cursor: pointer;
        }
        .tournament-card:hover {
            border-color: rgba(108,99,255,0.3);
            background: rgba(108,99,255,0.03);
        }
        .tournament-card-header {
            display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
        }
        .tournament-card-name {
            font-family: 'Outfit', sans-serif; font-size: 15px;
            font-weight: 600; color: var(--text-100); line-height: 1.3;
        }
        .tournament-badge {
            display: inline-flex; align-items: center; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;
        }
        .badge-upcoming { background: rgba(0,212,170,0.12); color: var(--accent); }
        .badge-ongoing { background: rgba(255,165,0,0.12); color: #ffa500; }
        .badge-category {
            background: rgba(108,99,255,0.12); color: var(--primary-light);
        }
        .tournament-card-meta {
            display: flex; flex-direction: column; gap: 6px;
        }
        .tournament-card-meta span {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: var(--text-400);
        }
        .tournament-card-meta i { width: 14px; height: 14px; flex-shrink: 0; }
        .tournament-card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 10px; border-top: 1px solid var(--border);
        }
        .tournament-card-footer .spots {
            font-size: 12px; font-weight: 500; color: var(--text-300);
        }
        .tournament-card-footer .spots strong { color: var(--text-100); }
        .tournament-card-footer .register-btn {
            font-size: 11px; font-weight: 700; color: var(--accent);
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .empty-state {
            text-align: center; padding: 40px 20px;
            color: var(--text-400); font-size: 14px;
        }
        .empty-state i { width: 40px; height: 40px; color: var(--text-400); margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }

        @media (max-width: 640px) {
            #bracketModal > div { margin: 16px; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav-container">
        <a href="<?= url('/index.php') ?>" class="brand-logo">
            <i data-lucide="trophy"></i>
            <span>TournamentHQ<em>.</em></span>
        </a>
        <nav class="nav-links">
            <a href="<?= url('/login.php') ?>" class="nav-login">
                <i data-lucide="log-in" style="width:16px;height:16px;"></i> Login
            </a>
        </nav>
    </div>
</header>

<div class="login-page">
    <div class="home-container">
        <aside class="landing-panel">
            <h1 class="landing-headline">
                Find your sport.<br><em>Compete</em> in tournaments.
            </h1>
            <p class="landing-lead">
                Browse upcoming tournaments across multiple sports. Create your player profile and register for events.
            </p>
        </aside>
    </div>
</div>



<div class="how-section">
    <h2>How It Works</h2>
    <div class="how-steps">
        <div class="how-step">
            <span class="how-step-num">1</span>
            <h3>Browse Sports</h3>
            <p>Explore upcoming tournaments across table tennis, tennis, badminton, and more.</p>
        </div>
        <div class="how-step">
            <span class="how-step-num">2</span>
            <h3>Register</h3>
            <p>Create your player profile and sign up for events that match your skill level.</p>
        </div>
        <div class="how-step">
            <span class="how-step-num">3</span>
            <h3>Compete</h3>
            <p>Show up, play your matches, and track your results on the leaderboard.</p>
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-num">500+</span>
            <span class="stat-label">Players</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">50+</span>
            <span class="stat-label">Tournaments</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">10K</span>
            <span class="stat-label">Matches Played</span>
        </div>
        <div class="stat-item">
            <span class="stat-num">100%</span>
            <span class="stat-label">Free</span>
        </div>
    </div>
</div>

<div class="tournament-section">
    <h2>Featured Tournaments</h2>
    <?php if (empty($featuredTournaments)): ?>
        <div class="empty-state">
            <i data-lucide="calendar-x"></i>
            <p>No upcoming tournaments yet. Check back soon!!</p>
        </div>
    <?php else: ?>
        <div class="tournament-grid">
            <?php foreach ($featuredTournaments as $t): ?>
                <div class="tournament-card" data-tid="<?= (int)$t['id'] ?>">
                    <div class="tournament-card-header">
                        <span class="tournament-card-name"><?= e($t['name']) ?></span>
                        <span class="tournament-badge badge-<?= $t['status'] ?>"><?= e($t['status']) ?></span>
                    </div>
                    <span class="tournament-badge badge-category"><?= e($t['sport'] ?? 'Table Tennis') ?></span>
                    <span class="tournament-badge badge-category"><?= e($t['category']) ?></span>
                    <div class="tournament-card-meta">
                        <span><i data-lucide="calendar"></i> <?= date('M j, Y', strtotime($t['start_date'])) ?></span>
                        <?php if ($t['venue']): ?>
                            <span><i data-lucide="map-pin"></i> <?= e($t['venue']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t['description'])): ?>
                            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;" title="<?= e($t['description']) ?>"><?= e($t['description']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tournament-card-footer">
                        <span class="spots"><strong><?= (int)$t['registered_count'] ?></strong> / <?= (int)$t['max_players'] ?> players</span>
                        <button type="button" class="register-btn js-view-bracket-btn" data-tid="<?= (int)$t['id'] ?>" data-tname="<?= e($t['name']) ?>" style="background:none;border:none;cursor:pointer;font-family:inherit;">View &rarr;</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer class="site-footer">
    TournamentHQ
</footer>

<!-- Bracket View Modal -->
<div id="bracketModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;padding:20px;">
    <div style="max-width:960px;margin:40px auto;background:var(--bg-800);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border);">
            <h3 id="bracketModalTitle" style="margin:0;font-family:'Outfit',sans-serif;font-size:18px;font-weight:700;color:var(--text-100);">Tournament Bracket</h3>
            <button type="button" id="bracketModalClose" style="background:none;border:none;color:var(--text-400);font-size:24px;cursor:pointer;padding:0 4px;line-height:1;" title="Close">&times;</button>
        </div>
        <div id="bracketModalBody" style="padding:24px;min-height:200px;overflow-x:auto;">
            <div style="text-align:center;padding:40px;color:var(--text-400);">Loading bracket...</div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('bracketModal');
    var modalBody = document.getElementById('bracketModalBody');
    var modalTitle = document.getElementById('bracketModalTitle');
    var closeBtn = document.getElementById('bracketModalClose');

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        modalBody.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-400);">Loading bracket...</div>';
    }

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });

    document.querySelectorAll('.js-view-bracket-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var tid = btn.getAttribute('data-tid');
            var tname = btn.getAttribute('data-tname');
            modalTitle.textContent = tname + ' — Bracket';
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            modalBody.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-400);">Loading bracket...</div>';

            fetch('<?= url('/includes/bracket_view_public.php') ?>?tournament_id=' + encodeURIComponent(tid))
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    if (html.indexOf('No bracket yet') !== -1 || html.trim() === '') {
                        modalBody.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-400);"><p style="font-size:15px;font-weight:600;margin-bottom:8px;">No bracket available yet</p><p style="font-size:13px;">The bracket for this tournament has not been generated. Check back once registration is closed and the tournament starts!</p></div>';
                    } else {
                        modalBody.innerHTML = html;
                        // Re-initialize Lucide icons inside the modal if available
                        if (window.lucide) {
                            try { lucide.createIcons(); } catch(_) {}
                        }
                        // Execute scripts embedded in the bracket HTML (drawBracketLines, etc.)
                        // innerHTML doesn't run <script> tags, so we extract and re-execute them
                        var scripts = modalBody.querySelectorAll('script');
                        scripts.forEach(function(old) {
                            var s = document.createElement('script');
                            s.textContent = old.textContent;
                            document.body.appendChild(s);
                            old.remove();
                        });
                        // Draw bracket lines after scripts have run and DOM is settled
                        setTimeout(function() {
                            if (typeof window.drawBracketLines === 'function') {
                                window.drawBracketLines();
                            }
                        }, 300);
                    }
                })
                .catch(function() {
                    modalBody.innerHTML = '<div style="text-align:center;padding:40px;color:var(--danger);">Failed to load bracket. Please try again.</div>';
                });
        });
    });

    // Also make the entire tournament card clickable to open bracket
    document.querySelectorAll('.tournament-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Don't double-fire if the View button was clicked
            if (e.target.closest('.js-view-bracket-btn')) return;
            var btn = card.querySelector('.js-view-bracket-btn');
            if (btn) btn.click();
        });
    });
})();
</script>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="<?= url('/assets/js/public.js') ?>"></script>
</body>
</html>
