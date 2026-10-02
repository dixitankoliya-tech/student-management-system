-- ==========================================================
-- SMS 2.0: Authentication System - Users Table
-- ==========================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account
-- Username: GLS_ADMIN
-- Password: ADMIN@GLSUNIVERSITY (bcrypt hashed)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('GLS_ADMIN', 'admin@glsuniversity.edu', '$2y$10$QO0k2R5dE5vW71f3K2jA2u2v4v9KqW1mO5f4N3P3Q3r2H7J9g8s', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE password_hash='$2y$10$QO0k2R5dE5vW71f3K2jA2u2v4v9KqW1mO5f4N3P3Q3r2H7J9g8s';
