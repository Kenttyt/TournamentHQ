<?php
/**
 * Shared UI for Auto Bracket Generator
 * Expects: $tournaments, $tid, $tournament, $entrants, $bracketGroups, $formAction, $selectedGroupSize
 */
?>
<div class="page-header">
    <div class="page-heading">
        <h1>Auto Bracket Generator</h1>
        <p>Split participants into groups — choose players per group (round-robin within each group) or select All for a full round robin</p>
    </div>
</div>

<div class="card mb-24">
    <div class="card-body" style="padding: 20px;">
        <form method="GET" class="filter-bar" style="align-items: flex-end;">
            <div class="form-group" style="margin: 0; flex: 1; max-width: 420px;">
                <label class="form-label">Tournament</label>
                <select name="tournament_id" class="form-select" onchange="this.form.submit()">
                    <option value="">— Select tournament —</option>
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $tid === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?> (<?= e($t['status']) ?> · <?= (int) $t['registered_count'] ?>/<?= (int) $t['max_players'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($tid && $tournament): ?>
<div class="content-grid mb-24">
    <div class="card">
        <div class="card-header" id="generatorCardHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="card-title"><?= e($tournament['name']) ?></div>
                    <span class="badge badge-<?= e($tournament['status']) ?>"><?= ucfirst(e($tournament['status'])) ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                    <span id="generatorToggleText">Collapse Setup</span>
                    <svg id="generatorToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </div>
            </div>
        </div>
        <div id="generatorCardBody" style="transition: max-height 0.3s ease-in-out, opacity 0.2s; max-height: 2000px; overflow: hidden; opacity: 1;">
            <div class="card-body">
                <p class="text-sm text-muted" style="margin-bottom: 16px;">
                    <?= (int) count($entrants) ?> participant(s) registered (account players and guests)
                </p>

                <?php if (count($entrants) < 2): ?>
                    <div class="flash-message flash-warning" style="position: static; margin-bottom: 16px;">
                        Need at least 2 participants before generating a bracket.
                    </div>
                <?php else:
                    $entrantCount = count($entrants);
                    $rawGroupSize = $selectedGroupSize ?? 4;
                    $isAllRR = ($rawGroupSize === 'all' || $rawGroupSize === 0);
                    $groupSize = $isAllRR ? $entrantCount : normalizeGroupSize((int) $rawGroupSize);
                    $estimatedGroups = $isAllRR ? 1 : estimateGroupCount($entrantCount, $groupSize);
                ?>
                    <form method="POST" action="<?= e($formAction) ?>" onsubmit="return confirm('Generate new group brackets? Existing scheduled matches for this tournament will be replaced.');">
                        <input type="hidden" name="action" value="generate">
                        <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 16px;">
                            <div class="form-group" style="margin: 0; min-width: 200px;">
                                <label class="form-label">Players per group</label>
                                <select name="group_size" class="form-select" id="groupSizeSelect">
                                    <option value="2" <?= !$isAllRR && $groupSize === 2 ? 'selected' : '' ?>>2 — Head-to-head</option>
                                    <option value="3" <?= !$isAllRR && $groupSize === 3 ? 'selected' : '' ?>>3 — Round-robin</option>
                                    <option value="4" <?= !$isAllRR && $groupSize === 4 ? 'selected' : '' ?>>4 — Round-robin</option>
                                    <option value="all" <?= $isAllRR ? 'selected' : '' ?>>All — Full Round Robin</option>
                                </select>
                                <p class="text-xs text-muted" style="margin-top: 6px;" id="groupSizeHint">
                                    <?php if ($isAllRR): ?>
                                        <?= $entrantCount ?> players → 1 group (full round robin · <?= $entrantCount * ($entrantCount - 1) / 2 ?> matches)
                                    <?php else: ?>
                                        <?= $entrantCount ?> players → about <?= $estimatedGroups ?> group(s)<?= ($entrantCount % $groupSize !== 0) ? ' (extra player goes to a random group)' : '' ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding-bottom: 10px;">
                                <input type="checkbox" name="shuffle" value="1">
                                Randomize player order
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Generate Bracket
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($entrants)): ?>
                <div style="margin-top: 20px; max-height: 200px; overflow-y: auto;">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Player</th><th>Club</th></tr></thead>
                        <tbody>
                        <?php foreach ($entrants as $i => $p): ?>
                            <tr>
                                <td class="text-muted text-sm"><?= $i + 1 ?></td>
                                <td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                <td class="text-sm"><?= e($p['club'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const header = document.getElementById('generatorCardHeader');
    const body = document.getElementById('generatorCardBody');
    const text = document.getElementById('generatorToggleText');
    const icon = document.getElementById('generatorToggleIcon');
    if (!header || !body) return;

    // Check localStorage state on load
    const isCollapsed = localStorage.getItem('generator_collapsed') === 'true';
    if (isCollapsed) {
        body.style.maxHeight = '0';
        body.style.opacity = '0';
        body.style.display = 'none';
        if (text) text.textContent = 'Expand Setup';
        if (icon) icon.style.transform = 'rotate(-180deg)';
    }

    header.addEventListener('mouseenter', function() {
        header.style.background = 'rgba(255, 255, 255, 0.02)';
    });
    header.addEventListener('mouseleave', function() {
        header.style.background = 'none';
    });

    header.addEventListener('click', function() {
        const currentlyCollapsed = body.style.maxHeight === '0px' || body.style.maxHeight === '0' || body.style.display === 'none';
        if (currentlyCollapsed) {
            body.style.display = 'block';
            body.offsetHeight;
            body.style.maxHeight = '2000px';
            body.style.opacity = '1';
            if (text) text.textContent = 'Collapse Setup';
            if (icon) icon.style.transform = 'rotate(0deg)';
            localStorage.setItem('generator_collapsed', 'false');
        } else {
            body.style.maxHeight = '0';
            body.style.opacity = '0';
            setTimeout(() => {
                if (body.style.maxHeight === '0px' || body.style.maxHeight === '0') {
                    body.style.display = 'none';
                }
            }, 300);
            if (text) text.textContent = 'Expand Setup';
            if (icon) icon.style.transform = 'rotate(-180deg)';
            localStorage.setItem('generator_collapsed', 'true');
        }
    });
})();
</script>
<?php endif; ?>

<div class="card">
    <div class="card-header" id="bracketCardHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div class="card-title" style="margin: 0;">Group Stage Brackets</div>
                <?php if ($tid): ?>
                    <span class="text-muted text-xs">Tournament #<?= $tid ?></span>
                <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                <span id="bracketToggleText">Collapse Bracket</span>
                <svg id="bracketToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
            </div>
        </div>
    </div>
    <div id="bracketCardBody" style="transition: max-height 0.4s ease-in-out, opacity 0.2s; max-height: 5000px; overflow: hidden; opacity: 1;">
        <div class="card-body">
            <?php
            $recordResultUrl = $formAction;
            $showOnlyPhase = 'group';
            include __DIR__ . '/bracket_view.php';
            ?>
        </div>
    </div>
</div>

<script>
(function() {
    const header = document.getElementById('bracketCardHeader');
    const body = document.getElementById('bracketCardBody');
    const text = document.getElementById('bracketToggleText');
    const icon = document.getElementById('bracketToggleIcon');
    if (!header || !body) return;

    // Check localStorage state on load
    const isCollapsed = localStorage.getItem('bracket_collapsed') === 'true';
    if (isCollapsed) {
        body.style.maxHeight = '0';
        body.style.opacity = '0';
        body.style.display = 'none';
        if (text) text.textContent = 'Expand Bracket';
        if (icon) icon.style.transform = 'rotate(-180deg)';
    }

    header.addEventListener('mouseenter', function() {
        header.style.background = 'rgba(255, 255, 255, 0.02)';
    });
    header.addEventListener('mouseleave', function() {
        header.style.background = 'none';
    });

    header.addEventListener('click', function() {
        const currentlyCollapsed = body.style.maxHeight === '0px' || body.style.maxHeight === '0' || body.style.display === 'none';
        if (currentlyCollapsed) {
            body.style.display = 'block';
            body.offsetHeight;
            body.style.maxHeight = '5000px';
            body.style.opacity = '1';
            if (text) text.textContent = 'Collapse Bracket';
            if (icon) icon.style.transform = 'rotate(0deg)';
            localStorage.setItem('bracket_collapsed', 'false');
        } else {
            body.style.maxHeight = '0';
            body.style.opacity = '0';
            setTimeout(() => {
                if (body.style.maxHeight === '0px' || body.style.maxHeight === '0') {
                    body.style.display = 'none';
                }
            }, 400);
            if (text) text.textContent = 'Expand Bracket';
            if (icon) icon.style.transform = 'rotate(-180deg)';
            localStorage.setItem('bracket_collapsed', 'true');
        }
    });
})();
</script>

<?php
// Check if group stage matches exist
$hasGroupMatches = false;
$allGroupMatchesCompleted = true;
$groupMatchesCount = 0;
if (!empty($bracketGroups)) {
    foreach ($bracketGroups as $group) {
        if (preg_match('/^Group [A-Z]/i', $group['label'])) {
            $hasGroupMatches = true;
            foreach ($group['matches'] as $m) {
                $groupMatchesCount++;
                if ($m['status'] !== 'completed') {
                    $allGroupMatchesCompleted = false;
                }
            }
        }
    }
}
?>

<?php if ($hasGroupMatches): ?>
    <div class="card mt-24">
        <div class="card-header" id="proceedKnockoutCardHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div class="card-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    Proceed to Knockout Stage
                </div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                    <span id="proceedKnockoutToggleText">Collapse Section</span>
                    <svg id="proceedKnockoutToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </div>
            </div>
        </div>
        <div id="proceedKnockoutCardBody" style="transition: max-height 0.4s ease-in-out, opacity 0.2s; max-height: 2000px; overflow: hidden; opacity: 1;">
            <div class="card-body">
                <p class="text-sm text-muted" style="margin-bottom: 16px;">
                    <?php if ($allGroupMatchesCompleted): ?>
                        <span style="color: var(--success); font-weight: 600;">✓ All <?= $groupMatchesCount ?> group stage matches are completed!</span> You can now proceed to generate the knockout bracket.
                    <?php else: ?>
                        <span style="color: var(--warning); font-weight: 600;">⚠ Note: Some group stage matches are still scheduled.</span> Proceeding will generate the knockout bracket using the current standings.
                    <?php endif; ?>
                </p>

                <?php 
                $qualifiers = getGroupStageQualifiers($tid);
                $rank1s = [];
                $rank2s = [];
                foreach ($qualifiers as $idx => $player) {
                    if ($idx % 2 === 0) {
                        $rank1s[] = $player;
                    } else {
                        $rank2s[] = $player;
                    }
                }
                if (!empty($qualifiers)):
                ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: var(--success); font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <span>🥇 Rank 1 Qualifiers</span>
                                <span class="badge badge-success" style="font-size: 10px; padding: 2px 6px;"><?= count($rank1s) ?></span>
                            </h4>
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--text-200); line-height: 1.8;">
                                <?php foreach ($rank1s as $p): ?>
                                    <li><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong> <span class="text-muted" style="font-size: 11px;">(<?= e($p['group_label']) ?>)</span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: var(--accent); font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <span>🥈 Rank 2 Qualifiers</span>
                                <span class="badge badge-accent" style="font-size: 10px; padding: 2px 6px;"><?= count($rank2s) ?></span>
                            </h4>
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--text-200); line-height: 1.8;">
                                <?php foreach ($rank2s as $p): ?>
                                    <li><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong> <span class="text-muted" style="font-size: 11px;">(<?= e($p['group_label']) ?>)</span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?= e($formAction) ?>" onsubmit="return confirm('Generate knockout bracket? This will fetch Rank 1 & Rank 2 from each group and create matches.');">
                    <input type="hidden" name="action" value="generate_knockout">
                    <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                        <div class="form-group" style="margin: 0; min-width: 200px;">
                            <label class="form-label">Knockout Format</label>
                            <select name="knockout_format" class="form-select">
                                <option value="single_elimination">Single Elimination</option>
                                <option value="double_elimination">Double Elimination</option>
                            </select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding-bottom: 10px;">
                            <input type="checkbox" name="include_3rd_place" value="1" checked>
                            Include 3rd Place Playoff
                        </label>
                        <button type="submit" class="btn btn-accent">
                            Generate Knockout Bracket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const header = document.getElementById('proceedKnockoutCardHeader');
        const body = document.getElementById('proceedKnockoutCardBody');
        const text = document.getElementById('proceedKnockoutToggleText');
        const icon = document.getElementById('proceedKnockoutToggleIcon');
        if (!header || !body) return;

        // Check localStorage state on load
        const isCollapsed = localStorage.getItem('proceed_knockout_collapsed') === 'true';
        if (isCollapsed) {
            body.style.maxHeight = '0';
            body.style.opacity = '0';
            body.style.display = 'none';
            if (text) text.textContent = 'Expand Section';
            if (icon) icon.style.transform = 'rotate(-180deg)';
        }

        header.addEventListener('mouseenter', function() {
            header.style.background = 'rgba(255, 255, 255, 0.02)';
        });
        header.addEventListener('mouseleave', function() {
            header.style.background = 'none';
        });

        header.addEventListener('click', function() {
            const currentlyCollapsed = body.style.maxHeight === '0px' || body.style.maxHeight === '0' || body.style.display === 'none';
            if (currentlyCollapsed) {
                body.style.display = 'block';
                body.offsetHeight;
                body.style.maxHeight = '2000px';
                body.style.opacity = '1';
                if (text) text.textContent = 'Collapse Section';
                if (icon) icon.style.transform = 'rotate(0deg)';
                localStorage.setItem('proceed_knockout_collapsed', 'false');
            } else {
                body.style.maxHeight = '0';
                body.style.opacity = '0';
                setTimeout(() => {
                    if (body.style.maxHeight === '0px' || body.style.maxHeight === '0') {
                        body.style.display = 'none';
                    }
                }, 400);
                if (text) text.textContent = 'Expand Section';
                if (icon) icon.style.transform = 'rotate(-180deg)';
                localStorage.setItem('proceed_knockout_collapsed', 'true');
            }
        });
    })();
    </script>
<?php endif; ?>

<?php
// Check if knockout matches exist
$hasKnockoutMatches = false;
if (!empty($bracketGroups)) {
    foreach ($bracketGroups as $group) {
        if (!preg_match('/^Group [A-Z]/i', $group['label'])) {
            $hasKnockoutMatches = true;
            break;
        }
    }
}
?>

<?php if ($hasKnockoutMatches): ?>
    <div class="card mt-24">
        <div class="card-header" id="knockoutBracketCardHeader" style="cursor: pointer; user-select: none; transition: background 0.2s;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div class="card-title" style="margin: 0;">Knockout Stage Brackets</div>
                    <?php if ($tid): ?>
                        <span class="text-muted text-xs">Tournament #<?= $tid ?></span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--text-400); font-size: 12px; font-weight: 500;">
                    <span id="knockoutBracketToggleText">Collapse Bracket</span>
                    <svg id="knockoutBracketToggleIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.3s;"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </div>
            </div>
        </div>
        <div id="knockoutBracketCardBody" style="transition: max-height 0.4s ease-in-out, opacity 0.2s; max-height: 5000px; overflow: hidden; opacity: 1;">
            <div class="card-body">
                <?php
                $recordResultUrl = $formAction;
                $showOnlyPhase = 'knockout';
                include __DIR__ . '/bracket_view.php';
                ?>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const header = document.getElementById('knockoutBracketCardHeader');
        const body = document.getElementById('knockoutBracketCardBody');
        const text = document.getElementById('knockoutBracketToggleText');
        const icon = document.getElementById('knockoutBracketToggleIcon');
        if (!header || !body) return;

        const isCollapsed = localStorage.getItem('knockout_bracket_collapsed') === 'true';
        if (isCollapsed) {
            body.style.maxHeight = '0';
            body.style.opacity = '0';
            body.style.display = 'none';
            if (text) text.textContent = 'Expand Bracket';
            if (icon) icon.style.transform = 'rotate(-180deg)';
        }

        header.addEventListener('mouseenter', function() {
            header.style.background = 'rgba(255, 255, 255, 0.02)';
        });
        header.addEventListener('mouseleave', function() {
            header.style.background = 'none';
        });

        header.addEventListener('click', function() {
            const currentlyCollapsed = body.style.maxHeight === '0px' || body.style.maxHeight === '0' || body.style.display === 'none';
            if (currentlyCollapsed) {
                body.style.display = 'block';
                body.offsetHeight;
                body.style.maxHeight = '5000px';
                body.style.opacity = '1';
                if (text) text.textContent = 'Collapse Bracket';
                if (icon) icon.style.transform = 'rotate(0deg)';
                localStorage.setItem('knockout_bracket_collapsed', 'false');
                
                // Dispatch resize event to force bracket connector lines redraw
                window.dispatchEvent(new Event('resize'));
                setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
                setTimeout(() => window.dispatchEvent(new Event('resize')), 400);
            } else {
                body.style.maxHeight = '0';
                body.style.opacity = '0';
                setTimeout(() => {
                    if (body.style.maxHeight === '0px' || body.style.maxHeight === '0') {
                        body.style.display = 'none';
                    }
                }, 400);
                if (text) text.textContent = 'Expand Bracket';
                if (icon) icon.style.transform = 'rotate(-180deg)';
                localStorage.setItem('knockout_bracket_collapsed', 'true');
            }
        });
    })();
    </script>
<?php endif; ?>

<!-- Record result modal -->
<div class="modal-overlay" id="bracketResultModal">
    <div class="modal" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title" id="bracketModalTitle">Record Match Result</div>
            <button type="button" class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= e($formAction) ?>">
            <input type="hidden" name="action" value="result">
            <input type="hidden" name="tournament_id" value="<?= $tid ?>">
            <input type="hidden" name="match_id" id="bracketMatchId">
            <input type="hidden" name="winner_key" id="bracketWinnerKey">
            <div class="modal-body">
                <p id="bracketMatchLabel" style="font-weight: 600; margin-bottom: 16px;"></p>
                <div class="form-group">
                    <label class="form-label">Winner</label>
                    <select id="bracketWinnerSelect" class="form-select" required>
                        <option value="">— Select winner —</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" id="bracketP1Label">Player 1 sets</label>
                        <input type="number" name="player1_score" class="form-control" min="0" value="0" required title="Sets won">
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="bracketP2Label">Player 2 sets</label>
                        <input type="number" name="player2_score" class="form-control" min="0" value="0" required title="Sets won">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <label class="form-label" style="font-weight: 600; margin-bottom: 8px;">Set Scores (Points)</label>
                    <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 12px; align-items: center; margin-bottom: 8px; text-align: center;">
                        <span></span>
                        <span id="bracketSetP1Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;">Player 1</span>
                        <span id="bracketSetP2Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;">Player 2</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="setScoresContainer">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 12px; align-items: center;">
                                <span style="font-size: 11px; font-weight: 600; color: var(--text-300);">Set <?= $s ?></span>
                                <input type="number" class="form-control js-set-p1" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 8px; font-size: 12px;">
                                <input type="number" class="form-control js-set-p2" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 8px; font-size: 12px;">
                            </div>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="set_scores" id="bracketSetScoresInput">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="bracketSaveBtn">Save Result</button>
            </div>
        </form>
    </div>
</div>

<script>
function calculateSetsFromSetInputs() {
    let p1Sets = 0;
    let p2Sets = 0;
    const setScores = [];
    
    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        
        if (p1Sets >= 3 || p2Sets >= 3) {
            p1Input.disabled = true;
            p2Input.disabled = true;
            p1Input.value = '';
            p2Input.value = '';
            continue;
        } else {
            p1Input.disabled = false;
            p2Input.disabled = false;
        }
        
        const p1Val = p1Input.value;
        const p2Val = p2Input.value;
        
        if (p1Val !== '' && p2Val !== '') {
            const p1 = parseInt(p1Val, 10);
            const p2 = parseInt(p2Val, 10);
            setScores.push(p1 + '-' + p2);
            if (p1 > p2) {
                p1Sets++;
            } else if (p2 > p1) {
                p2Sets++;
            }
        }
    }
    
    document.querySelector('[name="player1_score"]').value = p1Sets;
    document.querySelector('[name="player2_score"]').value = p2Sets;
    
    if (setScores.length > 0) {
        const sel = document.getElementById('bracketWinnerSelect');
        const p1Key = sel.options[1] ? sel.options[1].value : '';
        const p2Key = sel.options[2] ? sel.options[2].value : '';
        
        if (p1Sets > p2Sets) {
            sel.value = p1Key;
            document.getElementById('bracketWinnerKey').value = p1Key;
        } else if (p2Sets > p1Sets) {
            sel.value = p2Key;
            document.getElementById('bracketWinnerKey').value = p2Key;
        }
    }
    
    document.getElementById('bracketSetScoresInput').value = setScores.join(',');
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-set-p1, .js-set-p2').forEach(function(input) {
        input.addEventListener('input', calculateSetsFromSetInputs);
    });
});

function openBracketResultModal(btn) {
    const isEdit = btn.getAttribute('data-edit') === '1';
    const p1Key = btn.getAttribute('data-p1-key');
    const p2Key = btn.getAttribute('data-p2-key');
    const p1Name = btn.getAttribute('data-p1-name');
    const p2Name = btn.getAttribute('data-p2-name');
    const winnerKey = btn.getAttribute('data-winner-key') || '';

    document.getElementById('bracketModalTitle').textContent = isEdit ? 'Edit Match Result' : 'Record Match Result';
    document.getElementById('bracketSaveBtn').textContent = isEdit ? 'Update Result' : 'Save Result';
    document.getElementById('bracketMatchId').value = btn.getAttribute('data-match-id');
    document.getElementById('bracketMatchLabel').textContent = p1Name + ' vs ' + p2Name;
    document.getElementById('bracketP1Label').textContent = p1Name + ' sets';
    document.getElementById('bracketP2Label').textContent = p2Name + ' sets';
    document.getElementById('bracketSetP1Header').textContent = p1Name;
    document.getElementById('bracketSetP2Header').textContent = p2Name;

    const form = document.querySelector('#bracketResultModal form');
    form.querySelector('[name="player1_score"]').value = btn.getAttribute('data-p1-sets') || '0';
    form.querySelector('[name="player2_score"]').value = btn.getAttribute('data-p2-sets') || '0';

    const setScoresRaw = btn.getAttribute('data-set-scores') || '';
    document.getElementById('bracketSetScoresInput').value = setScoresRaw;
    const setsArray = setScoresRaw ? setScoresRaw.split(',') : [];
    
    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        
        if (setsArray[s - 1]) {
            const parts = setsArray[s - 1].split('-');
            p1Input.value = parts[0] || '';
            p2Input.value = parts[1] || '';
        } else {
            p1Input.value = '';
            p2Input.value = '';
        }
    }
    
    calculateSetsFromSetInputs();

    const sel = document.getElementById('bracketWinnerSelect');
    sel.innerHTML = '<option value="">— Select winner —</option>'
        + '<option value="' + p1Key + '">' + p1Name + '</option>'
        + '<option value="' + p2Key + '">' + p2Name + '</option>';
    sel.value = winnerKey;
    document.getElementById('bracketWinnerKey').value = winnerKey;
    sel.onchange = function () {
        document.getElementById('bracketWinnerKey').value = sel.value;
    };

    if (window.TTMS) {
        TTMS.openModal('bracketResultModal');
    } else {
        document.getElementById('bracketResultModal').classList.add('open');
    }
}

document.querySelectorAll('.js-bracket-result-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openBracketResultModal(btn);
    });
});

document.querySelector('#bracketResultModal form')?.addEventListener('submit', function (e) {
    const sel = document.getElementById('bracketWinnerSelect');
    document.getElementById('bracketWinnerKey').value = sel.value;
    if (!sel.value) {
        e.preventDefault();
        alert('Please select the winner.');
    }
});

(function () {
    const select = document.getElementById('groupSizeSelect');
    const hint = document.getElementById('groupSizeHint');
    const total = <?= (int) count($entrants ?? []) ?>;
    if (!select || !hint) return;
    select.addEventListener('change', function () {
        if (select.value === 'all') {
            const matches = total * (total - 1) / 2;
            hint.textContent = total + ' players → 1 group (full round robin · ' + matches + ' matches)';
            return;
        }
        const size = parseInt(select.value, 10) || 4;
        const groups = Math.ceil(total / size);
        const extra = total % size;
        let text = total + ' players → about ' + groups + ' group(s)';
        if (extra !== 0) {
            text += ' (extra player goes to a random group)';
        }
        hint.textContent = text;
    });
})();
</script>
