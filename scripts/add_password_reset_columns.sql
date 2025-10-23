-- Add password reset columns to users table if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_expiry DATETIME NULL;

-- Create index for faster token lookups
CREATE INDEX IF NOT EXISTS idx_password_reset_token ON users(password_reset_token);
