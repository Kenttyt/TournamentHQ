-- Add registration fee field (run once)
ALTER TABLE tournaments
    ADD COLUMN registration_fee VARCHAR(100) DEFAULT NULL AFTER prize_4th;
