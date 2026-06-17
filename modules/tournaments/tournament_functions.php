<?php
/**
 * Tournament Business Logic
 */
require_once __DIR__ . '/../../config/database.php';

function approvedRegistrationCountSql(): string {
    return "(COUNT(DISTINCT CASE WHEN tp.registration_status = 'approved' THEN tp.player_id END)
            + COALESCE((SELECT COUNT(*) FROM tournament_guests tg
                WHERE tg.tournament_id = t.id AND tg.registration_status = 'approved'), 0))";
}

function getAllTournaments(string $search = '', string $status = ''): array {
    $countSql = approvedRegistrationCountSql();
    $sql = "SELECT t.*, u.username AS organizer_name,
                   {$countSql} AS registered_count
            FROM tournaments t
            JOIN users u ON t.organizer_id = u.id
            LEFT JOIN tournament_players tp ON t.id = tp.tournament_id
            WHERE 1=1";
    $params = [];
    if ($search) { $sql .= " AND t.name LIKE ?"; $params[] = "%$search%"; }
    if ($status) { $sql .= " AND t.status = ?";  $params[] = $status; }
    $sql .= " GROUP BY t.id ORDER BY t.start_date DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTournamentById(int $id): ?array {
    $countSql = approvedRegistrationCountSql();
    $stmt = db()->prepare(
        "SELECT t.*, u.username AS organizer_name,
                {$countSql} AS registered_count
         FROM tournaments t
         JOIN users u ON t.organizer_id = u.id
         LEFT JOIN tournament_players tp ON t.id = tp.tournament_id
         WHERE t.id = ?
         GROUP BY t.id"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function isTournamentEditable(int $tournamentId): bool {
    $t = getTournamentById($tournamentId);
    return $t !== null && ($t['status'] ?? '') !== 'completed';
}

function getTournamentPlayers(int $tournamentId, ?string $registrationStatus = 'approved'): array {
    $sql = "SELECT p.*, tp.seed, tp.registered_at, tp.registration_status,
                p.first_name, p.last_name, p.club, p.nationality
         FROM players p
         JOIN tournament_players tp ON p.id = tp.player_id
         WHERE tp.tournament_id = ?";
    $params = [$tournamentId];
    if ($registrationStatus !== null) {
        $sql .= " AND tp.registration_status = ?";
        $params[] = $registrationStatus;
    }
    $sql .= " ORDER BY tp.seed ASC, p.last_name ASC, p.first_name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ensureTournamentPrizeColumns(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $exists = db()->query("SHOW COLUMNS FROM tournaments LIKE 'prize_champion'")->fetch();
        if (!$exists) {
            db()->exec(
                "ALTER TABLE tournaments
                 ADD COLUMN prize_champion VARCHAR(100) DEFAULT NULL AFTER prize_pool,
                 ADD COLUMN prize_2nd VARCHAR(100) DEFAULT NULL AFTER prize_champion,
                 ADD COLUMN prize_3rd VARCHAR(100) DEFAULT NULL AFTER prize_2nd,
                 ADD COLUMN prize_4th VARCHAR(100) DEFAULT NULL AFTER prize_3rd"
            );
        }
        $feeExists = db()->query("SHOW COLUMNS FROM tournaments LIKE 'registration_fee'")->fetch();
        if (!$feeExists) {
            db()->exec(
                "ALTER TABLE tournaments ADD COLUMN registration_fee VARCHAR(100) DEFAULT NULL AFTER prize_4th"
            );
        }
    } catch (Throwable $e) {
        // Ignore if columns already exist or ALTER not permitted
    }
}

function normalizeRegistrationFee(array $data): string {
    return trim($data['registration_fee'] ?? '');
}

function formatRegistrationFee(?array $tournament): string {
    if (!$tournament) {
        return '';
    }
    $fee = trim($tournament['registration_fee'] ?? '');
    return $fee !== '' ? $fee : '';
}

/** @return array{prize_champion:string,prize_2nd:string,prize_3rd:string,prize_4th:string,prize_pool:string} */
function normalizeTournamentPrizes(array $data): array {
    $champion = trim($data['prize_champion'] ?? '');
    $second   = trim($data['prize_2nd'] ?? '');
    $third    = trim($data['prize_3rd'] ?? '');
    $fourth   = trim($data['prize_4th'] ?? '');
    return [
        'prize_champion' => $champion,
        'prize_2nd'      => $second,
        'prize_3rd'      => $third,
        'prize_4th'      => $fourth,
        'prize_pool'     => buildPrizePoolSummary($champion, $second, $third, $fourth)
            ?: trim($data['prize_pool'] ?? ''),
    ];
}

function buildPrizePoolSummary(string $champion, string $second, string $third, string $fourth): string {
    $parts = [];
    if ($champion !== '') {
        $parts[] = 'Champion: ' . $champion;
    }
    if ($second !== '') {
        $parts[] = '2nd Place: ' . $second;
    }
    if ($third !== '') {
        $parts[] = '3rd Place: ' . $third;
    }
    if ($fourth !== '') {
        $parts[] = '4th Place: ' . $fourth;
    }
    return implode(' · ', $parts);
}

/** @return array<int, array{label:string, value:string}> */
function getTournamentPrizePlaces(?array $tournament): array {
    if (!$tournament) {
        return [];
    }
    $places = [
        ['label' => 'Champion', 'value' => trim($tournament['prize_champion'] ?? '')],
        ['label' => '2nd Place', 'value' => trim($tournament['prize_2nd'] ?? '')],
        ['label' => '3rd Place', 'value' => trim($tournament['prize_3rd'] ?? '')],
        ['label' => '4th Place', 'value' => trim($tournament['prize_4th'] ?? '')],
    ];
    $filled = array_values(array_filter($places, fn($p) => $p['value'] !== ''));
    if (!empty($filled)) {
        return $filled;
    }
    $legacy = trim($tournament['prize_pool'] ?? '');
    if ($legacy !== '') {
        return [['label' => 'Prize Pool', 'value' => $legacy]];
    }
    return [];
}

function createTournament(array $data): int {
    ensureTournamentPrizeColumns();
    $prizes = normalizeTournamentPrizes($data);
    $registrationFee = normalizeRegistrationFee($data);
    $stmt = db()->prepare(
        "INSERT INTO tournaments (organizer_id, name, category, description, format, status, max_players, start_date, end_date, venue, prize_pool, prize_champion, prize_2nd, prize_3rd, prize_4th, registration_fee)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['organizer_id'], $data['name'], $data['category'] ?? 'Open Singles', $data['description'],
        $data['format'], $data['status'] ?? 'upcoming', $data['max_players'],
        $data['start_date'], $data['end_date'] ?: null,
        $data['venue'], $prizes['prize_pool'], $prizes['prize_champion'], $prizes['prize_2nd'], $prizes['prize_3rd'], $prizes['prize_4th'], $registrationFee,
    ]);
    $tournamentId = (int) db()->lastInsertId();
    if ($tournamentId > 0 && ($data['status'] ?? 'upcoming') === 'upcoming') {
        require_once __DIR__ . '/../notifications/notification_functions.php';
        notifyAllPlayersNewTournament($tournamentId);
    }
    return $tournamentId;
}

function updateTournament(int $id, array $data): bool {
    ensureTournamentPrizeColumns();
    $prizes = normalizeTournamentPrizes($data);
    $registrationFee = normalizeRegistrationFee($data);
    $stmt = db()->prepare(
        "UPDATE tournaments SET name=?, category=?, description=?, format=?, status=?, max_players=?,
         start_date=?, end_date=?, venue=?, prize_pool=?, prize_champion=?, prize_2nd=?, prize_3rd=?, prize_4th=?, registration_fee=? WHERE id=?"
    );
    $ok = $stmt->execute([
        $data['name'], $data['category'] ?? 'Open Singles', $data['description'], $data['format'], $data['status'],
        $data['max_players'], $data['start_date'], $data['end_date'] ?: null,
        $data['venue'], $prizes['prize_pool'], $prizes['prize_champion'], $prizes['prize_2nd'], $prizes['prize_3rd'], $prizes['prize_4th'], $registrationFee, $id
    ]);
    // Schedule proof deletion 7 days from now when tournament is completed
    if ($ok && ($data['status'] ?? '') === 'completed') {
        scheduleProofDeletion($id);
    }
    return $ok;
}

/**
 * Sets proofs_delete_after to NOW + 7 days for the given tournament.
 * Safe to call multiple times — only updates if not already scheduled.
 */
function scheduleProofDeletion(int $tournamentId): void {
    try {
        $chk = db()->prepare("SELECT proofs_delete_after FROM tournaments WHERE id = ? LIMIT 1");
        $chk->execute([$tournamentId]);
        $row = $chk->fetch();
        // Only schedule once (don't override if already set)
        if ($row && $row['proofs_delete_after'] === null) {
            $deleteAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            db()->prepare("UPDATE tournaments SET proofs_delete_after = ? WHERE id = ?")
                ->execute([$deleteAt, $tournamentId]);
        }
    } catch (Throwable $e) {
        // Column may not exist yet on very old installs — silently skip
    }
}

/**
 * Deletes all payment proof files for a tournament from disk and clears
 * the payment_proof_path columns in tournament_guests.
 * Called by the cleanup script.
 */
function deleteAllTournamentProofs(int $tournamentId): int {
    require_once __DIR__ . '/../uploads/payment_proof.php';
    $stmt = db()->prepare(
        "SELECT DISTINCT payment_proof_path FROM tournament_guests
         WHERE tournament_id = ? AND payment_proof_path IS NOT NULL AND payment_proof_path != ''"
    );
    $stmt->execute([$tournamentId]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $deleted = 0;
    foreach ($paths as $path) {
        deletePaymentProofFile($path);
        $deleted++;
    }
    // Clear the paths from DB so they don't show broken links
    db()->prepare(
        "UPDATE tournament_guests SET payment_proof_path = NULL, payment_proof_original_name = NULL
         WHERE tournament_id = ?"
    )->execute([$tournamentId]);
    // Mark tournament as cleaned up
    db()->prepare("UPDATE tournaments SET proofs_delete_after = NULL WHERE id = ?")
        ->execute([$tournamentId]);
    return $deleted;
}

/**
 * Finds all tournaments whose proofs_delete_after has passed and deletes their proof files.
 * Returns a summary array for logging.
 */
function cleanupExpiredProofs(): array {
    $stmt = db()->prepare(
        "SELECT id, name FROM tournaments
         WHERE proofs_delete_after IS NOT NULL AND proofs_delete_after <= NOW()"
    );
    $stmt->execute();
    $due = $stmt->fetchAll();
    $results = [];
    foreach ($due as $t) {
        $count = deleteAllTournamentProofs((int) $t['id']);
        $results[] = [
            'tournament_id'   => (int) $t['id'],
            'tournament_name' => $t['name'],
            'files_deleted'   => $count,
        ];
    }
    return $results;
}

function deleteTournament(int $id): bool {
    return db()->prepare("DELETE FROM tournaments WHERE id=?")->execute([$id]);
}

function isTournamentOwnedBy(int $tournamentId, int $userId): bool {
    $t = getTournamentById($tournamentId);
    return $t && (int) $t['organizer_id'] === $userId;
}

function getTournamentCount(): int {
    return (int) db()->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
}

function getActiveTournamentCount(): int {
    return (int) db()->query("SELECT COUNT(*) FROM tournaments WHERE status='ongoing'")->fetchColumn();
}

function registerPlayerInTournament(int $tournamentId, int $playerId, ?int $seed = null, string $status = 'pending'): bool {
    if ($playerId <= 0) {
        return false;
    }
    if (isPlayerRegistered($tournamentId, $playerId)) {
        return true;
    }
    $stmt = db()->prepare(
        "INSERT INTO tournament_players (tournament_id, player_id, seed, registration_status) VALUES (?,?,?,?)"
    );
    if (!$stmt->execute([$tournamentId, $playerId, $seed, $status])) {
        return false;
    }
    return $stmt->rowCount() > 0;
}

function removePlayerFromTournament(int $tournamentId, int $playerId): bool {
    if (!isTournamentEditable($tournamentId)) {
        return false;
    }
    return db()->prepare("DELETE FROM tournament_players WHERE tournament_id=? AND player_id=?")->execute([$tournamentId, $playerId]);
}

function deleteTournamentGuest(int $guestId, int $tournamentId): bool {
    require_once __DIR__ . '/../uploads/payment_proof.php';
    $stmt = db()->prepare(
        "SELECT payment_proof_path FROM tournament_guests WHERE id = ? AND tournament_id = ?"
    );
    $stmt->execute([$guestId, $tournamentId]);
    $guest = $stmt->fetch();
    $ok = db()->prepare("DELETE FROM tournament_guests WHERE id = ? AND tournament_id = ?")->execute([$guestId, $tournamentId]);
    if ($ok && $guest && !empty($guest['payment_proof_path'])) {
        deletePaymentProofFile($guest['payment_proof_path']);
    }
    return $ok;
}

function removeTournamentRegistration(int $tournamentId, string $regType, int $regId): bool {
    if (!isTournamentEditable($tournamentId)) {
        return false;
    }
    if ($regType === 'player') {
        return removePlayerFromTournament($tournamentId, $regId);
    }
    if ($regType === 'guest') {
        return deleteTournamentGuest($regId, $tournamentId);
    }
    return false;
}

function getOrganizerTournaments(int $organizerId): array {
    $countSql = approvedRegistrationCountSql();
    $stmt = db()->prepare(
        "SELECT t.*, {$countSql} AS registered_count
         FROM tournaments t
         LEFT JOIN tournament_players tp ON t.id = tp.tournament_id
         WHERE t.organizer_id = ?
         GROUP BY t.id
         ORDER BY t.start_date DESC"
    );
    $stmt->execute([$organizerId]);
    return $stmt->fetchAll();
}

function isPlayerRegistered(int $tournamentId, int $playerId): bool {
    $stmt = db()->prepare(
        "SELECT 1 FROM tournament_players
         WHERE tournament_id = ? AND player_id = ?
         AND registration_status IN ('pending', 'approved')"
    );
    $stmt->execute([$tournamentId, $playerId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = db()->prepare(
        "SELECT 1 FROM tournament_guests
         WHERE tournament_id = ? AND registered_by_player_id = ?
         AND registration_status IN ('pending', 'approved')"
    );
    $stmt->execute([$tournamentId, $playerId]);
    return (bool) $stmt->fetchColumn();
}

function getPlayerRegistrationStatus(int $tournamentId, int $playerId): ?string {
    $stmt = db()->prepare(
        "SELECT registration_status FROM tournament_players WHERE tournament_id = ? AND player_id = ?"
    );
    $stmt->execute([$tournamentId, $playerId]);
    $status = $stmt->fetchColumn();
    if ($status !== false) {
        return (string) $status;
    }

    $stmt = db()->prepare(
        "SELECT registration_status FROM tournament_guests
         WHERE tournament_id = ? AND registered_by_player_id = ?"
    );
    $stmt->execute([$tournamentId, $playerId]);
    $guestStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($guestStatuses)) {
        return null;
    }
    if (in_array('approved', $guestStatuses, true)) {
        return 'approved';
    }
    if (in_array('pending', $guestStatuses, true)) {
        return 'pending';
    }
    return null;
}

function isPlayerApproved(int $tournamentId, int $playerId): bool {
    return getPlayerRegistrationStatus($tournamentId, $playerId) === 'approved';
}

function addTournamentGuest(
    int $tournamentId,
    ?int $registeredByPlayerId,
    string $firstName,
    string $lastName,
    string $status = 'pending',
    ?string $paymentProofPath = null,
    ?string $paymentProofOriginalName = null,
    ?string $club = null,
    ?string $nationality = null
): bool {
    if ($registeredByPlayerId && (($club === null || $club === '') || ($nationality === null || $nationality === ''))) {
        $submitterStmt = db()->prepare('SELECT club, nationality FROM players WHERE id = ? LIMIT 1');
        $submitterStmt->execute([$registeredByPlayerId]);
        $submitter = $submitterStmt->fetch();
        if ($submitter) {
            if ($club === null || $club === '') {
                $club = trim($submitter['club'] ?? '') ?: null;
            }
            if ($nationality === null || $nationality === '') {
                $nationality = trim($submitter['nationality'] ?? '') ?: null;
            }
        }
    }

    $stmt = db()->prepare(
        "INSERT INTO tournament_guests (tournament_id, registered_by_player_id, first_name, last_name, club, nationality, registration_status, payment_proof_path, payment_proof_original_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    return $stmt->execute([
        $tournamentId,
        $registeredByPlayerId,
        $firstName,
        $lastName,
        $club,
        $nationality,
        $status,
        $paymentProofPath,
        $paymentProofOriginalName,
    ]);
}

function isTournamentGuestRegistered(int $tournamentId, string $firstName, string $lastName): bool {
    $stmt = db()->prepare(
        "SELECT 1 FROM tournament_guests
         WHERE tournament_id = ? AND first_name = ? AND last_name = ?
         AND registration_status IN ('pending','approved')
         LIMIT 1"
    );
    $stmt->execute([$tournamentId, $firstName, $lastName]);
    return (bool) $stmt->fetchColumn();
}

function getSubmitterTournamentGuests(int $tournamentId, int $submitterPlayerId): array {
    $stmt = db()->prepare(
        "SELECT id, first_name, last_name, club, nationality, registration_status, created_at, payment_proof_path, payment_proof_original_name
         FROM tournament_guests
         WHERE tournament_id = ? AND registered_by_player_id = ?
         ORDER BY id ASC"
    );
    $stmt->execute([$tournamentId, $submitterPlayerId]);
    return $stmt->fetchAll();
}

/** Pending guest registrations grouped by submitter (for organizer approval + payment proof) */
function getPendingRegistrationGroups(int $tournamentId): array {
    $stmt = db()->prepare(
        "SELECT tg.id AS reg_id, tg.first_name, tg.last_name, tg.club, tg.nationality, tg.created_at AS registered_at,
                tg.registered_by_player_id, tg.payment_proof_path, tg.payment_proof_original_name,
                p.first_name AS reg_first, p.last_name AS reg_last
         FROM tournament_guests tg
         LEFT JOIN players p ON tg.registered_by_player_id = p.id
         WHERE tg.tournament_id = ? AND tg.registration_status = 'pending'
         ORDER BY tg.created_at ASC"
    );
    $stmt->execute([$tournamentId]);
    $groups = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (int) ($row['registered_by_player_id'] ?? 0);
        if (!isset($groups[$key])) {
            $submitter = trim(($row['reg_first'] ?? '') . ' ' . ($row['reg_last'] ?? ''));
            $groups[$key] = [
                'submitter_player_id' => $key,
                'submitter_name'      => $submitter !== '' ? $submitter : 'Unknown submitter',
                'payment_proof_path'  => $row['payment_proof_path'] ?? null,
                'payment_proof_original_name' => $row['payment_proof_original_name'] ?? null,
                'registered_at'       => $row['registered_at'],
                'players'             => [],
            ];
        }
        $groups[$key]['players'][] = [
            'reg_id'      => (int) $row['reg_id'],
            'reg_type'    => 'guest',
            'first_name'  => $row['first_name'],
            'last_name'   => $row['last_name'],
            'club'        => $row['club'] ?? '',
            'nationality' => $row['nationality'] ?? '',
        ];
        if (!empty($row['payment_proof_path']) && empty($groups[$key]['payment_proof_path'])) {
            $groups[$key]['payment_proof_path'] = $row['payment_proof_path'];
            $groups[$key]['payment_proof_original_name'] = $row['payment_proof_original_name'];
        }
    }
    return array_values($groups);
}

/** Approved account players + guests for public roster display */
function getTournamentApprovedEntrants(int $tournamentId): array {
    $entrants = [];
    foreach (getTournamentPlayers($tournamentId, 'approved') as $p) {
        $entrants[] = [
            'name'   => trim($p['first_name'] . ' ' . $p['last_name']),
            'club'   => trim($p['club'] ?? ''),
            'status' => 'approved',
        ];
    }
    foreach (getTournamentGuests($tournamentId, 'approved') as $g) {
        $entrants[] = [
            'name'   => trim($g['first_name'] . ' ' . $g['last_name']),
            'club'   => trim($g['club'] ?? ''),
            'status' => 'approved',
        ];
    }
    usort($entrants, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $entrants;
}

function getTournamentGuests(int $tournamentId, ?string $registrationStatus = 'approved'): array {
    $sql = "SELECT tg.*, p.first_name AS reg_first, p.last_name AS reg_last
         FROM tournament_guests tg
         LEFT JOIN players p ON tg.registered_by_player_id = p.id
         WHERE tg.tournament_id = ?";
    $params = [$tournamentId];
    if ($registrationStatus !== null) {
        $sql .= " AND tg.registration_status = ?";
        $params[] = $registrationStatus;
    }
    $sql .= " ORDER BY tg.id ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPendingTournamentRegistrations(int $tournamentId): array {
    $pending = [];
    $stmt = db()->prepare(
        "SELECT tp.player_id AS reg_id, 'player' AS reg_type, p.first_name, p.last_name, tp.registered_at,
                NULL AS registered_by_player_id
         FROM tournament_players tp
         JOIN players p ON tp.player_id = p.id
         WHERE tp.tournament_id = ? AND tp.registration_status = 'pending'
         ORDER BY tp.registered_at ASC"
    );
    $stmt->execute([$tournamentId]);
    foreach ($stmt->fetchAll() as $row) {
        $pending[] = $row;
    }
    $stmt = db()->prepare(
        "SELECT tg.id AS reg_id, 'guest' AS reg_type, tg.first_name, tg.last_name, tg.created_at AS registered_at,
                tg.registered_by_player_id, p.first_name AS reg_first, p.last_name AS reg_last
         FROM tournament_guests tg
         LEFT JOIN players p ON tg.registered_by_player_id = p.id
         WHERE tg.tournament_id = ? AND tg.registration_status = 'pending'
         ORDER BY tg.created_at ASC"
    );
    $stmt->execute([$tournamentId]);
    foreach ($stmt->fetchAll() as $row) {
        $pending[] = $row;
    }
    return $pending;
}

function setPlayerRegistrationStatus(int $tournamentId, int $playerId, string $status): bool {
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }
    return db()->prepare(
        "UPDATE tournament_players SET registration_status = ? WHERE tournament_id = ? AND player_id = ?"
    )->execute([$status, $tournamentId, $playerId]);
}

function setGuestRegistrationStatus(int $guestId, int $tournamentId, string $status): bool {
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }
    return db()->prepare(
        "UPDATE tournament_guests SET registration_status = ? WHERE id = ? AND tournament_id = ?"
    )->execute([$status, $guestId, $tournamentId]);
}

function approveTournamentRegistration(int $tournamentId, string $regType, int $regId, bool $approveLinkedGuests = true): bool {
    if ($regType === 'player') {
        if (!setPlayerRegistrationStatus($tournamentId, $regId, 'approved')) {
            return false;
        }
        if ($approveLinkedGuests) {
            db()->prepare(
                "UPDATE tournament_guests SET registration_status = 'approved'
                 WHERE tournament_id = ? AND registered_by_player_id = ? AND registration_status = 'pending'"
            )->execute([$tournamentId, $regId]);
        }
        require_once __DIR__ . '/../notifications/notification_functions.php';
        notifyPlayerRegistrationApproved($tournamentId, $regId);
        return true;
    }
    if ($regType === 'guest') {
        $stmt = db()->prepare(
            "SELECT registered_by_player_id, first_name, last_name FROM tournament_guests WHERE id = ? AND tournament_id = ?"
        );
        $stmt->execute([$regId, $tournamentId]);
        $guest = $stmt->fetch();
        if (!setGuestRegistrationStatus($regId, $tournamentId, 'approved')) {
            return false;
        }
        if ($guest && !empty($guest['registered_by_player_id'])) {
            require_once __DIR__ . '/../notifications/notification_functions.php';
            $guestName = trim($guest['first_name'] . ' ' . $guest['last_name']);
            notifySubmitterGuestReviewed($tournamentId, (int) $guest['registered_by_player_id'], $guestName, true);
        }
        return true;
    }
    return false;
}

function rejectTournamentRegistration(int $tournamentId, string $regType, int $regId): bool {
    if ($regType === 'player') {
        db()->prepare(
            "DELETE FROM tournament_guests WHERE tournament_id = ? AND registered_by_player_id = ? AND registration_status = 'pending'"
        )->execute([$tournamentId, $regId]);
        require_once __DIR__ . '/../notifications/notification_functions.php';
        notifyPlayerRegistrationDeclined($tournamentId, $regId);
        return db()->prepare(
            "DELETE FROM tournament_players WHERE tournament_id = ? AND player_id = ?"
        )->execute([$tournamentId, $regId]);
    }
    if ($regType === 'guest') {
        require_once __DIR__ . '/../uploads/payment_proof.php';
        $stmt = db()->prepare(
            "SELECT registered_by_player_id, first_name, last_name, payment_proof_path FROM tournament_guests WHERE id = ? AND tournament_id = ?"
        );
        $stmt->execute([$regId, $tournamentId]);
        $guest = $stmt->fetch();
        $proofPath = $guest['payment_proof_path'] ?? null;
        $ok = db()->prepare("DELETE FROM tournament_guests WHERE id = ? AND tournament_id = ?")->execute([$regId, $tournamentId]);
        if ($ok && $proofPath) {
            $chk = db()->prepare(
                "SELECT COUNT(*) FROM tournament_guests WHERE tournament_id = ? AND payment_proof_path = ?"
            );
            $chk->execute([$tournamentId, $proofPath]);
            if ((int) $chk->fetchColumn() === 0) {
                deletePaymentProofFile($proofPath);
            }
        }
        if ($ok && $guest && !empty($guest['registered_by_player_id'])) {
            require_once __DIR__ . '/../notifications/notification_functions.php';
            $guestName = trim($guest['first_name'] . ' ' . $guest['last_name']);
            notifySubmitterGuestReviewed($tournamentId, (int) $guest['registered_by_player_id'], $guestName, false);
        }
        return $ok;
    }
    return false;
}

function removePlayerGuestsFromTournament(int $tournamentId, int $registeredByPlayerId): bool {
    if (!isTournamentEditable($tournamentId)) {
        return false;
    }
    require_once __DIR__ . '/../uploads/payment_proof.php';
    $stmt = db()->prepare(
        "SELECT DISTINCT payment_proof_path FROM tournament_guests
         WHERE tournament_id = ? AND registered_by_player_id = ? AND payment_proof_path IS NOT NULL"
    );
    $stmt->execute([$tournamentId, $registeredByPlayerId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        deletePaymentProofFile($path);
    }
    $del = db()->prepare("DELETE FROM tournament_guests WHERE tournament_id = ? AND registered_by_player_id = ?");
    return $del->execute([$tournamentId, $registeredByPlayerId]);
}

/** All tournament participants: account players + guests */
function getTournamentEntrants(int $tournamentId): array {
    $entrants = [];
    foreach (getTournamentPlayers($tournamentId) as $p) {
        $entrants[] = [
            'type'       => 'player',
            'id'         => (int) $p['id'],
            'first_name' => $p['first_name'],
            'last_name'  => $p['last_name'],
            'club'       => $p['club'] ?? '',
            'seed'       => $p['seed'] ?? null,
            'is_guest'   => false,
        ];
    }
    foreach (getTournamentGuests($tournamentId) as $g) {
        $entrants[] = [
            'type'       => 'guest',
            'id'         => (int) $g['id'],
            'first_name' => $g['first_name'],
            'last_name'  => $g['last_name'],
            'club'        => $g['club'] ?? '',
            'nationality' => $g['nationality'] ?? '',
            'seed'        => null,
            'is_guest'    => true,
        ];
    }
    return $entrants;
}

/**
 * Get recently approved guest registrations that have no payment proof uploaded.
 * Used for organizer history views.
 */
function getApprovedGuestsWithoutPaymentProof(int $limit = 6): array {
    $stmt = db()->prepare(
        "SELECT tg.*, p.first_name AS submitter_first, p.last_name AS submitter_last, t.name AS tournament_name
         FROM tournament_guests tg
         LEFT JOIN players p ON tg.registered_by_player_id = p.id
         LEFT JOIN tournaments t ON tg.tournament_id = t.id
         WHERE tg.registration_status = 'approved' AND (tg.payment_proof_path IS NULL OR tg.payment_proof_path = '')
         ORDER BY tg.created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get recent approved registrations (players and guests) with tournament info.
 * Returns unified rows with fields: type ('player'|'guest'), entity_id, first_name, last_name,
 * tournament_id, tournament_name, submitter_first, submitter_last, ts
 */
function getRecentApprovedRegistrations(int $limit = 8): array {
    return getApprovedRegistrationHistory($limit);
}

function getApprovedRegistrationHistory(?int $limit = null): array {
    $sql = "(
        SELECT tp.player_id AS entity_id, 'player' AS type, p.first_name, p.last_name,
               tp.tournament_id, t.name AS tournament_name,
               NULL AS submitter_first, NULL AS submitter_last,
               NULL AS payment_proof_path,
               tp.registered_at AS ts
        FROM tournament_players tp
        JOIN players p ON tp.player_id = p.id
        JOIN tournaments t ON tp.tournament_id = t.id
        WHERE tp.registration_status = 'approved'
    ) UNION ALL (
        SELECT tg.id AS entity_id, 'guest' AS type, tg.first_name, tg.last_name,
               tg.tournament_id, t.name AS tournament_name,
               sp.first_name AS submitter_first, sp.last_name AS submitter_last,
               tg.payment_proof_path,
               tg.created_at AS ts
        FROM tournament_guests tg
        LEFT JOIN players sp ON tg.registered_by_player_id = sp.id
        JOIN tournaments t ON tg.tournament_id = t.id
        WHERE tg.registration_status = 'approved'
    )
    ORDER BY ts DESC";

    if ($limit !== null) {
        $sql .= "\n    LIMIT ?";
    }

    $stmt = db()->prepare($sql);
    if ($limit !== null) {
        $stmt->execute([$limit]);
    } else {
        $stmt->execute();
    }
    return $stmt->fetchAll();
}
