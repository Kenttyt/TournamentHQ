<?php
/**
 * Shared UI for Auto Bracket Generator
 * Expects: $tournaments, $tid, $tournament, $entrants, $bracketGroups, $formAction, $selectedGroupSize
 */
$isTeamEvent = !empty($tournament['is_team_event']);
$entrantLabel = $isTeamEvent ? 'team' : 'player';
$entrantLabelPlural = $isTeamEvent ? 'teams' : 'players';
?>
<div class="page-header">
    <div class="page-heading">
        <h1>Auto Bracket Generator</h1>
        <p>Split participants into groups — choose <?= $entrantLabelPlural ?> per group (round-robin within each group) or select All for a full round robin</p>
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
                    <?= (int) count($entrants) ?> <?= $entrantLabelPlural ?> registered
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
                    <form method="POST" action="<?= e($formAction) ?>" id="groupGenForm">
                        <input type="hidden" name="action" value="generate">
                        <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 16px;">
                            <div class="form-group" style="margin: 0; min-width: 200px;">
                                <label class="form-label"><?= ucfirst($entrantLabelPlural) ?> per group</label>
                                <select name="group_size" class="form-select" id="groupSizeSelect">
                                    <option value="2" <?= !$isAllRR && $groupSize === 2 ? 'selected' : '' ?>>2 — Head-to-head</option>
                                    <option value="3" <?= !$isAllRR && $groupSize === 3 ? 'selected' : '' ?>>3 — Round-robin</option>
                                    <option value="4" <?= !$isAllRR && $groupSize === 4 ? 'selected' : '' ?>>4 — Round-robin</option>
                                    <option value="all" <?= $isAllRR ? 'selected' : '' ?>>All — Full Round Robin</option>
                                </select>
                                <p class="text-xs text-muted" style="margin-top: 6px;" id="groupSizeHint">
                                    <?php if ($isAllRR): ?>
                                        <?= $entrantCount ?> <?= $entrantLabelPlural ?> → 1 group (full round robin · <?= $entrantCount * ($entrantCount - 1) / 2 ?> matches)
                                    <?php else: ?>
                                        <?= $entrantCount ?> <?= $entrantLabelPlural ?> → about <?= $estimatedGroups ?> group(s)<?= ($entrantCount % $groupSize !== 0) ? ' (extra ' . $entrantLabel . ' goes to a random group)' : '' ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding-bottom: 10px;">
                                <input type="checkbox" name="shuffle" value="1">
                                Randomize <?= $entrantLabel ?> order
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary" id="groupGenBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Generate Bracket
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($entrants)): ?>
                <div style="margin-top: 20px; max-height: 200px; overflow-y: auto;">
                    <table class="data-table">
                        <thead><tr><th>#</th><th><?= ucfirst($entrantLabel) ?></th><th>Club</th></tr></thead>
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
        <div class="card-body" id="bracket-view-body">
            <?php
            $recordResultUrl = $formAction;
            $showOnlyPhase = 'group';
            $bracketIsTeamEvent = !empty($tournament['is_team_event']);
            $bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
            $bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
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
                $qualifiers = getGroupStageQualifiers($tid, 3);
                $rank1s = [];
                $rank2s = [];
                $rank3s = [];
                foreach ($qualifiers as $player) {
                    $r = (int)($player['rank'] ?? 1);
                    if ($r === 1) $rank1s[] = $player;
                    elseif ($r === 2) $rank2s[] = $player;
                    elseif ($r === 3) $rank3s[] = $player;
                }
                if (!empty($qualifiers)):
                ?>
                    <div id="qualifierPreview" style="display: grid; gap: 16px; margin-bottom: 24px;">
                        <div class="qualifier-section" data-rank="1" style="background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px;">
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
                        <div class="qualifier-section" data-rank="2" style="display: none; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px;">
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
                        <?php if (!empty($rank3s)): ?>
                        <div class="qualifier-section" data-rank="3" style="display: none; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 16px;">
                            <h4 style="margin: 0 0 12px 0; color: #ffc107; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                <span>🥉 Rank 3 Qualifiers</span>
                                <span class="badge" style="font-size: 10px; padding: 2px 6px; background: rgba(255,193,7,0.15); color: #ffc107;"><?= count($rank3s) ?></span>
                            </h4>
                            <ul style="margin: 0; padding-left: 18px; font-size: 12px; color: var(--text-200); line-height: 1.8;">
                                <?php foreach ($rank3s as $p): ?>
                                    <li><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong> <span class="text-muted" style="font-size: 11px;">(<?= e($p['group_label']) ?>)</span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?= e($formAction) ?>" id="knockoutGenForm">
                    <input type="hidden" name="action" value="generate_knockout">
                    <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                        <div class="form-group" style="margin: 0; min-width: 200px;">
                            <label class="form-label">Qualifiers Per Group</label>
                            <select name="qualifiers_per_group" class="form-select" id="qualifiersPerGroup" onchange="updateQualifierPreview()">
                                <?php $selQPG = max(1, min(3, (int)($_GET['qualifiers_per_group'] ?? $_POST['qualifiers_per_group'] ?? 2))); ?>
                                <option value="1"<?= $selQPG === 1 ? ' selected' : '' ?>>Rank 1 only</option>
                                <option value="2"<?= $selQPG === 2 ? ' selected' : '' ?>>Rank 1 &amp; Rank 2</option>
                                <option value="3"<?= $selQPG === 3 ? ' selected' : '' ?>>Rank 1, 2 &amp; 3</option>
                            </select>
                            <p id="qualifierHint" style="margin: 6px 0 0 0; font-size: 11px; color: var(--text-400); line-height: 1.5;"></p>
                        </div>
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
                        <button type="submit" class="btn btn-accent" id="knockoutGenBtn">
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

    function updateQualifierPreview() {
        var sel = document.getElementById('qualifiersPerGroup');
        if (!sel) return;
        var val = parseInt(sel.value, 10);
        var sections = document.querySelectorAll('.qualifier-section');
        var visibleCount = 0;
        sections.forEach(function(sec) {
            var rank = parseInt(sec.getAttribute('data-rank'), 10);
            var show = rank <= val;
            sec.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        // Update grid columns to match visible count
        var grid = document.getElementById('qualifierPreview');
        if (grid) {
            grid.style.gridTemplateColumns = 'repeat(' + Math.max(1, visibleCount) + ', 1fr)';
        }
        // Update hint text
        var hint = document.getElementById('qualifierHint');
        if (hint) {
            var hints = {
                1: '🥇 Only group winners advance. Each Rank 1 faces another Rank 1 in the knockout bracket.',
                2: '🥇🥈 Top 2 from each group advance. Rank 1 is paired against Rank 2 in Round 1.',
                3: '🥇🥈🥉 Top 3 from each group advance. Rank 1 faces Rank 3, and Rank 2 faces Rank 2 in Round 1.'
            };
            hint.textContent = hints[val] || '';
        }
    }
    // Run immediately so the preview matches the default dropdown value
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateQualifierPreview);
    } else {
        updateQualifierPreview();
    }
    </script>

    <div id="knockoutLoadingOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; flex-direction:column; gap:16px;">
        <div style="width:56px; height:56px; border:4px solid rgba(255,255,255,0.15); border-top-color:var(--accent); border-radius:50%; animation:koSpin 0.8s linear infinite;"></div>
        <div style="color:var(--text-100); font-size:15px; font-weight:600; letter-spacing:0.3px;">Generating knockout bracket...</div>
        <div style="color:var(--text-400); font-size:12px;">Seeding qualifiers and building matches</div>
    </div>
    <style>@keyframes koSpin{to{transform:rotate(360deg)}}</style>
    <script>
    (function(){
        var form = document.getElementById('knockoutGenForm');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            var sel = document.getElementById('qualifiersPerGroup');
            var val = sel ? parseInt(sel.value, 10) : 2;
            var labels = { 1: 'Rank 1 only', 2: 'Rank 1 & Rank 2', 3: 'Rank 1, 2 & 3' };
            var ok = confirm('Generate knockout bracket? This will fetch ' + (labels[val] || 'qualifiers') + ' from each group and create matches.');
            if (!ok) {
                e.preventDefault();
                return;
            }
            var overlay = document.getElementById('knockoutLoadingOverlay');
            if (overlay) { overlay.style.display = 'flex'; }
            var btn = document.getElementById('knockoutGenBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'Generating...'; }
        });
    })();
</script>

<div id="groupLoadingOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center; flex-direction:column; gap:16px;">
    <div style="width:56px; height:56px; border:4px solid rgba(255,255,255,0.15); border-top-color:var(--primary, var(--accent)); border-radius:50%; animation:koSpin 0.8s linear infinite;"></div>
    <div style="color:var(--text-100); font-size:15px; font-weight:600; letter-spacing:0.3px;">Generating group bracket...</div>
    <div style="color:var(--text-400); font-size:12px;">Splitting participants into groups</div>
</div>
<script>
(function(){
    var form = document.getElementById('groupGenForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
        if (!confirm('Generate new group brackets? Existing scheduled matches for this tournament will be replaced.')) {
            e.preventDefault();
            return;
        }
        var overlay = document.getElementById('groupLoadingOverlay');
        if (overlay) { overlay.style.display = 'flex'; }
        var btn = document.getElementById('groupGenBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Generating...'; }
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
            <div class="card-body" id="knockout-view-body">
                <?php
                $recordResultUrl = $formAction;
                $showOnlyPhase = 'knockout';
                $bracketIsTeamEvent = !empty($tournament['is_team_event']);
                $bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
                $bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
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
    <div class="modal" style="max-width: <?= !empty($tournament['is_team_event']) ? '640px' : '420px' ?>;">
        <div class="modal-header">
            <div class="modal-title" id="bracketModalTitle">Record Match Result</div>
            <button type="button" class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= e($formAction) ?>">
            <input type="hidden" name="action" value="result">
            <input type="hidden" name="tournament_id" value="<?= $tid ?>">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="match_id" id="bracketMatchId">
            <input type="hidden" name="winner_key" id="bracketWinnerKey">
            <div class="modal-body">
                <p id="bracketMatchLabel" style="font-weight: 600; margin-bottom: 16px;"></p>
                <div class="form-group">
                    <label class="form-label" id="bracketWinnerLabel"><?= !empty($tournament['is_team_event']) ? 'Winning Team' : 'Winner' ?></label>
                    <select id="bracketWinnerSelect" class="form-select" required>
                        <option value="">— Select winner —</option>
                    </select>
                </div>
                <input type="hidden" name="player1_score" id="bracketP1Score" value="0">
                <input type="hidden" name="player2_score" id="bracketP2Score" value="0">
                <div class="form-group" style="margin-top: 16px; border-top: 1px solid var(--border); padding-top: 16px;">
                    <?php if (!empty($tournament['is_team_event'])): ?>
                    <label class="form-label" style="font-weight: 600; margin-bottom: 8px;">Game Results</label>
                    <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 4px; padding: 0 2px;">
                        <span></span>
                        <span id="bracketSetP1Header" style="font-size: 11px; font-weight: 700; color: var(--accent); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.3px;">Team 1</span>
                        <span id="bracketSetP2Header" style="font-size: 11px; font-weight: 700; color: var(--accent); text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.3px;">Team 2</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="setScoresContainer">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <div style="background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.06); border-radius: var(--radius-sm); padding: 10px 12px;" class="game-row">
                                <!-- Player name row -->
                                <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 6px;">
                                    <span style="font-size: 12px; font-weight: 700; color: var(--accent); letter-spacing: 0.3px;">Game <?= $s ?></span>
                                    <input type="text" class="form-control js-game-p1name" data-set="<?= $s ?>" placeholder="Type Player Name" style="padding: 5px 8px; font-size: 11px; height: 32px;">
                                    <input type="text" class="form-control js-game-p2name" data-set="<?= $s ?>" placeholder="Type Player Name" style="padding: 5px 8px; font-size: 11px; height: 32px;">
                                </div>
                                <!-- Sets row -->
                                <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 10px; align-items: center;">
                                    <span></span>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <input type="number" class="form-control js-set-p1" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 6px; font-size: 15px; font-weight: 700; text-align: center; height: 36px; width: 60px; flex-shrink: 0;">
                                        <span style="font-size: 11px; color: var(--text-400); white-space: nowrap;">sets</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <input type="number" class="form-control js-set-p2" data-set="<?= $s ?>" placeholder="0" min="0" style="padding: 4px 6px; font-size: 15px; font-weight: 700; text-align: center; height: 36px; width: 60px; flex-shrink: 0;">
                                        <span style="font-size: 11px; color: var(--text-400); white-space: nowrap;">sets</span>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php else: ?>
                    <label class="form-label" style="font-weight: 600; margin-bottom: 8px;">Set Scores (Points)</label>
                    <div style="display: grid; grid-template-columns: 60px 1fr 1fr; gap: 12px; align-items: center; margin-bottom: 8px; text-align: center;">
                        <span></span>
                        <span id="bracketSetP1Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;"><?= ucfirst($entrantLabel) ?> 1</span>
                        <span id="bracketSetP2Header" style="font-size: 11px; font-weight: 700; color: var(--text-200); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px;"><?= ucfirst($entrantLabel) ?> 2</span>
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
                    <?php endif; ?>
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
window.BRACKET_IS_TEAM_EVENT = <?= !empty($tournament['is_team_event']) ? 'true' : 'false' ?>;
window._bracketTeam1 = '';
window._bracketTeam2 = '';


function calculateSetsFromSetInputs() {
    let p1Games = 0;
    let p2Games = 0;
    const setScores = [];

    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        if (!p1Input || !p2Input) continue;

        if (window.BRACKET_IS_TEAM_EVENT) {
            const gameBlock = p1Input.closest('.game-row');
            const gameType = gameBlock ? (gameBlock.querySelector('.js-game-type')?.value || 'Singles') : 'Singles';
            const p1NameInput = document.querySelector(`.js-game-p1name[data-set="${s}"]`);
            const p2NameInput = document.querySelector(`.js-game-p2name[data-set="${s}"]`);
            const p1name = p1NameInput ? p1NameInput.value.trim() : '';
            const p2name = p2NameInput ? p2NameInput.value.trim() : '';
            const p1Val = p1Input.value;
            const p2Val = p2Input.value;

            if (p1Val !== '' && p2Val !== '') {
                const p1 = parseInt(p1Val, 10);
                const p2 = parseInt(p2Val, 10);
                setScores.push(gameType + '|' + p1name + '|' + p2name + '|' + p1 + '-' + p2);
                if (p1 > p2) p1Games++;
                else if (p2 > p1) p2Games++;
            } else if (p1name || p2name) {
                setScores.push(gameType + '|' + p1name + '|' + p2name + '|');
            }
        } else {
            // Original non-team logic: auto-disable rows once someone reaches 3 set-wins
            if (p1Games >= 3 || p2Games >= 3) {
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
                if (p1 > p2) p1Games++;
                else if (p2 > p1) p2Games++;
            }
        }
    }

    document.querySelector('[name="player1_score"]').value = p1Games;
    document.querySelector('[name="player2_score"]').value = p2Games;

    // Auto-select winner based on games/sets tally, but do not override if manual selection exists and is valid
    const sel = document.getElementById('bracketWinnerSelect');
    if (sel && sel.options.length > 2) {
        const p1Key = sel.options[1].value;
        const p2Key = sel.options[2].value;
        const currentWinner = sel.value;
        
        // Only auto-update if nothing is currently selected or if the current value is one of the valid options
        if (!currentWinner || currentWinner === p1Key || currentWinner === p2Key) {
            if (p1Games > p2Games) {
                sel.value = p1Key;
                document.getElementById('bracketWinnerKey').value = p1Key;
            } else if (p2Games > p1Games) {
                sel.value = p2Key;
                document.getElementById('bracketWinnerKey').value = p2Key;
            }
        }
    }

    document.getElementById('bracketSetScoresInput').value = setScores.join(',');

    // Re-assert team name headers — never let them be overwritten by player name inputs
    if (window.BRACKET_IS_TEAM_EVENT) {
        const p1hdr = document.getElementById('bracketSetP1Header');
        const p2hdr = document.getElementById('bracketSetP2Header');
        if (p1hdr && window._bracketTeam1) p1hdr.textContent = window._bracketTeam1;
        if (p2hdr && window._bracketTeam2) p2hdr.textContent = window._bracketTeam2;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-set-p1, .js-set-p2, .js-game-p1name, .js-game-p2name').forEach(function(input) {
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

    const p1Club = btn.getAttribute('data-p1-club') || p1Name;
    const p2Club = btn.getAttribute('data-p2-club') || p2Name;

    // For team events: use the registered team name (p1Name) as the display name;
    // p1Club (from the 'club' DB field) is only a fallback if it differs.
    const p1DisplayName = window.BRACKET_IS_TEAM_EVENT ? (p1Name || p1Club) : p1Name;
    const p2DisplayName = window.BRACKET_IS_TEAM_EVENT ? (p2Name || p2Club) : p2Name;

    if (window.BRACKET_IS_TEAM_EVENT) {
        document.getElementById('bracketMatchLabel').textContent = p1DisplayName + ' vs ' + p2DisplayName;
        document.querySelectorAll('.js-team1-label').forEach(el => el.textContent = p1DisplayName);
        document.querySelectorAll('.js-team2-label').forEach(el => el.textContent = p2DisplayName);
    } else {
        document.getElementById('bracketMatchLabel').textContent = p1Name + ' vs ' + p2Name;
    }

    // Lock the team names into protected variables
    window._bracketTeam1 = p1DisplayName;
    window._bracketTeam2 = p2DisplayName;

    const p1hdr = document.getElementById('bracketSetP1Header');
    const p2hdr = document.getElementById('bracketSetP2Header');
    if (p1hdr) p1hdr.textContent = p1DisplayName;
    if (p2hdr) p2hdr.textContent = p2DisplayName;



    const form = document.querySelector('#bracketResultModal form');
    const p1ScoreInput = form.querySelector('[name="player1_score"]');
    const p2ScoreInput = form.querySelector('[name="player2_score"]');
    if (p1ScoreInput) p1ScoreInput.value = btn.getAttribute('data-p1-sets') || '0';
    if (p2ScoreInput) p2ScoreInput.value = btn.getAttribute('data-p2-sets') || '0';

    const setScoresRaw = btn.getAttribute('data-set-scores') || '';
    document.getElementById('bracketSetScoresInput').value = setScoresRaw;
    const setsArray = setScoresRaw ? setScoresRaw.split(',') : [];

    for (let s = 1; s <= 5; s++) {
        const p1Input = document.querySelector(`.js-set-p1[data-set="${s}"]`);
        const p2Input = document.querySelector(`.js-set-p2[data-set="${s}"]`);
        if (!p1Input || !p2Input) continue;

        if (window.BRACKET_IS_TEAM_EVENT) {
            const p1NameInput = document.querySelector(`.js-game-p1name[data-set="${s}"]`);
            const p2NameInput = document.querySelector(`.js-game-p2name[data-set="${s}"]`);
            const gameTypeSelect = document.querySelector(`.js-game-type[data-set="${s}"]`);
            if (setsArray[s - 1]) {
                const parts = setsArray[s - 1].split('|');
                if (gameTypeSelect && parts[0]) gameTypeSelect.value = parts[0];
                if (p1NameInput) p1NameInput.value = parts[1] || '';
                if (p2NameInput) p2NameInput.value = parts[2] || '';
                const scores = (parts[3] || '').split('-');
                p1Input.value = scores[0] || '';
                p2Input.value = scores[1] || '';
            } else {
                if (p1NameInput) p1NameInput.value = '';
                if (p2NameInput) p2NameInput.value = '';
                p1Input.value = '';
                p2Input.value = '';
            }
        } else {
            if (setsArray[s - 1]) {
                const parts = setsArray[s - 1].split('-');
                p1Input.value = parts[0] || '';
                p2Input.value = parts[1] || '';
            } else {
                p1Input.value = '';
                p2Input.value = '';
            }
        }
    }

    const sel = document.getElementById('bracketWinnerSelect');
    if (sel) {
        const wP1Label = window.BRACKET_IS_TEAM_EVENT ? p1DisplayName : p1Name;
        const wP2Label = window.BRACKET_IS_TEAM_EVENT ? p2DisplayName : p2Name;
        sel.innerHTML = '<option value="">— Select winner —</option>'
            + '<option value="' + p1Key + '">' + wP1Label + '</option>'
            + '<option value="' + p2Key + '">' + wP2Label + '</option>';
        sel.value = winnerKey;
        document.getElementById('bracketWinnerKey').value = winnerKey;
        sel.onchange = function () {
            document.getElementById('bracketWinnerKey').value = sel.value;
        };
    }
    calculateSetsFromSetInputs();

    // If calculateSets didn't auto-set a winner or winnerKey was already set, restore/keep it
    if (winnerKey) {
        sel.value = winnerKey;
        document.getElementById('bracketWinnerKey').value = winnerKey;
    }

    if (window.TTMS) {
        TTMS.openModal('bracketResultModal');
    } else {
        document.getElementById('bracketResultModal').classList.add('open');
    }
}

(function () {
    const select = document.getElementById('groupSizeSelect');
    const hint = document.getElementById('groupSizeHint');
    const total = <?= (int) count($entrants ?? []) ?>;
    if (!select || !hint) return;
    select.addEventListener('change', function () {
        if (select.value === 'all') {
            const matches = total * (total - 1) / 2;
            hint.textContent = total + ' <?= $entrantLabelPlural ?> → 1 group (full round robin · ' + matches + ' matches)';
            return;
        }
        const size = parseInt(select.value, 10) || 4;
        const groups = Math.ceil(total / size);
        const extra = total % size;
        let text = total + ' <?= $entrantLabelPlural ?> → about ' + groups + ' group(s)';
        if (extra !== 0) {
            text += ' (extra <?= $entrantLabel ?> goes to a random group)';
        }
        hint.textContent = text;
    });
})();

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-bracket-result-btn');
    if (btn) openBracketResultModal(btn);
});

document.querySelector('#bracketResultModal form')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const sel = document.getElementById('bracketWinnerSelect');
    document.getElementById('bracketWinnerKey').value = sel.value;
    if (!sel.value) {
        alert('Please select the winner.');
        return;
    }
    e.target.submit();
});
</script>
<?php if (!empty($tid)): ?>
<script>
(function() {
    var TOURNAMENT_ID = <?= (int)$tid ?>;
    if (!TOURNAMENT_ID || window.__bracketPolling) return;

    var POLL_INTERVAL = 2000;
    var pollTimer = null;
    var lastTs = -1;
    var isRefreshing = false;

    function createStatusDot() {
        var header = document.getElementById('bracketCardHeader');
        if (!header || document.getElementById('ws-status-dot')) return;
        var dot = document.createElement('span');
        dot.id = 'ws-status-dot';
        dot.title = 'Live updates active';
        dot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;background:#00d4aa;margin-left:8px;vertical-align:middle;transition:background 0.3s;';
        var wrapper = header.querySelector('div');
        if (wrapper) wrapper.appendChild(dot);
    }

    function setStatus(color, title) {
        var dot = document.getElementById('ws-status-dot');
        if (dot) { dot.style.background = color; dot.title = title; }
    }

    function refreshBrackets() {
        if (isRefreshing) return;
        isRefreshing = true;
        setStatus('#f0ad4e', 'Updating...');

        var baseUrl = '<?= url('/includes/bracket_view_ajax.php') ?>?tournament_id=' + TOURNAMENT_ID + '&record_result_url=' + encodeURIComponent('<?= e($formAction) ?>') + '&_=';
        var ts = Date.now();
        var pending = 0;
        var updated = false;

        var groupContainer = document.getElementById('bracket-view-body');
        var knockoutContainer = document.getElementById('knockout-view-body');

        function onPhaseDone() {
            pending--;
            if (pending <= 0) {
                isRefreshing = false;
                if (typeof window.drawBracketLines === 'function') window.drawBracketLines();
                if (typeof adjustGridColumns === 'function') adjustGridColumns();
                setStatus(updated ? '#00d4aa' : '#f0ad4e', updated ? 'Live updates active' : 'Update failed');
            }
        }

        function replaceHTML(container, html) {
            if (!container || !html.trim()) { onPhaseDone(); return; }
            container.innerHTML = html;
            var scripts = container.querySelectorAll('script');
            scripts.forEach(function(old) {
                var s = document.createElement('script');
                if (old.src) s.src = old.src; else s.textContent = old.textContent;
                old.parentNode.replaceChild(s, old);
            });
            updated = true;
            onPhaseDone();
        }

        pending++;
        var xhrGroup = new XMLHttpRequest();
        xhrGroup.open('GET', baseUrl + ts + '&phase=group', true);
        xhrGroup.onload = function() {
            replaceHTML(groupContainer, xhrGroup.status === 200 ? xhrGroup.responseText : '');
        };
        xhrGroup.onerror = function() { replaceHTML(groupContainer, ''); };
        xhrGroup.send();

        if (knockoutContainer) {
            pending++;
            var xhrKO = new XMLHttpRequest();
            xhrKO.open('GET', baseUrl + ts + '&phase=knockout', true);
            xhrKO.onload = function() {
                replaceHTML(knockoutContainer, xhrKO.status === 200 ? xhrKO.responseText : '');
            };
            xhrKO.onerror = function() { replaceHTML(knockoutContainer, ''); };
            xhrKO.send();
        }
    }

    function poll() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '<?= url('/includes/match_timestamp.php') ?>?tournament_id=' + TOURNAMENT_ID + '&_=' + Date.now(), true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var ts = parseFloat(data.timestamp) || 0;
                    if (lastTs === -1) {
                        lastTs = ts;
                        createStatusDot();
                        setStatus('#00d4aa', 'Live updates active');
                        return;
                    }
                    if (ts > lastTs) {
                        lastTs = ts;
                        refreshBrackets();
                    }
                } catch(e) {}
            }
        };
        xhr.send();
    }

    poll();
    pollTimer = setInterval(poll, POLL_INTERVAL);

    window.__bracketPolling = true;
})();
</script>
<?php endif; ?>
