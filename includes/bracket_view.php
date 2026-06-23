<?php
/** @var array $bracketGroups */
/** @var int $tid */
/** @var string $recordResultUrl */

global $bracketIsTeamEvent;
if (!isset($bracketEntrantLabel)) {
    $bracketIsTeamEvent = false;
    if (!empty($tid) && isset($tournament) && !empty($tournament)) {
        $bracketIsTeamEvent = !empty($tournament['is_team_event']);
    } elseif (!empty($tid) && function_exists('db')) {
        try {
            $bracketIsTeamEvent = (int) db()->query("SELECT is_team_event FROM tournaments WHERE id = " . (int)$tid)->fetchColumn() === 1;
        } catch (Exception $e) {}
    }
    $bracketEntrantLabel = $bracketIsTeamEvent ? 'team' : 'player';
    $bracketEntrantLabelPlural = $bracketIsTeamEvent ? 'teams' : 'players';
}

if (empty($bracketGroups)): ?>
    <div class="empty-state" style="padding: 40px;">
        <h3>No bracket yet</h3>
        <?php if (!empty($recordResultUrl)): ?>
            <p>Select a tournament, choose <strong><?= $bracketEntrantLabelPlural ?? 'players' ?> per group</strong>, then click <strong>Generate Bracket</strong>.</p>
        <?php else: ?>
            <p>The bracket for this tournament has not been generated yet. Please check back once registration is closed and the tournament starts!</p>
        <?php endif; ?>
    </div>
<?php else: 
    // Separate group stages from knockout stages
    $groupStages = [];
    $knockoutStages = [];
    foreach ($bracketGroups as $group) {
        if (preg_match('/^Group [A-Z]/i', $group['label'])) {
            $groupStages[] = $group;
        } else {
            $knockoutStages[] = $group;
        }
    }

    $viewPhase = $showOnlyPhase ?? 'all';

    // Load participant seeds and club/team names for display
    global $participantSeeds;
    global $participantClubs;
    $participantSeeds = [];
    $participantClubs = [];
    if (!empty($tid)) {
        require_once __DIR__ . '/../modules/tournaments/tournament_functions.php';
        $entrants = getTournamentEntrants($tid);
        foreach ($entrants as $e) {
            $key = $e['type'] . ':' . $e['id'];
            $participantSeeds[$key] = $e['seed'];
            $participantClubs[$key] = $e['club'] ?? '';
        }
    }

    // Helper function to render a bracket column/round
    if (!function_exists('renderBracketRoundColumn')) {
        function renderBracketRoundColumn(array $group, ?string $recordResultUrl, string $extraClass = '') {
            global $koMatchNumber;
            global $participantSeeds;
            global $bracketEntrantLabel;
            global $bracketIsTeamEvent;
            $isGroupRound = preg_match('/^Group [A-Z]/i', $group['label']);
            
            // Calculate standings dynamically for this group
            $standings = [];
            foreach ($group['matches'] as $m) {
                $p1Key = matchParticipantKey($m, 1);
                $p2Key = matchParticipantKey($m, 2);
                $p1Name = trim($m['p1_first'] . ' ' . $m['p1_last']);
                $p2Name = trim($m['p2_first'] . ' ' . $m['p2_last']);
                
                if (!isset($standings[$p1Key])) {
                    $standings[$p1Key] = [
                        'name' => $p1Name,
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'sets_won' => 0,
                        'sets_lost' => 0
                    ];
                }
                if (!isset($standings[$p2Key])) {
                    $standings[$p2Key] = [
                        'name' => $p2Name,
                        'played' => 0,
                        'wins' => 0,
                        'losses' => 0,
                        'sets_won' => 0,
                        'sets_lost' => 0
                    ];
                }
                
                if ($m['status'] === 'completed') {
                    $standings[$p1Key]['played']++;
                    $standings[$p2Key]['played']++;
                    
                    $p1Score = (int)$m['player1_score'];
                    $p2Score = (int)$m['player2_score'];
                    
                    $standings[$p1Key]['sets_won'] += $p1Score;
                    $standings[$p1Key]['sets_lost'] += $p2Score;
                    
                    $standings[$p2Key]['sets_won'] += $p2Score;
                    $standings[$p2Key]['sets_lost'] += $p1Score;
                    
                    if (matchWinnerIsSlot($m, 1)) {
                        $standings[$p1Key]['wins']++;
                        $standings[$p2Key]['losses']++;
                    } elseif (matchWinnerIsSlot($m, 2)) {
                        $standings[$p2Key]['wins']++;
                        $standings[$p1Key]['losses']++;
                    }
                }
            }
            
            // Sort standings: Wins DESC, Sets Diff DESC, Sets Won DESC
            uasort($standings, function($a, $b) {
                if ($b['wins'] !== $a['wins']) {
                    return $b['wins'] <=> $a['wins'];
                }
                $aDiff = $a['sets_won'] - $a['sets_lost'];
                $bDiff = $b['sets_won'] - $b['sets_lost'];
                if ($bDiff !== $aDiff) {
                    return $bDiff <=> $aDiff;
                }
                return $b['sets_won'] <=> $a['sets_won'];
            });
            ?>
            <div class="bracket-round <?= $isGroupRound ? 'group-round' : '' ?> <?= e($extraClass) ?>">
                <div class="bracket-round-label" style="font-size: 13px; font-weight: 700; margin-bottom: 4px; color: var(--text-100);"><?= e($group['label']) ?></div>
                
                <?php if ($isGroupRound): ?>
                    <!-- Beautiful, compact Group Standings table (List of Players) -->
                    <div class="group-standings" style="margin-bottom: 16px; background: var(--bg-700); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px;">
                        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-300); margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;">
                            <span>Standings</span>
                            <span style="font-size: 9px; color: var(--text-400); text-transform: none;">Sorted by Wins</span>
                        </div>
                        <table class="group-standings-table" style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: left;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); color: var(--text-400); font-size: 10px;">
                                    <th style="text-align: center; width: 24px;">#</th>
                                    <th><?= ucfirst($bracketEntrantLabel ?? 'Player') ?></th>
                                    <th style="text-align: center; width: 20px;">P</th>
                                    <th style="text-align: center; width: 20px;">W</th>
                                    <th style="text-align: center; width: 20px;">L</th>
                                    <th style="text-align: center; width: 36px;"><?= !empty($bracketIsTeamEvent) ? 'Games' : 'Sets' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($standings as $playerKey => $stats): 
                                    $isTop = $rank <= 2; // top 2 typically qualify
                                ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); <?= $isTop ? 'color: var(--text-100);' : 'color: var(--text-300);' ?>">
                                        <td style="text-align: center; padding: 4px 0;">
                                            <span class="rank-badge <?= $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : 'rank-other') ?>" style="width: 16px; height: 16px; font-size: 9px; display: inline-flex; line-height: 16px;">
                                                <?= $rank ?>
                                            </span>
                                        </td>
                                        <td style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px;" title="<?= e($stats['name']) ?>">
                                            <?= e($stats['name']) ?>
                                        </td>
                                        <td style="text-align: center; color: var(--text-400);"><?= $stats['played'] ?></td>
                                        <td style="text-align: center; font-weight: 650; color: var(--success);"><?= $stats['wins'] ?></td>
                                        <td style="text-align: center; color: var(--danger);"><?= $stats['losses'] ?></td>
                                        <td style="text-align: center; font-size: 10px; color: var(--text-400);">
                                            <?= $stats['sets_won'] ?>:<?= $stats['sets_lost'] ?>
                                        </td>
                                    </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--text-400); margin-bottom: 4px; padding-left: 4px;">Matches</div>
                <?php endif; ?>

                <?php if ($isGroupRound): ?><div class="group-match-grid"><?php endif; ?>
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

                    $p1Seed = $participantSeeds[$p1Key] ?? null;
                    $p2Seed = $participantSeeds[$p2Key] ?? null;
                    
                    $p1Name = trim($m['p1_first'] . ' ' . $m['p1_last']);
                    $p2Name = trim($m['p2_first'] . ' ' . $m['p2_last']);
                    
                    $matchNoText = '';
                    if (!$isGroupRound && isset($koMatchNumber)) {
                        $matchNoText = $koMatchNumber;
                        $koMatchNumber++;
                    }
                ?>
                    <?php
                    // Detect a bye match: completed but one side has no player
                    $isByeMatch = $m['status'] === 'completed'
                        && !$isGroupRound
                        && ($p1Name === '' || $p2Name === '');
                    ?>
                    <div class="bracket-match">
                        <?php if ($matchNoText !== ''): ?>
                            <span class="match-num-badge" style="position: absolute; left: -29px; top: 29px; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: var(--text-300); background: var(--bg-800); border: 1px solid var(--border); border-radius: 50%; z-index: 11;"><?= $matchNoText ?></span>
                        <?php endif; ?>

                        <div class="bracket-player <?= $p1Win ? 'winner' : ($p2Win ? 'loser' : '') ?>" style="padding-left: 0; position: relative;">
                            <span style="display: flex; align-items: center; width: 100%;">
                                <span class="player-seed-badge" style="background: rgba(255,255,255,0.05); border-right: 1px solid var(--border); color: var(--text-400); width: 26px; height: 38px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; margin-right: 10px; border-top-left-radius: inherit; border-bottom-left-radius: inherit;"><?= $p1Seed !== null ? $p1Seed : '—' ?></span>
                                <span style="flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; padding-right: 6px;" title="<?= e($p1Name) ?>">
                                    <?php if ($p1Name !== ''): ?>
                                        <?= e($p1Name) ?>
                                    <?php elseif ($isByeMatch): ?>
                                        <span style="color: var(--accent); font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; opacity: 0.85;">BYE</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-400); font-style: italic; opacity: 0.65;">TBD</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($recordResultUrl) && (!isset($bracketAllowSwap) || $bracketAllowSwap !== false) && $extraClass === 'k-round-1' && $p1Name !== '' && !$isByeMatch && $m['status'] === 'scheduled'): ?>
                                    <button type="button" class="js-swap-slot-btn" 
                                            data-match-id="<?= (int) $m['id'] ?>" 
                                            data-slot="1" 
                                            data-player-name="<?= e($p1Name) ?>" 
                                            style="background: none; border: none; color: var(--accent); cursor: pointer; padding: 2px 8px; font-size: 13px; font-weight: 700; margin-left: auto; display: inline-flex; align-items: center; transition: transform 0.2s;"
                                            title="Swap <?= ucfirst($bracketEntrantLabel ?? 'Player') ?> position"
                                            onmouseover="this.style.transform='scale(1.25)'"
                                            onmouseout="this.style.transform='scale(1)'">
                                        ⇄
                                    </button>
                                <?php endif; ?>
                            </span>
                            <?php if ($m['status'] === 'completed' && !$isByeMatch): ?>
                                <span class="bracket-score" style="margin-right: 12px;"><?= (int) $m['player1_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bracket-player <?= $p2Win ? 'winner' : ($p1Win ? 'loser' : '') ?>" style="padding-left: 0; position: relative;">
                            <span style="display: flex; align-items: center; width: 100%;">
                                <span class="player-seed-badge" style="background: rgba(255,255,255,0.05); border-right: 1px solid var(--border); color: var(--text-400); width: 26px; height: 38px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; margin-right: 10px; border-top-left-radius: inherit; border-bottom-left-radius: inherit;"><?= $p2Seed !== null ? $p2Seed : '—' ?></span>
                                <span style="flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; padding-right: 6px;" title="<?= e($p2Name) ?>">
                                    <?php if ($isByeMatch && $p2Name === ''): ?>
                                        <span style="color: var(--accent); font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; opacity: 0.85;">BYE</span>
                                    <?php elseif ($p2Name !== ''): ?>
                                        <?= e($p2Name) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-400); font-style: italic; opacity: 0.65;">TBD</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($recordResultUrl) && (!isset($bracketAllowSwap) || $bracketAllowSwap !== false) && $extraClass === 'k-round-1' && $p2Name !== '' && !$isByeMatch && $m['status'] === 'scheduled'): ?>
                                    <button type="button" class="js-swap-slot-btn" 
                                            data-match-id="<?= (int) $m['id'] ?>" 
                                            data-slot="2" 
                                            data-player-name="<?= e($p2Name) ?>" 
                                            style="background: none; border: none; color: var(--accent); cursor: pointer; padding: 2px 8px; font-size: 13px; font-weight: 700; margin-left: auto; display: inline-flex; align-items: center; transition: transform 0.2s;"
                                            title="Swap <?= ucfirst($bracketEntrantLabel ?? 'Player') ?> position"
                                            onmouseover="this.style.transform='scale(1.25)'"
                                            onmouseout="this.style.transform='scale(1)'">
                                        ⇄
                                    </button>
                                <?php endif; ?>
                            </span>
                            <?php if ($m['status'] === 'completed' && !$isByeMatch): ?>
                                <span class="bracket-score" style="margin-right: 12px;"><?= (int) $m['player2_score'] ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($m['status'] === 'completed' && !empty($m['set_scores'])): ?>
                            <div style="padding: 4px 12px; font-size: 10px; color: var(--text-400); border-top: 1px solid rgba(255,255,255,0.03); background: rgba(0,0,0,0.1); line-height: 1.6; overflow: visible;">
                                <?php 
                                if (strpos($m['set_scores'], '|') !== false) {
                                    $games = explode(',', $m['set_scores']);
                                    $renderedGames = [];
                                    $gameNum = 1;
                                    foreach ($games as $game) {
                                        $parts = explode('|', $game);
                                        if (count($parts) >= 4) {
                                            $gameP1Name = !empty($parts[1]) ? $parts[1] : 'T1 Player';
                                            $gameP2Name = !empty($parts[2]) ? $parts[2] : 'T2 Player';
                                            $score = $parts[3];
                                        } elseif (count($parts) >= 3) {
                                            $gameP1Name = !empty($parts[0]) ? $parts[0] : 'T1 Player';
                                            $gameP2Name = !empty($parts[1]) ? $parts[1] : 'T2 Player';
                                            $score = $parts[2];
                                        } else {
                                            $gameNum++;
                                            continue;
                                        }
                                        $renderedGames[] = !empty($score)
                                            ? "G{$gameNum}: {$gameP1Name} vs {$gameP2Name} ({$score})"
                                            : "G{$gameNum}: {$gameP1Name} vs {$gameP2Name} (—)";
                                        $gameNum++;
                                    }
                                    echo implode('<br>', array_map('e', $renderedGames));
                                } else {
                                    echo '<span style="font-family: monospace; letter-spacing: 0.5px;">' . e(str_replace(',', '  ', $m['set_scores'])) . '</span>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($isByeMatch): ?>
                            <div style="padding: 6px 12px; border-top: 1px solid var(--border); text-align: center;">
                                <span style="font-size: 10px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">
                                    ✓ Walkover — Auto Advanced
                                </span>
                            </div>
                        <?php elseif (!empty($recordResultUrl) && $p1Name !== '' && $p2Name !== ''): ?>
                            <div style="padding: 8px 12px; border-top: 1px solid var(--border); text-align: center;">
                                <button type="button" class="btn btn-sm js-bracket-result-btn <?= $m['status'] === 'completed' ? 'btn-outline' : 'btn-accent' ?>"
                                    data-match-id="<?= (int) $m['id'] ?>"
                                    data-p1-key="<?= e($p1Key) ?>"
                                    data-p2-key="<?= e($p2Key) ?>"
                                    data-p1-name="<?= e($p1Name) ?>"
                                    data-p2-name="<?= e($p2Name) ?>"
                                    data-p1-club="<?= e($participantClubs[$p1Key] ?? '') ?>"
                                    data-p2-club="<?= e($participantClubs[$p2Key] ?? '') ?>"
                                    data-p1-sets="<?= (int) $m['player1_score'] ?>"
                                    data-p2-sets="<?= (int) $m['player2_score'] ?>"
                                    data-set-scores="<?= e($m['set_scores'] ?? '') ?>"
                                    data-winner-key="<?= e($winnerKey) ?>"
                                    data-edit="<?= $m['status'] === 'completed' ? '1' : '0' ?>">
                                    <?= $m['status'] === 'completed' ? 'Edit Result' : 'Record Result' ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($isGroupRound): ?></div><?php endif; ?>
            </div>
            <?php
        }
    }
    ?>

    <style>
        .bracket-round {
            min-width: 280px !important;
        }
        
        .bracket-round.group-round {
            min-width: auto;
            justify-content: flex-start !important;
            gap: 12px !important;
        }

        .group-standings-table th, 
        .group-standings-table td {
            padding: 6px 4px;
            vertical-align: middle;
        }

        .group-match-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            width: 100%;
            overflow-x: auto;
            padding-bottom: 8px;
            align-content: start;
            justify-items: stretch;
        }

        @media (max-width: 640px) {
            .group-match-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 8px;
            }
        }

        .group-match-grid .bracket-match {
            min-width: auto !important;
            width: 100%;
        }

        .bracket-player span[style*="white-space: nowrap"] {
            max-width: 180px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 640px) {
            .bracket-player span[style*="white-space: nowrap"] {
                max-width: none;
                white-space: normal;
                overflow: visible;
                text-overflow: unset;
                word-break: break-word;
                line-height: 1.2;
            }
        }

        /* =============================================
           Knockout Stage Bracket Tree – Layout Only
           Lines are drawn by JavaScript below.
           ============================================= */
        .knockout-bracket-tree {
            display: flex;
            flex-direction: row;
            gap: 50px;
            padding: 40px 20px 30px;
            overflow-x: auto;
            position: relative;
            align-items: stretch;
        }
        .knockout-bracket-tree .bracket-round {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            min-width: 240px !important;
            position: relative;
        }
        .knockout-bracket-tree .bracket-round .bracket-round-label {
            position: absolute;
            top: -28px;
            left: 0;
            right: 0;
        }

        .knockout-bracket-tree .bracket-player {
            height: 38px !important;
            box-sizing: border-box;
        }

        .knockout-bracket-tree .bracket-match {
            position: relative;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            overflow: visible !important;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .knockout-bracket-tree .bracket-match .bracket-player:first-of-type {
            border-top-left-radius: var(--radius-sm);
            border-top-right-radius: var(--radius-sm);
        }
        .knockout-bracket-tree .bracket-match .bracket-player:last-of-type {
            border-bottom-left-radius: var(--radius-sm);
            border-bottom-right-radius: var(--radius-sm);
        }

        /* Connector lines created by JS */
        .ko-line {
            position: absolute;
            pointer-events: none;
            z-index: 1;
        }
    </style>

    <!-- Phase 1: Group Stage Brackets -->
    <?php if (($viewPhase === 'all' || $viewPhase === 'group') && !empty($groupStages)): ?>
        <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
            <span>🟢 Phase 1: Group Stage Standings & Matches</span>
        </div>
        <div class="bracket-container" style="margin-bottom: 40px; border-bottom: 1px dashed var(--border); padding-bottom: 24px; align-items: flex-start;">
            <?php foreach ($groupStages as $group) {
                renderBracketRoundColumn($group, $recordResultUrl);
            } ?>
        </div>
    <?php endif; ?>

    <!-- Phase 2: Knockout Stage Brackets -->
    <?php
    // Fetch format selection to split brackets correctly
    $tournamentFormat = 'single_elimination';
    if (!empty($tid)) {
        $stmtF = db()->prepare("SELECT format FROM tournaments WHERE id = ?");
        $stmtF->execute([$tid]);
        $tournamentFormat = $stmtF->fetchColumn() ?: 'single_elimination';
    }

    // Generate unique IDs per bracket tree container
    $koTreeId = 'koBracket_' . substr(md5(uniqid('', true)), 0, 8);
    $losersTreeId = 'losersBracket_' . substr(md5(uniqid('', true)), 0, 8);

    // Initialize bracket group arrays (populated inside respective branches)
    $regularKnockout = [];
    $thirdPlaceStages = [];
    $gfStages = [];

    if (($viewPhase === 'all' || $viewPhase === 'knockout') && !empty($knockoutStages)): ?>
        <?php if ($tournamentFormat === 'double_elimination'): 
            $winnersStages = [];
            $losersStages = [];
            $gfStages = [];
            foreach ($knockoutStages as $group) {
                if (strpos($group['label'], 'Winners') === 0) {
                    $winnersStages[] = $group;
                } elseif (strpos($group['label'], 'Losers') === 0) {
                    $losersStages[] = $group;
                } elseif (strpos($group['label'], 'Grand Final') === 0) {
                    $gfStages[] = $group;
                }
            }
        ?>
            <!-- Winners Bracket -->
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
                <span>🏆 Winners Bracket (Starts here)</span>
            </div>
            <div class="bracket-container knockout-bracket-tree" id="<?= e($koTreeId) ?>" style="min-height: 480px; margin-bottom: 40px; border-bottom: 1px dashed var(--border); padding-bottom: 40px;">
                <?php 
                global $koMatchNumber;
                $koMatchNumber = 1;
                $kIdx = 1;
                foreach ($winnersStages as $group) {
                    renderBracketRoundColumn($group, $recordResultUrl, 'k-round-' . $kIdx);
                    $kIdx++;
                } ?>
            </div>

            <!-- Losers Bracket -->
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
                <span>📉 Losers Bracket (Double Elimination - One more loss eliminates)</span>
            </div>
            <div class="bracket-container knockout-bracket-tree" id="<?= e($losersTreeId) ?>" style="min-height: 480px; margin-bottom: 40px; border-bottom: 1px dashed var(--border); padding-bottom: 40px;">
                <?php 
                $kIdx = 1;
                foreach ($losersStages as $group) {
                    renderBracketRoundColumn($group, $recordResultUrl, 'l-round-' . $kIdx);
                    $kIdx++;
                } ?>
            </div>

            <!-- Grand Finals -->
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
                <span>👑 Grand Finals</span>
            </div>
            <div class="bracket-container" style="display: flex; gap: 24px; padding: 20px 4px 40px; align-items: flex-start; justify-content: flex-start; overflow-x: auto;">
                <?php 
                foreach ($gfStages as $group) {
                    if (strpos($group['label'], 'Reset') !== false) {
                        $m = $group['matches'][0] ?? null;
                        $hasP = $m && (!empty($m['player1_id']) || !empty($m['player1_guest_id']) || !empty($m['player2_id']) || !empty($m['player2_guest_id']));
                        if (!$hasP) {
                            continue;
                        }
                    }
                    renderBracketRoundColumn($group, $recordResultUrl);
                } ?>
            </div>
        <?php else: 
            // Separate 3rd Place Playoff from regular knockout stages
            $regularKnockout = [];
            $thirdPlaceStages = [];
            foreach ($knockoutStages as $group) {
                if (strpos($group['label'], '3rd Place') !== false) {
                    $thirdPlaceStages[] = $group;
                } else {
                    $regularKnockout[] = $group;
                }
            }
        ?>
            <!-- Single Elimination Knockout Stage Brackets (Unchanged) -->
            <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
                <span>🏆 Phase 2: Knockout Stage Brackets</span>
            </div>
            <div class="bracket-container knockout-bracket-tree" id="<?= e($koTreeId) ?>">
                <?php 
                global $koMatchNumber;
                $koMatchNumber = 1;
                $kIdx = 1;
                foreach ($regularKnockout as $group) {
                    renderBracketRoundColumn($group, $recordResultUrl, 'k-round-' . $kIdx);
                    $kIdx++;
                } ?>
            </div>

            <?php if (!empty($thirdPlaceStages)): ?>
                <!-- 3rd Place Playoff -->
                <div style="margin-top: 32px; border-top: 1px dashed var(--border); padding-top: 24px;">
                    <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; color: var(--text-300); margin-bottom: 12px; padding-left: 4px; display: flex; align-items: center; gap: 8px;">
                        <span>🥉 3rd Place Playoff</span>
                    </div>
                    <div class="bracket-container" style="display: flex; gap: 24px; padding: 20px 4px 20px; align-items: flex-start; justify-content: flex-start; overflow-x: auto;">
                        <?php foreach ($thirdPlaceStages as $group) {
                            renderBracketRoundColumn($group, $recordResultUrl);
                        } ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        // ============================================================
        // Final Standings Podium — works for both Single & Double Elim
        // ============================================================
        $champion = null;
        $secondPlace = null;
        $thirdPlaceNames = []; // Array: supports tied 3rd (no playoff) or single 3rd (with playoff)

        if ($tournamentFormat === 'double_elimination') {
            // For double elimination: find the last completed Grand Final match
            $lastGF = null;
            foreach ($gfStages as $gfGroup) {
                foreach ($gfGroup['matches'] as $gfm) {
                    if ($gfm['status'] === 'completed') {
                        $lastGF = $gfm;
                    }
                }
            }
            if ($lastGF) {
                $champion = trim($lastGF['winner_first'] . ' ' . $lastGF['winner_last']);
                $winKey = '';
                if (!empty($lastGF['winner_id'])) {
                    $winKey = 'player:' . (int) $lastGF['winner_id'];
                } elseif (!empty($lastGF['winner_guest_id'])) {
                    $winKey = 'guest:' . (int) $lastGF['winner_guest_id'];
                }
                $p1Key = matchParticipantKey($lastGF, 1);
                if ($winKey === $p1Key) {
                    $secondPlace = trim($lastGF['p2_first'] . ' ' . $lastGF['p2_last']);
                } else {
                    $secondPlace = trim($lastGF['p1_first'] . ' ' . $lastGF['p1_last']);
                }
            }
        } else {
            // For single elimination: Final = last regular knockout group (1 match)
            $finalGroup = !empty($regularKnockout) ? end($regularKnockout) : null;
            if ($finalGroup) {
                $finalMatch = $finalGroup['matches'][0] ?? null;
                if ($finalMatch && $finalMatch['status'] === 'completed') {
                    $champion = trim($finalMatch['winner_first'] . ' ' . $finalMatch['winner_last']);
                    $winKey = '';
                    if (!empty($finalMatch['winner_id'])) {
                        $winKey = 'player:' . (int) $finalMatch['winner_id'];
                    } elseif (!empty($finalMatch['winner_guest_id'])) {
                        $winKey = 'guest:' . (int) $finalMatch['winner_guest_id'];
                    }
                    $p1Key = matchParticipantKey($finalMatch, 1);
                    if ($winKey === $p1Key) {
                        $secondPlace = trim($finalMatch['p2_first'] . ' ' . $finalMatch['p2_last']);
                    } else {
                        $secondPlace = trim($finalMatch['p1_first'] . ' ' . $finalMatch['p1_last']);
                    }
                }
            }
            // 3rd place from the playoff match (if it exists)
            if (!empty($thirdPlaceStages)) {
                $thirdMatch = $thirdPlaceStages[0]['matches'][0] ?? null;
                if ($thirdMatch && $thirdMatch['status'] === 'completed') {
                    $thirdPlaceNames[] = trim($thirdMatch['winner_first'] . ' ' . $thirdMatch['winner_last']);
                }
            } else {
                // No 3rd place playoff — both semifinal losers are tied for 3rd
                // Semifinal = second-to-last knockout group (the one before the Final)
                $koCount = count($regularKnockout);
                if ($koCount >= 2) {
                    $semiGroup = $regularKnockout[$koCount - 2];
                    foreach ($semiGroup['matches'] as $semiMatch) {
                        if ($semiMatch['status'] === 'completed') {
                            $sWinKey = '';
                            if (!empty($semiMatch['winner_id'])) {
                                $sWinKey = 'player:' . (int) $semiMatch['winner_id'];
                            } elseif (!empty($semiMatch['winner_guest_id'])) {
                                $sWinKey = 'guest:' . (int) $semiMatch['winner_guest_id'];
                            }
                            $sp1Key = matchParticipantKey($semiMatch, 1);
                            if ($sWinKey === $sp1Key) {
                                $loserName = trim($semiMatch['p2_first'] . ' ' . $semiMatch['p2_last']);
                            } else {
                                $loserName = trim($semiMatch['p1_first'] . ' ' . $semiMatch['p1_last']);
                            }
                            if ($loserName !== '') {
                                $thirdPlaceNames[] = $loserName;
                            }
                        }
                    }
                }
            }
        }

        // Only render if at least the champion is determined
        if ($champion):
        ?>
        <!-- Final Standings Podium -->
        <div style="margin-top: 40px; border-top: 2px solid var(--border); padding-top: 32px;">
            <div style="font-size: 14px; font-weight: 700; text-transform: uppercase; color: var(--text-200); margin-bottom: 20px; padding-left: 4px; display: flex; align-items: center; gap: 8px; letter-spacing: 0.5px;">
                <span>📊 Final Standings</span>
            </div>

            <div style="display: flex; flex-wrap: wrap; gap: 16px; padding: 0 4px;">
                <!-- Champion / 1st Place -->
                <div style="flex: 1; min-width: 200px; max-width: 320px; background: linear-gradient(135deg, rgba(255, 215, 0, 0.12) 0%, rgba(255, 180, 0, 0.06) 100%); border: 1px solid rgba(255, 215, 0, 0.25); border-radius: var(--radius-md); padding: 20px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -8px; right: -8px; font-size: 48px; opacity: 0.12; pointer-events: none;">🏆</div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span style="font-size: 28px;">🥇</span>
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #FFD700;">Champion</span>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--text-100); line-height: 1.3;">
                        <?= e($champion) ?>
                    </div>
                </div>

                <!-- 2nd Place -->
                <?php if ($secondPlace): ?>
                <div style="flex: 1; min-width: 200px; max-width: 320px; background: linear-gradient(135deg, rgba(192, 192, 192, 0.10) 0%, rgba(160, 160, 180, 0.05) 100%); border: 1px solid rgba(192, 192, 192, 0.20); border-radius: var(--radius-md); padding: 20px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -8px; right: -8px; font-size: 48px; opacity: 0.10; pointer-events: none;">🥈</div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span style="font-size: 28px;">🥈</span>
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #C0C0C0;">2nd Place</span>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--text-100); line-height: 1.3;">
                        <?= e($secondPlace) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 3rd Place (single or tied) -->
                <?php if (!empty($thirdPlaceNames)): ?>
                <div style="flex: 1; min-width: 200px; max-width: 320px; background: linear-gradient(135deg, rgba(205, 127, 50, 0.10) 0%, rgba(180, 110, 40, 0.05) 100%); border: 1px solid rgba(205, 127, 50, 0.20); border-radius: var(--radius-md); padding: 20px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -8px; right: -8px; font-size: 48px; opacity: 0.10; pointer-events: none;">🥉</div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span style="font-size: 28px;">🥉</span>
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #CD7F32;">3rd Place<?= count($thirdPlaceNames) > 1 ? ' (Tied)' : '' ?></span>
                    </div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--text-100); line-height: 1.3;">
                        <?php foreach ($thirdPlaceNames as $i => $name): ?>
                            <?= e($name) ?><?= $i < count($thirdPlaceNames) - 1 ? '<br>' : '' ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <script>
        (function() {
            var LINE_W = 2;
            var LINE_COLOR = 'rgba(255, 255, 255, 0.35)';
            function getMatchCenterY(m, cRect, sT) {
                var players = m.querySelectorAll('.bracket-player');
                if (players.length >= 2) {
                    var p1Rect = players[0].getBoundingClientRect();
                    var p2Rect = players[1].getBoundingClientRect();
                    return (p1Rect.top + p2Rect.bottom) / 2 - cRect.top + sT;
                }
                var r = m.getBoundingClientRect();
                return r.top + (r.height / 2) - cRect.top + sT;
            }

            window.drawBracketLines = function drawBracketLines() {
                var ids = ['<?= e($koTreeId) ?>', '<?= e($losersTreeId) ?>'];
                ids.forEach(function(cid) {
                    var container = document.getElementById(cid);
                    if (!container) return;

                    // Clear previous lines
                    var old = container.querySelectorAll('.ko-line');
                    for (var i = 0; i < old.length; i++) old[i].remove();

                    var rounds = container.querySelectorAll('.bracket-round');
                    if (rounds.length < 2) return;

                    var cRect = container.getBoundingClientRect();
                    var sL = container.scrollLeft;
                    var sT = container.scrollTop;

                    for (var r = 0; r < rounds.length - 1; r++) {
                        var cur = rounds[r].querySelectorAll('.bracket-match');
                        var nxt = rounds[r + 1].querySelectorAll('.bracket-match');
                        if (cur.length === 0 || nxt.length === 0) continue;

                        var isHalving = (cur.length === 2 * nxt.length);

                        if (isHalving) {
                            // Halving transition (2-to-1)
                            for (var i = 0; i < cur.length; i += 2) {
                                var m1 = cur[i];
                                var m2 = cur[i + 1] || null;
                                var nm = nxt[Math.floor(i / 2)];
                                if (!m1 || !nm) continue;

                                var r1 = m1.getBoundingClientRect();
                                var r2 = m2 ? m2.getBoundingClientRect() : r1;
                                var rn = nm.getBoundingClientRect();

                                var y1 = getMatchCenterY(m1, cRect, sT);
                                var y2 = m2 ? getMatchCenterY(m2, cRect, sT) : y1;
                                var yn = getMatchCenterY(nm, cRect, sT);

                                var xRight = r1.right - cRect.left + sL;
                                var xNextL = rn.left - cRect.left + sL;
                                var xMid   = Math.round((xRight + xNextL) / 2);

                                if (m2) {
                                    mk(container, xRight, y1 - LINE_W / 2, xMid - xRight, LINE_W);
                                    mk(container, xRight, y2 - LINE_W / 2, xMid - xRight, LINE_W);
                                    var topY = Math.min(y1, y2, yn);
                                    var botY = Math.max(y1, y2, yn);
                                    mk(container, xMid - LINE_W / 2, topY, LINE_W, botY - topY + LINE_W);
                                    mk(container, xMid, yn - LINE_W / 2, xNextL - xMid, LINE_W);
                                } else {
                                    mk(container, xRight, y1 - LINE_W / 2, xMid - xRight, LINE_W);
                                    var topY = Math.min(y1, yn);
                                    var botY = Math.max(y1, yn);
                                    mk(container, xMid - LINE_W / 2, topY, LINE_W, botY - topY + LINE_W);
                                    mk(container, xMid, yn - LINE_W / 2, xNextL - xMid, LINE_W);
                                }
                            }
                        } else {
                            // 1-to-1 transition (same size, e.g. 1a to 1b)
                            for (var i = 0; i < cur.length; i++) {
                                var m1 = cur[i];
                                var nm = nxt[i] || null;
                                if (!m1 || !nm) continue;

                                var r1 = m1.getBoundingClientRect();
                                var rn = nm.getBoundingClientRect();

                                var y1 = getMatchCenterY(m1, cRect, sT);
                                var yn = getMatchCenterY(nm, cRect, sT);

                                var xRight = r1.right - cRect.left + sL;
                                var xNextL = rn.left - cRect.left + sL;

                                // Connect straight from match1 right side to next match left side
                                mk(container, xRight, y1 - LINE_W / 2, xNextL - xRight, LINE_W);
                            }
                        }
                    }
                });
            }

            function mk(p, x, y, w, h) {
                var d = document.createElement('div');
                d.className = 'ko-line';
                d.style.cssText = 'left:' + x + 'px;top:' + y + 'px;width:' + Math.max(0, w) + 'px;height:' + Math.max(0, h) + 'px;background:' + LINE_COLOR;
                p.appendChild(d);
            }

            // Initial draw
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', drawBracketLines);
            } else {
                drawBracketLines();
            }

            // Redraw on resize & scroll
            window.addEventListener('resize', drawBracketLines);
            var tree = document.getElementById('<?= e($koTreeId) ?>');
            if (tree) tree.addEventListener('scroll', drawBracketLines);
            var tree2 = document.getElementById('<?= e($losersTreeId) ?>');
            if (tree2) tree2.addEventListener('scroll', drawBracketLines);

            // Also redraw after a short delay (for late-rendering elements)
            setTimeout(drawBracketLines, 300);
            setTimeout(drawBracketLines, 1000);
        })();
        </script>
    <?php endif; ?>

    <?php 
    // Only render the swap modal and its JavaScript in knockout/all views, and only once
    if ($viewPhase !== 'group' && !isset($GLOBALS['swapModalRendered'])): 
        $GLOBALS['swapModalRendered'] = true;
    ?>
    <!-- Swap bracket slots modal -->
    <div class="modal-overlay" id="bracketSwapModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <div class="modal-title">Swap Bracket Position</div>
                <button type="button" class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= e($recordResultUrl) ?>">
                <input type="hidden" name="action" value="swap_slots">
                <input type="hidden" name="tournament_id" value="<?= $tid ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="match1_id" id="swapMatch1Id">
                <input type="hidden" name="slot1" id="swapSlot1">
                <div class="modal-body">
                    <p style="font-size: 13px; color: var(--text-200); margin-bottom: 16px;">
                        Swap <strong id="swapSourcePlayerName" style="color: var(--accent);"></strong> with another <?= $bracketEntrantLabel ?? 'player' ?> in the first round to balance the bracket:
                    </p>
                    <div class="form-group">
                        <label class="form-label">Swap With</label>
                        <select name="match2_info" id="swapTargetSelect" class="form-select" required>
                            <!-- Options filled dynamically by JS -->
                        </select>
                    </div>
                    <input type="hidden" name="match2_id" id="swapMatch2Id">
                    <input type="hidden" name="slot2" id="swapSlot2">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-accent">Confirm Swap</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        document.querySelectorAll('.js-swap-slot-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var matchId = btn.getAttribute('data-match-id');
                var slot = btn.getAttribute('data-slot');
                var playerName = btn.getAttribute('data-player-name');
                
                document.getElementById('swapMatch1Id').value = matchId;
                document.getElementById('swapSlot1').value = slot;
                document.getElementById('swapSourcePlayerName').textContent = playerName;
                
                var select = document.getElementById('swapTargetSelect');
                select.innerHTML = '<option value="">— Select participant to swap with —</option>';
                
                document.querySelectorAll('.js-swap-slot-btn').forEach(function(otherBtn) {
                    var oMatchId = otherBtn.getAttribute('data-match-id');
                    var oSlot = otherBtn.getAttribute('data-slot');
                    var oPlayerName = otherBtn.getAttribute('data-player-name');
                    
                    if (oMatchId === matchId && oSlot === slot) return;
                    
                    var opt = document.createElement('option');
                    opt.value = oMatchId + ':' + oSlot;
                    opt.textContent = oPlayerName + ' (Match #' + oMatchId + ', Slot ' + oSlot + ')';
                    select.appendChild(opt);
                });
                
                select.onchange = function() {
                    var val = select.value;
                    if (val) {
                        var parts = val.split(':');
                        document.getElementById('swapMatch2Id').value = parts[0];
                        document.getElementById('swapSlot2').value = parts[1];
                    } else {
                        document.getElementById('swapMatch2Id').value = '';
                        document.getElementById('swapSlot2').value = '';
                    }
                };
                
                if (window.TTMS && typeof window.TTMS.openModal === 'function') {
                    window.TTMS.openModal('bracketSwapModal');
                } else {
                    document.getElementById('bracketSwapModal').classList.add('open');
                }
            });
        });

        // Simple modal close listener fallback
        document.querySelectorAll('#bracketSwapModal [data-modal-close]').forEach(function(el) {
            el.addEventListener('click', function() {
                if (window.TTMS && typeof window.TTMS.closeModal === 'function') {
                    window.TTMS.closeModal('bracketSwapModal');
                } else {
                    document.getElementById('bracketSwapModal').classList.remove('open');
                }
            });
        });
    })();
    </script>

    <script>
    // Dynamically adjust grid columns based on match count
    // Rule: 7+ matches = add another column. Ensures columns have a minimum width of 260px for names to display fully, scrolling horizontally if needed.
    function adjustGridColumns() {
        document.querySelectorAll('.group-match-grid').forEach(function(grid) {
            var matchCount = grid.querySelectorAll('.bracket-match').length;
            var columns = Math.max(1, Math.ceil(matchCount / 7));
            grid.style.gridTemplateColumns = 'repeat(' + columns + ', minmax(260px, 1fr))';
        });
    }

    // Run on load and after DOM updates
    document.addEventListener('DOMContentLoaded', adjustGridColumns);
    window.addEventListener('load', adjustGridColumns);
    </script>
    <?php endif; ?>

    <?php if (!empty($tid) && ($viewPhase ?? 'all') === 'all'): ?>
    <script>
    (function() {
        var TOURNAMENT_ID = <?= (int)$tid ?>;
        if (!TOURNAMENT_ID) return;
        if (window.__sse) return;

        var POLL_INTERVAL = 2000;
        var pollTimer = null;
        var lastTs = -1;

        function getBracketBody() {
            return document.getElementById('bracket-view-body')
                || document.getElementById('knockout-view-body')
                || document.getElementById('knockoutBracketCardBody')
                || document.querySelector('.card-body');
        }

        function createStatusDot() {
            var cardHeader = document.querySelector('.card-header');
            if (!cardHeader || document.getElementById('ws-status-dot')) return;
            var dot = document.createElement('span');
            dot.id = 'ws-status-dot';
            dot.title = 'Live updates active';
            dot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;background:#00d4aa;margin-left:8px;vertical-align:middle;transition:background 0.3s;';
            cardHeader.appendChild(dot);
        }

        function setStatus(color, title) {
            var dot = document.getElementById('ws-status-dot');
            if (dot) { dot.style.background = color; dot.title = title; }
        }

        function refreshBracket() {
            var body = getBracketBody();
            if (!body) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/TournamentHQ/includes/bracket_view_ajax.php?tournament_id=' + TOURNAMENT_ID + '&_=' + Date.now(), true);
            xhr.onload = function() {
                if (xhr.status === 200 && xhr.responseText.trim()) {
                    var temp = document.createElement('div');
                    temp.innerHTML = xhr.responseText;
                    body.innerHTML = '';
                    while (temp.firstChild) {
                        body.appendChild(temp.firstChild);
                    }
                    if (typeof window.drawBracketLines === 'function') window.drawBracketLines();
                    if (typeof adjustGridColumns === 'function') adjustGridColumns();
                }
            };
            xhr.send();
        }

        function poll() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/TournamentHQ/includes/match_timestamp.php?tournament_id=' + TOURNAMENT_ID + '&_=' + Date.now(), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        var ts = parseFloat(data.timestamp) || 0;
                        if (lastTs === -1) {
                            lastTs = ts;
                            setStatus('#00d4aa', 'Live updates active');
                            return;
                        }
                        if (ts > lastTs) {
                            lastTs = ts;
                            refreshBracket();
                        }
                    } catch(e) {}
                }
            };
            xhr.send();
        }

        function startPolling() {
            poll();
            pollTimer = setInterval(poll, POLL_INTERVAL);
        }

        createStatusDot();
        startPolling();

        window.__sse = { tid: TOURNAMENT_ID };
    })();
    </script>
    <?php endif; ?>

<?php endif; ?>

