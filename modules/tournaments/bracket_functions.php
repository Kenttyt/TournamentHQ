<?php
/**
 * Group-based bracket generation (organizer chooses players per group)
 */
require_once __DIR__ . '/tournament_functions.php';
require_once __DIR__ . '/../matches/match_functions.php';

function normalizeGroupSize(int $size): int {
    return max(2, min(4, $size));
}

function groupLabel(int $index): string {
    $label = '';
    $n = $index;
    do {
        $label = chr(65 + ($n % 26)) . $label;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return 'Group ' . $label;
}

function estimateGroupCount(int $entrantCount, int $groupSize): int {
    if ($entrantCount < 1) {
        return 0;
    }
    $groupSize = normalizeGroupSize($groupSize);
    // Extra players are merged into a random group, not left as a separate mini-group
    return (int) intdiv($entrantCount, $groupSize) ?: 1;
}

/**
 * Distribute players evenly across groups so remainder players are spread out, not all in one group.
 * @param list<list<array>> $groups
 * @return array{0: list<list<array>>, 1: list<array{player:string,group:string}>}
 */
function distributeRemainderIntoGroups(array $groups, int $groupSize): array {
    $merged = [];
    $groupSize = normalizeGroupSize($groupSize);

    // Collect all remainder players (those in the last incomplete group)
    $last = $groups[count($groups) - 1];
    if (count($last) >= $groupSize) {
        // Last group is already full, nothing to merge
        return [$groups, $merged];
    }

    // Pop the incomplete last group
    $remainder = array_pop($groups);
    if (empty($remainder)) {
        return [$groups, $merged];
    }

    // If only one group left and we have remainder, we need a different strategy
    if (empty($groups)) {
        // Put remainder back as they are (no other groups to merge into)
        $groups[] = $remainder;
        return [$groups, $merged];
    }

    // Distribute remainder players one-by-one to existing groups
    // This creates more even distribution: 4-4-3 instead of 5-3-3
    $groupIdx = mt_rand(0, count($groups) - 1);
    foreach ($remainder as $entrant) {
        $groups[$groupIdx][] = $entrant;
        $merged[] = [
            'player' => entrantDisplayName($entrant),
            'group'  => groupLabel($groupIdx),
        ];
        // Round-robin through groups so each gets one remainder player before repeating
        $groupIdx = ($groupIdx + 1) % count($groups);
    }

    return [$groups, $merged];
}

function deleteTournamentScheduledMatches(int $tournamentId): void {
    db()->prepare("DELETE FROM matches WHERE tournament_id = ? AND status = 'scheduled'")
        ->execute([$tournamentId]);
}

function deleteTournamentKnockoutMatches(int $tournamentId): void {
    db()->prepare("DELETE FROM matches WHERE tournament_id = ? AND round > 1")
        ->execute([$tournamentId]);
}

function entrantDisplayName(array $entrant): string {
    return trim($entrant['first_name'] . ' ' . $entrant['last_name']);
}

/** @return list<array{0: array, 1: array}> */
function groupRoundRobinPairs(array $group): array {
    $pairs = [];
    $n = count($group);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $pairs[] = [$group[$i], $group[$j]];
        }
    }
    return $pairs;
}

/**
 * @return array{ok:bool,message?:string,matches?:int,groups?:int,group_size?:int,merged?:list<array{player:string,group:string}>}
 */
function generateTournamentBracket(
    int $tournamentId,
    bool $shuffle = false,
    bool $replaceScheduled = true,
    int $groupSize = 4
): array {
    $tournament = getTournamentById($tournamentId);
    if (!$tournament) {
        return ['ok' => false, 'message' => 'Tournament not found.'];
    }

    if (!in_array($tournament['status'], ['upcoming', 'ongoing'], true)) {
        return ['ok' => false, 'message' => 'Brackets can only be generated for upcoming or ongoing tournaments.'];
    }

    $entrants = getTournamentEntrants($tournamentId);

    // Deduplicate: if the same person is registered as both a player and a guest,
    // keep only the player entry to prevent them appearing in multiple groups.
    $seenNames = [];
    $deduped = [];
    foreach ($entrants as $e) {
        $nameKey = strtolower(trim($e['first_name'] . ' ' . $e['last_name']));
        if (isset($seenNames[$nameKey])) {
            continue;
        }
        $seenNames[$nameKey] = true;
        $deduped[] = $e;
    }
    $entrants = $deduped;

    $count = count($entrants);
    if ($count < 2) {
        return ['ok' => false, 'message' => 'At least 2 participants (players or guests) are required to generate a bracket.'];
    }

    if ($replaceScheduled) {
        deleteTournamentScheduledMatches($tournamentId);
        deleteTournamentKnockoutMatches($tournamentId);
    }

    $ordered = $entrants;
    if ($shuffle) {
        shuffle($ordered);
    }

    $merged = [];

    // groupSize === 0 means "All — Full Round Robin" (single group with every player)
    if ($groupSize === 0) {
        $groups = [$ordered];
        $actualGroupSize = $count;
    } else {
        $groupSize = normalizeGroupSize($groupSize);
        $actualGroupSize = $groupSize;
        $groups = array_chunk($ordered, $groupSize);
        [$groups, $merged] = distributeRemainderIntoGroups($groups, $groupSize);
    }

    $tableNum = 1;
    $created = 0;

    foreach ($groups as $gi => $group) {
        $label = groupLabel($gi);
        $memberCount = count($group);

        if ($memberCount < 2) {
            continue;
        }

        $pairs = groupRoundRobinPairs($group);
        $matchNo = 1;
        foreach ($pairs as [$e1, $e2]) {
            $roundName = $label;
            if (count($pairs) > 1) {
                $roundName .= ' · Match ' . $matchNo;
            }
            createMatchFromEntrants(
                $tournamentId,
                $e1,
                $e2,
                1,
                $roundName,
                $tournament['start_date'] ?? '',
                $tableNum++
            );
            $created++;
            $matchNo++;
        }
    }

    if ($created === 0) {
        return ['ok' => false, 'message' => 'No matches could be created. Try a smaller group size or add more participants.'];
    }

    if ($tournament['status'] === 'upcoming') {
        db()->prepare("UPDATE tournaments SET status = 'ongoing' WHERE id = ?")->execute([$tournamentId]);
    }

    return [
        'ok'         => true,
        'matches'    => $created,
        'groups'     => count($groups),
        'group_size' => $actualGroupSize,
        'merged'     => $merged,
    ];
}

function bracketGroupKeyFromMatch(array $match): string {
    $name = trim($match['round_name'] ?? 'Ungrouped');
    if (preg_match('/^(Group [A-Z]+)/', $name, $m)) {
        return $m[1];
    }
    if (preg_match('/^(.*?)\s*·/', $name, $m)) {
        return trim($m[1]);
    }
    return $name;
}

/**
 * Get top N players from each group based on group standings
 * @return list<array{type:string,id:int,first_name:string,last_name:string,group_label:string,rank:int}>
 */
function getGroupStageQualifiers(int $tournamentId, int $qualifiersPerGroup = 2): array {
    $qualifiersPerGroup = max(1, min(4, $qualifiersPerGroup));
    $groups = buildBracketGroups($tournamentId);
    $qualifiers = [];
    
    foreach ($groups as $group) {
        // Only look at group rounds
        if (!preg_match('/^Group [A-Z]/i', $group['label'])) {
            continue;
        }
        
        // Calculate standings for this group
        $standings = [];
        foreach ($group['matches'] as $m) {
            $p1Key = matchParticipantKey($m, 1);
            $p2Key = matchParticipantKey($m, 2);
            
            // Extract participant info
            $p1Type = !empty($m['player1_id']) ? 'player' : 'guest';
            $p1Id = !empty($m['player1_id']) ? $m['player1_id'] : $m['player1_guest_id'];
            $p2Type = !empty($m['player2_id']) ? 'player' : 'guest';
            $p2Id = !empty($m['player2_id']) ? $m['player2_id'] : $m['player2_guest_id'];
            
            if (!isset($standings[$p1Key])) {
                $standings[$p1Key] = [
                    'type' => $p1Type,
                    'id' => (int)$p1Id,
                    'first_name' => $m['p1_first'],
                    'last_name' => $m['p1_last'],
                    'wins' => 0,
                    'losses' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0
                ];
            }
            if (!isset($standings[$p2Key])) {
                $standings[$p2Key] = [
                    'type' => $p2Type,
                    'id' => (int)$p2Id,
                    'first_name' => $m['p2_first'],
                    'last_name' => $m['p2_last'],
                    'wins' => 0,
                    'losses' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0
                ];
            }
            
            if ($m['status'] === 'completed') {
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
        
        // Take top N
        $top = array_slice($standings, 0, $qualifiersPerGroup);
        $rank = 0;
        foreach ($top as $player) {
            $rank++;
            $qualifiers[] = [
                'type' => $player['type'],
                'id' => $player['id'],
                'first_name' => $player['first_name'],
                'last_name' => $player['last_name'],
                'group_label' => $group['label'],
                'rank' => $rank,
            ];
        }
    }
    
    return $qualifiers;
}

/**
 * Generate standard bracket seeding order for a given bracket size (must be power of 2).
 * E.g. for size 8: [1, 8, 4, 5, 2, 7, 3, 6]
 * This ensures seed 1 and seed 2 are on opposite halves and only meet in the final.
 * @return list<int> 1-indexed seed positions
 */
function standardBracketSeeding(int $size): array {
    if ($size === 1) return [1];
    if ($size === 2) return [1, 2];
    $half = standardBracketSeeding($size / 2);
    $result = [];
    foreach ($half as $pos) {
        $result[] = $pos;
        $result[] = $size + 1 - $pos;
    }
    return $result;
}

/**
 * Get the round name based on match count
 */
function knockoutRoundName(int $matchCount): string {
    if ($matchCount === 1) return 'Final';
    if ($matchCount === 2) return 'Semifinal';
    if ($matchCount === 4) return 'Quarterfinal';
    if ($matchCount === 8) return 'Round of 16';
    if ($matchCount === 16) return 'Round of 32';
    if ($matchCount === 32) return 'Round of 64';
    if ($matchCount === 64) return 'Round of 128';
    return 'Knockout Round';
}

function generateKnockoutStage(int $tournamentId, string $bracketType, bool $include3rdPlace = false, int $qualifiersPerGroup = 2): array {
    $tournament = getTournamentById($tournamentId);
    if (!$tournament) {
        return ['ok' => false, 'message' => 'Tournament not found.'];
    }
    
    $qualifiers = getGroupStageQualifiers($tournamentId, $qualifiersPerGroup);
    $count = count($qualifiers);
    if ($count < 2) {
        return ['ok' => false, 'message' => 'Not enough qualifiers (at least 2 required) from the group stage to generate a knockout bracket.'];
    }
    
    // Delete any existing knockout matches (round > 1)
    db()->prepare("DELETE FROM matches WHERE tournament_id = ? AND round > 1")->execute([$tournamentId]);
    
    // Build seeded list ensuring same-rank players never face each other in round 1.
    // Strategy: pair rank-1s directly against rank-2s (from different groups) in each
    // first-round match. Top seeds are placed on opposite halves so they can only
    // meet in the Final.
    $bracketSize = 1;
    while ($bracketSize < $count) {
        $bracketSize *= 2;
    }

    // Group qualifiers by rank
    $byRank = [];
    foreach ($qualifiers as $q) {
        $byRank[$q['rank']][] = $q;
    }
    ksort($byRank);

    // Shuffle within each rank group so bracket draw is randomised
    foreach ($byRank as &$rankGroup) {
        shuffle($rankGroup);
    }
    unset($rankGroup);

    // --- Build bracket slots ---
    // Pairing rules by qualifiers_per_group:
    //   1 qualifier: Rank 1 vs Rank 1 (plain seeding)
    //   2 qualifiers: Rank 1 vs Rank 2
    //   3 qualifiers: Rank 1 vs Rank 3, and Rank 2 vs Rank 2
    $rankGroups = $byRank; // keyed by rank number
    $rank1s = $rankGroups[1] ?? [];
    $rank2s = $rankGroups[2] ?? [];
    $rank3s = $rankGroups[3] ?? [];

    // Default numMatches (may be overridden in the 3-qualifier path)
    $numMatches = $bracketSize / 2;

    if ($qualifiersPerGroup >= 3 && !empty($rank3s)) {
        // Rank 1 vs Rank 3, then Rank 2 vs Rank 2 matches
        $numR1R3 = max(count($rank1s), count($rank3s));
        $numR2R2 = (int) ceil(count($rank2s) / 2);
        $actualMatches = $numR1R3 + $numR2R2;
        // Re-calculate bracket size to fit all matches
        $bracketSize = 1;
        while ($bracketSize < $actualMatches * 2) {
            $bracketSize *= 2;
        }
        $numMatches = $bracketSize / 2;

        // Pair rank1[i] vs rank3[i]
        $pairs = [];
        $r3Padded = array_pad($rank3s, count($rank1s), null);
        for ($i = 0; $i < count($rank1s); $i++) {
            $pairs[] = [$rank1s[$i], $r3Padded[$i]];
        }
        // Pair rank2[i] vs rank2[i+1]
        for ($i = 0; $i + 1 < count($rank2s); $i += 2) {
            $pairs[] = [$rank2s[$i], $rank2s[$i + 1]];
        }
        // If odd rank2 count, last rank2 gets a bye
        if (count($rank2s) % 2 === 1) {
            $pairs[] = [end($rank2s), null];
        }
        // Pad remaining bracket slots with byes
        while (count($pairs) < $numMatches) {
            $pairs[] = [null, null];
        }
    } else {
        // Default: Rank 1 vs Rank 2 (or plain seeding for rank-1 only)
        $primarySeeds   = $rank1s;
        $secondarySeeds = [];
        foreach ($rankGroups as $rank => $players) {
            if ($rank === array_key_first($rankGroups)) continue;
            foreach ($players as $p) {
                $secondarySeeds[] = $p;
            }
        }
        // Pad both lists so they each fill half the bracket
        while (count($primarySeeds) < $numMatches) {
            $primarySeeds[] = null;
        }
        while (count($secondarySeeds) < $numMatches) {
            $secondarySeeds[] = null;
        }

        // Apply standard seeding within the primary group so that the best seeds
        // end up on opposite sides of the bracket (only meet in the Final).
        $primaryOrder = standardBracketSeeding($numMatches);
        $orderedPrimary = [];
        foreach ($primaryOrder as $pos) {
            $orderedPrimary[] = $primarySeeds[$pos - 1] ?? null;
        }

        // Similarly order secondary seeds (mirror so best secondary opposes best primary)
        $secondaryOrder = array_reverse($primaryOrder);
        $orderedSecondary = [];
        foreach ($secondaryOrder as $pos) {
            $orderedSecondary[] = $secondarySeeds[$pos - 1] ?? null;
        }

        // Build final slot list: each match gets one primary + one secondary player
        $slots = [];
        for ($i = 0; $i < $numMatches; $i++) {
            $slots[] = $orderedPrimary[$i];
            $slots[] = $orderedSecondary[$i];
        }

        // Create first-round match pairs from adjacent slots
        $pairs = [];
        for ($i = 0; $i < count($slots); $i += 2) {
            $pairs[] = [$slots[$i], $slots[$i + 1]];
        }
    }
    
    $tableNum = 1;
    $created = 0;
    $firstRoundMatchCount = count($pairs);
    $isDoubleElim = ($bracketType === 'double_elimination');
    $roundName = ($isDoubleElim ? "Winners " : "") . knockoutRoundName($firstRoundMatchCount);
    
    $matchNo = 1;
    foreach ($pairs as [$e1, $e2]) {
        $name = $roundName;
        if ($firstRoundMatchCount > 1) {
            $name .= ' · Match ' . $matchNo;
        }
        
        // Build INSERT data — one or both sides may be null (bye)
        $p1Id = null; $p1GuestId = null;
        $p2Id = null; $p2GuestId = null;
        
        if ($e1 !== null) {
            if ($e1['type'] === 'player') { $p1Id = $e1['id']; }
            else { $p1GuestId = $e1['id']; }
        }
        if ($e2 !== null) {
            if ($e2['type'] === 'player') { $p2Id = $e2['id']; }
            else { $p2GuestId = $e2['id']; }
        }
        
        $stmt = db()->prepare(
            "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
             VALUES (?, ?, ?, ?, ?, 2, ?, ?, ?, 'scheduled')"
        );
        $stmt->execute([
            $tournamentId,
            $p1Id, $p2Id, $p1GuestId, $p2GuestId,
            $name,
            $tournament['start_date'] ?? null,
            $tableNum++
        ]);
        $created++;
        $matchNo++;
    }
    
    // Save format selection back to the tournament
    db()->prepare("UPDATE tournaments SET format = ? WHERE id = ?")->execute([$bracketType, $tournamentId]);
    
    // Generate all subsequent empty rounds (Semifinals, Finals, etc.) up to the Final
    $currentRoundMatches = $firstRoundMatchCount;
    $currentRoundNum = 2; // Knockout Round 1 is round 2
    
    while ($currentRoundMatches > 1) {
        $nextRoundMatches = (int) ceil($currentRoundMatches / 2);
        $nextRoundNum = $currentRoundNum + 1;
        $nextRoundName = ($isDoubleElim ? "Winners " : "") . knockoutRoundName($nextRoundMatches);
        
        for ($mIdx = 1; $mIdx <= $nextRoundMatches; $mIdx++) {
            $name = $nextRoundName;
            if ($nextRoundMatches > 1) {
                $name .= ' · Match ' . $mIdx;
            }
            
            $stmt = db()->prepare(
                "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
                 VALUES (?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, 'scheduled')"
            );
            $stmt->execute([
                $tournamentId,
                $nextRoundNum,
                $name,
                $tournament['start_date'] ?: null,
                $tableNum++
            ]);
        }
        
        $currentRoundMatches = $nextRoundMatches;
        $currentRoundNum = $nextRoundNum;
    }
    
    if ($isDoubleElim) {
        $numWinnersRounds = (int) log($bracketSize, 2);
        for ($w = 1; $w < $numWinnersRounds; $w++) {
            $numMatches = $bracketSize / pow(2, $w + 1);
            
            // Losers Round w a
            $roundA = 10 + 2 * $w;
            $roundAName = "Losers Round " . $w . "a";
            for ($mIdx = 1; $mIdx <= $numMatches; $mIdx++) {
                $name = $roundAName;
                if ($numMatches > 1) {
                    $name .= ' · Match ' . $mIdx;
                }
                $stmt = db()->prepare(
                    "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
                     VALUES (?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, 'scheduled')"
                );
                $stmt->execute([
                    $tournamentId,
                    $roundA,
                    $name,
                    $tournament['start_date'] ?: null,
                    $tableNum++
                ]);
            }
            
            // Losers Round w b
            $roundB = 10 + 2 * $w + 1;
            $roundBName = "Losers Round " . $w . "b";
            for ($mIdx = 1; $mIdx <= $numMatches; $mIdx++) {
                $name = $roundBName;
                if ($numMatches > 1) {
                    $name .= ' · Match ' . $mIdx;
                }
                $stmt = db()->prepare(
                    "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
                     VALUES (?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, 'scheduled')"
                );
                $stmt->execute([
                    $tournamentId,
                    $roundB,
                    $name,
                    $tournament['start_date'] ?: null,
                    $tableNum++
                ]);
            }
        }
        
        // Grand Finals
        // Match 1
        $stmt = db()->prepare(
            "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
             VALUES (?, NULL, NULL, NULL, NULL, 20, 'Grand Final · Match 1', ?, ?, 'scheduled')"
        );
        $stmt->execute([
            $tournamentId,
            $tournament['start_date'] ?: null,
            $tableNum++
        ]);
        
        // Match 2 (Reset)
        $stmt = db()->prepare(
            "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
             VALUES (?, NULL, NULL, NULL, NULL, 21, 'Grand Final · Match 2 (Reset)', ?, ?, 'scheduled')"
        );
        $stmt->execute([
            $tournamentId,
            $tournament['start_date'] ?: null,
            $tableNum++
        ]);
    }
    
    // 3rd Place Playoff (only for single elimination with >= 4 players, or double elimination)
    // Double elimination already has Grand Finals to decide 3rd place implicitly, so only offer
    // 3rd place for single elim. However we support it for both if requested.
    if ($include3rdPlace && !$isDoubleElim && $bracketSize >= 4) {
        // 3rd place match is at round 30 (well above normal rounds to avoid clashes)
        $stmt = db()->prepare(
            "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
             VALUES (?, NULL, NULL, NULL, NULL, 30, '3rd Place Playoff', ?, ?, 'scheduled')"
        );
        $stmt->execute([
            $tournamentId,
            $tournament['start_date'] ?: null,
            $tableNum++
        ]);
    }

    // After all rounds are created, scan round-2 matches for byes (one player, no opponent)
    // and automatically advance them. This handles the padded bye slots.
    $stmtR2 = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 2 ORDER BY id ASC");
    $stmtR2->execute([$tournamentId]);
    $round2Ids = $stmtR2->fetchAll(PDO::FETCH_COLUMN);
    foreach ($round2Ids as $r2Id) {
        processKnockoutByeMatch((int)$r2Id);
    }

    return [
        'ok' => true,
        'matches' => $created,
        'round_name' => $roundName,
    ];
}

/** @return list<array{label:string,matches:array}> */
function buildBracketGroups(int $tournamentId): array {
    $matches = getTournamentMatches($tournamentId);
    if (empty($matches)) {
        return [];
    }

    $byGroup = [];
    foreach ($matches as $m) {
        $key = bracketGroupKeyFromMatch($m);
        
        if (!isset($byGroup[$key])) {
            $byGroup[$key] = ['label' => $key, 'matches' => []];
        }
        $byGroup[$key]['matches'][] = $m;
    }

    $groups = array_values($byGroup);
    
    usort($groups, function($a, $b) {
        $isAGroup = preg_match('/^Group [A-Z]/i', $a['label']);
        $isBGroup = preg_match('/^Group [A-Z]/i', $b['label']);
        
        if ($isAGroup && !$isBGroup) {
            return -1;
        }
        if (!$isAGroup && $isBGroup) {
            return 1;
        }
        if ($isAGroup && $isBGroup) {
            return strcmp($a['label'], $b['label']);
        }
        // For knockout rounds, sort by round number column
        $rA = $a['matches'][0]['round'] ?? 1;
        $rB = $b['matches'][0]['round'] ?? 1;
        if ($rA !== $rB) {
            return $rA <=> $rB;
        }
        return strcmp($a['label'], $b['label']);
    });
    
    return $groups;
}

function swapBracketParticipants(int $tournamentId, int $match1Id, int $slot1, int $match2Id, int $slot2): bool {
    $m1 = getMatchById($match1Id);
    $m2 = getMatchById($match2Id);
    if (!$m1 || !$m2) {
        return false;
    }
    
    // Get keys/ids for match 1 slot
    $p1Id = ($slot1 === 1) ? $m1['player1_id'] : $m1['player2_id'];
    $g1Id = ($slot1 === 1) ? $m1['player1_guest_id'] : $m1['player2_guest_id'];
    
    // Get keys/ids for match 2 slot
    $p2Id = ($slot2 === 1) ? $m2['player1_id'] : $m2['player2_id'];
    $g2Id = ($slot2 === 1) ? $m2['player1_guest_id'] : $m2['player2_guest_id'];
    
    // Update match 1
    if ($slot1 === 1) {
        db()->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = ? WHERE id = ?")
            ->execute([$p2Id, $g2Id, $match1Id]);
    } else {
        db()->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = ? WHERE id = ?")
            ->execute([$p2Id, $g2Id, $match1Id]);
    }
    
    // Update match 2
    if ($slot2 === 1) {
        db()->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = ? WHERE id = ?")
            ->execute([$p1Id, $g1Id, $match2Id]);
    } else {
        db()->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = ? WHERE id = ?")
            ->execute([$p1Id, $g1Id, $match2Id]);
    }
    
    // Clear status, scores, and winner for the first-round matches being swapped
    db()->prepare("UPDATE matches SET status = 'scheduled', winner_id = NULL, winner_guest_id = NULL, player1_score = 0, player2_score = 0 WHERE id IN (?, ?)")
        ->execute([$match1Id, $match2Id]);
        
    // Reset all subsequent knockout rounds (round > 2) back to empty (TBD)
    db()->prepare("UPDATE matches SET player1_id = NULL, player2_id = NULL, player1_guest_id = NULL, player2_guest_id = NULL, winner_id = NULL, winner_guest_id = NULL, player1_score = 0, player2_score = 0, status = 'scheduled' WHERE tournament_id = ? AND round > 2")
        ->execute([$tournamentId]);
        
    // Recalculate walkovers/byes for all round 2 matches
    $stmtR2 = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 2 ORDER BY id ASC");
    $stmtR2->execute([$tournamentId]);
    $round2Ids = $stmtR2->fetchAll(PDO::FETCH_COLUMN);
    foreach ($round2Ids as $r2Id) {
        processKnockoutByeMatch((int)$r2Id);
    }
    
    return true;
}

