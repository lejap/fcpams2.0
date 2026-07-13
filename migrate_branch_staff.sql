-- ============================================================
-- Migration: Branch-based staff assignment & complaint visibility
-- Run this on existing databases to add branch_id to users
-- and is_ho to branches (if not already present).
-- ============================================================

-- 1. Add is_ho column to branches (safe re-run)
ALTER TABLE branches ADD COLUMN IF NOT EXISTS is_ho TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Ensure users.branch_id exists (already in schema, but safe to re-run)
-- If the column doesn't exist yet on production:
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL;
-- ALTER TABLE users ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

-- NOTE: The users table already has branch_id in database.sql.
-- This migration is here for documentation. If your production DB
-- is missing branch_id, uncomment the lines above.
