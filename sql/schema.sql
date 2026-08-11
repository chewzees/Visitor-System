CREATE DATABASE IF NOT EXISTS visitor_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE visitor_mgmt;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin','Security','Staff') NOT NULL DEFAULT 'Staff',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    last_login DATETIME NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NULL,
    id_passport VARCHAR(80) NOT NULL,
    phone VARCHAR(40) NULL,
    company VARCHAR(120) NULL,
    host_name VARCHAR(120) NOT NULL,
    host_email VARCHAR(160) NULL,
    purpose VARCHAR(255) NULL,
    notes TEXT NULL,
    visit_date DATE NOT NULL,
    status ENUM('Pending','Approved','Checked In','Checked Out','Rejected') NOT NULL DEFAULT 'Pending',
    qr_token VARCHAR(64) NOT NULL UNIQUE,
    photo_path VARCHAR(255) NULL,
    checked_in_at DATETIME NULL,
    checked_out_at DATETIME NULL,
    expected_out_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_visit_date (visit_date),
    INDEX idx_status (status),
    INDEX idx_id_passport (id_passport),
    INDEX idx_host_name (host_name),
    CONSTRAINT fk_visitors_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blacklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    id_passport VARCHAR(80) NOT NULL,
    reason TEXT NOT NULL,
    added_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blacklist_id (id_passport),
    CONSTRAINT fk_blacklist_added_by FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(60) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_action (action),
    INDEX idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT UNSIGNED NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'email',
    recipient VARCHAR(160) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_visitor (visitor_id)
) ENGINE=InnoDB;

INSERT INTO users (username, full_name, email, password_hash, role, status) VALUES
('admin', 'System Administrator', 'admin@vms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active'),
('security1', 'Security Officer', 'security@vms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Security', 'Active'),
('staff1', 'John Host', 'staff@vms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 'Active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO visitors (full_name, email, id_passport, host_name, host_email, purpose, visit_date, status, qr_token, checked_in_at, created_by) VALUES
('test1', 'test1@gmail.com', '111', 'John Host', 'staff@vms.local', 'Meeting', '2026-03-02', 'Checked In', 'tok_test1_a1b2c3', NOW(), 1),
('Jane Doe', 'jane@example.com', 'ID001', 'John Host', 'staff@vms.local', 'Interview', '2026-03-02', 'Approved', 'tok_jane_d4e5f6', NULL, 1),
('Bob Smith', 'bob@example.com', 'ID002', 'John Host', 'staff@vms.local', 'Delivery', '2026-03-02', 'Approved', 'tok_bob_g7h8i9', NULL, 1),
('Alice Tan', 'alice@example.com', 'ID003', 'John Host', 'staff@vms.local', 'Vendor visit', CURDATE(), 'Checked In', 'tok_alice_j0k1l2', NOW(), 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);
