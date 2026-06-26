-- Add is_ho column to branches table to distinguish Head Office branches from regular branches
ALTER TABLE branches ADD COLUMN IF NOT EXISTS is_ho TINYINT(1) NOT NULL DEFAULT 0;
