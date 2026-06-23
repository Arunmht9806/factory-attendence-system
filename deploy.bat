@echo off
REM Deploy Factory Attendance System and Start XAMPP servers

echo Starting factory_attendance_system deployment...

REM Copy project files to XAMPP htdocs
echo Copying project files to XAMPP...
if not exist "C:\xampp\htdocs\factory_attendance_system" mkdir "C:\xampp\htdocs\factory_attendance_system"
xcopy "C:\Users\NWAT\Desktop\New folder\factory_attendance_system\*" "C:\xampp\htdocs\factory_attendance_system\" /E /I /Y /Q

REM Initialize database
echo Initializing MySQL database...
"C:\xampp\mysql\bin\mysql.exe" -u root < "C:\xampp\htdocs\factory_attendance_system\database.sql"

REM Start XAMPP services
echo Starting Apache and MySQL...
"C:\xampp\apache\bin\httpd.exe" -k install
"C:\xampp\mysql\bin\mysqld.exe" --install

REM Start services via net command
net start Apache2.4
net start MySQL

echo.
echo ========================================
echo Deployment Complete!
echo ========================================
echo Open your browser and navigate to:
echo http://localhost/factory_attendance_system/index.php
echo.
echo phpMyAdmin: http://localhost/phpmyadmin/
echo.
pause
