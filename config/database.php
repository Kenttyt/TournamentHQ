<?php
/**
 * Database Configuration
 * Table Tennis Tournament Management System
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'tt_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

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
                
                // Self-healing migration: Add 'category' column if it does not exist
                try {
                    self::$instance->query("SELECT category FROM tournaments LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec("ALTER TABLE tournaments ADD COLUMN category VARCHAR(100) DEFAULT 'Open Singles' AFTER name");
                }
                
                // Self-healing migration: Add 'tournament_guests' table if it does not exist
                try {
                    self::$instance->query("SELECT id FROM tournament_guests LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS tournament_guests (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        tournament_id INT NOT NULL,
                        registered_by_player_id INT NOT NULL,
                        first_name VARCHAR(50) NOT NULL,
                        last_name VARCHAR(50) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
                        FOREIGN KEY (registered_by_player_id) REFERENCES players(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB");
                }
                
                // Self-healing migration: Make 'registered_by_player_id' nullable in tournament_guests
                try {
                    self::$instance->exec("ALTER TABLE tournament_guests MODIFY COLUMN registered_by_player_id INT NULL");
                } catch (PDOException $ex) {
                    // Ignore if already nullable
                }

                // Self-healing migration: payment proof on guest registrations
                try {
                    self::$instance->query('SELECT payment_proof_path FROM tournament_guests LIMIT 1');
                } catch (PDOException $ex) {
                    self::$instance->exec(
                        "ALTER TABLE tournament_guests
                         ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER registration_status,
                         ADD COLUMN payment_proof_original_name VARCHAR(200) NULL AFTER payment_proof_path"
                    );
                }

                // Self-healing migration: password reset tokens
                try {
                    self::$instance->query('SELECT id FROM password_reset_tokens LIMIT 1');
                } catch (PDOException $ex) {
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
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

                // Self-healing migration: in-app notifications
                try {
                    self::$instance->query("SELECT id FROM notifications LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec("CREATE TABLE IF NOT EXISTS notifications (
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

                // Self-healing migration: registration approval status
                try {
                    self::$instance->query("SELECT registration_status FROM tournament_players LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec(
                        "ALTER TABLE tournament_players
                         ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER seed"
                    );
                }
                try {
                    self::$instance->query("SELECT registration_status FROM tournament_guests LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec(
                        "ALTER TABLE tournament_guests
                         ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER last_name"
                    );
                }

                // Self-healing migration: Guest participants in matches
                try {
                    self::$instance->query("SELECT player1_guest_id FROM matches LIMIT 1");
                } catch (PDOException $ex) {
                    self::$instance->exec("ALTER TABLE matches
                        MODIFY COLUMN player1_id INT NULL,
                        MODIFY COLUMN player2_id INT NULL");
                    self::$instance->exec("ALTER TABLE matches
                        ADD COLUMN player1_guest_id INT NULL AFTER player2_id,
                        ADD COLUMN player2_guest_id INT NULL AFTER player1_guest_id,
                        ADD COLUMN winner_guest_id INT NULL AFTER winner_id");
                    try {
                        self::$instance->exec("ALTER TABLE matches
                            ADD CONSTRAINT fk_match_p1_guest FOREIGN KEY (player1_guest_id) REFERENCES tournament_guests(id) ON DELETE CASCADE");
                    } catch (PDOException $fk) { /* may already exist */ }
                    try {
                        self::$instance->exec("ALTER TABLE matches
                            ADD CONSTRAINT fk_match_p2_guest FOREIGN KEY (player2_guest_id) REFERENCES tournament_guests(id) ON DELETE CASCADE");
                    } catch (PDOException $fk) { /* may already exist */ }
                    try {
                        self::$instance->exec("ALTER TABLE matches
                            ADD CONSTRAINT fk_match_winner_guest FOREIGN KEY (winner_guest_id) REFERENCES tournament_guests(id) ON DELETE SET NULL");
                    } catch (PDOException $fk) { /* may already exist */ }
                }

                self::ensureDefaultAdminUser(self::$instance);
            } catch (PDOException $e) {
                die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$instance;
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
