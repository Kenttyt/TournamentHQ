<?php
/** @var array $bracketGroups */
/** @var int $tid */
/** @var string $recordResultUrl */
if (empty($bracketGroups)): ?>
    <div class="empty-state" style="padding: 40px;">
        <div class="empty-icon">🏓</div>
        <h3>No bracket yet</h3>
        <p>Select a tournament, choose <strong>players per group</strong>, then click <strong>Generate Bracket</strong>.</p>
    </div>
<?php else: ?>
    <div class="bracket-container">
        <?php foreach ($bracketGroups as $group): ?>
            <div class="bracket-round">
                <div class="bracket-round-label"><?= e($group['label']) ?></div>
                <?php foreach ($group['matches'] as $m):
                    $p1Win = matchWinnerIsSlot($m, 1);
                    $p2Win = matchWinnerIsSlot($m, 2);
                    $p1Key = matchParticipantKey($m, 1);
                    $p2Key = matchParticipantKey($m, 2);
                    $winnerKey = '';
                    if ($m['status'] === 'completed') {
                        if (!empty($m['winner_id'])) {
                            $winnerKey = 'player:' . (int) $m['winner_id'];
                        } elseif (!empty($m['winner_guest_id'])) {
                            $winnerKey = 'guest:' . (int) $m['winner_guest_id'];
                        }
                    }
                ?>
                    <div class="bracket-match">
                        <div class="bracket-player <?= $p1Win ? 'winner' : ($p2Win ? 'loser' : '') ?>">
                            <span><?= e($m['p1_first'] . ' ' . $m['p1_last']) ?></span>
                            <?php if ($m['status'] === 'completed'): ?>
                                <span class="bracket-score"><?= (int) $m['player1_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bracket-player <?= $p2Win ? 'winner' : ($p1Win ? 'loser' : '') ?>">
                            <span><?= e($m['p2_first'] . ' ' . $m['p2_last']) ?></span>
                            <?php if ($m['status'] === 'completed'): ?>
                                <span class="bracket-score"><?= (int) $m['player2_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($recordResultUrl)): ?>
                            <div style="padding: 8px 12px; border-top: 1px solid var(--border); text-align: center;">
                                <button type="button" class="btn btn-sm js-bracket-result-btn <?= $m['status'] === 'completed' ? 'btn-outline' : 'btn-accent' ?>"
                                    data-match-id="<?= (int) $m['id'] ?>"
                                    data-p1-key="<?= e($p1Key) ?>"
                                    data-p2-key="<?= e($p2Key) ?>"
                                    data-p1-name="<?= e($m['p1_first'] . ' ' . $m['p1_last']) ?>"
                                    data-p2-name="<?= e($m['p2_first'] . ' ' . $m['p2_last']) ?>"
                                    data-p1-sets="<?= (int) $m['player1_score'] ?>"
                                    data-p2-sets="<?= (int) $m['player2_score'] ?>"
                                    data-winner-key="<?= e($winnerKey) ?>"
                                    data-edit="<?= $m['status'] === 'completed' ? '1' : '0' ?>">
                                    <?= $m['status'] === 'completed' ? 'Edit Result' : 'Record Result' ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
