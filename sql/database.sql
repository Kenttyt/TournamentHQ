-- ============================================================
-- Table Tennis Tournament Management System
-- Database Schema + Seed Data
-- Last updated: 2026-06-14
-- ============================================================

CREATE DATABASE IF NOT EXISTS tt_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tt_system;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50)  NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    email               VARCHAR(100) NOT NULL UNIQUE,
    role                ENUM('admin','organizer','player') NOT NULL DEFAULT 'player',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    auth_method         ENUM('local','google') DEFAULT 'local',
    is_verified         TINYINT(1)   DEFAULT 1,
    verification_token  VARCHAR(255) NULL,
    token_expires       DATETIME     NULL,
    created_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- USER PROFILES TABLE (display_name etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    display_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- PLAYERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS players (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    date_of_birth   DATE,
    gender          ENUM('male','female','other') DEFAULT 'male',
    club            VARCHAR(100),
    nationality     VARCHAR(60), -- Represents the player's Place
    profile_image   VARCHAR(255) DEFAULT 'default_avatar.png',
    points          INT          NOT NULL DEFAULT 0,
    wins            INT          NOT NULL DEFAULT 0,
    losses          INT          NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TOURNAMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS tournaments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id     INT          NOT NULL,
    name             VARCHAR(150) NOT NULL,
    category         VARCHAR(100) NOT NULL DEFAULT 'Open Singles',
    description      TEXT,
    format           ENUM('single_elimination','round_robin','double_elimination') NOT NULL DEFAULT 'single_elimination',
    status           ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    max_players      INT          NOT NULL DEFAULT 16,
    start_date       DATE         NOT NULL,
    end_date         DATE,
    venue            VARCHAR(150),
    prize_pool       VARCHAR(100),
    prize_champion   VARCHAR(100),
    prize_2nd        VARCHAR(100),
    prize_3rd        VARCHAR(100),
    prize_4th        VARCHAR(100),
    registration_fee VARCHAR(100),
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    proofs_delete_after  DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TOURNAMENT PLAYERS (Registered accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS tournament_players (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id       INT NOT NULL,
    player_id           INT NOT NULL,
    registered_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    seed                INT DEFAULT NULL,
    registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    UNIQUE KEY unique_registration (tournament_id, player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id)     REFERENCES players(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TOURNAMENT GUESTS (Walk-in / non-account players)
-- ============================================================
CREATE TABLE IF NOT EXISTS tournament_guests (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id               INT          NOT NULL,
    registered_by_player_id     INT          NULL,
    first_name                  VARCHAR(50)  NOT NULL,
    last_name                   VARCHAR(50)  NOT NULL,
    club                        VARCHAR(100) NULL,
    nationality                 VARCHAR(60)  NULL,
    registration_status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    payment_proof_path          VARCHAR(255) NULL,
    payment_proof_original_name VARCHAR(200) NULL,
    created_at                  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id)           REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (registered_by_player_id) REFERENCES players(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- MATCHES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS matches (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id   INT          NOT NULL,
    player1_id      INT          NULL,
    player2_id      INT          NULL,
    player1_guest_id INT         NULL,
    player2_guest_id INT         NULL,
    winner_id       INT          DEFAULT NULL,
    winner_guest_id INT          NULL,
    player1_score   INT          DEFAULT 0,
    player2_score   INT          DEFAULT 0,
    set_scores      VARCHAR(255) DEFAULT NULL,
    round           INT          NOT NULL DEFAULT 1,
    round_name      VARCHAR(50)  DEFAULT 'Round 1',
    match_date      DATETIME,
    table_number    INT          DEFAULT 1,
    status          ENUM('scheduled','ongoing','completed','walkover') NOT NULL DEFAULT 'scheduled',
    notes           TEXT,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id)    REFERENCES tournaments(id)       ON DELETE CASCADE,
    FOREIGN KEY (player1_id)       REFERENCES players(id)           ON DELETE CASCADE,
    FOREIGN KEY (player2_id)       REFERENCES players(id)           ON DELETE CASCADE,
    FOREIGN KEY (winner_id)        REFERENCES players(id)           ON DELETE SET NULL,
    FOREIGN KEY (player1_guest_id) REFERENCES tournament_guests(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_guest_id) REFERENCES tournament_guests(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_guest_id)  REFERENCES tournament_guests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- PASSWORD RESET TOKENS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT      NOT NULL,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token (token_hash),
    INDEX idx_reset_user  (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- IN-APP NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(120) NOT NULL,
    message    TEXT         NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Admin User
-- admin    password: admin
INSERT INTO users (username, password, email, role, is_active, auth_method, is_verified) VALUES
('admin', '$2y$10$3PuDlbwZGUBYEwy6Z9W.KedsOf1SXv0KcaHcmxlPSFnZ3QGmyiseC', 'admin@ttms.com', 'admin', 1, 'local', 1);

