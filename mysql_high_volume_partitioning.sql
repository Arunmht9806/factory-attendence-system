-- Optional high-volume MySQL playbook for deployments that need partitioning and archival.
--
-- This file stays within the current stack: MySQL only.
-- It is intentionally not executed automatically by the PHP app.
-- Review and apply on a dedicated MySQL environment during a maintenance window.
--
-- Important:
-- 1. Partitioning event tables may require dropping or redesigning foreign keys depending on your MySQL version.
-- 2. Test this on staging first.
-- 3. Keep the existing application-level daily summary table for fast reads.

USE factory_attendance;

-- Archive tables keep historical rows out of the hot write path.
CREATE TABLE IF NOT EXISTS attendance_punches_archive LIKE attendance_punches;
CREATE TABLE IF NOT EXISTS vehicle_punches_archive LIKE vehicle_punches;

ALTER TABLE attendance_punches_archive
    ADD INDEX idx_attendance_archive_emp_time (emp_id, timestamp),
    ADD INDEX idx_attendance_archive_time_emp (timestamp, emp_id);

ALTER TABLE vehicle_punches_archive
    ADD INDEX idx_vehicle_archive_emp_time (emp_id, timestamp),
    ADD INDEX idx_vehicle_archive_time_emp (timestamp, emp_id);

-- Example partitioned hot tables.
-- Build these as replacement tables, backfill data, then rename during cutover.

DROP TABLE IF EXISTS attendance_punches_partitioned;
CREATE TABLE attendance_punches_partitioned (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    emp_id VARCHAR(20) NOT NULL,
    timestamp DATETIME NOT NULL,
    vehicle_name VARCHAR(100) NULL,
    vehicle_purpose VARCHAR(255) NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    gps_accuracy_meters DECIMAL(8,2) NULL,
    PRIMARY KEY (id, timestamp),
    KEY idx_attendance_emp_time (emp_id, timestamp),
    KEY idx_attendance_time_emp (timestamp, emp_id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE COLUMNS(timestamp) (
    PARTITION p2026_07 VALUES LESS THAN ('2026-08-01 00:00:00'),
    PARTITION p2026_08 VALUES LESS THAN ('2026-09-01 00:00:00'),
    PARTITION pmax VALUES LESS THAN (MAXVALUE)
);

DROP TABLE IF EXISTS vehicle_punches_partitioned;
CREATE TABLE vehicle_punches_partitioned (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    emp_id VARCHAR(20) NOT NULL,
    timestamp DATETIME NOT NULL,
    vehicle_name VARCHAR(100) NOT NULL,
    vehicle_purpose VARCHAR(255) NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    session_type ENUM('start','end') NOT NULL DEFAULT 'start',
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    gps_accuracy_meters DECIMAL(8,2) NULL,
    PRIMARY KEY (id, timestamp),
    KEY idx_vehicle_emp_time (emp_id, timestamp),
    KEY idx_vehicle_time_emp (timestamp, emp_id),
    KEY idx_vehicle_session_token (session_token)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
PARTITION BY RANGE COLUMNS(timestamp) (
    PARTITION p2026_07 VALUES LESS THAN ('2026-08-01 00:00:00'),
    PARTITION p2026_08 VALUES LESS THAN ('2026-09-01 00:00:00'),
    PARTITION pmax VALUES LESS THAN (MAXVALUE)
);

DELIMITER $$

CREATE PROCEDURE archive_attendance_before(IN cutoff_datetime DATETIME)
BEGIN
    INSERT INTO attendance_punches_archive
    SELECT *
    FROM attendance_punches
    WHERE timestamp < cutoff_datetime;

    DELETE FROM attendance_punches
    WHERE timestamp < cutoff_datetime;
END$$

CREATE PROCEDURE archive_vehicle_usage_before(IN cutoff_datetime DATETIME)
BEGIN
    INSERT INTO vehicle_punches_archive
    SELECT *
    FROM vehicle_punches
    WHERE timestamp < cutoff_datetime;

    DELETE FROM vehicle_punches
    WHERE timestamp < cutoff_datetime;
END$$

CREATE PROCEDURE add_next_month_attendance_partition(
    IN table_name VARCHAR(64),
    IN partition_name VARCHAR(32),
    IN upper_bound DATETIME
)
BEGIN
    SET @sql_text = CONCAT(
        'ALTER TABLE ', table_name,
        ' REORGANIZE PARTITION pmax INTO (',
        'PARTITION ', partition_name,
        ' VALUES LESS THAN (''', DATE_FORMAT(upper_bound, '%Y-%m-%d %H:%i:%s'), '''),',
        'PARTITION pmax VALUES LESS THAN (MAXVALUE))'
    );
    PREPARE stmt FROM @sql_text;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;

-- Example monthly maintenance:
-- CALL add_next_month_attendance_partition('attendance_punches_partitioned', 'p2026_09', '2026-10-01 00:00:00');
-- CALL add_next_month_attendance_partition('vehicle_punches_partitioned', 'p2026_09', '2026-10-01 00:00:00');
-- CALL archive_attendance_before('2026-01-01 00:00:00');
-- CALL archive_vehicle_usage_before('2026-01-01 00:00:00');