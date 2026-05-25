-- Registration approval workflow
ALTER TABLE tournament_players
    ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER seed;

ALTER TABLE tournament_guests
    ADD COLUMN registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER last_name;
