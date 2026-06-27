<?php
/**
 * Database Configuration
 * Table Tennis Tournament Management System
 */

// Load local configuration first if it exists (e.g., for production/InfinityFree)
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
} else {
    // Default configuration (local development)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'tt_system');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::runMigrations(self::$instance);
                self::ensureDefaultAdminUser(self::$instance);
            } catch (PDOException $e) {
                error_log('TournamentHQ DB Error: ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Service temporarily unavailable.']));
            }
        }
        return self::$instance;
    }

    /**
     * Safety-net migrations for databases created before the schema was complete.
     * New installs importing database.sql won't need any of these.
     */
    private static function runMigrations(PDO $pdo): void {
        // tournaments.category
        try { $pdo->query("SELECT category FROM tournaments LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN category VARCHAR(100) DEFAULT 'Open Singles' AFTER name");
        }

        // tournaments.sport
        try { $pdo->query("SELECT sport FROM tournaments LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN sport VARCHAR(100) DEFAULT 'Table Tennis' AFTER name");
        }

        // tournaments.is_team_event
        try { $pdo->query("SELECT is_team_event FROM tournaments LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN is_team_event TINYINT(1) NOT NULL DEFAULT 0 AFTER sport");
        }

        // tournament_guests table (full modern schema)
        try { $pdo->query("SELECT id FROM tournament_guests LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tournament_guests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tournament_id INT NOT NULL,
                registered_by_player_id INT NULL,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                club VARCHAR(100) NULL,
                nationality VARCHAR(60) NULL,
                registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                payment_proof_path VARCHAR(255) NULL,
                payment_proof_original_name VARCHAR(200) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                FOREIGN KEY (registered_by_player_id) REFERENCES players(id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }

        // tournament_guests column additions (for very old installs)
        $guestCols = [
            'registered_by_player_id' => "ALTER TABLE tournament_guests MODIFY COLUMN registered_by_player_id INT NULL",
            'club'                    => "ALTER TABLE tournament_guests ADD COLUMN club VARCHAR(100) NULL AFTER last_name, ADD COLUMN nationality VARCHAR(60) NULL AFTER club",
            'registration_status'     => "ALTER TABLE tournament_guests ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER last_name",
            'payment_proof_path'      => "ALTER TABLE tournament_guests ADD COLUMN payment_proof_path VARCHAR(255) NULL, ADD COLUMN payment_proof_original_name VARCHAR(200) NULL",
        ];
        foreach ($guestCols as $col => $sql) {
            try { $pdo->query("SELECT $col FROM tournament_guests LIMIT 1"); }
            catch (PDOException $e) { try { $pdo->exec($sql); } catch (PDOException $ignored) {} }
        }

        // password_reset_tokens table
        try { $pdo->query("SELECT id FROM password_reset_tokens LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_reset_token (token_hash),
                INDEX idx_reset_user (user_id)
            ) ENGINE=InnoDB");
        }

        // notifications table
        try { $pdo->query("SELECT id FROM notifications LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(120) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(255) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_notifications_user_read (user_id, is_read, created_at)
            ) ENGINE=InnoDB");
        }

        // tournament_players.registration_status
        try { $pdo->query("SELECT registration_status FROM tournament_players LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE tournament_players ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER seed");
        }

        // matches: nullable player IDs + guest columns
        try { $pdo->query("SELECT player1_guest_id FROM matches LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE matches MODIFY COLUMN player1_id INT NULL, MODIFY COLUMN player2_id INT NULL");
            $pdo->exec("ALTER TABLE matches
                ADD COLUMN player1_guest_id INT NULL AFTER player2_id,
                ADD COLUMN player2_guest_id INT NULL AFTER player1_guest_id,
                ADD COLUMN winner_guest_id INT NULL AFTER winner_id");
            foreach (['fk_match_p1_guest'=>'player1_guest_id CASCADE','fk_match_p2_guest'=>'player2_guest_id CASCADE','fk_match_winner_guest'=>'winner_guest_id SET NULL'] as $name=>$def) {
                [$col, $action] = explode(' ', $def, 2);
                try { $pdo->exec("ALTER TABLE matches ADD CONSTRAINT $name FOREIGN KEY ($col) REFERENCES tournament_guests(id) ON DELETE $action"); }
                catch (PDOException $fk) { /* already exists */ }
            }
        }

        // users: auth_method + verification columns
        try { $pdo->query("SELECT auth_method FROM users LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE users ADD COLUMN auth_method ENUM('local','google') DEFAULT 'local' AFTER is_active");
            $pdo->exec("UPDATE users SET auth_method = 'local'");
        }
        try { $pdo->query("SELECT is_verified FROM users LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 1 AFTER auth_method");
            $pdo->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL AFTER is_verified");
            $pdo->exec("ALTER TABLE users ADD COLUMN token_expires DATETIME NULL AFTER verification_token");
            $pdo->exec("UPDATE users SET is_verified = 1 WHERE is_verified IS NULL");
        }

        // matches: set_scores column
        try { $pdo->query("SELECT set_scores FROM matches LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE matches ADD COLUMN set_scores VARCHAR(255) NULL AFTER player2_score");
        }

        // tournaments: proofs_delete_after column (scheduled proof cleanup)
        try { $pdo->query("SELECT proofs_delete_after FROM tournaments LIMIT 1"); }
        catch (PDOException $e) {
            $pdo->exec("ALTER TABLE tournaments ADD COLUMN proofs_delete_after DATETIME NULL DEFAULT NULL AFTER updated_at");
        }

        // Migrate notification link paths from old /table-tennis-system/ prefix to /TournamentHQ/
        try {
            $pdo->exec("UPDATE notifications SET link = REPLACE(link, '/table-tennis-system/', '/TournamentHQ/') WHERE link LIKE '%/table-tennis-system/%'");
        } catch (PDOException $e) {
            // Ignore if table doesn't exist yet
        }

        // Modify users role enum to support 'umpire'
        try {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','organizer','player','umpire') NOT NULL DEFAULT 'player'");
        } catch (PDOException $e) {
            // Ignore if already modified or fails
        }

        // Add tournament_id column to users table
        try {
            $pdo->query("SELECT tournament_id FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN tournament_id INT NULL AFTER role");
                $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE");
            } catch (PDOException $ex) {}
        }

        // user_profiles table (display_name storage)
        try {
            $pdo->query("SELECT user_id FROM user_profiles LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_profiles (
                    user_id INT PRIMARY KEY,
                    display_name VARCHAR(100) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB");
                $pdo->exec("INSERT IGNORE INTO user_profiles (user_id, display_name) SELECT id, username FROM users");
            } catch (PDOException $ex) {
                error_log("user_profiles migration failed: " . $ex->getMessage());
            }
        }
    }

    /** Default login: username admin, password admin */
    private static function ensureDefaultAdminUser(PDO $pdo): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $pdo->query("SELECT id FROM users LIMIT 1");
        } catch (PDOException $ex) {
            return;
        }

        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $adminEmail = 'admin@ttms.com';
        $adminUsername = 'admin';

        $stmt = $pdo->prepare("SELECT id, password, username, email FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$adminUsername]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $stmt = $pdo->prepare("SELECT id, password, username, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$adminEmail]);
            $admin = $stmt->fetch();
        }

        if ($admin) {
            $needsPassword = !password_verify('admin', $admin['password']);
            $needsUsername = ($admin['username'] ?? '') !== $adminUsername;
            $needsEmail = ($admin['email'] ?? '') !== $adminEmail;

            if ($needsUsername) {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                $check->execute([$adminUsername, (int) $admin['id']]);
                if ($check->fetch()) {
                    $needsUsername = false;
                }
            }

            if ($needsPassword || $needsUsername || $needsEmail) {
                $sql = "UPDATE users SET role = 'admin', is_active = 1";
                $params = [];
                if ($needsPassword) {
                    $sql .= ", password = ?";
                    $params[] = $hash;
                }
                if ($needsUsername) {
                    $sql .= ", username = ?";
                    $params[] = $adminUsername;
                }
                if ($needsEmail) {
                    $sql .= ", email = ?";
                    $params[] = $adminEmail;
                }
                $sql .= " WHERE id = ?";
                $params[] = (int) $admin['id'];
                $pdo->prepare($sql)->execute($params);
            } else {
                $pdo->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE id = ?")
                    ->execute([(int) $admin['id']]);
            }
            return;
        }

        try {
            $pdo->prepare(
                "INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, 'admin', 1)"
            )->execute([$adminUsername, $hash, $adminEmail]);
        } catch (PDOException $ex) {
            if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) {
                throw $ex;
            }
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$adminEmail, $adminUsername]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare(
                    "UPDATE users SET username = ?, password = ?, email = ?, role = 'admin', is_active = 1 WHERE id = ?"
                )->execute([$adminUsername, $hash, $adminEmail, (int) $row['id']]);
            }
        }
    }
}

function db(): PDO {
    return Database::getConnection();
}
