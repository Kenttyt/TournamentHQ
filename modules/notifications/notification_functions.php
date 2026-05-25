<?php
/**
 * In-app notifications (bell)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../players/player_functions.php';

function createNotification(int $userId, string $type, string $title, string $message, ?string $link = null): bool {
    if ($userId <= 0) {
        return false;
    }
    return db()->prepare(
        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)"
    )->execute([$userId, $type, $title, $message, $link]);
}

function getUnreadNotificationCount(int $userId): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getUserNotifications(int $userId, string $role, int $limit = 50): array {
    $items = [];

    $stmt = db()->prepare(
        "SELECT * FROM notifications WHERE user_id = ?
         ORDER BY is_read ASC, created_at DESC LIMIT ?"
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $items[] = formatNotificationRow($row);
    }

    foreach (getLiveRegistrationAlerts($userId, $role) as $live) {
        $items[] = $live;
    }

    usort($items, static function ($a, $b) {
        if ($a['is_read'] !== $b['is_read']) {
            return $a['is_read'] <=> $b['is_read'];
        }
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return array_slice(mergeNotificationDisplayList($items), 0, $limit);
}

function tournamentNotificationLabel(array $tournament): string {
    $label = $tournament['name'] ?? 'Tournament';
    if (!empty($tournament['category'])) {
        $label .= ' (' . $tournament['category'] . ')';
    }
    return $label;
}

/** One unread approval/decline per tournament title — merges older duplicate rows for display */
function mergeNotificationDisplayList(array $items): array {
    $merged = [];
    $buckets = [];

    foreach ($items as $item) {
        if (!empty($item['live'])) {
            $merged[] = $item;
            continue;
        }
        if (!in_array($item['type'], ['registration_approved', 'registration_declined'], true)) {
            $merged[] = $item;
            continue;
        }

        $key = $item['type'] . '|' . $item['title'];
        if (!isset($buckets[$key])) {
            $buckets[$key] = $item;
            $buckets[$key]['_ids'] = !empty($item['id']) ? [(int) $item['id']] : [];
            continue;
        }

        if (!empty($item['id'])) {
            $buckets[$key]['_ids'][] = (int) $item['id'];
        }
        if (empty($item['is_read'])) {
            $buckets[$key]['is_read'] = false;
        }
        if (strcmp($item['created_at'] ?? '', $buckets[$key]['created_at'] ?? '') > 0) {
            $buckets[$key]['created_at'] = $item['created_at'];
        }
        $buckets[$key]['message'] = combineApprovalMessages(
            $buckets[$key]['message'],
            $item['message'],
            $item['type'] === 'registration_approved'
        );
    }

    foreach ($buckets as $bucket) {
        unset($bucket['_ids']);
        $merged[] = $bucket;
    }

    usort($merged, static function ($a, $b) {
        if ($a['is_read'] !== $b['is_read']) {
            return $a['is_read'] <=> $b['is_read'];
        }
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return $merged;
}

function combineApprovalMessages(string $existing, string $incoming, bool $approved): string {
    $namesA = extractPlayerNamesFromApprovalMessage($existing, $approved);
    $namesB = extractPlayerNamesFromApprovalMessage($incoming, $approved);
    $names = array_values(array_unique(array_merge($namesA, $namesB)));

    $tournamentLabel = extractTournamentLabelFromMessage($existing)
        ?: extractTournamentLabelFromMessage($incoming);

    if ($approved) {
        if (empty($names)) {
            return 'You are confirmed for ' . $tournamentLabel . '. Good luck!';
        }
        return 'You are confirmed for ' . $tournamentLabel . ': ' . implode(', ', $names) . '. Good luck!';
    }

    if (empty($names)) {
        return 'Registration for ' . $tournamentLabel . ' was not approved.';
    }
    return $tournamentLabel . ': ' . implode(', ', $names) . ' was not approved.';
}

function extractTournamentLabelFromMessage(string $message): string {
    if (preg_match('/You are confirmed for (.+?)(?::|\. Good luck!|$)/i', $message, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/^(.+?):/u', $message, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/for (.+?)\./i', $message, $m)) {
        return trim($m[1]);
    }
    return '';
}

function extractPlayerNamesFromApprovalMessage(string $message, bool $approved): array {
    if (preg_match('/You are confirmed for .+?:\s*(.+?)\.\s*Good luck!/i', $message, $m)) {
        return parseNameList($m[1]);
    }
    if (preg_match('/^[^:]+:\s*(.+?)\s+was not approved/i', $message, $m)) {
        return parseNameList($m[1]);
    }
    if (preg_match('/^(.+?)\s+was approved for/i', $message, $m)) {
        return [trim($m[1])];
    }
    if (preg_match('/^(.+?)\s+was not approved for/i', $message, $m)) {
        return [trim($m[1])];
    }
    return [];
}

function parseNameList(string $segment): array {
    return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $segment))));
}

function appendGuestToApprovalMessage(string $existing, string $tLabel, string $guestName, bool $approved): string {
    $names = extractPlayerNamesFromApprovalMessage($existing, $approved);
    if (!in_array($guestName, $names, true)) {
        $names[] = $guestName;
    }

    if ($approved) {
        return 'You are confirmed for ' . $tLabel . ': ' . implode(', ', $names) . '. Good luck!';
    }

    return $tLabel . ': ' . implode(', ', $names) . ' was not approved.';
}

function upsertSubmitterTournamentNotification(
    int $userId,
    int $tournamentId,
    bool $approved,
    ?string $guestName = null
): void {
    require_once __DIR__ . '/../tournaments/tournament_functions.php';
    $t = getTournamentById($tournamentId);
    if (!$t) {
        return;
    }

    $type = $approved ? 'registration_approved' : 'registration_declined';
    $title = $approved ? 'Registration approved' : 'Registration declined';
    $tLabel = tournamentNotificationLabel($t);
    $link = '/table-tennis-system/player/index.php';

    $stmt = db()->prepare(
        "SELECT id, message FROM notifications
         WHERE user_id = ? AND type = ? AND is_read = 0 AND title = ?
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$userId, $type, $title]);
    $existing = $stmt->fetch();

    if ($guestName && $existing) {
        $message = appendGuestToApprovalMessage((string) $existing['message'], $tLabel, $guestName, $approved);
        db()->prepare("UPDATE notifications SET message = ?, created_at = NOW() WHERE id = ?")
            ->execute([$message, (int) $existing['id']]);
        return;
    }

    if ($existing) {
        return;
    }

    if ($approved) {
        $message = $guestName
            ? 'You are confirmed for ' . $tLabel . ': ' . $guestName . '. Good luck!'
            : 'You are confirmed for ' . $tLabel . '. Good luck!';
    } else {
        $message = $guestName
            ? $tLabel . ': ' . $guestName . ' was not approved.'
            : 'Registration for ' . $tLabel . ' was not approved.';
    }

    createNotification($userId, $type, $title, $message, $link);
}

function getNotificationBadgeCount(int $userId, string $role): int {
    if (!in_array($role, ['organizer', 'player'], true)) {
        return 0;
    }
    $count = getUnreadNotificationCount($userId);
    foreach (getLiveRegistrationAlerts($userId, $role) as $live) {
        if (empty($live['is_read'])) {
            $count++;
        }
    }
    return $count;
}

function formatNotificationRow(array $row): array {
    return [
        'id'         => (int) $row['id'],
        'type'       => $row['type'],
        'title'      => $row['title'],
        'message'    => $row['message'],
        'link'       => $row['link'] ?? null,
        'is_read'    => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
        'live'       => false,
    ];
}

function getLiveRegistrationAlerts(int $userId, string $role): array {
    $alerts = [];

    if ($role === 'organizer') {
        $stmt = db()->prepare(
            "SELECT t.id, t.name,
                (SELECT COUNT(*) FROM tournament_players tp
                 WHERE tp.tournament_id = t.id AND tp.registration_status = 'pending') +
                (SELECT COUNT(*) FROM tournament_guests tg
                 WHERE tg.tournament_id = t.id AND tg.registration_status = 'pending') AS pending_count
             FROM tournaments t
             WHERE t.organizer_id = ? AND t.status = 'upcoming'
             HAVING pending_count > 0"
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $n = (int) $row['pending_count'];
            $alerts[] = [
                'id'         => 0,
                'type'       => 'registration_pending',
                'title'      => 'Approval needed',
                'message'    => $n . ' pending registration' . ($n === 1 ? '' : 's') . ' for ' . $row['name'],
                'link'       => '/table-tennis-system/organizer/tournaments.php',
                'is_read'    => false,
                'created_at' => date('Y-m-d H:i:s'),
                'live'       => true,
            ];
        }
    } elseif ($role === 'admin') {
        $stmt = db()->query(
            "SELECT t.id, t.name,
                (SELECT COUNT(*) FROM tournament_players tp
                 WHERE tp.tournament_id = t.id AND tp.registration_status = 'pending') +
                (SELECT COUNT(*) FROM tournament_guests tg
                 WHERE tg.tournament_id = t.id AND tg.registration_status = 'pending') AS pending_count
             FROM tournaments t
             WHERE t.status = 'upcoming'
             HAVING pending_count > 0"
        );
        foreach ($stmt->fetchAll() as $row) {
            $n = (int) $row['pending_count'];
            $alerts[] = [
                'id'         => 0,
                'type'       => 'registration_pending',
                'title'      => 'Approval needed',
                'message'    => $n . ' pending registration' . ($n === 1 ? '' : 's') . ' for ' . $row['name'],
                'link'       => '/table-tennis-system/admin/manage_tournaments.php',
                'is_read'    => false,
                'created_at' => date('Y-m-d H:i:s'),
                'live'       => true,
            ];
        }
    } elseif ($role === 'player') {
        $player = getPlayerByUserId($userId);
        if ($player) {
            $stmt = db()->prepare(
                "SELECT DISTINCT t.name FROM (
                    SELECT tournament_id FROM tournament_players
                    WHERE player_id = ? AND registration_status = 'pending'
                    UNION
                    SELECT tournament_id FROM tournament_guests
                    WHERE registered_by_player_id = ? AND registration_status = 'pending'
                ) pending_regs
                JOIN tournaments t ON t.id = pending_regs.tournament_id
                WHERE t.status = 'upcoming'"
            );
            $stmt->execute([(int) $player['id'], (int) $player['id']]);
            foreach ($stmt->fetchAll() as $row) {
                $alerts[] = [
                    'id'         => 0,
                    'type'       => 'registration_submitted',
                    'title'      => 'Awaiting approval',
                    'message'    => 'Players you submitted for ' . $row['name'] . ' are pending organizer confirmation.',
                    'link'       => '/table-tennis-system/player/index.php',
                    'is_read'    => false,
                    'created_at' => date('Y-m-d H:i:s'),
                    'live'       => true,
                ];
            }
        }
    }

    return $alerts;
}

function markNotificationRead(int $notificationId, int $userId): bool {
    return db()->prepare(
        "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
    )->execute([$notificationId, $userId]);
}

function markAllNotificationsRead(int $userId): bool {
    return db()->prepare(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
    )->execute([$userId]);
}

function notifyAllPlayersNewTournament(int $tournamentId): void {
    require_once __DIR__ . '/../tournaments/tournament_functions.php';
    $t = getTournamentById($tournamentId);
    if (!$t || ($t['status'] ?? '') !== 'upcoming') {
        return;
    }
    $start = $t['start_date'] ? date('M j, Y', strtotime($t['start_date'])) : 'TBA';
    $stmt = db()->query(
        "SELECT DISTINCT u.id FROM users u
         INNER JOIN players p ON p.user_id = u.id
         WHERE u.role = 'player' AND u.is_active = 1"
    );
    $message = 'New tournament "' . $t['name'] . '" (' . ($t['category'] ?? 'Open') . ') opens ' . $start . '. Register players on your dashboard.';
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        createNotification(
            (int) $userId,
            'tournament_new',
            'New tournament available',
            $message,
            '/table-tennis-system/player/index.php'
        );
    }
}

function notifyOrganizerRegistrationRequest(int $tournamentId, int $playerId, int $playerCount = 1): void {
    require_once __DIR__ . '/../tournaments/tournament_functions.php';
    $t = getTournamentById($tournamentId);
    $player = getPlayerById($playerId);
    if (!$t || !$player) {
        return;
    }
    $name = trim($player['first_name'] . ' ' . $player['last_name']);
    $n = max(1, $playerCount);
    createNotification(
        (int) $t['organizer_id'],
        'registration_pending',
        'New registration request',
        $name . ' submitted ' . $n . ' player' . ($n === 1 ? '' : 's') . ' for ' . $t['name'] . ' with payment proof. Review payment and approve.',
        '/table-tennis-system/organizer/tournaments.php'
    );
}

function notifySubmitterGuestReviewed(int $tournamentId, int $submitterPlayerId, string $guestName, bool $approved): void {
    $player = getPlayerById($submitterPlayerId);
    if (!$player || empty($player['user_id'])) {
        return;
    }
    upsertSubmitterTournamentNotification((int) $player['user_id'], $tournamentId, $approved, $guestName);
}

function notifyPlayerRegistrationApproved(int $tournamentId, int $playerId): void {
    $player = getPlayerById($playerId);
    if (!$player || empty($player['user_id'])) {
        return;
    }
    upsertSubmitterTournamentNotification((int) $player['user_id'], $tournamentId, true, null);
}

function notifyPlayerRegistrationDeclined(int $tournamentId, int $playerId): void {
    $player = getPlayerById($playerId);
    if (!$player || empty($player['user_id'])) {
        return;
    }
    upsertSubmitterTournamentNotification((int) $player['user_id'], $tournamentId, false, null);
}

function notificationIcon(string $type): string {
    return match ($type) {
        'registration_pending'    => 'user-plus',
        'registration_submitted'  => 'clock',
        'registration_approved'   => 'check-circle',
        'registration_declined'   => 'x-circle',
        'tournament_new'          => 'trophy',
        default                   => 'bell',
    };
}

function notificationTimeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return (int) floor($diff / 86400) . 'd ago';
    }
    return date('M j', $ts);
}
