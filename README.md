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

## Notes

- The system stores data in MySQL and serves the UI through PHP pages and AJAX endpoints.
- For production use, secure the database credentials and access control before deploying.
