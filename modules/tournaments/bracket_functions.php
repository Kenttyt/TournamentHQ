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
 * Merge a short last chunk into random full group(s) so no one is left solo.
 * @param list<list<array>> $groups
 * @return array{0: list<list<array>>, 1: list<array{player:string,group:string}>}
 */
function distributeRemainderIntoGroups(array $groups, int $groupSize): array {
    $merged = [];
    $groupSize = normalizeGroupSize($groupSize);

    while (count($groups) > 1) {
        $last = $groups[count($groups) - 1];
        if (count($last) >= $groupSize) {
            break;
        }
        $remainder = array_pop($groups);
        if (empty($remainder)) {
            break;
        }

        $targetIdx = array_rand($groups);
        $targetLabel = groupLabel($targetIdx);

        foreach ($remainder as $entrant) {
            $groups[$targetIdx][] = $entrant;
            $merged[] = [
                'player' => entrantDisplayName($entrant),
                'group'  => $targetLabel,
            ];
        }
    }

    return [$groups, $merged];
}

function deleteTournamentScheduledMatches(int $tournamentId): void {
    db()->prepare("DELETE FROM matches WHERE tournament_id = ? AND status = 'scheduled'")
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

    $groupSize = normalizeGroupSize($groupSize);
    $entrants = getTournamentEntrants($tournamentId);
    $count = count($entrants);
    if ($count < 2) {
        return ['ok' => false, 'message' => 'At least 2 participants (players or guests) are required to generate a bracket.'];
    }

    if ($replaceScheduled) {
        deleteTournamentScheduledMatches($tournamentId);
    }

    $ordered = $entrants;
    if ($shuffle) {
        shuffle($ordered);
    }

    $groups = array_chunk($ordered, $groupSize);
    [$groups, $merged] = distributeRemainderIntoGroups($groups, $groupSize);

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
        'group_size' => $groupSize,
        'merged'     => $merged,
    ];
}

function bracketGroupKeyFromMatch(array $match): string {
    $name = trim($match['round_name'] ?? 'Ungrouped');
    if (preg_match('/^(Group [A-Z]+)/', $name, $m)) {
        return $m[1];
    }
    return $name;
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
        // Hide legacy single-elimination buckets (e.g. "Round of 16")
        if (!preg_match('/^Group [A-Z]/', $key)) {
            continue;
        }
        if (!isset($byGroup[$key])) {
            $byGroup[$key] = ['label' => $key, 'matches' => []];
        }
        $byGroup[$key]['matches'][] = $m;
    }

    $groups = array_values($byGroup);
    usort($groups, fn($a, $b) => strcmp($a['label'], $b['label']));
    return $groups;
}

/** @deprecated Use buildBracketGroups */
function buildBracketRounds(int $tournamentId): array {
    return buildBracketGroups($tournamentId);
}
