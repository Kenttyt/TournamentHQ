<?php
/**
 * Shared UI for Auto Bracket Generator
 * Expects: $tournaments, $tid, $tournament, $entrants, $bracketGroups, $formAction, $selectedGroupSize
 */
?>
<div class="page-header">
    <div class="page-heading">
        <h1>Auto Bracket Generator</h1>
        <p>Split participants into groups — choose 2, 3, or 4 players per group (round-robin within each group)</p>
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
        <div class="card-header">
            <div class="card-title"><?= e($tournament['name']) ?></div>
            <span class="badge badge-<?= e($tournament['status']) ?>"><?= ucfirst(e($tournament['status'])) ?></span>
        </div>
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
                $groupSize = normalizeGroupSize($selectedGroupSize ?? 4);
                $estimatedGroups = estimateGroupCount($entrantCount, $groupSize);
            ?>
                <form method="POST" action="<?= e($formAction) ?>" onsubmit="return confirm('Generate new group brackets? Existing scheduled matches for this tournament will be replaced.');">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0; min-width: 200px;">
                            <label class="form-label">Players per group</label>
                            <select name="group_size" class="form-select" id="groupSizeSelect">
                                <option value="2" <?= $groupSize === 2 ? 'selected' : '' ?>>2 — Head-to-head</option>
                                <option value="3" <?= $groupSize === 3 ? 'selected' : '' ?>>3 — Round-robin</option>
                                <option value="4" <?= $groupSize === 4 ? 'selected' : '' ?>>4 — Round-robin</option>
                            </select>
                            <p class="text-xs text-muted" style="margin-top: 6px;" id="groupSizeHint">
                                <?= $entrantCount ?> players → about <?= $estimatedGroups ?> group(s)<?= ($entrantCount % $groupSize !== 0) ? ' (extra player goes to a random group)' : '' ?>
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
                    <thead><tr><th>#</th><th>Player</th><th>Type</th><th>Seed</th></tr></thead>
                    <tbody>
                    <?php foreach ($entrants as $i => $p): ?>
                        <tr>
                            <td class="text-muted text-sm"><?= $i + 1 ?></td>
                            <td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td class="text-sm">
                                <?php if (!empty($p['is_guest'])): ?>
                                    <span class="badge badge-ongoing" style="font-size: 10px; padding: 2px 8px;">Guest</span>
                                <?php else: ?>
                                    <span class="text-muted">Account</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm"><?= !empty($p['seed']) ? (int) $p['seed'] : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Tournament Bracket</div>
        <?php if ($tid): ?>
            <span class="text-muted text-xs">Tournament #<?= $tid ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php
        $recordResultUrl = $formAction;
        include __DIR__ . '/bracket_view.php';
        ?>
    </div>
</div>

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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="bracketSaveBtn">Save Result</button>
            </div>
        </form>
    </div>
</div>

<script>
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

    const form = document.querySelector('#bracketResultModal form');
    form.querySelector('[name="player1_score"]').value = btn.getAttribute('data-p1-sets') || '0';
    form.querySelector('[name="player2_score"]').value = btn.getAttribute('data-p2-sets') || '0';

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
