-- Theft Track & Reporting — Full database setup for XAMPP / phpMyAdmin
-- Run in phpMyAdmin or: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS theft_track_reporting_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE theft_track_reporting_db;

-- Registered users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Theft reports
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tracking_id VARCHAR(20) NOT NULL UNIQUE,
  user_id INT NULL,
  fullname VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  email VARCHAR(100) NULL,
  item VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(200) NOT NULL,
  status ENUM('Pending', 'Under Investigation', 'Resolved') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tracking_id (tracking_id),
  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_user_id (user_id),
  CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Admin accounts
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Status changes & investigation notes
CREATE TABLE IF NOT EXISTS case_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  update_type ENUM('status', 'note') NOT NULL,
  status_value VARCHAR(50) NULL,
  note_text TEXT NULL,
  created_by VARCHAR(100) NOT NULL DEFAULT 'Admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_report_id (report_id),
  CONSTRAINT fk_case_updates_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Suspects published on the public Fraud Alerts page (managed in admin)
CREATE TABLE IF NOT EXISTS suspects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alias VARCHAR(150) NOT NULL,
  case_type VARCHAR(100) NOT NULL DEFAULT 'Theft / Fraud',
  last_seen VARCHAR(200) NOT NULL,
  description TEXT NULL,
  photo_path VARCHAR(255) NULL,
  risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  linked_tracking_id VARCHAR(20) NULL,
  created_by VARCHAR(100) NOT NULL DEFAULT 'Admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_risk (risk_level)
) ENGINE=InnoDB;

-- Default admin: visit backend/seed_admin.php once (admin / admin123)
