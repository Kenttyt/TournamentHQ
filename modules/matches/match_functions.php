<?php
/**
 * Match Business Logic
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../tournaments/tournament_functions.php';

function matchSelectSql(): string {
    return "SELECT m.*,
                   COALESCE(p1.first_name, g1.first_name) AS p1_first,
                   COALESCE(p1.last_name, g1.last_name) AS p1_last,
                   COALESCE(p2.first_name, g2.first_name) AS p2_first,
                   COALESCE(p2.last_name, g2.last_name) AS p2_last,
                   COALESCE(pw.first_name, wg.first_name) AS winner_first,
                   COALESCE(pw.last_name, wg.last_name) AS winner_last,
                   t.name AS tournament_name,
                   t.category AS tournament_category";
}

function matchFromSql(): string {
    return " FROM matches m
            LEFT JOIN players p1 ON m.player1_id = p1.id
            LEFT JOIN tournament_guests g1 ON m.player1_guest_id = g1.id
            LEFT JOIN players p2 ON m.player2_id = p2.id
            LEFT JOIN tournament_guests g2 ON m.player2_guest_id = g2.id
            LEFT JOIN players pw ON m.winner_id = pw.id
            LEFT JOIN tournament_guests wg ON m.winner_guest_id = wg.id
            JOIN tournaments t ON m.tournament_id = t.id";
}

function getAllMatches(string $search = '', string $status = '', ?int $tournamentId = null): array {
    $sql = matchSelectSql() . matchFromSql() . " WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (COALESCE(p1.first_name, g1.first_name) LIKE ? OR COALESCE(p1.last_name, g1.last_name) LIKE ?
                      OR COALESCE(p2.first_name, g2.first_name) LIKE ? OR COALESCE(p2.last_name, g2.last_name) LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($status) { $sql .= " AND m.status = ?"; $params[] = $status; }
    if ($tournamentId) { $sql .= " AND m.tournament_id = ?"; $params[] = $tournamentId; }
    $sql .= " ORDER BY m.match_date DESC, m.id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getMatchById(int $id): ?array {
    $stmt = db()->prepare(matchSelectSql() . matchFromSql() . " WHERE m.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function createMatch(array $data): int {
    $stmt = db()->prepare(
        "INSERT INTO matches (tournament_id, player1_id, player2_id, player1_guest_id, player2_guest_id, round, round_name, match_date, table_number, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')"
    );
    $stmt->execute([
        $data['tournament_id'],
        $data['player1_id'] ?? null,
        $data['player2_id'] ?? null,
        $data['player1_guest_id'] ?? null,
        $data['player2_guest_id'] ?? null,
        $data['round'],
        $data['round_name'],
        $data['match_date'] ?: null,
        $data['table_number'] ?? 1,
    ]);
    return (int) db()->lastInsertId();
}

/** @param array{type:string,id:int} $entrant */
function createMatchFromEntrants(int $tournamentId, array $entrant1, array $entrant2, int $round, string $roundName, string $matchDate, int $tableNumber): int {
    $data = [
        'tournament_id' => $tournamentId,
        'round'         => $round,
        'round_name'    => $roundName,
        'match_date'    => $matchDate,
        'table_number'  => $tableNumber,
    ];
    if ($entrant1['type'] === 'player') {
        $data['player1_id'] = $entrant1['id'];
    } else {
        $data['player1_guest_id'] = $entrant1['id'];
    }
    if ($entrant2['type'] === 'player') {
        $data['player2_id'] = $entrant2['id'];
    } else {
        $data['player2_guest_id'] = $entrant2['id'];
    }
    return createMatch($data);
}

function matchParticipantKey(array $match, int $slot): string {
    if ($slot === 1) {
        return !empty($match['player1_id']) ? 'player:' . $match['player1_id'] : 'guest:' . $match['player1_guest_id'];
    }
    return !empty($match['player2_id']) ? 'player:' . $match['player2_id'] : 'guest:' . $match['player2_guest_id'];
}

function parseParticipantKey(string $key): ?array {
    if (preg_match('/^(player|guest):(\d+)$/', $key, $m)) {
        return ['type' => $m[1], 'id' => (int) $m[2]];
    }
    return null;
}

function recordMatchResult(int $matchId, int $winnerId, int $p1Score, int $p2Score, ?string $setScores = null): bool {
    return recordBracketMatchResult($matchId, 'player:' . $winnerId, $p1Score, $p2Score, $setScores);
}

function matchWinnerKey(array $match): ?string {
    if (($match['status'] ?? '') !== 'completed') {
        return null;
    }
    if (!empty($match['winner_id'])) {
        return 'player:' . $match['winner_id'];
    }
    if (!empty($match['winner_guest_id'])) {
        return 'guest:' . $match['winner_guest_id'];
    }
    return null;
}

function adjustPlayerStatsForMatchResult(string $winnerKey, string $loserKey, int $delta): void {
    $winner = parseParticipantKey($winnerKey);
    $loser  = parseParticipantKey($loserKey);
    $pdo    = db();

    if ($winner && $winner['type'] === 'player') {
        if ($delta > 0) {
            $pdo->prepare('UPDATE players SET wins = wins + 1 WHERE id = ?')->execute([$winner['id']]);
        } else {
            $pdo->prepare('UPDATE players SET wins = GREATEST(0, wins - 1) WHERE id = ?')->execute([$winner['id']]);
        }
    }
    if ($loser && $loser['type'] === 'player') {
        if ($delta > 0) {
            $pdo->prepare('UPDATE players SET losses = losses + 1 WHERE id = ?')->execute([$loser['id']]);
        } else {
            $pdo->prepare('UPDATE players SET losses = GREATEST(0, losses - 1) WHERE id = ?')->execute([$loser['id']]);
        }
    }
}

function processKnockoutByeMatch(int $matchId): void {
    $match = getMatchById($matchId);
    if (!$match || $match['status'] === 'completed') {
        return;
    }
    if ((int)$match['round'] <= 1) {
        return; // Only apply to knockout rounds
    }

    $has1 = !empty($match['player1_id']) || !empty($match['player1_guest_id']);
    $has2 = !empty($match['player2_id']) || !empty($match['player2_guest_id']);

    // Both present — normal match, do nothing
    if ($has1 && $has2) {
        return;
    }
    // Neither present — nothing to do yet
    if (!$has1 && !$has2) {
        return;
    }

    // For rounds > 2 (subsequent rounds), a match is only a bye if the feeder matches
    // from the previous round are already completed. Otherwise, the empty slot is
    // just waiting for the other feeder match to finish playing.
    $currentRound = (int)$match['round'];
    if ($currentRound > 2) {
        $tournamentId = (int)$match['tournament_id'];
        $prevRound = $currentRound - 1;
        
        // Get all matches in the previous round
        $stmtPrev = db()->prepare("SELECT id, status FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
        $stmtPrev->execute([$tournamentId, $prevRound]);
        $prevMatches = $stmtPrev->fetchAll();
        
        // Find position of this match in the current round
        $stmtCurr = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
        $stmtCurr->execute([$tournamentId, $currentRound]);
        $currMatches = $stmtCurr->fetchAll(PDO::FETCH_COLUMN);
        
        $pos = array_search($matchId, $currMatches);
        if ($pos !== false) {
            $matchNo = $pos + 1;
            $feed1Idx = ($matchNo - 1) * 2;
            $feed2Idx = $feed1Idx + 1;
            
            $feed1 = $prevMatches[$feed1Idx] ?? null;
            $feed2 = $prevMatches[$feed2Idx] ?? null;
            
            // If any of the feeder matches is not completed, it is not a bye walkover yet!
            if ($feed1 && $feed1['status'] !== 'completed') {
                return;
            }
            if ($feed2 && $feed2['status'] !== 'completed') {
                return;
            }
        }
    }


    // One player, no opponent → walkover: auto-advance that player
    if ($has1) {
        $winnerKey = !empty($match['player1_id']) ? 'player:' . $match['player1_id'] : 'guest:' . $match['player1_guest_id'];
    } else {
        $winnerKey = !empty($match['player2_id']) ? 'player:' . $match['player2_id'] : 'guest:' . $match['player2_guest_id'];
    }

    $winner = parseParticipantKey($winnerKey);
    if (!$winner) {
        return;
    }

    $winnerPlayerId = $winner['type'] === 'player' ? $winner['id'] : null;
    $winnerGuestId  = $winner['type'] === 'guest'  ? $winner['id'] : null;

    // Mark the match as completed (walkover, score 1-0)
    $stmt = db()->prepare(
        "UPDATE matches SET winner_id = ?, winner_guest_id = ?, player1_score = ?, player2_score = ?, status = 'completed' WHERE id = ?"
    );
    $stmt->execute([
        $winnerPlayerId,
        $winnerGuestId,
        $has1 ? 1 : 0,
        $has1 ? 0 : 1,
        $matchId
    ]);

    $tournamentId  = (int) $match['tournament_id'];
    $tournament = getTournamentById($tournamentId);
    if ($tournament && $tournament['format'] === 'double_elimination') {
        $p1Key = matchParticipantKey($match, 1);
        $p2Key = matchParticipantKey($match, 2);
        $loserKey = ($winnerKey === $p1Key) ? $p2Key : $p1Key;
        advanceDoubleElimination($match, $winnerKey, $loserKey);
        return;
    }

    // Advance winner to the next round
    $currentRound  = (int) $match['round'];
    $nextRound     = $currentRound + 1;

    $stmtCurr = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
    $stmtCurr->execute([$tournamentId, $currentRound]);
    $currMatches = $stmtCurr->fetchAll(PDO::FETCH_COLUMN);

    $pos = array_search($matchId, $currMatches);
    if ($pos === false) {
        return;
    }

    $matchNo      = $pos + 1;
    $nextMatchNo  = (int) ceil($matchNo / 2);
    $isSlot1      = ($matchNo % 2 !== 0);

    $stmtNext = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
    $stmtNext->execute([$tournamentId, $nextRound]);
    $nextMatches = $stmtNext->fetchAll(PDO::FETCH_COLUMN);

    if (!isset($nextMatches[$nextMatchNo - 1])) {
        return;
    }

    $nextMatchId = $nextMatches[$nextMatchNo - 1];

    if ($winnerPlayerId) {
        if ($isSlot1) {
            db()->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = NULL WHERE id = ?")->execute([$winnerPlayerId, $nextMatchId]);
        } else {
            db()->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = NULL WHERE id = ?")->execute([$winnerPlayerId, $nextMatchId]);
        }
    } elseif ($winnerGuestId) {
        if ($isSlot1) {
            db()->prepare("UPDATE matches SET player1_id = NULL, player1_guest_id = ? WHERE id = ?")->execute([$winnerGuestId, $nextMatchId]);
        } else {
            db()->prepare("UPDATE matches SET player2_id = NULL, player2_guest_id = ? WHERE id = ?")->execute([$winnerGuestId, $nextMatchId]);
        }
    }

    // Recursively check if the next match is also a bye
    processKnockoutByeMatch($nextMatchId);
}

function recordBracketMatchResult(int $matchId, string $winnerKey, int $p1Score, int $p2Score, ?string $setScores = null): bool {
    $winner = parseParticipantKey($winnerKey);
    if (!$winner) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Fetch the latest match state with FOR UPDATE to prevent concurrency issues
        $stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ? FOR UPDATE");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match) {
            $pdo->rollBack();
            return false;
        }

        $oldWinnerKey = null;
        if (($match['status'] ?? '') === 'completed') {
            if (!empty($match['winner_id'])) {
                $oldWinnerKey = 'player:' . $match['winner_id'];
            } elseif (!empty($match['winner_guest_id'])) {
                $oldWinnerKey = 'guest:' . $match['winner_guest_id'];
            }
        }

        if ($oldWinnerKey) {
            $p1Key = !empty($match['player1_id']) ? 'player:' . $match['player1_id'] : 'guest:' . $match['player1_guest_id'];
            $p2Key = !empty($match['player2_id']) ? 'player:' . $match['player2_id'] : 'guest:' . $match['player2_guest_id'];
            $oldLoserKey = ($oldWinnerKey === $p1Key) ? $p2Key : $p1Key;
            adjustPlayerStatsForMatchResult($oldWinnerKey, $oldLoserKey, -1);
        }

        $winnerPlayerId = $winner['type'] === 'player' ? $winner['id'] : null;
        $winnerGuestId  = $winner['type'] === 'guest' ? $winner['id'] : null;

        $stmt = $pdo->prepare(
            "UPDATE matches SET winner_id = ?, winner_guest_id = ?, player1_score = ?, player2_score = ?, set_scores = ?, status = 'completed' WHERE id = ?"
        );
        $ok = $stmt->execute([$winnerPlayerId, $winnerGuestId, $p1Score, $p2Score, $setScores, $matchId]);

        if ($ok) {
            $p1Key = !empty($match['player1_id']) ? 'player:' . $match['player1_id'] : 'guest:' . $match['player1_guest_id'];
            $p2Key = !empty($match['player2_id']) ? 'player:' . $match['player2_id'] : 'guest:' . $match['player2_guest_id'];
            $loserKey = ($winnerKey === $p1Key) ? $p2Key : $p1Key;
            adjustPlayerStatsForMatchResult($winnerKey, $loserKey, 1);
            
            $tournament = getTournamentById((int) $match['tournament_id']);
            if ($tournament && $tournament['format'] === 'double_elimination') {
                advanceDoubleElimination($match, $winnerKey, $loserKey);
                $pdo->commit();
                return $ok;
            }
            
            // If it is a knockout stage match (round > 1), automatically advance the winner to the next round TBD match
            if ($match['round'] > 1) {
                $tournamentId = (int) $match['tournament_id'];
                $currentRound = (int) $match['round'];
                $nextRound = $currentRound + 1;
                
                // Get all matches in the current round for this tournament, ordered by ID
                $stmtCurr = $pdo->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                $stmtCurr->execute([$tournamentId, $currentRound]);
                $currMatches = $stmtCurr->fetchAll(PDO::FETCH_COLUMN);
                
                $pos = array_search($matchId, $currMatches);
                if ($pos !== false) {
                    $matchNo = $pos + 1;
                    $nextMatchNo = (int) ceil($matchNo / 2);
                    $isSlot1 = ($matchNo % 2 !== 0);
                    
                    // Get all matches in the next round, ordered by ID
                    $stmtNext = $pdo->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                    $stmtNext->execute([$tournamentId, $nextRound]);
                    $nextMatches = $stmtNext->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (isset($nextMatches[$nextMatchNo - 1])) {
                        $nextMatchId = $nextMatches[$nextMatchNo - 1];
                        
                        if ($winnerPlayerId) {
                            if ($isSlot1) {
                                $stmtUpd = $pdo->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = NULL WHERE id = ?");
                            } else {
                                $stmtUpd = $pdo->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = NULL WHERE id = ?");
                            }
                            $stmtUpd->execute([$winnerPlayerId, $nextMatchId]);
                        } elseif ($winnerGuestId) {
                            if ($isSlot1) {
                                $stmtUpd = $pdo->prepare("UPDATE matches SET player1_id = NULL, player1_guest_id = ? WHERE id = ?");
                            } else {
                                $stmtUpd = $pdo->prepare("UPDATE matches SET player2_id = NULL, player2_guest_id = ? WHERE id = ?");
                            }
                            $stmtUpd->execute([$winnerGuestId, $nextMatchId]);
                        }

                        // Check if the next match is now a bye (only one player, no opponent) and auto-advance
                        processKnockoutByeMatch($nextMatchId);
                    }
                    
                    // 3rd Place Playoff: if this is a semifinal match (2 matches in current round,
                    // 1 match in next round = Final), place the loser into round 30
                    if (count($currMatches) === 2 && count($nextMatches) === 1) {
                        $stmt3rd = $pdo->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 30 ORDER BY id ASC LIMIT 1");
                        $stmt3rd->execute([$tournamentId]);
                        $thirdPlaceMatchId = $stmt3rd->fetchColumn();
                        
                        if ($thirdPlaceMatchId) {
                            // Determine loser identity
                            $loserPlayerId = null;
                            $loserGuestId = null;
                            $parts = explode(':', $loserKey);
                            if ($parts[0] === 'player') {
                                $loserPlayerId = (int) $parts[1];
                            } else {
                                $loserGuestId = (int) $parts[1];
                            }
                            
                            // Slot 1 for first semifinal loser, slot 2 for second
                            $isSlot1_3rd = ($matchNo % 2 !== 0);
                            if ($loserPlayerId) {
                                if ($isSlot1_3rd) {
                                    $stmtUpd3 = $pdo->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = NULL WHERE id = ?");
                                } else {
                                    $stmtUpd3 = $pdo->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = NULL WHERE id = ?");
                                }
                                $stmtUpd3->execute([$loserPlayerId, $thirdPlaceMatchId]);
                            } elseif ($loserGuestId) {
                                if ($isSlot1_3rd) {
                                    $stmtUpd3 = $pdo->prepare("UPDATE matches SET player1_id = NULL, player1_guest_id = ? WHERE id = ?");
                                } else {
                                    $stmtUpd3 = $pdo->prepare("UPDATE matches SET player2_id = NULL, player2_guest_id = ? WHERE id = ?");
                                }
                                $stmtUpd3->execute([$loserGuestId, $thirdPlaceMatchId]);
                            }
                        }
                    }
                }
            }
        }

        $pdo->commit();
        return $ok;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}


function updateMatchResult(int $matchId, array $data): bool {
    $stmt = db()->prepare(
        "UPDATE matches SET winner_id=?, player1_score=?, player2_score=?, set_scores=?, status=?,
         match_date=?, table_number=?, notes=? WHERE id=?"
    );
    return $stmt->execute([
        $data['winner_id'] ?: null, $data['player1_score'], $data['player2_score'],
        $data['set_scores'] ?? null,
        $data['status'], $data['match_date'] ?: null,
        $data['table_number'] ?? 1, $data['notes'] ?? null, $matchId
    ]);
}

function deleteMatch(int $id): bool {
    return db()->prepare("DELETE FROM matches WHERE id=?")->execute([$id]);
}

function getMatchCount(): int {
    return (int) db()->query("SELECT COUNT(*) FROM matches")->fetchColumn();
}

function getRecentMatches(?int $limit = null): array {
    $sql = matchSelectSql() . matchFromSql() . " WHERE m.status = 'completed' ORDER BY m.updated_at DESC";
    if ($limit !== null) {
        $sql .= " LIMIT ?";
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($limit !== null ? [$limit] : []);
    return $stmt->fetchAll();
}

function getUpcomingMatches(int $limit = 5): array {
    $stmt = db()->prepare(matchSelectSql() . matchFromSql() . " WHERE m.status = 'scheduled' ORDER BY m.match_date ASC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getTournamentMatches(int $tournamentId): array {
    $stmt = db()->prepare(
        matchSelectSql() . matchFromSql() . " WHERE m.tournament_id = ? ORDER BY m.round ASC, m.id ASC"
    );
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function matchWinnerIsSlot(array $match, int $slot): bool {
    if ($match['status'] !== 'completed') {
        return false;
    }
    if ($slot === 1) {
        if (!empty($match['winner_id']) && !empty($match['player1_id'])) {
            return (int) $match['winner_id'] === (int) $match['player1_id'];
        }
        if (!empty($match['winner_guest_id']) && !empty($match['player1_guest_id'])) {
            return (int) $match['winner_guest_id'] === (int) $match['player1_guest_id'];
        }
    } else {
        if (!empty($match['winner_id']) && !empty($match['player2_id'])) {
            return (int) $match['winner_id'] === (int) $match['player2_id'];
        }
        if (!empty($match['winner_guest_id']) && !empty($match['player2_guest_id'])) {
            return (int) $match['winner_guest_id'] === (int) $match['player2_guest_id'];
        }
    }
    return false;
}

function advanceDoubleElimination(array $match, string $winnerKey, string $loserKey): void {
    $tournamentId = (int)$match['tournament_id'];
    $currentRound = (int)$match['round'];
    $matchId = (int)$match['id'];
    
    // Parse winner and loser keys
    $winner = parseParticipantKey($winnerKey);
    $loser = parseParticipantKey($loserKey);
    if (!$winner) return;

    $winnerPlayerId = $winner['type'] === 'player' ? $winner['id'] : null;
    $winnerGuestId  = $winner['type'] === 'guest'  ? $winner['id'] : null;
    $loserPlayerId  = $loser ? ($loser['type'] === 'player' ? $loser['id'] : null) : null;
    $loserGuestId   = $loser ? ($loser['type'] === 'guest'  ? $loser['id'] : null) : null;

    $roundName = $match['round_name'] ?? '';

    // helper to update a match slot
    $updateSlot = function(int $targetMatchId, int $slot, ?int $playerId, ?int $guestId) {
        if ($slot === 1) {
            $stmt = db()->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = ? WHERE id = ?");
        } else {
            $stmt = db()->prepare("UPDATE matches SET player2_id = ?, player2_guest_id = ? WHERE id = ?");
        }
        $stmt->execute([$playerId, $guestId, $targetMatchId]);
        processKnockoutByeMatch($targetMatchId);
    };

    if (strpos($roundName, 'Winners') === 0) {
        // --- WINNERS BRACKET ---
        // 1. Advance winner in Winners Bracket
        $stmtCurr = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
        $stmtCurr->execute([$tournamentId, $currentRound]);
        $currMatches = $stmtCurr->fetchAll(PDO::FETCH_COLUMN);
        
        $pos = array_search($matchId, $currMatches);
        if ($pos !== false) {
            $matchNo = $pos + 1;
            
            // Check if current Winners round is Winners Final (only 1 match in the round)
            if (count($currMatches) === 1) {
                // Winners Champion goes to Grand Finals Match 1, Slot 1
                $stmtGF = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 20 LIMIT 1");
                $stmtGF->execute([$tournamentId]);
                $gfId = $stmtGF->fetchColumn();
                if ($gfId) {
                    $updateSlot((int)$gfId, 1, $winnerPlayerId, $winnerGuestId);
                }
                
                // Loser drops to Losers Final
                $stmtMaxLosers = db()->prepare("SELECT MAX(round) FROM matches WHERE tournament_id = ? AND round_name LIKE 'Losers%'");
                $stmtMaxLosers->execute([$tournamentId]);
                $maxLosersRound = (int)$stmtMaxLosers->fetchColumn();
                if ($maxLosersRound) {
                    $stmtLF = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC LIMIT 1");
                    $stmtLF->execute([$tournamentId, $maxLosersRound]);
                    $lfId = $stmtLF->fetchColumn();
                    if ($lfId) {
                        $updateSlot((int)$lfId, 2, $loserPlayerId, $loserGuestId);
                    }
                }
            } else {
                // Normal Winners Round -> next Winners Round
                $nextRound = $currentRound + 1;
                $nextMatchNo = (int) ceil($matchNo / 2);
                $isSlot1 = ($matchNo % 2 !== 0);
                
                $stmtNext = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                $stmtNext->execute([$tournamentId, $nextRound]);
                $nextMatches = $stmtNext->fetchAll(PDO::FETCH_COLUMN);
                
                if (isset($nextMatches[$nextMatchNo - 1])) {
                    $nextMatchId = (int)$nextMatches[$nextMatchNo - 1];
                    $updateSlot($nextMatchId, $isSlot1 ? 1 : 2, $winnerPlayerId, $winnerGuestId);
                }

                // Loser drops to Losers Bracket!
                $w = $currentRound - 1; // Winners round index (1-based)
                if ($w === 1) {
                    // Winners Round 1 losers go to Losers Round 1a (round 12)
                    $targetRound = 12;
                    $targetMatchNo = (int) ceil($matchNo / 2);
                    $isTargetSlot1 = ($matchNo % 2 !== 0);
                    
                    $stmtL = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                    $stmtL->execute([$tournamentId, $targetRound]);
                    $lMatches = $stmtL->fetchAll(PDO::FETCH_COLUMN);
                    if (isset($lMatches[$targetMatchNo - 1])) {
                        $lMatchId = (int)$lMatches[$targetMatchNo - 1];
                        $updateSlot($lMatchId, $isTargetSlot1 ? 1 : 2, $loserPlayerId, $loserGuestId);
                    }
                } else {
                    // Winners Round w (w > 1) losers go to Losers Round (w-1)b (round 10 + 2*(w-1) + 1 = 9 + 2*w)
                    $targetRound = 9 + 2 * $w;
                    $targetMatchNo = $matchNo; // 1-to-1 match index
                    
                    $stmtL = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                    $stmtL->execute([$tournamentId, $targetRound]);
                    $lMatches = $stmtL->fetchAll(PDO::FETCH_COLUMN);
                    if (isset($lMatches[$targetMatchNo - 1])) {
                        $lMatchId = (int)$lMatches[$targetMatchNo - 1];
                        $updateSlot($lMatchId, 2, $loserPlayerId, $loserGuestId); // Takes slot 2
                    }
                }
            }
        }
    } elseif (strpos($roundName, 'Losers') === 0) {
        // --- LOSERS BRACKET ---
        $stmtCurr = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
        $stmtCurr->execute([$tournamentId, $currentRound]);
        $currMatches = $stmtCurr->fetchAll(PDO::FETCH_COLUMN);
        
        $pos = array_search($matchId, $currMatches);
        if ($pos !== false) {
            $matchNo = $pos + 1;
            
            // Check if this is the last Losers round (Losers Final)
            $stmtMaxLosers = db()->prepare("SELECT MAX(round) FROM matches WHERE tournament_id = ? AND round_name LIKE 'Losers%'");
            $stmtMaxLosers->execute([$tournamentId]);
            $maxLosersRound = (int)$stmtMaxLosers->fetchColumn();
            
            if ($currentRound === $maxLosersRound) {
                // Winner goes to Grand Finals Match 1, Slot 2 as the Losers Bracket Champion!
                $stmtGF = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 20 LIMIT 1");
                $stmtGF->execute([$tournamentId]);
                $gfId = $stmtGF->fetchColumn();
                if ($gfId) {
                    $updateSlot((int)$gfId, 2, $winnerPlayerId, $winnerGuestId);
                }
            } else {
                // Determine if it is a 'a' round or 'b' round
                $isRoundA = ($currentRound % 2 === 0);
                
                if ($isRoundA) {
                    // Losers Round w a -> Losers Round w b (same round number + 1, same match index, Slot 1)
                    $nextRound = $currentRound + 1;
                    $stmtNext = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                    $stmtNext->execute([$tournamentId, $nextRound]);
                    $nextMatches = $stmtNext->fetchAll(PDO::FETCH_COLUMN);
                    if (isset($nextMatches[$matchNo - 1])) {
                        $nextMatchId = (int)$nextMatches[$matchNo - 1];
                        $updateSlot($nextMatchId, 1, $winnerPlayerId, $winnerGuestId); // Takes slot 1
                    }
                } else {
                    // Losers Round w b -> Losers Round (w+1)a (round + 1, half as many matches, Slot 1 or 2)
                    $nextRound = $currentRound + 1;
                    $nextMatchNo = (int) ceil($matchNo / 2);
                    $isSlot1 = ($matchNo % 2 !== 0);
                    
                    $stmtNext = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = ? ORDER BY id ASC");
                    $stmtNext->execute([$tournamentId, $nextRound]);
                    $nextMatches = $stmtNext->fetchAll(PDO::FETCH_COLUMN);
                    if (isset($nextMatches[$nextMatchNo - 1])) {
                        $nextMatchId = (int)$nextMatches[$nextMatchNo - 1];
                        $updateSlot($nextMatchId, $isSlot1 ? 1 : 2, $winnerPlayerId, $winnerGuestId);
                    }
                }
            }
        }
    } elseif (strpos($roundName, 'Grand Final') === 0) {
        // --- GRAND FINALS ---
        if ($currentRound === 20) {
            // Grand Final Match 1
            $winnersChampWon = matchWinnerIsSlot($match, 1);
            
            if ($winnersChampWon) {
                // Winners Champion wins tournament! No bracket reset needed.
                // Clear any players in Grand Final Match 2 (Reset) so it's not active/shown
                db()->prepare("UPDATE matches SET player1_id = NULL, player2_id = NULL, player1_guest_id = NULL, player2_guest_id = NULL, status = 'scheduled' WHERE tournament_id = ? AND round = 21")
                    ->execute([$tournamentId]);
                
                // Update tournament status to completed
                db()->prepare("UPDATE tournaments SET status = 'completed' WHERE id = ?")->execute([$tournamentId]);
            } else {
                // Losers Champion won! Bracket reset occurs.
                // Schedule Grand Final Match 2 (Reset) with same players
                $stmtGF2 = db()->prepare("SELECT id FROM matches WHERE tournament_id = ? AND round = 21 LIMIT 1");
                $stmtGF2->execute([$tournamentId]);
                $gf2Id = $stmtGF2->fetchColumn();
                if ($gf2Id) {
                    $p1Id = $match['player1_id'];
                    $p1GuestId = $match['player1_guest_id'];
                    $p2Id = $match['player2_id'];
                    $p2GuestId = $match['player2_guest_id'];
                    
                    db()->prepare("UPDATE matches SET player1_id = ?, player1_guest_id = ?, player2_id = ?, player2_guest_id = ?, status = 'scheduled', winner_id = NULL, winner_guest_id = NULL, player1_score = 0, player2_score = 0 WHERE id = ?")
                        ->execute([$p1Id, $p1GuestId, $p2Id, $p2GuestId, $gf2Id]);
                }
            }
        } elseif ($currentRound === 21) {
            // Grand Final Match 2 (Reset)
            db()->prepare("UPDATE tournaments SET status = 'completed' WHERE id = ?")->execute([$tournamentId]);
        }
    }
}
