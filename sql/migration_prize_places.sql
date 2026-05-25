-- Add Champion / 2nd / 3rd / 4th place prize fields (run once)
ALTER TABLE tournaments
    ADD COLUMN prize_champion VARCHAR(100) DEFAULT NULL AFTER prize_pool,
    ADD COLUMN prize_2nd VARCHAR(100) DEFAULT NULL AFTER prize_champion,
    ADD COLUMN prize_3rd VARCHAR(100) DEFAULT NULL AFTER prize_2nd,
    ADD COLUMN prize_4th VARCHAR(100) DEFAULT NULL AFTER prize_3rd;
