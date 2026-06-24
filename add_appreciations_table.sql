-- ============================================================
-- FCPAMS: Appreciation / Commendation Table
-- Run this against your fcpams database to add the new feature
-- ============================================================

USE fcpams;

-- Create the appreciations table
CREATE TABLE IF NOT EXISTS appreciations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_name   VARCHAR(255)  NOT NULL,
    user_phone  VARCHAR(20)   NOT NULL,
    user_email  VARCHAR(255),
    user_branch VARCHAR(100),
    staff_name  VARCHAR(255)  NOT NULL,
    appreciation VARCHAR(50)  NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
