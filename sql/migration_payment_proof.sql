-- Payment proof for guest registrations
ALTER TABLE tournament_guests
    ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER registration_status,
    ADD COLUMN payment_proof_original_name VARCHAR(200) NULL AFTER payment_proof_path;
