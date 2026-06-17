-- Migration: add email verification fields to users
ALTER TABLE users
  ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN verification_token VARCHAR(128) DEFAULT NULL,
  ADD COLUMN token_expires DATETIME DEFAULT NULL;
