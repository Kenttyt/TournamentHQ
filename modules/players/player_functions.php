<?php
/**
 * Player Business Logic
 */
require_once __DIR__ . '/../../config/database.php';

function getAllPlayers(string $search = ''): array {
    $sql = "SELECT p.*, u.username, u.email, u.is_active
            FROM players p
            JOIN users u ON p.user_id = u.id
            WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.club LIKE ? OR p.nationality LIKE ?)";
        $like = "%$search%";
        $params = [$like, $like, $like, $like];
    }
    $sql .= " ORDER BY p.first_name ASC, p.last_name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPlayerById(int $id): ?array {
    $stmt = db()->prepare("SELECT p.*, u.username, u.email, u.is_active, u.role
                           FROM players p JOIN users u ON p.user_id = u.id
                           WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getPlayerByUserId(int $userId): ?array {
    $stmt = db()->prepare("SELECT p.*, u.username, u.email
                           FROM players p JOIN users u ON p.user_id = u.id
                           WHERE p.user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function createPlayer(array $data): int {
    $stmt = db()->prepare(
        "INSERT INTO players (user_id, first_name, last_name, date_of_birth, gender, club, nationality)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['user_id'], $data['first_name'], $data['last_name'],
        $data['date_of_birth'] ?: null, $data['gender'], $data['club'], $data['nationality'],
    ]);
    return (int) db()->lastInsertId();
}

function updatePlayer(int $id, array $data): bool {
    $stmt = db()->prepare(
        "UPDATE players SET first_name=?, last_name=?, date_of_birth=?, gender=?, club=?, nationality=?
         WHERE id=?"
    );
    return $stmt->execute([
        $data['first_name'], $data['last_name'], $data['date_of_birth'] ?: null,
        $data['gender'], $data['club'], $data['nationality'], $id
    ]);
}

function deletePlayer(int $id): bool {
    $stmt = db()->prepare("DELETE FROM players WHERE id=?");
    return $stmt->execute([$id]);
}

function getPlayerCount(): int {
    return (int) db()->query("SELECT COUNT(*) FROM players")->fetchColumn();
}

function getPlayerTournaments(int $playerId): array {
    $stmt = db()->prepare(
        "SELECT t.*, tp.seed, tp.registered_at, tp.registration_status
         FROM tournaments t
         JOIN tournament_players tp ON t.id = tp.tournament_id
         WHERE tp.player_id = ? AND tp.registration_status IN ('pending', 'approved')
         UNION
         SELECT t.*, NULL AS seed, MIN(tg.created_at) AS registered_at,
                CASE
                    WHEN SUM(tg.registration_status = 'approved') > 0 THEN 'approved'
                    ELSE 'pending'
                END AS registration_status
         FROM tournaments t
         JOIN tournament_guests tg ON t.id = tg.tournament_id
         WHERE tg.registered_by_player_id = ?
           AND tg.registration_status IN ('pending', 'approved')
         GROUP BY t.id
         ORDER BY start_date DESC"
    );
    $stmt->execute([$playerId, $playerId]);
    return $stmt->fetchAll();
}

function getPlayerMatches(int $playerId, int $limit = 20): array {
    $stmt = db()->prepare(
        "SELECT m.*,
                p1.first_name AS p1_first, p1.last_name AS p1_last,
                p2.first_name AS p2_first, p2.last_name AS p2_last,
                t.name AS tournament_name
         FROM matches m
         JOIN players p1 ON m.player1_id = p1.id
         JOIN players p2 ON m.player2_id = p2.id
         JOIN tournaments t ON m.tournament_id = t.id
         WHERE m.player1_id = ? OR m.player2_id = ?
         ORDER BY m.match_date DESC
         LIMIT ?"
    );
    $stmt->execute([$playerId, $playerId, $limit]);
    return $stmt->fetchAll();
}
