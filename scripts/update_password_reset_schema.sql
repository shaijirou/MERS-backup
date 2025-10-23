-- Update users table to use reset codes instead of tokens
ALTER TABLE users MODIFY COLUMN password_reset_token VARCHAR(10) NULL;
ALTER TABLE users RENAME COLUMN password_reset_token TO password_reset_code;
-- password_reset_expiry column already exists
