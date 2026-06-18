-- FCPAMS Complete Database Schema
-- Optimized for XAMPP / MariaDB
-- Updated: Full feature parity with Node.js version

CREATE DATABASE IF NOT EXISTS fcpams;
USE fcpams;

-- 1. Branches Table
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Dropdown Options Table (for Inquiries / Suggestions / Requests)
CREATE TABLE IF NOT EXISTS dropdown_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('INQUIRY', 'SUGGESTION', 'REQUEST', 'COMPLAINT_DETAIL', 'TRANSACTION_TYPE') NOT NULL,
    label VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Users Table (Admin & Staff only)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'STAFF') DEFAULT 'STAFF',
    branch_id INT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

-- 4. Citizens Table (self-registered portal users)
CREATE TABLE IF NOT EXISTS citizens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    branch VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Tickets Table (Inquiries, Suggestions, Requests from citizens)
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_no VARCHAR(50) UNIQUE,
    user_name VARCHAR(255) NOT NULL,
    user_phone VARCHAR(20) NOT NULL,
    user_email VARCHAR(255),
    user_branch VARCHAR(100),
    type ENUM('INQUIRY', 'SUGGESTION', 'REQUEST'),
    option_id INT,
    message TEXT,
    status ENUM('OPEN', 'RESOLVED', 'CLOSED', 'SPAM') DEFAULT 'OPEN',
    admin_remark TEXT,
    resolved_at TIMESTAMP NULL,
    resolved_by_name VARCHAR(255),
    confirmed_at TIMESTAMP NULL,
    confirmed_by_name VARCHAR(255),
    confirm_remark TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (option_id) REFERENCES dropdown_options(id) ON DELETE SET NULL
);

-- 6. Surveys Table
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Questions Table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    text VARCHAR(500) NOT NULL,
    type ENUM('TEXT', 'CHOICE', 'RATING', 'MULTI_SELECT') NOT NULL,
    options TEXT, -- JSON string for CHOICE questions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);

-- 8. Survey Responses Table
CREATE TABLE IF NOT EXISTS survey_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    survey_id INT NOT NULL,
    user_name VARCHAR(255),
    user_phone VARCHAR(20),
    user_email VARCHAR(255),
    user_branch VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);

-- 9. Answers Table
CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    response_id INT NOT NULL,
    question_id INT NOT NULL,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- 10. Complaints Table (separate from tickets - full complaint form)
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(255) NOT NULL,
    user_phone VARCHAR(20) NOT NULL,
    user_email VARCHAR(255),
    user_branch VARCHAR(100),
    complaint_details VARCHAR(500),
    transaction_type VARCHAR(255),
    description TEXT,
    raised_previously BOOLEAN DEFAULT FALSE,
    previous_details TEXT,
    desired_resolution TEXT,
    has_documents BOOLEAN DEFAULT FALSE,
    document_path VARCHAR(255),
    status ENUM('OPEN', 'RESOLVED', 'CLOSED', 'SPAM') DEFAULT 'OPEN',
    complexity ENUM('SIMPLE', 'COMPLEX') DEFAULT 'SIMPLE',
    admin_remark TEXT,
    resolved_at TIMESTAMP NULL,
    resolved_by_name VARCHAR(255),
    confirmed_at TIMESTAMP NULL,
    confirmed_by_name VARCHAR(255),
    confirm_remark TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Add Official FCPAMS Branches
INSERT IGNORE INTO branches (name) VALUES 
    ('Main Branch'),
    ('Bansalan Branch'),
    ('Magsaysay Branch'),
    ('Davao Branch'),
    ('Digos Branch'),
    ('Kidapawan Branch'),
    ('Koronadal Branch'),
    ('General Santos Branch');

-- Add Dropdown Options (INQUIRY)
INSERT IGNORE INTO dropdown_options (type, label) VALUES 
    ('INQUIRY', 'Billing & Payments'),
    ('INQUIRY', 'Account Status'),
    ('INQUIRY', 'Loan Inquiry'),
    ('INQUIRY', 'Technical Support'),
    ('INQUIRY', 'Product Availability'),
    ('INQUIRY', 'Membership & Registration');

-- Add Dropdown Options (SUGGESTION)
INSERT IGNORE INTO dropdown_options (type, label) VALUES 
    ('SUGGESTION', 'Mobile App Improvement'),
    ('SUGGESTION', 'Customer Service Experience'),
    ('SUGGESTION', 'New Feature Request'),
    ('SUGGESTION', 'Branch Cleanliness'),
    ('SUGGESTION', 'Staff Attitude'),
    ('SUGGESTION', 'Process Improvement');

-- Add Dropdown Options (REQUEST)
INSERT IGNORE INTO dropdown_options (type, label) VALUES 
    ('REQUEST', 'Certificate of Membership'),
    ('REQUEST', 'Statement of Account'),
    ('REQUEST', 'Loan Clearance'),
    ('REQUEST', 'Passbook Replacement'),
    ('REQUEST', 'ATM Card Request'),
    ('REQUEST', 'Other Document');

-- Add Dropdown Options (COMPLAINT_DETAIL)
INSERT IGNORE INTO dropdown_options (type, label) VALUES 
    ('COMPLAINT_DETAIL', 'SERVICE ISSUE'),
    ('COMPLAINT_DETAIL', 'PRODUCT QUALITY'),
    ('COMPLAINT_DETAIL', 'STAFF BEHAVIOR'),
    ('COMPLAINT_DETAIL', 'POLICY/PROCEDURE'),
    ('COMPLAINT_DETAIL', 'PHYSICAL INFRASTRUCTURE'),
    ('COMPLAINT_DETAIL', 'OTHER');

-- Add Dropdown Options (TRANSACTION_TYPE)
INSERT IGNORE INTO dropdown_options (type, label) VALUES 
    ('TRANSACTION_TYPE', 'DEPOSIT'),
    ('TRANSACTION_TYPE', 'PAYMENT'),
    ('TRANSACTION_TYPE', 'WITHDRAWAL'),
    ('TRANSACTION_TYPE', 'LOAN APPLICATION'),
    ('TRANSACTION_TYPE', 'LOAN PROCESSING'),
    ('TRANSACTION_TYPE', 'OPENING SAVINGS ACCOUNT'),
    ('TRANSACTION_TYPE', 'MEMBERSHIP APPLICATION'),
    ('TRANSACTION_TYPE', 'GENERAL INQUIRY'),
    ('TRANSACTION_TYPE', 'INSURANCE RELATED'),
    ('TRANSACTION_TYPE', 'OTHERS');

-- Add Default Admin Account (Password: password)
-- bcrypt hash for "password"
INSERT IGNORE INTO users (name, email, password_hash, role, is_approved) 
VALUES ('System Admin', 'admin@fcpams.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', TRUE);
