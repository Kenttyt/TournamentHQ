-- ============================================================
-- Table Tennis Tournament Management System
-- Database Schema + Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS tt_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tt_system;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin','organizer','player') NOT NULL DEFAULT 'player',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PLAYERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other') DEFAULT 'male',
    club VARCHAR(100),
    nationality VARCHAR(60),
    profile_image VARCHAR(255) DEFAULT 'default_avatar.png',
    points INT NOT NULL DEFAULT 0,
    wins INT NOT NULL DEFAULT 0,
    losses INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TOURNAMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Open Singles',
    description TEXT,
    format ENUM('single_elimination','round_robin','double_elimination') NOT NULL DEFAULT 'single_elimination',
    status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    max_players INT NOT NULL DEFAULT 16,
    start_date DATE NOT NULL,
    end_date DATE,
    venue VARCHAR(150),
    prize_pool VARCHAR(100),
    prize_champion VARCHAR(100),
    prize_2nd VARCHAR(100),
    prize_3rd VARCHAR(100),
    prize_4th VARCHAR(100),
    registration_fee VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TOURNAMENT PLAYERS (Registration)
-- ============================================================
CREATE TABLE IF NOT EXISTS tournament_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    seed INT DEFAULT NULL,
    UNIQUE KEY unique_registration (tournament_id, player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- MATCHES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    player1_id INT NOT NULL,
    player2_id INT NOT NULL,
    winner_id INT DEFAULT NULL,
    player1_score INT DEFAULT 0,
    player2_score INT DEFAULT 0,
    round INT NOT NULL DEFAULT 1,
    round_name VARCHAR(50) DEFAULT 'Round 1',
    match_date DATETIME,
    table_number INT DEFAULT 1,
    status ENUM('scheduled','ongoing','completed','walkover') NOT NULL DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (player1_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES players(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- RANKINGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL UNIQUE,
    global_rank INT DEFAULT NULL,
    total_points INT NOT NULL DEFAULT 0,
    tournaments_played INT NOT NULL DEFAULT 0,
    matches_played INT NOT NULL DEFAULT 0,
    matches_won INT NOT NULL DEFAULT 0,
    matches_lost INT NOT NULL DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin user (username: admin, password: admin)
INSERT INTO users (username, password, email, role) VALUES
('admin', '$2y$10$3PuDlbwZGUBYEwy6Z9W.KedsOf1SXv0KcaHcmxlPSFnZ3QGmyiseC', 'admin@ttms.com', 'admin'),
('organizer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer1@ttms.com', 'organizer'),
('player1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player1@ttms.com', 'player'),
('player2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player2@ttms.com', 'player'),
('player3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player3@ttms.com', 'player'),
('player4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player4@ttms.com', 'player'),
('player5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player5@ttms.com', 'player'),
('player6', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'player6@ttms.com', 'player');

-- Player profiles
INSERT INTO players (user_id, first_name, last_name, date_of_birth, gender, club, nationality, points, wins, losses) VALUES
(3, 'Marco', 'Santos',   '1998-03-15', 'male',   'Manila Smashers',    'Philippines', 1200, 15, 4),
(4, 'Li',    'Wei',      '2000-07-22', 'male',   'Dragon Table Club',  'China',       1450, 22, 3),
(5, 'Anna',  'Reyes',    '1999-11-01', 'female', 'Pasig Paddlers',     'Philippines', 980,  10, 8),
(6, 'Kenji', 'Tanaka',   '1997-05-30', 'male',   'Tokyo Spin Masters', 'Japan',       1600, 28, 5),
(7, 'Sofia', 'Mendoza',  '2001-09-14', 'female', 'Cebu Champs',        'Philippines', 870,  8,  9),
(8, 'Ravi',  'Krishnan', '1996-12-05', 'male',   'Mumbai Spinners',    'India',       1320, 18, 6);

-- Tournaments
INSERT INTO tournaments (organizer_id, name, category, description, format, status, max_players, start_date, end_date, venue, prize_pool) VALUES
(2, 'National Open 2026', 'Men\'s Singles', 'Annual national table tennis open championship.', 'single_elimination', 'ongoing',  16, '2026-05-20', '2026-05-26', 'Rizal Memorial Sports Complex, Manila', '₱50,000'),
(2, 'Metro League Season 1', 'Women\'s Singles', 'City-wide round-robin league for all levels.', 'round_robin', 'upcoming', 8,  '2026-06-01', '2026-06-30', 'SM Mall of Asia Arena', '₱20,000'),
(2, 'Club Championship 2025', 'Mixed Doubles', 'Ended club-level championship.', 'single_elimination', 'completed', 8,  '2025-11-10', '2025-11-15', 'Makati Sports Hub', '₱10,000');

-- Tournament registrations
INSERT INTO tournament_players (tournament_id, player_id, seed) VALUES
(1, 1, 3), (1, 2, 1), (1, 3, 5), (1, 4, 2), (1, 5, 6), (1, 6, 4),
(2, 1, 2), (2, 2, 1), (2, 3, 3), (2, 4, 4), (2, 5, 6), (2, 6, 5),
(3, 1, 2), (3, 2, 1), (3, 4, 3), (3, 6, 4);

-- Matches for National Open 2026
INSERT INTO matches (tournament_id, player1_id, player2_id, winner_id, player1_score, player2_score, round, round_name, match_date, table_number, status) VALUES
(1, 1, 3, 1, 11, 7,  1, 'Round of 16', '2026-05-20 09:00:00', 1, 'completed'),
(1, 2, 5, 2, 11, 4,  1, 'Round of 16', '2026-05-20 10:00:00', 2, 'completed'),
(1, 4, 6, 4, 11, 9,  1, 'Round of 16', '2026-05-21 09:00:00', 1, 'completed'),
(1, 1, 2, 2, 8,  11, 2, 'Quarterfinal','2026-05-22 14:00:00', 1, 'completed'),
(1, 4, 2, NULL, 0, 0, 3, 'Semifinal',  '2026-05-25 15:00:00', 1, 'scheduled');

-- Completed tournament matches
INSERT INTO matches (tournament_id, player1_id, player2_id, winner_id, player1_score, player2_score, round, round_name, match_date, table_number, status) VALUES
(3, 1, 4, 4, 9,  11, 1, 'Semifinal', '2025-11-10 10:00:00', 1, 'completed'),
(3, 2, 6, 2, 11, 8,  1, 'Semifinal', '2025-11-10 12:00:00', 2, 'completed'),
(3, 4, 2, 2, 7,  11, 2, 'Final',     '2025-11-15 15:00:00', 1, 'completed');

-- Rankings
INSERT INTO rankings (player_id, global_rank, total_points, tournaments_played, matches_played, matches_won, matches_lost, win_rate) VALUES
(1, 3, 1200, 2, 5, 3, 2, 60.00),
(2, 1, 1450, 3, 8, 7, 1, 87.50),
(3, 5, 980,  2, 3, 1, 2, 33.33),
(4, 2, 1600, 2, 5, 4, 1, 80.00),
(5, 6, 870,  2, 2, 0, 2, 0.00),
(6, 4, 1320, 2, 4, 2, 2, 50.00);
