-- SQL schema for Factory Attendance System
CREATE DATABASE IF NOT EXISTS factory_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE factory_attendance;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','hr','it','viewer') NOT NULL DEFAULT 'viewer',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department ENUM('Production', 'Office') NOT NULL DEFAULT 'Production',
    can_edit_attendance TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    is_purged TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_employees_department_id (department, id),
    INDEX idx_employees_active_department_id (is_deleted, department, id),
    INDEX idx_employees_archive_state_id (is_deleted, is_purged, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
    `date` DATE PRIMARY KEY,
    description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_punches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(20) NOT NULL,
    timestamp DATETIME NOT NULL,
    vehicle_name VARCHAR(100) NULL,
    vehicle_purpose VARCHAR(255) NULL,
    INDEX (emp_id),
    INDEX (timestamp),
    INDEX idx_attendance_emp_time (emp_id, timestamp),
    INDEX idx_attendance_time_emp (timestamp, emp_id),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_punches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(20) NOT NULL,
    timestamp DATETIME NOT NULL,
    vehicle_name VARCHAR(100) NOT NULL,
    vehicle_purpose VARCHAR(255) NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    session_type ENUM('start','end') NOT NULL DEFAULT 'start',
    INDEX (emp_id),
    INDEX (timestamp),
    INDEX (session_token),
    INDEX idx_vehicle_emp_time (emp_id, timestamp),
    INDEX idx_vehicle_time_emp (timestamp, emp_id),
    INDEX idx_vehicle_session_type_token (session_type, session_token),
    INDEX idx_vehicle_emp_session_time (emp_id, session_type, timestamp),
    INDEX idx_vehicle_name_session_time (vehicle_name, session_type, timestamp),
    CONSTRAINT fk_vehicle_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_daily_summary (
    emp_id VARCHAR(20) NOT NULL,
    attendance_date DATE NOT NULL,
    first_punch_at DATETIME NULL,
    last_punch_at DATETIME NULL,
    punch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    raw_times_text TEXT NOT NULL,
    session_text TEXT NOT NULL,
    session_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_work_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    vehicle_name VARCHAR(100) NULL,
    vehicle_purpose VARCHAR(255) NULL,
    vehicle_text TEXT NOT NULL,
    gps_point_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    gps_points_json LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (emp_id, attendance_date),
    INDEX idx_attendance_summary_date_emp (attendance_date, emp_id),
    CONSTRAINT fk_attendance_summary_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(20) NOT NULL,
    leave_type VARCHAR(40) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    leave_days DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    reason VARCHAR(500) NOT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    remarks VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (emp_id),
    INDEX (start_date),
    INDEX (end_date),
    INDEX idx_leave_start_emp (start_date, emp_id),
    INDEX idx_leave_emp_status_start (emp_id, status, start_date),
    CONSTRAINT fk_leave_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS travel_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_date DATE NULL,
    emp_id VARCHAR(20) NOT NULL,
    branch VARCHAR(120) NULL,
    destination VARCHAR(200) NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    departure_date DATE NULL,
    arrival_date DATE NULL,
    mode_of_travel ENUM('Office Vehicle','Air','Bus','Other') NOT NULL DEFAULT 'Office Vehicle',
    mode_other VARCHAR(120) NULL,
    advance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    requested_by VARCHAR(120) NULL,
    checked_by VARCHAR(120) NULL,
    approved_by VARCHAR(120) NULL,
    total_days DECIMAL(6,2) NULL,
    tada_per_day DECIMAL(10,2) NULL,
    other_expenses DECIMAL(10,2) NULL,
    total_expenses DECIMAL(10,2) NULL,
    settlement_requested_by VARCHAR(120) NULL,
    settlement_checked_by VARCHAR(120) NULL,
    settlement_approved_by VARCHAR(120) NULL,
    notes VARCHAR(700) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (emp_id),
    INDEX (form_date),
    INDEX (departure_date),
    INDEX idx_travel_form_emp (form_date, emp_id),
    INDEX idx_travel_emp_departure (emp_id, departure_date),
    CONSTRAINT fk_travel_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO employees (id, name, department, can_edit_attendance) VALUES
('EMP001', 'Ram Bahadur', 'Production', 0),
('EMP002', 'Sita Thapa', 'Office', 1),
('EMP003', 'Krishna Shrestha', 'Production', 0),
('EMP004', 'Gita Giri', 'Office', 0);

INSERT IGNORE INTO holidays (`date`, description) VALUES
('2026-01-01', 'New Year''s Day'),
('2026-05-01', 'Labour Day'),
('2026-06-17', 'Fête de la Musique');
