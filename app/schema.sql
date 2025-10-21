-- HabitTracker Database Schema
-- MySQL database schema for habit tracking application with encrypted habit names
--
-- SECURITY NOTES:
-- - Habit names are stored encrypted (AES-256-CBC) to protect against database breaches
-- - Passwords are hashed with bcrypt (handled in PHP, not stored in plain text)
-- - Each encrypted habit name includes its own IV (initialization vector)
-- - Encryption is transparent to users - no UX changes required

-- Create database (optional - you may want to create this manually)
-- CREATE DATABASE IF NOT EXISTS habittracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE habittracker;

-- Users table
-- Stores user accounts with email-based authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_hash VARCHAR(32) NOT NULL UNIQUE,  -- MD5 hash used for session identification
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_hash (user_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Habits table
-- Stores user habits with ENCRYPTED names for privacy protection
--
-- ENCRYPTION DETAILS:
-- - name_encrypted: Base64-encoded encrypted habit name with IV prepended
-- - Format: base64(IV + encrypted_name) where IV is 16 bytes
-- - Each habit has unique encryption even if names are identical
-- - Decryption requires master key + user_id (user-specific encryption)
CREATE TABLE IF NOT EXISTS habits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name_encrypted TEXT NOT NULL,  -- Encrypted habit name (base64 encoded with IV)
    color VARCHAR(20) NOT NULL,    -- Color code (e.g., 'red', 'blue', '#FF5733')
    sort_order INT DEFAULT 0,      -- Order for displaying habits (0-5 for 6 habits max)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_user_sort (user_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Habit entries table
-- Stores completion records for each habit on specific dates
--
-- SECURITY NOTE: Dates are NOT encrypted because:
-- - Dates alone reveal no sensitive information
-- - Encryption would prevent efficient querying/sorting
-- - Database indexes on dates enable fast streak calculations
-- - Without the habit name, knowing completion dates is meaningless
CREATE TABLE IF NOT EXISTS habit_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    habit_id INT NOT NULL,
    date DATE NOT NULL,           -- Completion date (NOT encrypted for query efficiency)
    completed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE,
    UNIQUE KEY unique_habit_date (habit_id, date),  -- Prevent duplicate entries
    INDEX idx_habit_id (habit_id),
    INDEX idx_date (date),
    INDEX idx_habit_date (habit_id, date)  -- Composite index for efficient queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login attempts tracking
-- Used for rate limiting and brute force protection
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    attempt_count INT DEFAULT 1,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_locked (email, locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pending registrations (email verification flow)
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification codes (for email verification and password reset)
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    code_type ENUM('registration', 'password_reset', 'reset_token') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    INDEX idx_email_type (email, code_type),
    INDEX idx_expires (expires_at),
    INDEX idx_code (code, code_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up expired records (optional - can be run periodically via cron)
-- DELETE FROM pending_registrations WHERE expires_at < NOW();
-- DELETE FROM verification_codes WHERE expires_at < NOW();
-- DELETE FROM login_attempts WHERE locked_until IS NOT NULL AND locked_until < NOW();
