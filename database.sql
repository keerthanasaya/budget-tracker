-- ============================================
-- Budget Tracker Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS budget_tracker;
USE budget_tracker;

-- ---------- USERS ----------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------- CATEGORIES ----------
-- Each user has their own set of categories (income/expense)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    type ENUM('income', 'expense') NOT NULL DEFAULT 'expense',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_per_user (user_id, name, type)
);

-- ---------- TRANSACTIONS ----------
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    type ENUM('income', 'expense') NOT NULL DEFAULT 'expense',
    amount DECIMAL(12, 2) NOT NULL,
    description VARCHAR(255),
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ---------- SEED DEFAULT CATEGORIES (optional helper) ----------
-- Run this manually after a user registers, replacing <USER_ID>,
-- or let the app auto-create defaults on first login (handled in PHP).
-- INSERT INTO categories (user_id, name, type) VALUES
-- (<USER_ID>, 'Salary', 'income'),
-- (<USER_ID>, 'Freelance', 'income'),
-- (<USER_ID>, 'Food', 'expense'),
-- (<USER_ID>, 'Rent', 'expense'),
-- (<USER_ID>, 'Transport', 'expense'),
-- (<USER_ID>, 'Utilities', 'expense'),
-- (<USER_ID>, 'Entertainment', 'expense'),
-- (<USER_ID>, 'Other', 'expense');
