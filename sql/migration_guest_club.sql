-- Guest registrations inherit club/place from the player who submitted them
ALTER TABLE tournament_guests
    ADD COLUMN club VARCHAR(100) NULL AFTER last_name,
    ADD COLUMN nationality VARCHAR(60) NULL AFTER club;

UPDATE tournament_guests tg
INNER JOIN players p ON tg.registered_by_player_id = p.id
SET tg.club = p.club, tg.nationality = p.nationality
WHERE tg.registered_by_player_id IS NOT NULL;
