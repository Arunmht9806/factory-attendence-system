# Factory Attendance System

A PHP + MySQL factory attendance management system with biometric punch simulation, employee administration, holiday management, and attendance reporting.

## Installation

1. Place the `factory_attendance_system` folder in your local web server document root (for example, `htdocs` in XAMPP).
2. Create the database using the provided SQL file:
   - Open phpMyAdmin or MySQL shell.
   - Execute the statements in `database.sql`.
3. Update database credentials in `db.php` if your MySQL user or password is different.
4. Open `index.php` through your local server, e.g. `http://localhost/factory_attendance_system/index.php`.

## Features

- Employee add / edit / delete
- Public holidays add / delete
- Biometric punch simulator
- Attendance record computation with overtime and weekend/holiday logic
- Export attendance report as TSV file
- Responsive dashboard UI using Tailwind CSS
- Daily attendance rollup table for report reads, reducing repeated raw punch scans on large datasets
- Composite punch indexes on `(emp_id, timestamp)` and `(timestamp, emp_id)` for high-volume range queries
- Shared frontend JSON request helper with timeout and safe retry for read operations in the dashboard and punch terminal
- Runtime schema bootstrap caching so normal API requests stop rechecking table and column metadata after initialization

## Notes

- The system stores data in MySQL and serves the UI through PHP pages and AJAX endpoints.
- For production use, secure the database credentials and access control before deploying.
- The runtime now auto-builds `attendance_daily_summary` and keeps it synchronized on punch, edit, and delete operations; fresh installs should use the updated `database.sql`.
- This change removes the main read bottleneck in attendance screens and exports, but sustained `500GB/day` workloads still require database partitioning, replicas, and operational archival outside this single-node PHP app.
- An optional high-volume MySQL operational script is available in `mysql_high_volume_partitioning.sql` for staged partitioning and archival rollout.
- A step-by-step deployment guide for that rollout is available in `mysql_cutover_plan.md`.
