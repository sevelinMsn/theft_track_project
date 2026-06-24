-- Run in phpMyAdmin if you already have thefttrack_db (adds admin-managed suspects)
USE thefttrack_db;

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
