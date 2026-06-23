<?php
require_once __DIR__ . '/db.php';
session_start();

$action = $_REQUEST['action'] ?? '';
$mysqli = db_connect();

$publicActions = ['listEmployees', 'punch', 'punchVehicle'];

function has_full_access_role($role) {
    return in_array($role, ['admin', 'hr', 'it'], true);
}

function ensure_users_table($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $mysqli->query("SHOW TABLES LIKE 'users'");
    $ready = $result && $result->num_rows > 0;
    if (!$ready) {
        $createSql = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','hr','it','viewer') NOT NULL DEFAULT 'viewer',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ready = $mysqli->query($createSql) === true;
    }

    if ($ready) {
        $roleResult = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");
        $roleType = $roleResult ? (string)($roleResult->fetch_assoc()['Type'] ?? '') : '';
        if ($roleType && (strpos($roleType, "'hr'") === false || strpos($roleType, "'it'") === false)) {
            $alterOk = $mysqli->query("ALTER TABLE users MODIFY role ENUM('admin','hr','it','viewer') NOT NULL DEFAULT 'viewer'");
            if (!$alterOk) {
                return false;
            }

            $verifyRoleResult = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");
            $verifyRoleType = $verifyRoleResult ? (string)($verifyRoleResult->fetch_assoc()['Type'] ?? '') : '';
            if (strpos($verifyRoleType, "'hr'") === false || strpos($verifyRoleType, "'it'") === false) {
                return false;
            }
        }
    }

    if ($ready) {
        $countRes = $mysqli->query('SELECT COUNT(*) AS cnt FROM users');
        $count = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
        if ($count === 0) {
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            $defaultUser = 'admin';
            $defaultRole = 'admin';
            $stmt->bind_param('sss', $defaultUser, $hash, $defaultRole);
            $stmt->execute();
            $stmt->close();
        }
    }

    return $ready;
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        respond(['success' => false, 'message' => 'Authentication required.']);
    }
}

function require_admin() {
    require_login();
    if (!has_full_access_role($_SESSION['role'] ?? '')) {
        respond(['success' => false, 'message' => 'Admin/HR/IT permission required.']);
    }
}

function is_viewer_role() {
    return (string)($_SESSION['role'] ?? '') === 'viewer';
}

function get_logged_in_employee_id($mysqli) {
    $username = (string)($_SESSION['username'] ?? '');
    if (!validate_employee_id($username)) {
        return '';
    }
    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $count = 0;
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0 ? $username : '';
}

function is_it_designation($designation) {
    $designation = trim((string)$designation);
    if ($designation === '') {
        return false;
    }
    return preg_match('/\bIT\b/i', $designation) === 1;
}

function should_use_default_employee_password($role) {
    return $role === 'viewer';
}

function derive_employee_login_role($designation, $canEditAttendance) {
    if (is_it_designation($designation)) {
        return 'it';
    }
    return ((int)$canEditAttendance === 1) ? 'hr' : 'viewer';
}

function upsert_employee_login_user($mysqli, $empId, $designation, $canEditAttendance, &$errorMessage = '') {
    $errorMessage = '';
    if (!validate_employee_id($empId)) {
        $errorMessage = 'Invalid employee ID for login sync.';
        return false;
    }
    if (!ensure_users_table($mysqli)) {
        $errorMessage = 'Could not prepare users table for employee login sync.';
        return false;
    }

    $loginRole = derive_employee_login_role($designation, $canEditAttendance);
    $defaultPassword = should_use_default_employee_password($loginRole) ? '123' : $empId;
    $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);

    // Keep existing password_hash on re-sync so manually set passwords stay valid.
    $stmt = $mysqli->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)');
    if (!$stmt) {
        $errorMessage = 'Could not prepare employee login user statement: ' . $mysqli->error;
        return false;
    }

    $stmt->bind_param('sss', $empId, $passwordHash, $loginRole);
    $ok = $stmt->execute();
    if (!$ok) {
        $errorMessage = 'Could not sync employee login user: ' . $stmt->error;
    }
    $stmt->close();
    return $ok;
}

function sync_all_employee_login_users($mysqli, &$errors = []) {
    $errors = [];
    $withDesig = ensure_designation_column($mysqli);
    $withHrPermission = ensure_hr_permission_column($mysqli);
    $designationCol = $withDesig ? 'designation' : '"" AS designation';
    $hrCol = $withHrPermission ? 'can_edit_attendance' : '0 AS can_edit_attendance';

    $result = $mysqli->query("SELECT id, {$designationCol}, {$hrCol} FROM employees");
    if (!$result) {
        $errors[] = 'Could not load employees for login sync: ' . $mysqli->error;
        return false;
    }

    while ($row = $result->fetch_assoc()) {
        $empId = (string)($row['id'] ?? '');
        $designation = (string)($row['designation'] ?? '');
        $canEditAttendance = (int)($row['can_edit_attendance'] ?? 0);
        $syncError = '';
        if (!upsert_employee_login_user($mysqli, $empId, $designation, $canEditAttendance, $syncError)) {
            $errors[] = '[' . $empId . '] ' . ($syncError ?: 'Unknown sync error.');
        }
    }

    return count($errors) === 0;
}

function respond($data) {
    json_response($data);
}

function ensure_statement($stmt, $mysqli, $message) {
    if ($stmt) {
        return $stmt;
    }
    respond([
        'success' => false,
        'message' => $message . ': ' . $mysqli->error,
    ]);
}

function execute_statement_or_fail($stmt, $message) {
    if ($stmt->execute()) {
        return;
    }
    $error = $stmt->error;
    $stmt->close();
    respond([
        'success' => false,
        'message' => $message . ': ' . $error,
    ]);
}

function parse_datetime_input($value) {
    if (!$value) {
        return null;
    }
    $value = trim($value);
    $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt && $dt->format($format) === $value) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    return null;
}

function is_valid_date_ymd($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

function is_valid_department($department) {
    return in_array($department, ['Production', 'Office'], true);
}

function validate_employee_id($id) {
    return (bool) preg_match('/^[A-Za-z0-9_.-]{1,20}$/', $id);
}

function vehicle_session_duration_parts($startTimestamp, $endTimestamp) {
    if (!$startTimestamp || !$endTimestamp) {
        return [0, ''];
    }

    $start = strtotime($startTimestamp);
    $end = strtotime($endTimestamp);
    if ($start === false || $end === false || $end < $start) {
        return [0, ''];
    }

    $totalMinutes = (int) floor(($end - $start) / 60);
    $hours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;

    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($minutes > 0 || !$parts) {
        $parts[] = $minutes . 'm';
    }

    return [$totalMinutes, implode(' ', $parts)];
}

function ensure_vehicle_columns($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $nameResult = $mysqli->query("SHOW COLUMNS FROM attendance_punches LIKE 'vehicle_name'");
    $purposeResult = $mysqli->query("SHOW COLUMNS FROM attendance_punches LIKE 'vehicle_purpose'");
    $ready = $nameResult && $nameResult->num_rows > 0 && $purposeResult && $purposeResult->num_rows > 0;

    if (!$ready) {
        $mysqli->query("ALTER TABLE attendance_punches ADD COLUMN vehicle_name VARCHAR(100) NULL AFTER timestamp");
        $mysqli->query("ALTER TABLE attendance_punches ADD COLUMN vehicle_purpose VARCHAR(255) NULL AFTER vehicle_name");
        $nameVerify = $mysqli->query("SHOW COLUMNS FROM attendance_punches LIKE 'vehicle_name'");
        $purposeVerify = $mysqli->query("SHOW COLUMNS FROM attendance_punches LIKE 'vehicle_purpose'");
        $ready = $nameVerify && $nameVerify->num_rows > 0 && $purposeVerify && $purposeVerify->num_rows > 0;
    }

    return $ready;
}

function ensure_vehicle_punches_table($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $mysqli->query("SHOW TABLES LIKE 'vehicle_punches'");
    $ready = $result && $result->num_rows > 0;

    if (!$ready) {
        $createSql = "CREATE TABLE vehicle_punches (
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
            CONSTRAINT fk_vehicle_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ready = $mysqli->query($createSql) === true;
    } else {
        $tokenResult = $mysqli->query("SHOW COLUMNS FROM vehicle_punches LIKE 'session_token'");
        $typeResult = $mysqli->query("SHOW COLUMNS FROM vehicle_punches LIKE 'session_type'");
        $hasToken = $tokenResult && $tokenResult->num_rows > 0;
        $hasType = $typeResult && $typeResult->num_rows > 0;
        if (!$hasToken) {
            $mysqli->query("ALTER TABLE vehicle_punches ADD COLUMN session_token VARCHAR(64) NOT NULL DEFAULT '' AFTER vehicle_purpose");
        }
        if (!$hasType) {
            $mysqli->query("ALTER TABLE vehicle_punches ADD COLUMN session_type ENUM('start','end') NOT NULL DEFAULT 'start' AFTER session_token");
        }
        $verifyToken = $mysqli->query("SHOW COLUMNS FROM vehicle_punches LIKE 'session_token'");
        $verifyType = $mysqli->query("SHOW COLUMNS FROM vehicle_punches LIKE 'session_type'");
        $ready = $verifyToken && $verifyToken->num_rows > 0 && $verifyType && $verifyType->num_rows > 0;
    }

    return $ready;
}

function ensure_leave_requests_table($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $mysqli->query("SHOW TABLES LIKE 'leave_requests'");
    $ready = $result && $result->num_rows > 0;

    if (!$ready) {
        $createSql = "CREATE TABLE leave_requests (
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
            CONSTRAINT fk_leave_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ready = $mysqli->query($createSql) === true;
    }

    return $ready;
}

function ensure_travel_orders_table($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $mysqli->query("SHOW TABLES LIKE 'travel_orders'");
    $ready = $result && $result->num_rows > 0;

    if (!$ready) {
        $createSql = "CREATE TABLE travel_orders (
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
            CONSTRAINT fk_travel_employee FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ready = $mysqli->query($createSql) === true;
    }

    return $ready;
}

function ensure_designation_column($mysqli) {
    static $desigReady = null;
    if ($desigReady !== null) {
        return $desigReady;
    }

    $result = $mysqli->query("SHOW COLUMNS FROM employees LIKE 'designation'");
    $desigReady = $result && $result->num_rows > 0;

    if (!$desigReady) {
        $mysqli->query("ALTER TABLE employees ADD COLUMN designation VARCHAR(100) NOT NULL DEFAULT '' AFTER name");
        $verify = $mysqli->query("SHOW COLUMNS FROM employees LIKE 'designation'");
        $desigReady = $verify && $verify->num_rows > 0;
    }

    return $desigReady;
}

function ensure_hr_permission_column($mysqli) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $result = $mysqli->query("SHOW COLUMNS FROM employees LIKE 'can_edit_attendance'");
    $ready = $result && $result->num_rows > 0;

    if (!$ready) {
        $mysqli->query("ALTER TABLE employees ADD COLUMN can_edit_attendance TINYINT(1) NOT NULL DEFAULT 0 AFTER department");
        $verify = $mysqli->query("SHOW COLUMNS FROM employees LIKE 'can_edit_attendance'");
        $ready = $verify && $verify->num_rows > 0;
    }

    return $ready;
}

function employee_can_edit_attendance($mysqli, $empId) {
    if (!$empId || !ensure_hr_permission_column($mysqli)) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT can_edit_attendance FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $canEdit = 0;
    $stmt->bind_param('s', $empId);
    $stmt->execute();
    $stmt->bind_result($canEdit);
    $stmt->fetch();
    $stmt->close();
    return (int) $canEdit === 1;
}

function verify_employee_password($mysqli, $empId, $password) {
    if ($empId === '' || $password === '') {
        return false;
    }
    if (!ensure_users_table($mysqli)) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT password_hash FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $empId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['password_hash'])) {
        return false;
    }

    return password_verify($password, $row['password_hash']);
}

function normalize_employee_text($value) {
    return mb_strtolower(trim((string)$value));
}

function verify_employee_punch_profile($mysqli, $empId, $empName, $empDepartment, $empDesignation) {
    if ($empId === '' || $empName === '' || $empDepartment === '') {
        return false;
    }
    if (!is_valid_department($empDepartment)) {
        return false;
    }

    $hasDesignation = ensure_designation_column($mysqli);
    $sql = $hasDesignation
        ? 'SELECT name, department, designation FROM employees WHERE id = ? LIMIT 1'
        : 'SELECT name, department, "" AS designation FROM employees WHERE id = ? LIMIT 1';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $empId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $dbName = normalize_employee_text($row['name'] ?? '');
    $dbDepartment = normalize_employee_text($row['department'] ?? '');
    $dbDesignation = normalize_employee_text($row['designation'] ?? '');

    $reqName = normalize_employee_text($empName);
    $reqDepartment = normalize_employee_text($empDepartment);
    $reqDesignation = normalize_employee_text($empDesignation);

    if ($dbName !== $reqName || $dbDepartment !== $reqDepartment) {
        return false;
    }

    if ($hasDesignation && $dbDesignation !== $reqDesignation) {
        return false;
    }

    return true;
}

function register_employee_punch($mysqli, $empId, $timestamp, $vehicleName, $vehiclePurpose, $requireVehicleDetails = false) {
    if (!$empId || !$timestamp) {
        respond(['success' => false, 'message' => 'Employee and valid timestamp are required.']);
    }

    if ($requireVehicleDetails) {
        if (!$vehicleName || !$vehiclePurpose) {
            respond(['success' => false, 'message' => 'Vehicle punch requires both vehicle number and purpose.']);
        }
    } else {
        // Regular punch does not carry vehicle metadata.
        $vehicleName = '';
        $vehiclePurpose = '';
    }

    $stmt = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
    $stmt->bind_param('s', $empId);
    $stmt->execute();
    $existsEmployee = 0;
    $stmt->bind_result($existsEmployee);
    $stmt->fetch();
    $stmt->close();
    if (!$existsEmployee) {
        respond(['success' => false, 'message' => 'Employee not found.']);
    }

    if ($requireVehicleDetails) {
        if (!ensure_vehicle_punches_table($mysqli)) {
            respond(['success' => false, 'message' => 'Vehicle punch storage is not ready.']);
        }
        $lookup = $mysqli->prepare('SELECT vp.session_token FROM vehicle_punches vp WHERE vp.emp_id = ? AND vp.vehicle_name = ? AND vp.vehicle_purpose = ? AND vp.session_type = "start" AND NOT EXISTS (SELECT 1 FROM vehicle_punches v2 WHERE v2.session_token = vp.session_token AND v2.session_type = "end") ORDER BY vp.timestamp DESC, vp.id DESC LIMIT 1');
        if (!$lookup) {
            respond(['success' => false, 'message' => 'Vehicle punch lookup failed: ' . $mysqli->error]);
        }
        $lookup->bind_param('sss', $empId, $vehicleName, $vehiclePurpose);
        if (!$lookup->execute()) {
            $error = $lookup->error;
            $lookup->close();
            respond(['success' => false, 'message' => 'Vehicle punch lookup failed: ' . $error]);
        }
        $existingVehicle = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!empty($existingVehicle['session_token'])) {
            $sessionToken = $existingVehicle['session_token'];
            $stmt = $mysqli->prepare('INSERT INTO vehicle_punches (emp_id, timestamp, vehicle_name, vehicle_purpose, session_token, session_type) VALUES (?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                respond(['success' => false, 'message' => 'Could not complete vehicle session: ' . $mysqli->error]);
            }
            $sessionType = 'end';
            $stmt->bind_param('ssssss', $empId, $timestamp, $vehicleName, $vehiclePurpose, $sessionToken, $sessionType);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                respond(['success' => false, 'message' => 'Could not complete vehicle session: ' . $error]);
            }
            $stmt->close();
            respond([
                'success' => true,
                'message' => 'Vehicle session completed successfully.',
                'vehicleName' => '',
                'vehiclePurpose' => '',
                'sessionCompleted' => true,
            ]);
        }

        // Do not allow another employee to start a session with a vehicle that is already in use.
        $openVehicleCheck = $mysqli->prepare('SELECT vp.emp_id FROM vehicle_punches vp WHERE vp.vehicle_name = ? AND vp.emp_id <> ? AND vp.session_type = "start" AND NOT EXISTS (SELECT 1 FROM vehicle_punches v2 WHERE v2.session_token = vp.session_token AND v2.session_type = "end") ORDER BY vp.timestamp DESC, vp.id DESC LIMIT 1');
        if (!$openVehicleCheck) {
            respond(['success' => false, 'message' => 'Vehicle availability check failed: ' . $mysqli->error]);
        }
        $openVehicleCheck->bind_param('ss', $vehicleName, $empId);
        if (!$openVehicleCheck->execute()) {
            $error = $openVehicleCheck->error;
            $openVehicleCheck->close();
            respond(['success' => false, 'message' => 'Vehicle availability check failed: ' . $error]);
        }
        $openVehicleRow = $openVehicleCheck->get_result()->fetch_assoc();
        $openVehicleCheck->close();
        if (!empty($openVehicleRow['emp_id'])) {
            respond([
                'success' => false,
                'message' => 'This vehicle is already in an active session by employee ' . $openVehicleRow['emp_id'] . '. Please complete that session first.',
            ]);
        }

        $sessionToken = bin2hex(random_bytes(16));
        $stmt = $mysqli->prepare('INSERT INTO vehicle_punches (emp_id, timestamp, vehicle_name, vehicle_purpose, session_token, session_type) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            respond(['success' => false, 'message' => 'Could not start vehicle session: ' . $mysqli->error]);
        }
        $sessionType = 'start';
        $stmt->bind_param('ssssss', $empId, $timestamp, $vehicleName, $vehiclePurpose, $sessionToken, $sessionType);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            respond(['success' => false, 'message' => 'Could not start vehicle session: ' . $error]);
        }
        $stmt->close();
    } else {
        $dayStart = substr($timestamp, 0, 10) . ' 00:00:00';
        $dayEnd = substr($timestamp, 0, 10) . ' 23:59:59';
        $punchCountStmt = $mysqli->prepare('SELECT COUNT(*) FROM attendance_punches WHERE emp_id = ? AND timestamp BETWEEN ? AND ?');
        $punchCountStmt->bind_param('sss', $empId, $dayStart, $dayEnd);
        $punchCountStmt->execute();
        $punchCount = 0;
        $punchCountStmt->bind_result($punchCount);
        $punchCountStmt->fetch();
        $punchCountStmt->close();

        if ($punchCount >= 12) {
            respond(['success' => false, 'message' => 'Maximum 6 working sessions are allowed per day.']);
        }

        if (ensure_vehicle_columns($mysqli)) {
            $stmt = $mysqli->prepare('INSERT INTO attendance_punches (emp_id, timestamp, vehicle_name, vehicle_purpose) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $empId, $timestamp, $vehicleName, $vehiclePurpose);
        } else {
            $stmt = $mysqli->prepare('INSERT INTO attendance_punches (emp_id, timestamp) VALUES (?, ?)');
            $stmt->bind_param('ss', $empId, $timestamp);
        }
        $stmt->execute();
    }


    respond([
        'success' => true,
        'message' => $requireVehicleDetails ? 'Vehicle session started successfully.' : 'Punch registered successfully.',
        'vehicleName' => $vehicleName,
        'vehiclePurpose' => $vehiclePurpose,
    ]);
}

function parse_attendance_time_entries($date, $rawTimes) {
    $parts = preg_split('/[\s,|]+/', trim((string) $rawTimes));
    $entries = [];

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $part)) {
            $candidate = $date . ' ' . $part . ':00';
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $part)) {
            $candidate = $date . ' ' . $part;
        } else {
            return null;
        }

        $parsed = parse_datetime_input($candidate);
        if (!$parsed) {
            return null;
        }
        $entries[] = $parsed;
    }

    if (count($entries) === 0 || count($entries) % 2 !== 0 || count($entries) > 12) {
        return null;
    }

    sort($entries);
    return $entries;
}

function build_session_details($timestamps) {
    sort($timestamps);
    $sessions = [];
    $totalSeconds = 0;

    for ($i = 0; $i + 1 < count($timestamps); $i += 2) {
        $checkIn = strtotime($timestamps[$i]);
        $checkOut = strtotime($timestamps[$i + 1]);
        if ($checkOut > $checkIn) {
            $seconds = $checkOut - $checkIn;
            $totalSeconds += $seconds;
            $sessions[] = [
                'checkIn' => date('H:i:s', $checkIn),
                'checkOut' => date('H:i:s', $checkOut),
                'hours' => round($seconds / 3600, 2),
            ];
        }
    }

    $sessionTextParts = [];
    foreach ($sessions as $index => $session) {
        $sessionTextParts[] = 'S' . ($index + 1) . ': ' . $session['checkIn'] . ' - ' . $session['checkOut'] . ' (' . number_format($session['hours'], 2) . 'h)';
    }

    return [
        'sessions' => $sessions,
        'sessionText' => $sessionTextParts ? implode(' | ', $sessionTextParts) : 'No complete session',
        'totalSeconds' => $totalSeconds,
        'sessionCount' => count($sessions),
    ];
}

function xml_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Pure-PHP ZIP reader (no ZipArchive needed).
// Scans local file headers in the raw ZIP bytes.
function zip_extract_entry(string $zipBytes, string $entryPath): ?string {
    $pos = 0;
    $len = strlen($zipBytes);
    while ($pos <= $len - 30) {
        if (substr($zipBytes, $pos, 4) !== "\x50\x4b\x03\x04") {
            $pos++;
            continue;
        }
        [, $compression]    = unpack('v', substr($zipBytes, $pos + 8,  2));
        [, $compressedSize] = unpack('V', substr($zipBytes, $pos + 18, 4));
        [, $fileNameLen]    = unpack('v', substr($zipBytes, $pos + 26, 2));
        [, $extraLen]       = unpack('v', substr($zipBytes, $pos + 28, 2));
        $fileName   = substr($zipBytes, $pos + 30, $fileNameLen);
        $dataOffset = $pos + 30 + $fileNameLen + $extraLen;
        if ($fileName === $entryPath) {
            $raw = substr($zipBytes, $dataOffset, $compressedSize);
            return $compression === 8 ? gzinflate($raw) : $raw;
        }
        $pos = $dataOffset + $compressedSize;
    }
    return null;
}

function xlsx_parse_rows(string $zipBytes): ?array {
    // Read shared strings (type="s" cells reference this table).
    $sharedStrings = [];
    $ssXml = zip_extract_entry($zipBytes, 'xl/sharedStrings.xml');
    if ($ssXml) {
        preg_match_all('/<si>.*?<\/si>/s', $ssXml, $siMatches);
        foreach ($siMatches[0] as $si) {
            preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $si, $tMatches);
            $sharedStrings[] = implode('', $tMatches[1]);
        }
    }

    $sheetXml = zip_extract_entry($zipBytes, 'xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        return null;
    }

    $rows = [];
    preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches);
    foreach ($rowMatches[1] as $rowContent) {
        $cells = [];
        preg_match_all('/<c\s([^>]*)>(.*?)<\/c>/s', $rowContent, $cellMatches, PREG_SET_ORDER);
        foreach ($cellMatches as $cell) {
            $attrs = $cell[1];
            $inner = $cell[2];
            preg_match('/r="([A-Z]+\d+)"/', $attrs, $rMatch);
            if (!$rMatch) continue;
            $colLetters = preg_replace('/\d/', '', $rMatch[1]);
            $colIndex   = 0;
            foreach (str_split($colLetters) as $ch) {
                $colIndex = $colIndex * 26 + (ord($ch) - 64);
            }
            $colIndex--;

            $type  = '';
            preg_match('/t="([^"]*)"/', $attrs, $tMatch);
            if ($tMatch) $type = $tMatch[1];

            $value = '';
            if (preg_match('/<v>([^<]*)<\/v>/', $inner, $vMatch)) {
                $value = $vMatch[1];
                if ($type === 's' && isset($sharedStrings[(int)$value])) {
                    $value = $sharedStrings[(int)$value];
                }
            } elseif (preg_match('/<is>.*?<t[^>]*>([^<]*)<\/t>.*?<\/is>/s', $inner, $isMatch)) {
                $value = $isMatch[1];
            }
            // Expand array if needed.
            while (count($cells) <= $colIndex) $cells[] = '';
            $cells[$colIndex] = html_entity_decode($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
        $rows[] = $cells;
    }
    return $rows;
}

function xlsx_column_name($index) {
    $name = '';
    $index++;
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $index = intdiv($index - 1, 26);
    }
    return $name;
}

function xlsx_sheet_xml($title, $rows) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    foreach ($rows as $rowIndex => $row) {
        $xml .= '<row r="' . ($rowIndex + 1) . '">';
        foreach (array_values($row) as $cellIndex => $cellValue) {
            $cellRef = xlsx_column_name($cellIndex) . ($rowIndex + 1);
            $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . xml_escape($cellValue) . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function xlsx_sanitize_sheet_title($title, $fallback = 'Sheet') {
    $name = trim((string) $title);
    if ($name === '') {
        $name = $fallback;
    }
    $name = preg_replace('/[\\\/*?:\[\]]+/', '_', $name);
    if ($name === '') {
        $name = $fallback;
    }
    if (mb_strlen($name) > 31) {
        $name = mb_substr($name, 0, 31);
    }
    return $name;
}

function xlsx_build_package($sheetTitle, $rows, $additionalSheets = []) {
    $sheetDefs = [[
        'title' => xlsx_sanitize_sheet_title($sheetTitle, 'Sheet1'),
        'rows' => is_array($rows) ? $rows : [],
    ]];

    foreach ($additionalSheets as $index => $sheet) {
        $rawTitle = is_array($sheet) ? ($sheet['title'] ?? '') : '';
        $sheetRows = (is_array($sheet) && isset($sheet['rows']) && is_array($sheet['rows'])) ? $sheet['rows'] : [];
        $title = xlsx_sanitize_sheet_title($rawTitle, 'Sheet' . ($index + 2));

        $baseTitle = $title;
        $suffix = 2;
        $existingTitles = array_column($sheetDefs, 'title');
        while (in_array($title, $existingTitles, true)) {
            $extra = ' (' . $suffix . ')';
            $maxLen = 31 - mb_strlen($extra);
            $title = mb_substr($baseTitle, 0, max(1, $maxLen)) . $extra;
            $suffix++;
        }

        $sheetDefs[] = [
            'title' => $title,
            'rows' => $sheetRows,
        ];
    }

    $contentTypeOverrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $workbookSheetsXml = '';
    $workbookRelsXml = '';

    $files = [
        '[Content_Types].xml' => '',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
    ];

    foreach ($sheetDefs as $sheetIndex => $sheetDef) {
        $sheetId = $sheetIndex + 1;
        $relId = 'rId' . $sheetId;
        $sheetPath = 'xl/worksheets/sheet' . $sheetId . '.xml';

        $contentTypeOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbookSheetsXml .= '<sheet name="' . xml_escape($sheetDef['title']) . '" sheetId="' . $sheetId . '" r:id="' . $relId . '"/>';
        $workbookRelsXml .= '<Relationship Id="' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
        $files[$sheetPath] = xlsx_sheet_xml($sheetDef['title'], $sheetDef['rows']);
    }

    $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . $contentTypeOverrides
        . '</Types>';

    $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $workbookSheetsXml . '</sheets>'
        . '</workbook>';

    $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $workbookRelsXml
        . '</Relationships>';

    if (class_exists('ZipArchive')) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpFile !== false) {
            $zip = new ZipArchive();
            if ($zip->open($tmpFile, ZipArchive::OVERWRITE) === true) {
                foreach ($files as $path => $content) {
                    $zip->addFromString(str_replace('\\', '/', $path), (string) $content);
                }
                $zip->close();
                $bytes = (string) @file_get_contents($tmpFile);
                @unlink($tmpFile);
                if ($bytes !== '') {
                    return $bytes;
                }
            } else {
                @unlink($tmpFile);
            }
        }
    }

    $zipData = '';
    $centralDirectory = '';
    $offset = 0;

    foreach ($files as $path => $content) {
        $path = str_replace('\\', '/', $path);
        $data = $content;
        $crc = crc32($data);
        if ($crc < 0) {
            $crc = $crc + 4294967296;
        }
        $compressed = gzdeflate($data);
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $pathLength = strlen($path);

        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $pathLength, 0);
        $zipData .= $localHeader . $path . $compressed;

        $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, 0, 0, $crc, $compressedSize, $uncompressedSize, $pathLength, 0, 0, 0, 0, 0, $offset) . $path;
        $offset += strlen($localHeader) + $pathLength + $compressedSize;
    }

    $centralDirectorySize = strlen($centralDirectory);
    $endRecord = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralDirectorySize, $offset, 0);
    return $zipData . $centralDirectory . $endRecord;
}

function output_xlsx_download($filename, $xlsxBytes) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsxBytes));
    echo $xlsxBytes;
    exit;
}

if (!in_array($action, $publicActions, true)) {
    require_login();
}

switch ($action) {
    case 'listUsers':
        require_admin();
        if (!ensure_users_table($mysqli)) {
            respond(['success' => false, 'message' => 'Could not prepare users table.']);
        }
        $stmt = ensure_statement($mysqli->prepare('SELECT id, username, role, created_at FROM users ORDER BY id'), $mysqli, 'Could not load login users');
        execute_statement_or_fail($stmt, 'Could not load login users');
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        respond(['success' => true, 'users' => $users]);
        break;

    case 'saveUser':
        require_admin();
        if (!ensure_users_table($mysqli)) {
            respond(['success' => false, 'message' => 'Could not prepare users table. Please make sure the users role column supports admin, hr, it, and viewer.']);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $username = sanitize($_POST['username'] ?? '');
        $password = trim((string) ($_POST['password'] ?? ''));
        $submittedRole = strtolower(sanitize($_POST['role'] ?? 'viewer'));

        if ($username === '' || !preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $username)) {
            respond(['success' => false, 'message' => 'User ID must be 1-60 chars using letters, numbers, dot, dash, or underscore.']);
        }

        $role = $submittedRole;
        $designationValue = '';
        $empRoleStmt = ensure_statement($mysqli->prepare('SELECT can_edit_attendance, designation FROM employees WHERE id = ? LIMIT 1'), $mysqli, 'Could not validate employee role');
        $empRoleStmt->bind_param('s', $username);
        execute_statement_or_fail($empRoleStmt, 'Could not validate employee role');
        $employee = $empRoleStmt->get_result()->fetch_assoc();
        $empRoleStmt->close();

        if ($employee) {
            $designationValue = (string)($employee['designation'] ?? '');
            if (is_it_designation($designationValue)) {
                $role = 'it';
            } else {
                $role = ((int)($employee['can_edit_attendance'] ?? 0) === 1) ? 'hr' : 'viewer';
            }
        } elseif ($id <= 0) {
            respond(['success' => false, 'message' => 'User ID must match an existing Staff ID from Manage Staff.']);
        }

        if (!in_array($role, ['admin', 'hr', 'it', 'viewer'], true)) {
            respond(['success' => false, 'message' => 'Invalid role selected.']);
        }

        $usesDefaultPassword = should_use_default_employee_password($role);
        $effectivePassword = $usesDefaultPassword ? '123' : $password;

        if ($id > 0) {
            $checkStmt = ensure_statement($mysqli->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1'), $mysqli, 'Could not validate login user');
            $checkStmt->bind_param('si', $username, $id);
            execute_statement_or_fail($checkStmt, 'Could not validate login user');
            $exists = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            if ($exists) {
                respond(['success' => false, 'message' => 'User ID already exists.']);
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    respond(['success' => false, 'message' => 'Password must be at least 6 characters.']);
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = ensure_statement($mysqli->prepare('UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?'), $mysqli, 'Could not prepare login user update');
                $stmt->bind_param('sssi', $username, $hash, $role, $id);
            } else {
                $stmt = ensure_statement($mysqli->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?'), $mysqli, 'Could not prepare login user update');
                $stmt->bind_param('ssi', $username, $role, $id);
            }
            execute_statement_or_fail($stmt, 'Could not update login user');
            $stmt->close();
            respond(['success' => true, 'message' => 'User updated successfully.']);
        }

        if (!$usesDefaultPassword && strlen($effectivePassword) < 6) {
            respond(['success' => false, 'message' => 'Password must be at least 6 characters for HR/Admin/IT users.']);
        }
        $existsStmt = ensure_statement($mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1'), $mysqli, 'Could not validate login user');
        $existsStmt->bind_param('s', $username);
        execute_statement_or_fail($existsStmt, 'Could not validate login user');
        $existing = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();
        if ($existing) {
            respond(['success' => false, 'message' => 'User ID already exists.']);
        }

        $hash = password_hash($effectivePassword, PASSWORD_BCRYPT);
        $stmt = ensure_statement($mysqli->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'), $mysqli, 'Could not prepare login user create');
        $stmt->bind_param('sss', $username, $hash, $role);
        execute_statement_or_fail($stmt, 'Could not create login user');
        $stmt->close();
        $createMessage = $usesDefaultPassword
            ? 'User created successfully. Default employee password is set to 123.'
            : 'User created successfully.';
        respond(['success' => true, 'message' => $createMessage]);
        break;

    case 'deleteUser':
        require_admin();
        $id = (int) ($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Valid user ID is required.']);
        }
        if ((int)($_SESSION['user_id'] ?? 0) === $id) {
            respond(['success' => false, 'message' => 'You cannot delete your own logged-in account.']);
        }

        $countRes = $mysqli->query('SELECT COUNT(*) AS cnt FROM users');
        $totalUsers = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
        if ($totalUsers <= 1) {
            respond(['success' => false, 'message' => 'At least one user account must remain.']);
        }

        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected < 1) {
            respond(['success' => false, 'message' => 'User not found.']);
        }

        respond(['success' => true, 'message' => 'User deleted successfully.']);
        break;

    case 'listEmployees':
        $syncErrors = [];
        sync_all_employee_login_users($mysqli, $syncErrors);
        $hasDesig = ensure_designation_column($mysqli);
        $hasHrPerm = ensure_hr_permission_column($mysqli);
        $hasVehiclePunches = ensure_vehicle_punches_table($mysqli);
        $viewerEmpId = '';
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => true, 'employees' => []]);
            }
        }
        $selectCols = 'id, name, ' . ($hasDesig ? 'designation, ' : '"" AS designation, ') . 'department, ' . ($hasHrPerm ? 'can_edit_attendance, ' : '0 AS can_edit_attendance, ');
        if ($hasVehiclePunches) {
            $selectCols .= "COALESCE((SELECT vp.vehicle_name FROM vehicle_punches vp WHERE vp.emp_id = employees.id AND vp.session_type = 'start' AND NOT EXISTS (SELECT 1 FROM vehicle_punches v2 WHERE v2.session_token = vp.session_token AND v2.session_type = 'end') ORDER BY vp.timestamp DESC, vp.id DESC LIMIT 1), '') AS last_vehicle_name, ";
            $selectCols .= "COALESCE((SELECT vp.vehicle_purpose FROM vehicle_punches vp WHERE vp.emp_id = employees.id AND vp.session_type = 'start' AND NOT EXISTS (SELECT 1 FROM vehicle_punches v2 WHERE v2.session_token = vp.session_token AND v2.session_type = 'end') ORDER BY vp.timestamp DESC, vp.id DESC LIMIT 1), '') AS last_vehicle_purpose";
        } else {
            $selectCols .= "'' AS last_vehicle_name, '' AS last_vehicle_purpose";
        }
        $sql = "SELECT {$selectCols} FROM employees";
        if ($viewerEmpId !== '') {
            $sql .= ' WHERE id = ?';
        }
        $sql .= ' ORDER BY id';
        $stmt = $mysqli->prepare($sql);
        if ($viewerEmpId !== '') {
            $stmt->bind_param('s', $viewerEmpId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $employees = $result->fetch_all(MYSQLI_ASSOC);
        respond(['success' => true, 'employees' => $employees]);
        break;

    case 'saveEmployee':
        require_admin();
        $id = sanitize($_POST['id'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $department = sanitize($_POST['department'] ?? 'Production');
        $canEditAttendance = isset($_POST['canEditAttendance']) && $_POST['canEditAttendance'] === '1' ? 1 : 0;
        if (!$id || !$name) {
            respond(['success' => false, 'message' => 'Employee ID and name are required.']);
        }
        if (!validate_employee_id($id)) {
            respond(['success' => false, 'message' => 'Employee ID must be 1-20 chars using letters, numbers, dot, dash, or underscore.']);
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            respond(['success' => false, 'message' => 'Employee name must be between 2 and 100 characters.']);
        }
        if (mb_strlen($designation) > 100) {
            respond(['success' => false, 'message' => 'Designation cannot exceed 100 characters.']);
        }
        if (!is_valid_department($department)) {
            respond(['success' => false, 'message' => 'Invalid department selected.']);
        }
        $exists = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
        $exists->bind_param('s', $id);
        $exists->execute();
        $exists->bind_result($count);
        $exists->fetch();
        $exists->close();

        $withDesig = ensure_designation_column($mysqli);
        $withHrPermission = ensure_hr_permission_column($mysqli);

        if ($count > 0) {
            $setCols = 'name = ?, ' . ($withDesig ? 'designation = ?, ' : '') . 'department = ?' . ($withHrPermission ? ', can_edit_attendance = ?' : '');
            $sql = "UPDATE employees SET {$setCols} WHERE id = ?";
            $stmt = $mysqli->prepare($sql);
            if ($withDesig && $withHrPermission) {
                $stmt->bind_param('sssis', $name, $designation, $department, $canEditAttendance, $id);
            } elseif ($withDesig) {
                $stmt->bind_param('ssss', $name, $designation, $department, $id);
            } elseif ($withHrPermission) {
                $stmt->bind_param('ssis', $name, $department, $canEditAttendance, $id);
            } else {
                $stmt->bind_param('sss', $name, $department, $id);
            }
            $stmt->execute();
        } else {
            if ($withDesig && $withHrPermission) {
                $stmt = $mysqli->prepare('INSERT INTO employees (id, name, designation, department, can_edit_attendance) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssi', $id, $name, $designation, $department, $canEditAttendance);
            } elseif ($withDesig) {
                $stmt = $mysqli->prepare('INSERT INTO employees (id, name, designation, department) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssss', $id, $name, $designation, $department);
            } elseif ($withHrPermission) {
                $stmt = $mysqli->prepare('INSERT INTO employees (id, name, department, can_edit_attendance) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $id, $name, $department, $canEditAttendance);
            } else {
                $stmt = $mysqli->prepare('INSERT INTO employees (id, name, department) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $id, $name, $department);
            }
            $stmt->execute();
        }

        $loginRole = derive_employee_login_role($designation, $canEditAttendance);
        $defaultPassword = should_use_default_employee_password($loginRole) ? '123' : $id;
        $syncError = '';
        if (!upsert_employee_login_user($mysqli, $id, $designation, $canEditAttendance, $syncError)) {
            respond(['success' => false, 'message' => $syncError ?: 'Could not create employee login user.']);
        }

        respond([
            'success' => true,
            'message' => 'Employee saved successfully. Viewer password is set to 123; HR/Admin/IT use secure credentials.',
            'loginUsername' => $id,
            'loginPassword' => isset($defaultPassword) ? $defaultPassword : '123',
            'loginRole' => isset($loginRole) ? $loginRole : 'viewer',
        ]);
        break;

    case 'deleteEmployee':
        require_admin();
        $id = sanitize($_REQUEST['id'] ?? '');
        if (!$id) {
            respond(['success' => false, 'message' => 'Employee ID required.']);
        }
        if (!validate_employee_id($id)) {
            respond(['success' => false, 'message' => 'Invalid employee ID format.']);
        }

        $hasVehiclePunches = ensure_vehicle_punches_table($mysqli);
        $hasUsersTable = ensure_users_table($mysqli);

        $mysqli->begin_transaction();
        try {
            if ($hasVehiclePunches) {
                $vehicleStmt = $mysqli->prepare('DELETE FROM vehicle_punches WHERE emp_id = ?');
                if (!$vehicleStmt) {
                    throw new RuntimeException('Could not prepare vehicle cleanup statement.');
                }
                $vehicleStmt->bind_param('s', $id);
                if (!$vehicleStmt->execute()) {
                    throw new RuntimeException('Could not remove vehicle records: ' . $vehicleStmt->error);
                }
                $vehicleStmt->close();
            }

            $attendanceStmt = $mysqli->prepare('DELETE FROM attendance_punches WHERE emp_id = ?');
            if (!$attendanceStmt) {
                throw new RuntimeException('Could not prepare attendance cleanup statement.');
            }
            $attendanceStmt->bind_param('s', $id);
            if (!$attendanceStmt->execute()) {
                throw new RuntimeException('Could not remove attendance records: ' . $attendanceStmt->error);
            }
            $attendanceStmt->close();

            if ($hasUsersTable) {
                $userStmt = $mysqli->prepare('DELETE FROM users WHERE username = ?');
                if (!$userStmt) {
                    throw new RuntimeException('Could not prepare login cleanup statement.');
                }
                $userStmt->bind_param('s', $id);
                if (!$userStmt->execute()) {
                    throw new RuntimeException('Could not remove login user: ' . $userStmt->error);
                }
                $userStmt->close();
            }

            $employeeStmt = $mysqli->prepare('DELETE FROM employees WHERE id = ?');
            if (!$employeeStmt) {
                throw new RuntimeException('Could not prepare employee delete statement.');
            }
            $employeeStmt->bind_param('s', $id);
            if (!$employeeStmt->execute()) {
                throw new RuntimeException('Could not delete employee: ' . $employeeStmt->error);
            }
            $deletedEmployees = $employeeStmt->affected_rows;
            $employeeStmt->close();

            if ($deletedEmployees < 1) {
                throw new RuntimeException('Employee not found.');
            }

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            respond(['success' => false, 'message' => $e->getMessage()]);
        }

        respond(['success' => true, 'message' => 'Employee deleted successfully.']);
        break;

    case 'listHolidays':
        $stmt = $mysqli->prepare('SELECT date, description FROM holidays ORDER BY date');
        $stmt->execute();
        $result = $stmt->get_result();
        $holidays = $result->fetch_all(MYSQLI_ASSOC);
        respond(['success' => true, 'holidays' => $holidays]);
        break;

    case 'saveHoliday':
        $date = sanitize($_POST['date'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if (!$date || !$description) {
            respond(['success' => false, 'message' => 'Holiday date and description are required.']);
        }
        if (!is_valid_date_ymd($date)) {
            respond(['success' => false, 'message' => 'Holiday date must be in YYYY-MM-DD format.']);
        }
        require_admin();
        if (mb_strlen($description) < 2 || mb_strlen($description) > 255) {
            respond(['success' => false, 'message' => 'Holiday description must be between 2 and 255 characters.']);
        }
        $stmt = $mysqli->prepare('INSERT INTO holidays (`date`, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)');
        $stmt->bind_param('ss', $date, $description);
        $stmt->execute();
        respond(['success' => true, 'message' => 'Holiday saved successfully.']);
        break;

    case 'deleteHoliday':
        $date = sanitize($_REQUEST['date'] ?? '');
        if (!$date || !is_valid_date_ymd($date)) respond(['success' => false, 'message' => 'Valid holiday date is required.']);
        require_admin();
        $stmt = $mysqli->prepare('DELETE FROM holidays WHERE `date` = ?');
        $stmt->bind_param('s', $date);
        $stmt->execute();
        respond(['success' => true, 'message' => 'Holiday deleted successfully.']);
        break;

    case 'listLeaveRequests':
        if (!ensure_leave_requests_table($mysqli)) {
            respond(['success' => false, 'message' => 'Leave request table is not ready.']);
        }
        $start = sanitize($_GET['start'] ?? '');
        $end = sanitize($_GET['end'] ?? '');
        $department = sanitize($_GET['department'] ?? 'All');
        $empId = sanitize($_GET['empId'] ?? 'All');
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => true, 'leaveRequests' => []]);
            }
            $empId = $viewerEmpId;
            $department = 'All';
        }

        $sql = 'SELECT lr.id, lr.emp_id, e.name, e.department, lr.leave_type, lr.start_date, lr.end_date, lr.leave_days, lr.reason, lr.status, lr.remarks, lr.created_at, lr.updated_at FROM leave_requests lr JOIN employees e ON e.id = lr.emp_id WHERE 1=1';
        $params = [];
        $types = '';

        if ($start !== '' && is_valid_date_ymd($start)) {
            $sql .= ' AND lr.start_date >= ?';
            $params[] = $start;
            $types .= 's';
        }
        if ($end !== '' && is_valid_date_ymd($end)) {
            $sql .= ' AND lr.end_date <= ?';
            $params[] = $end;
            $types .= 's';
        }
        if ($department !== 'All' && is_valid_department($department)) {
            $sql .= ' AND e.department = ?';
            $params[] = $department;
            $types .= 's';
        }
        if ($empId !== 'All') {
            if (!validate_employee_id($empId)) {
                respond(['success' => false, 'message' => 'Invalid employee filter.']);
            }
            $sql .= ' AND lr.emp_id = ?';
            $params[] = $empId;
            $types .= 's';
        }

        $sql .= ' ORDER BY lr.start_date DESC, lr.id DESC';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            respond(['success' => false, 'message' => 'Could not prepare leave request listing.']);
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        respond(['success' => true, 'leaveRequests' => $rows]);
        break;

    case 'saveLeaveRequest':
        require_login();
        $viewerMode = is_viewer_role();
        if (!$viewerMode) {
            require_admin();
        }
        if (!ensure_leave_requests_table($mysqli)) {
            respond(['success' => false, 'message' => 'Leave request table is not ready.']);
        }

        $id = (int)($_POST['id'] ?? 0);
        $empId = sanitize($_POST['empId'] ?? '');
        $leaveType = sanitize($_POST['leaveType'] ?? '');
        $startDate = sanitize($_POST['startDate'] ?? '');
        $endDate = sanitize($_POST['endDate'] ?? '');
        $leaveDays = (float)($_POST['leaveDays'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $status = sanitize($_POST['status'] ?? 'Pending');
        $remarks = sanitize($_POST['remarks'] ?? '');

        if ($viewerMode) {
            if ($id > 0) {
                respond(['success' => false, 'message' => 'Only admin can edit leave requests.']);
            }
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => false, 'message' => 'Viewer account is not linked to an employee profile.']);
            }
            $empId = $viewerEmpId;
            $status = 'Pending';
            $pendingStmt = $mysqli->prepare('SELECT COUNT(*) FROM leave_requests WHERE emp_id = ? AND status = "Pending"');
            if ($pendingStmt) {
                $pendingStmt->bind_param('s', $empId);
                $pendingStmt->execute();
                $pendingStmt->bind_result($pendingCount);
                $pendingCount = 0;
                $pendingStmt->fetch();
                $pendingStmt->close();
                if ($pendingCount > 0) {
                    respond(['success' => false, 'message' => 'You already have a pending leave request. Please wait for admin approval.']);
                }
            }
        }

        if (!validate_employee_id($empId)) {
            respond(['success' => false, 'message' => 'Invalid employee ID.']);
        }
        if (!is_valid_date_ymd($startDate) || !is_valid_date_ymd($endDate)) {
            respond(['success' => false, 'message' => 'Start and end dates must be valid YYYY-MM-DD values.']);
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            respond(['success' => false, 'message' => 'End date cannot be earlier than start date.']);
        }
        if (mb_strlen($leaveType) < 2 || mb_strlen($leaveType) > 40) {
            respond(['success' => false, 'message' => 'Leave type must be between 2 and 40 characters.']);
        }
        if ($leaveDays <= 0 || $leaveDays > 365) {
            respond(['success' => false, 'message' => 'Leave days must be greater than 0 and not more than 365.']);
        }
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            respond(['success' => false, 'message' => 'Reason must be between 3 and 500 characters.']);
        }
        if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            respond(['success' => false, 'message' => 'Invalid leave status.']);
        }
        if (mb_strlen($remarks) > 500) {
            respond(['success' => false, 'message' => 'Remarks cannot exceed 500 characters.']);
        }

        $empStmt = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
        $empStmt->bind_param('s', $empId);
        $empStmt->execute();
        $empStmt->bind_result($empExists);
        $empExists = 0;
        $empStmt->fetch();
        $empStmt->close();
        if ($empExists < 1) {
            respond(['success' => false, 'message' => 'Employee not found.']);
        }

        if ($id > 0) {
            $stmt = $mysqli->prepare('UPDATE leave_requests SET emp_id = ?, leave_type = ?, start_date = ?, end_date = ?, leave_days = ?, reason = ?, status = ?, remarks = ? WHERE id = ?');
            if (!$stmt) {
                respond(['success' => false, 'message' => 'Could not prepare leave update statement.']);
            }
            $stmt->bind_param('ssssdsssi', $empId, $leaveType, $startDate, $endDate, $leaveDays, $reason, $status, $remarks, $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected < 0) {
                respond(['success' => false, 'message' => 'Leave request update failed.']);
            }
            respond(['success' => true, 'message' => 'Leave request updated successfully.']);
        }

        $stmt = $mysqli->prepare('INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, leave_days, reason, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            respond(['success' => false, 'message' => 'Could not prepare leave insert statement.']);
        }
        $stmt->bind_param('ssssdsss', $empId, $leaveType, $startDate, $endDate, $leaveDays, $reason, $status, $remarks);
        $stmt->execute();
        $stmt->close();
        respond(['success' => true, 'message' => 'Leave request saved successfully.']);
        break;

    case 'deleteLeaveRequest':
        require_admin();
        if (!ensure_leave_requests_table($mysqli)) {
            respond(['success' => false, 'message' => 'Leave request table is not ready.']);
        }
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Valid leave request ID is required.']);
        }
        $stmt = $mysqli->prepare('DELETE FROM leave_requests WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        if ($deleted < 1) {
            respond(['success' => false, 'message' => 'Leave request not found.']);
        }
        respond(['success' => true, 'message' => 'Leave request deleted successfully.']);
        break;

    case 'listTravelOrders':
        if (!ensure_travel_orders_table($mysqli)) {
            respond(['success' => false, 'message' => 'Travel order table is not ready.']);
        }
        ensure_designation_column($mysqli);
        $start = sanitize($_GET['start'] ?? '');
        $end = sanitize($_GET['end'] ?? '');
        $department = sanitize($_GET['department'] ?? 'All');
        $empId = sanitize($_GET['empId'] ?? 'All');
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => true, 'travelOrders' => []]);
            }
            $empId = $viewerEmpId;
            $department = 'All';
        }

        $sql = 'SELECT t.*, e.name, e.department, e.designation FROM travel_orders t JOIN employees e ON e.id = t.emp_id WHERE 1=1';
        $params = [];
        $types = '';

        if ($start !== '' && is_valid_date_ymd($start)) {
            $sql .= ' AND (t.form_date IS NULL OR t.form_date >= ?)';
            $params[] = $start;
            $types .= 's';
        }
        if ($end !== '' && is_valid_date_ymd($end)) {
            $sql .= ' AND (t.form_date IS NULL OR t.form_date <= ?)';
            $params[] = $end;
            $types .= 's';
        }
        if ($department !== 'All' && is_valid_department($department)) {
            $sql .= ' AND e.department = ?';
            $params[] = $department;
            $types .= 's';
        }
        if ($empId !== 'All') {
            if (!validate_employee_id($empId)) {
                respond(['success' => false, 'message' => 'Invalid employee filter.']);
            }
            $sql .= ' AND t.emp_id = ?';
            $params[] = $empId;
            $types .= 's';
        }

        $sql .= ' ORDER BY COALESCE(t.form_date, t.created_at) DESC, t.id DESC';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            respond(['success' => false, 'message' => 'Could not prepare travel order listing.']);
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        respond(['success' => true, 'travelOrders' => $rows]);
        break;

    case 'saveTravelOrder':
        require_admin();
        if (!ensure_travel_orders_table($mysqli)) {
            respond(['success' => false, 'message' => 'Travel order table is not ready.']);
        }

        $id = (int)($_POST['id'] ?? 0);
        $formDate = sanitize($_POST['formDate'] ?? '');
        $empId = sanitize($_POST['empId'] ?? '');
        $branch = sanitize($_POST['branch'] ?? '');
        $destination = sanitize($_POST['destination'] ?? '');
        $purpose = sanitize($_POST['purpose'] ?? '');
        $departureDate = sanitize($_POST['departureDate'] ?? '');
        $arrivalDate = sanitize($_POST['arrivalDate'] ?? '');
        $modeOfTravel = sanitize($_POST['modeOfTravel'] ?? 'Office Vehicle');
        $modeOther = sanitize($_POST['modeOther'] ?? '');
        $advanceAmount = (float)($_POST['advanceAmount'] ?? 0);
        $requestedBy = sanitize($_POST['requestedBy'] ?? '');
        $checkedBy = sanitize($_POST['checkedBy'] ?? '');
        $approvedBy = sanitize($_POST['approvedBy'] ?? '');
        $totalDays = trim((string)($_POST['totalDays'] ?? ''));
        $tadaPerDay = trim((string)($_POST['tadaPerDay'] ?? ''));
        $otherExpenses = trim((string)($_POST['otherExpenses'] ?? ''));
        $totalExpenses = trim((string)($_POST['totalExpenses'] ?? ''));
        $settlementRequestedBy = sanitize($_POST['settlementRequestedBy'] ?? '');
        $settlementCheckedBy = sanitize($_POST['settlementCheckedBy'] ?? '');
        $settlementApprovedBy = sanitize($_POST['settlementApprovedBy'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        if (!validate_employee_id($empId)) {
            respond(['success' => false, 'message' => 'Valid employee ID is required.']);
        }
        if ($formDate !== '' && !is_valid_date_ymd($formDate)) {
            respond(['success' => false, 'message' => 'Form date must be valid YYYY-MM-DD.']);
        }
        if ($departureDate !== '' && !is_valid_date_ymd($departureDate)) {
            respond(['success' => false, 'message' => 'Departure date must be valid YYYY-MM-DD.']);
        }
        if ($arrivalDate !== '' && !is_valid_date_ymd($arrivalDate)) {
            respond(['success' => false, 'message' => 'Arrival date must be valid YYYY-MM-DD.']);
        }
        if ($departureDate !== '' && $arrivalDate !== '' && strtotime($arrivalDate) < strtotime($departureDate)) {
            respond(['success' => false, 'message' => 'Arrival date cannot be earlier than departure date.']);
        }
        if (mb_strlen($destination) < 2 || mb_strlen($destination) > 200) {
            respond(['success' => false, 'message' => 'Destination must be between 2 and 200 characters.']);
        }
        if (mb_strlen($purpose) < 3 || mb_strlen($purpose) > 500) {
            respond(['success' => false, 'message' => 'Purpose must be between 3 and 500 characters.']);
        }
        if (!in_array($modeOfTravel, ['Office Vehicle', 'Air', 'Bus', 'Other'], true)) {
            respond(['success' => false, 'message' => 'Invalid mode of travel.']);
        }
        if (mb_strlen($modeOther) > 120) {
            respond(['success' => false, 'message' => 'Other mode cannot exceed 120 characters.']);
        }
        if ($advanceAmount < 0) {
            respond(['success' => false, 'message' => 'Advance amount cannot be negative.']);
        }
        if (mb_strlen($branch) > 120 || mb_strlen($requestedBy) > 120 || mb_strlen($checkedBy) > 120 || mb_strlen($approvedBy) > 120 || mb_strlen($settlementRequestedBy) > 120 || mb_strlen($settlementCheckedBy) > 120 || mb_strlen($settlementApprovedBy) > 120) {
            respond(['success' => false, 'message' => 'Name/branch fields cannot exceed 120 characters.']);
        }
        if (mb_strlen($notes) > 700) {
            respond(['success' => false, 'message' => 'Notes cannot exceed 700 characters.']);
        }

        $empStmt = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
        $empStmt->bind_param('s', $empId);
        $empStmt->execute();
        $empStmt->bind_result($empExists);
        $empExists = 0;
        $empStmt->fetch();
        $empStmt->close();
        if ($empExists < 1) {
            respond(['success' => false, 'message' => 'Employee not found.']);
        }

        $totalDaysValue = ($totalDays === '') ? null : (float)$totalDays;
        $tadaPerDayValue = ($tadaPerDay === '') ? null : (float)$tadaPerDay;
        $otherExpensesValue = ($otherExpenses === '') ? null : (float)$otherExpenses;
        $totalExpensesValue = ($totalExpenses === '') ? null : (float)$totalExpenses;
        if ($totalDaysValue !== null && $totalDaysValue < 0) {
            respond(['success' => false, 'message' => 'Total days cannot be negative.']);
        }
        if ($tadaPerDayValue !== null && $tadaPerDayValue < 0) {
            respond(['success' => false, 'message' => 'TADA per day cannot be negative.']);
        }
        if ($otherExpensesValue !== null && $otherExpensesValue < 0) {
            respond(['success' => false, 'message' => 'Other expenses cannot be negative.']);
        }
        if ($totalExpensesValue !== null && $totalExpensesValue < 0) {
            respond(['success' => false, 'message' => 'Total expenses cannot be negative.']);
        }

        $formDateValue = ($formDate === '') ? null : $formDate;
        $departureDateValue = ($departureDate === '') ? null : $departureDate;
        $arrivalDateValue = ($arrivalDate === '') ? null : $arrivalDate;

        if ($id > 0) {
            $stmt = $mysqli->prepare('UPDATE travel_orders SET form_date = ?, emp_id = ?, branch = ?, destination = ?, purpose = ?, departure_date = ?, arrival_date = ?, mode_of_travel = ?, mode_other = ?, advance_amount = ?, requested_by = ?, checked_by = ?, approved_by = ?, total_days = ?, tada_per_day = ?, other_expenses = ?, total_expenses = ?, settlement_requested_by = ?, settlement_checked_by = ?, settlement_approved_by = ?, notes = ? WHERE id = ?');
            if (!$stmt) {
                respond(['success' => false, 'message' => 'Could not prepare travel order update.']);
            }
            $stmt->bind_param('sssssssssdsssddddssssi', $formDateValue, $empId, $branch, $destination, $purpose, $departureDateValue, $arrivalDateValue, $modeOfTravel, $modeOther, $advanceAmount, $requestedBy, $checkedBy, $approvedBy, $totalDaysValue, $tadaPerDayValue, $otherExpensesValue, $totalExpensesValue, $settlementRequestedBy, $settlementCheckedBy, $settlementApprovedBy, $notes, $id);
            $stmt->execute();
            $stmt->close();
            respond(['success' => true, 'message' => 'Travel order updated successfully.']);
        }

        $stmt = $mysqli->prepare('INSERT INTO travel_orders (form_date, emp_id, branch, destination, purpose, departure_date, arrival_date, mode_of_travel, mode_other, advance_amount, requested_by, checked_by, approved_by, total_days, tada_per_day, other_expenses, total_expenses, settlement_requested_by, settlement_checked_by, settlement_approved_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            respond(['success' => false, 'message' => 'Could not prepare travel order insert.']);
        }
        $stmt->bind_param('sssssssssdsssddddssss', $formDateValue, $empId, $branch, $destination, $purpose, $departureDateValue, $arrivalDateValue, $modeOfTravel, $modeOther, $advanceAmount, $requestedBy, $checkedBy, $approvedBy, $totalDaysValue, $tadaPerDayValue, $otherExpensesValue, $totalExpensesValue, $settlementRequestedBy, $settlementCheckedBy, $settlementApprovedBy, $notes);
        $stmt->execute();
        $stmt->close();
        respond(['success' => true, 'message' => 'Travel order saved successfully.']);
        break;

    case 'deleteTravelOrder':
        require_admin();
        if (!ensure_travel_orders_table($mysqli)) {
            respond(['success' => false, 'message' => 'Travel order table is not ready.']);
        }
        $id = (int)($_REQUEST['id'] ?? 0);
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Valid travel order ID is required.']);
        }
        $stmt = $mysqli->prepare('DELETE FROM travel_orders WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        if ($deleted < 1) {
            respond(['success' => false, 'message' => 'Travel order not found.']);
        }
        respond(['success' => true, 'message' => 'Travel order deleted successfully.']);
        break;

    case 'punch':
        $empId = sanitize($_POST['empId'] ?? '');
        $empName = sanitize($_POST['empName'] ?? '');
        $empDepartment = sanitize($_POST['empDepartment'] ?? '');
        $empDesignation = sanitize($_POST['empDesignation'] ?? '');
        $employeePassword = (string)($_POST['employeePassword'] ?? '');
        $timestamp = parse_datetime_input($_POST['timestamp'] ?? '');
        if (!verify_employee_punch_profile($mysqli, $empId, $empName, $empDepartment, $empDesignation)) {
            respond(['success' => false, 'message' => 'Employee details do not match. Please select employee again.']);
        }
        if ($employeePassword === '') {
            respond(['success' => false, 'message' => 'Employee password is required to punch.']);
        }
        if (!verify_employee_password($mysqli, $empId, $employeePassword)) {
            respond(['success' => false, 'message' => 'Invalid employee ID or password.']);
        }
        register_employee_punch($mysqli, $empId, $timestamp, '', '', false);
        break;

        require_admin();
    case 'punchVehicle':
        $empId = sanitize($_POST['empId'] ?? '');
        $empName = sanitize($_POST['empName'] ?? '');
        $empDepartment = sanitize($_POST['empDepartment'] ?? '');
        $empDesignation = sanitize($_POST['empDesignation'] ?? '');
        $employeePassword = (string)($_POST['employeePassword'] ?? '');
        $timestamp = parse_datetime_input($_POST['timestamp'] ?? '');
        $vehicleName = sanitize($_POST['vehicleName'] ?? '');
        $vehiclePurpose = sanitize($_POST['vehiclePurpose'] ?? '');
        if (!verify_employee_punch_profile($mysqli, $empId, $empName, $empDepartment, $empDesignation)) {
            respond(['success' => false, 'message' => 'Employee details do not match. Please select employee again.']);
        }
        if ($employeePassword === '') {
            respond(['success' => false, 'message' => 'Employee password is required to punch.']);
        }
        if (!verify_employee_password($mysqli, $empId, $employeePassword)) {
            respond(['success' => false, 'message' => 'Invalid employee ID or password.']);
        }
        register_employee_punch($mysqli, $empId, $timestamp, $vehicleName, $vehiclePurpose, true);
        break;

    case 'attendanceRecords':
        $start = sanitize($_GET['start'] ?? date('Y-m-01'));
        $end = sanitize($_GET['end'] ?? date('Y-m-d'));
        $department = sanitize($_GET['department'] ?? 'All');
        $empIdFilter = sanitize($_GET['empId'] ?? 'All');
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => true, 'records' => [], 'summary' => []]);
            }
            $empIdFilter = $viewerEmpId;
            $department = 'All';
        }
        if ($empIdFilter !== 'All' && !validate_employee_id($empIdFilter)) {
            respond(['success' => false, 'message' => 'Invalid employee filter.']);
        }
        $params = [$start, $end . ' 23:59:59'];
        $query = 'SELECT p.emp_id, p.timestamp, e.name, e.department FROM attendance_punches p JOIN employees e ON p.emp_id = e.id WHERE p.timestamp BETWEEN ? AND ?';
        if ($department !== 'All') {
            $query .= ' AND e.department = ?';
            $params[] = $department;
        }
        if ($empIdFilter !== 'All') {
            $query .= ' AND e.id = ?';
            $params[] = $empIdFilter;
        }
        $query .= ' ORDER BY p.emp_id, p.timestamp';

        $stmt = $mysqli->prepare($query);
        if ($department !== 'All' && $empIdFilter !== 'All') {
            $stmt->bind_param('ssss', $params[0], $params[1], $params[2], $params[3]);
        } elseif ($department !== 'All' || $empIdFilter !== 'All') {
            $stmt->bind_param('sss', $params[0], $params[1], $params[2]);
        } else {
            $stmt->bind_param('ss', $params[0], $params[1]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $punches = $result->fetch_all(MYSQLI_ASSOC);

        $holidayResult = $mysqli->query('SELECT `date`, description FROM holidays');
        $holidayMap = [];
        while ($row = $holidayResult->fetch_assoc()) {
            $holidayMap[$row['date']] = $row['description'];
        }

        $employeeQuery = 'SELECT id, name, department FROM employees';
        $employeeParams = [];
        $employeeWhere = [];
        if ($department !== 'All') {
            $employeeWhere[] = 'department = ?';
            $employeeParams[] = $department;
        }
        if ($empIdFilter !== 'All') {
            $employeeWhere[] = 'id = ?';
            $employeeParams[] = $empIdFilter;
        }
        if ($employeeWhere) {
            $employeeQuery .= ' WHERE ' . implode(' AND ', $employeeWhere);
        }
        $employeeQuery .= ' ORDER BY id';
        $employeeStmt = $mysqli->prepare($employeeQuery);
        if (count($employeeParams) === 2) {
            $employeeStmt->bind_param('ss', $employeeParams[0], $employeeParams[1]);
        } elseif (count($employeeParams) === 1) {
            $employeeStmt->bind_param('s', $employeeParams[0]);
        }
        $employeeStmt->execute();
        $employeesForRange = $employeeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $records = [];
        foreach ($punches as $punch) {
            $dateKey = substr($punch['timestamp'], 0, 10);
            $key = $punch['emp_id'] . '_' . $dateKey;
            $records[$key]['empId'] = $punch['emp_id'];
            $records[$key]['name'] = $punch['name'];
            $records[$key]['department'] = $punch['department'];
            $records[$key]['date'] = $dateKey;
            $records[$key]['timestamps'][] = $punch['timestamp'];
        }

        if (ensure_vehicle_punches_table($mysqli)) {
            $vehicleParams = [$start, $end . ' 23:59:59'];
            $vehicleQuery = 'SELECT v.emp_id, v.timestamp, v.vehicle_name, v.vehicle_purpose, e.name, e.department FROM vehicle_punches v JOIN employees e ON v.emp_id = e.id WHERE v.timestamp BETWEEN ? AND ?';
            if ($department !== 'All') {
                $vehicleQuery .= ' AND e.department = ?';
                $vehicleParams[] = $department;
            }
            if ($empIdFilter !== 'All') {
                $vehicleQuery .= ' AND e.id = ?';
                $vehicleParams[] = $empIdFilter;
            }
            $vehicleQuery .= ' ORDER BY v.emp_id, v.timestamp';

            $vehicleStmt = $mysqli->prepare($vehicleQuery);
            if ($department !== 'All' && $empIdFilter !== 'All') {
                $vehicleStmt->bind_param('ssss', $vehicleParams[0], $vehicleParams[1], $vehicleParams[2], $vehicleParams[3]);
            } elseif ($department !== 'All' || $empIdFilter !== 'All') {
                $vehicleStmt->bind_param('sss', $vehicleParams[0], $vehicleParams[1], $vehicleParams[2]);
            } else {
                $vehicleStmt->bind_param('ss', $vehicleParams[0], $vehicleParams[1]);
            }
            $vehicleStmt->execute();
            $vehicleRows = $vehicleStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($vehicleRows as $vehicleRow) {
                $dateKey = substr($vehicleRow['timestamp'], 0, 10);
                $key = $vehicleRow['emp_id'] . '_' . $dateKey;
                if (!isset($records[$key])) {
                    $records[$key]['empId'] = $vehicleRow['emp_id'];
                    $records[$key]['name'] = $vehicleRow['name'];
                    $records[$key]['department'] = $vehicleRow['department'];
                    $records[$key]['date'] = $dateKey;
                    $records[$key]['timestamps'] = [];
                }
                $records[$key]['vehicleEntries'][] = trim(($vehicleRow['vehicle_name'] ?? '') . ' - ' . ($vehicleRow['vehicle_purpose'] ?? ''), ' -');
                $records[$key]['vehicleName'] = $vehicleRow['vehicle_name'] ?? '';
                $records[$key]['vehiclePurpose'] = $vehicleRow['vehicle_purpose'] ?? '';
            }
        }

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        foreach ($employeesForRange as $employeeRow) {
            $cursor = clone $startDate;
            while ($cursor <= $endDate) {
                $dateKey = $cursor->format('Y-m-d');
                $key = $employeeRow['id'] . '_' . $dateKey;
                if (!isset($records[$key])) {
                    $records[$key] = [
                        'empId' => $employeeRow['id'],
                        'name' => $employeeRow['name'],
                        'department' => $employeeRow['department'],
                        'date' => $dateKey,
                        'timestamps' => [],
                    ];
                }
                $cursor->modify('+1 day');
            }
        }

        usort($records, static function ($left, $right) {
            $cmpEmp = strcmp((string)($left['empId'] ?? ''), (string)($right['empId'] ?? ''));
            if ($cmpEmp !== 0) {
                return $cmpEmp;
            }
            return strcmp((string)($left['date'] ?? ''), (string)($right['date'] ?? ''));
        });

        $output = [];
        $summaryByEmployee = [];
        foreach ($records as $record) {
            $sessionData = build_session_details($record['timestamps']);
            $totalSeconds = $sessionData['totalSeconds'];
            $totalHours = $totalSeconds / 3600;
            $dayOfWeek = date('w', strtotime($record['date']));
            $isHoliday = isset($holidayMap[$record['date']]);
            $isSpecial = $dayOfWeek == 6 || $isHoliday;
            $dayType = $isHoliday ? 'Holiday (' . $holidayMap[$record['date']] . ')' : ($dayOfWeek == 6 ? 'Saturday Weekend' : 'Weekday');
            $hasAttendance = !empty($record['timestamps']);
            $regularHours = 0;
            $otHours = 0;
            if ($isSpecial) {
                $otHours = $totalHours;
            } else {
                $regularHours = min(8, $totalHours);
                $otHours = max(0, $totalHours - 8);
            }

            $leaveType = '-';
            $leaveDays = 0.0;
            if (!$isSpecial) {
                if ($hasAttendance) {
                    if ((int)($sessionData['sessionCount'] ?? 0) === 1 && $totalHours < 8) {
                        $leaveType = 'Half Leave';
                        $leaveDays = 0.5;
                    } else {
                        $leaveType = 'Present';
                    }
                } else {
                    $leaveType = 'Full Leave';
                    $leaveDays = 1.0;
                }
            }

            $sessionText = $sessionData['sessionText'];
            if (!$hasAttendance) {
                $sessionText = $isSpecial ? 'No attendance' : 'Leave';
            }
            $vehicleEntries = array_values(array_unique($record['vehicleEntries'] ?? []));

            $empId = $record['empId'];
            if (!isset($summaryByEmployee[$empId])) {
                $summaryByEmployee[$empId] = [
                    'empId' => $record['empId'],
                    'name' => $record['name'],
                    'department' => $record['department'],
                    'totalDays' => 0,
                    'weekendDays' => 0,
                    'holidayDays' => 0,
                    'presentDays' => 0,
                    'regularHours' => 0.0,
                    'otHours' => 0.0,
                    'totalHours' => 0.0,
                    'leaveDays' => 0.0,
                    'halfLeaveDays' => 0.0,
                    'fullLeaveDays' => 0.0,
                ];
            }
            $summaryByEmployee[$empId]['totalDays'] += 1;
            if ($dayOfWeek == 6) {
                $summaryByEmployee[$empId]['weekendDays'] += 1;
            }
            if ($isHoliday) {
                $summaryByEmployee[$empId]['holidayDays'] += 1;
            }
            if (!$isSpecial && $leaveType === 'Present') {
                $summaryByEmployee[$empId]['presentDays'] += 1;
            }
            $summaryByEmployee[$empId]['regularHours'] += $regularHours;
            $summaryByEmployee[$empId]['otHours'] += $otHours;
            $summaryByEmployee[$empId]['totalHours'] += $totalHours;
            $summaryByEmployee[$empId]['leaveDays'] += $leaveDays;
            if ($leaveType === 'Half Leave') {
                $summaryByEmployee[$empId]['halfLeaveDays'] += 0.5;
            } elseif ($leaveType === 'Full Leave') {
                $summaryByEmployee[$empId]['fullLeaveDays'] += 1.0;
            }

            $output[] = [
                'empId' => $record['empId'],
                'name' => $record['name'],
                'department' => $record['department'],
                'date' => $record['date'],
                'dayType' => $dayType,
                'sessionCount' => $sessionData['sessionCount'],
                'sessionText' => $sessionText,
                'rawTimes' => implode(', ', array_map(static fn($ts) => substr($ts, 11), $record['timestamps'])),
                'vehicleName' => $record['vehicleName'] ?? '',
                'vehiclePurpose' => $record['vehiclePurpose'] ?? '',
                'vehicleText' => $vehicleEntries ? implode(' | ', $vehicleEntries) : 'No vehicle used',
                'regularHours' => round($regularHours, 2),
                'otHours' => round($otHours, 2),
                'totalHours' => round($totalHours, 2),
                'leaveType' => $leaveType,
                'leaveDays' => round($leaveDays, 2),
                'isSpecial' => $isSpecial,
            ];
        }

        $summaryList = [];
        foreach ($summaryByEmployee as $summary) {
            $summaryList[] = [
                'empId' => $summary['empId'],
                'name' => $summary['name'],
                'department' => $summary['department'],
                'regularHours' => round($summary['regularHours'], 2),
                'otHours' => round($summary['otHours'], 2),
                'totalHours' => round($summary['totalHours'], 2),
                'leaveDays' => round($summary['leaveDays'], 2),
                'halfLeaveDays' => round($summary['halfLeaveDays'], 2),
                'fullLeaveDays' => round($summary['fullLeaveDays'], 2),
            ];
        }
        usort($summaryList, static fn($a, $b) => strcmp($a['empId'], $b['empId']));

        respond(['success' => true, 'records' => array_values($output), 'summary' => $summaryList]);
        break;

    case 'deleteAttendanceDay':
        $hrId = sanitize($_POST['hrId'] ?? '');
        $empId = sanitize($_POST['empId'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        if (!$hrId || !$empId || !$date) {
            respond(['success' => false, 'message' => 'HR ID, employee ID, and date are required.']);
        }
        if (!validate_employee_id($hrId) || !validate_employee_id($empId)) {
            respond(['success' => false, 'message' => 'Invalid employee ID format.']);
        }
        if (!is_valid_date_ymd($date)) {
            respond(['success' => false, 'message' => 'Date must be in YYYY-MM-DD format.']);
        }
        if (!employee_can_edit_attendance($mysqli, $hrId)) {
            respond(['success' => false, 'message' => 'Selected staff does not have HR attendance edit permission.']);
        }
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $stmt = $mysqli->prepare('DELETE FROM attendance_punches WHERE emp_id = ? AND timestamp BETWEEN ? AND ?');
        $stmt->bind_param('sss', $empId, $dayStart, $dayEnd);
        $stmt->execute();
        if (ensure_vehicle_punches_table($mysqli)) {
            $vehicleStmt = $mysqli->prepare('DELETE FROM vehicle_punches WHERE emp_id = ? AND timestamp BETWEEN ? AND ?');
            $vehicleStmt->bind_param('sss', $empId, $dayStart, $dayEnd);
            $vehicleStmt->execute();
        }
        respond(['success' => true, 'message' => 'Attendance deleted successfully.']);
        break;

    case 'updateAttendanceDay':
        $hrId = sanitize($_POST['hrId'] ?? '');
        $empId = sanitize($_POST['empId'] ?? '');
        $date = sanitize($_POST['date'] ?? '');
        $times = sanitize($_POST['times'] ?? '');
        $vehicleName = sanitize($_POST['vehicleName'] ?? '');
        $vehiclePurpose = sanitize($_POST['vehiclePurpose'] ?? '');
        if (!$hrId || !$empId || !$date || !$times) {
            respond(['success' => false, 'message' => 'HR ID, employee ID, date, and session times are required.']);
        }
        if (!validate_employee_id($hrId) || !validate_employee_id($empId)) {
            respond(['success' => false, 'message' => 'Invalid employee ID format.']);
        }
        if (!is_valid_date_ymd($date)) {
            respond(['success' => false, 'message' => 'Date must be in YYYY-MM-DD format.']);
        }
        if (!employee_can_edit_attendance($mysqli, $hrId)) {
            respond(['success' => false, 'message' => 'Selected staff does not have HR attendance edit permission.']);
        }
        if (($vehicleName && !$vehiclePurpose) || (!$vehicleName && $vehiclePurpose)) {
            respond(['success' => false, 'message' => 'Enter both office vehicle and purpose, or leave both blank.']);
        }

        $entries = parse_attendance_time_entries($date, $times);
        if (!$entries) {
            respond(['success' => false, 'message' => 'Enter valid session times like 08:00,12:00,13:00,17:00 with a maximum of 6 sessions.']);
        }

        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $mysqli->begin_transaction();
        $deleteStmt = $mysqli->prepare('DELETE FROM attendance_punches WHERE emp_id = ? AND timestamp BETWEEN ? AND ?');
        $deleteStmt->bind_param('sss', $empId, $dayStart, $dayEnd);
        $deleteStmt->execute();
        if (ensure_vehicle_punches_table($mysqli)) {
            $deleteVehicleStmt = $mysqli->prepare('DELETE FROM vehicle_punches WHERE emp_id = ? AND timestamp BETWEEN ? AND ?');
            $deleteVehicleStmt->bind_param('sss', $empId, $dayStart, $dayEnd);
            $deleteVehicleStmt->execute();
        }

        if (ensure_vehicle_columns($mysqli)) {
            $insertStmt = $mysqli->prepare('INSERT INTO attendance_punches (emp_id, timestamp, vehicle_name, vehicle_purpose) VALUES (?, ?, ?, ?)');
            foreach ($entries as $entry) {
                $emptyVehicle = '';
                $emptyPurpose = '';
                $insertStmt->bind_param('ssss', $empId, $entry, $emptyVehicle, $emptyPurpose);
                $insertStmt->execute();
            }
        } else {
            $insertStmt = $mysqli->prepare('INSERT INTO attendance_punches (emp_id, timestamp) VALUES (?, ?)');
            foreach ($entries as $entry) {
                $insertStmt->bind_param('ss', $empId, $entry);
                $insertStmt->execute();
            }
        }

        if (ensure_vehicle_punches_table($mysqli) && $vehicleName && $vehiclePurpose) {
            $vehicleTimestamp = $entries[0] ?? ($date . ' 00:00:00');
            $insertVehicleStmt = $mysqli->prepare('INSERT INTO vehicle_punches (emp_id, timestamp, vehicle_name, vehicle_purpose) VALUES (?, ?, ?, ?)');
            $insertVehicleStmt->bind_param('ssss', $empId, $vehicleTimestamp, $vehicleName, $vehiclePurpose);
            $insertVehicleStmt->execute();
        }

        $mysqli->commit();
        respond(['success' => true, 'message' => 'Attendance updated successfully.']);
        break;

    case 'exportAttendance':
        $start = sanitize($_GET['start'] ?? date('Y-m-01'));
        $end = sanitize($_GET['end'] ?? date('Y-m-d'));
        $department = sanitize($_GET['department'] ?? 'All');
        $empIdFilter = sanitize($_GET['empId'] ?? 'All');
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => false, 'message' => 'Viewer account is not linked to an employee profile.']);
            }
            $empIdFilter = $viewerEmpId;
            $department = 'All';
        }
        if ($empIdFilter !== 'All' && !validate_employee_id($empIdFilter)) {
            respond(['success' => false, 'message' => 'Invalid employee filter.']);
        }
        $params = [$start, $end . ' 23:59:59'];
        $query = 'SELECT p.emp_id, p.timestamp, e.name, e.department FROM attendance_punches p JOIN employees e ON p.emp_id = e.id WHERE p.timestamp BETWEEN ? AND ?';
        if ($department !== 'All') {
            $query .= ' AND e.department = ?';
            $params[] = $department;
        }
        if ($empIdFilter !== 'All') {
            $query .= ' AND e.id = ?';
            $params[] = $empIdFilter;
        }
        $query .= ' ORDER BY p.emp_id, p.timestamp';
        $stmt = $mysqli->prepare($query);
        if ($department !== 'All' && $empIdFilter !== 'All') {
            $stmt->bind_param('ssss', $params[0], $params[1], $params[2], $params[3]);
        } elseif ($department !== 'All' || $empIdFilter !== 'All') {
            $stmt->bind_param('sss', $params[0], $params[1], $params[2]);
        } else {
            $stmt->bind_param('ss', $params[0], $params[1]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $punches = $result->fetch_all(MYSQLI_ASSOC);

        $holidayResult = $mysqli->query('SELECT `date`, description FROM holidays');
        $holidayMap = [];
        while ($row = $holidayResult->fetch_assoc()) {
            $holidayMap[$row['date']] = $row['description'];
        }

        $employeeQuery = 'SELECT id, name, department FROM employees';
        $employeeParams = [];
        $employeeWhere = [];
        if ($department !== 'All') {
            $employeeWhere[] = 'department = ?';
            $employeeParams[] = $department;
        }
        if ($empIdFilter !== 'All') {
            $employeeWhere[] = 'id = ?';
            $employeeParams[] = $empIdFilter;
        }
        if ($employeeWhere) {
            $employeeQuery .= ' WHERE ' . implode(' AND ', $employeeWhere);
        }
        $employeeQuery .= ' ORDER BY id';
        $employeeStmt = $mysqli->prepare($employeeQuery);
        if (count($employeeParams) === 2) {
            $employeeStmt->bind_param('ss', $employeeParams[0], $employeeParams[1]);
        } elseif (count($employeeParams) === 1) {
            $employeeStmt->bind_param('s', $employeeParams[0]);
        }
        $employeeStmt->execute();
        $employeesForRange = $employeeStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $grouped = [];
        foreach ($punches as $punch) {
            $dateKey = substr($punch['timestamp'], 0, 10);
            $key = $punch['emp_id'] . '_' . $dateKey;
            $grouped[$key]['empId'] = $punch['emp_id'];
            $grouped[$key]['name'] = $punch['name'];
            $grouped[$key]['department'] = $punch['department'];
            $grouped[$key]['date'] = $dateKey;
            $grouped[$key]['timestamps'][] = $punch['timestamp'];
        }

        if (ensure_vehicle_punches_table($mysqli)) {
            $vehicleParams = [$start, $end . ' 23:59:59'];
            $vehicleQuery = 'SELECT v.emp_id, v.timestamp, v.vehicle_name, v.vehicle_purpose, e.name, e.department FROM vehicle_punches v JOIN employees e ON v.emp_id = e.id WHERE v.timestamp BETWEEN ? AND ?';
            if ($department !== 'All') {
                $vehicleQuery .= ' AND e.department = ?';
                $vehicleParams[] = $department;
            }
            if ($empIdFilter !== 'All') {
                $vehicleQuery .= ' AND e.id = ?';
                $vehicleParams[] = $empIdFilter;
            }
            $vehicleQuery .= ' ORDER BY v.emp_id, v.timestamp';

            $vehicleStmt = $mysqli->prepare($vehicleQuery);
            if ($department !== 'All' && $empIdFilter !== 'All') {
                $vehicleStmt->bind_param('ssss', $vehicleParams[0], $vehicleParams[1], $vehicleParams[2], $vehicleParams[3]);
            } elseif ($department !== 'All' || $empIdFilter !== 'All') {
                $vehicleStmt->bind_param('sss', $vehicleParams[0], $vehicleParams[1], $vehicleParams[2]);
            } else {
                $vehicleStmt->bind_param('ss', $vehicleParams[0], $vehicleParams[1]);
            }
            $vehicleStmt->execute();
            $vehicleRows = $vehicleStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($vehicleRows as $vehicleRow) {
                $dateKey = substr($vehicleRow['timestamp'], 0, 10);
                $key = $vehicleRow['emp_id'] . '_' . $dateKey;
                if (!isset($grouped[$key])) {
                    $grouped[$key]['empId'] = $vehicleRow['emp_id'];
                    $grouped[$key]['name'] = $vehicleRow['name'];
                    $grouped[$key]['department'] = $vehicleRow['department'];
                    $grouped[$key]['date'] = $dateKey;
                    $grouped[$key]['timestamps'] = [];
                }
                $grouped[$key]['vehicleEntries'][] = trim(($vehicleRow['vehicle_name'] ?? '') . ' - ' . ($vehicleRow['vehicle_purpose'] ?? ''), ' -');
            }
        }

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        foreach ($employeesForRange as $employeeRow) {
            $cursor = clone $startDate;
            while ($cursor <= $endDate) {
                $dateKey = $cursor->format('Y-m-d');
                $key = $employeeRow['id'] . '_' . $dateKey;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'empId' => $employeeRow['id'],
                        'name' => $employeeRow['name'],
                        'department' => $employeeRow['department'],
                        'date' => $dateKey,
                        'timestamps' => [],
                    ];
                }
                $cursor->modify('+1 day');
            }
        }

        usort($grouped, static function ($left, $right) {
            $cmpEmp = strcmp((string)($left['empId'] ?? ''), (string)($right['empId'] ?? ''));
            if ($cmpEmp !== 0) {
                return $cmpEmp;
            }
            return strcmp((string)($left['date'] ?? ''), (string)($right['date'] ?? ''));
        });

        $rows = [];
        $rows[] = ['Employee ID', 'Name', 'Department', 'Date', 'Day Type', 'Session Time', 'Leave Type', 'Leave Days', 'Office Vehicle Used', 'Regular Hours', 'Overtime Hours', 'Total Hours'];
        $summaryByEmployee = [];
        foreach ($grouped as $record) {
            $sessionData = build_session_details($record['timestamps']);
            $totalSeconds = $sessionData['totalSeconds'];
            $totalHours = $totalSeconds / 3600;
            $dayOfWeek = date('w', strtotime($record['date']));
            $isHoliday = isset($holidayMap[$record['date']]);
            $isSpecial = $dayOfWeek == 6 || $isHoliday;
            $dayType = $isHoliday ? 'Holiday (' . $holidayMap[$record['date']] . ')' : ($dayOfWeek == 6 ? 'Saturday Weekend' : 'Weekday');
            $hasAttendance = !empty($record['timestamps']);
            $regularHours = 0;
            $otHours = 0;
            if ($isSpecial) {
                $otHours = $totalHours;
            } else {
                $regularHours = min(8, $totalHours);
                $otHours = max(0, $totalHours - 8);
            }
            $leaveType = '-';
            $leaveDays = 0.0;
            if (!$isSpecial) {
                if ($hasAttendance) {
                    if ((int)($sessionData['sessionCount'] ?? 0) === 1 && $totalHours < 8) {
                        $leaveType = 'Half Leave';
                        $leaveDays = 0.5;
                    } else {
                        $leaveType = 'Present';
                    }
                } else {
                    $leaveType = 'Full Leave';
                    $leaveDays = 1.0;
                }
            }
            $sessionText = $sessionData['sessionText'];
            if (!$hasAttendance) {
                $sessionText = $isSpecial ? 'No attendance' : 'Leave';
            }
            $vehicleEntries = array_values(array_unique($record['vehicleEntries'] ?? []));

            $empId = $record['empId'];
            if (!isset($summaryByEmployee[$empId])) {
                $summaryByEmployee[$empId] = [
                    'empId' => $record['empId'],
                    'name' => $record['name'],
                    'department' => $record['department'],
                    'totalDays' => 0,
                    'weekendDays' => 0,
                    'holidayDays' => 0,
                    'presentDays' => 0,
                    'regularHours' => 0.0,
                    'otHours' => 0.0,
                    'totalHours' => 0.0,
                    'leaveDays' => 0.0,
                    'halfLeaveDays' => 0.0,
                    'fullLeaveDays' => 0.0,
                ];
            }
            $summaryByEmployee[$empId]['totalDays'] += 1;
            if ($dayOfWeek == 6) {
                $summaryByEmployee[$empId]['weekendDays'] += 1;
            }
            if ($isHoliday) {
                $summaryByEmployee[$empId]['holidayDays'] += 1;
            }
            if (!$isSpecial && $leaveType === 'Present') {
                $summaryByEmployee[$empId]['presentDays'] += 1;
            }
            $summaryByEmployee[$empId]['regularHours'] += $regularHours;
            $summaryByEmployee[$empId]['otHours'] += $otHours;
            $summaryByEmployee[$empId]['totalHours'] += $totalHours;
            $summaryByEmployee[$empId]['leaveDays'] += $leaveDays;
            if ($leaveType === 'Half Leave') {
                $summaryByEmployee[$empId]['halfLeaveDays'] += 0.5;
            } elseif ($leaveType === 'Full Leave') {
                $summaryByEmployee[$empId]['fullLeaveDays'] += 1.0;
            }

            $rows[] = [$record['empId'], $record['name'], $record['department'], $record['date'], $dayType, $sessionText, $leaveType, number_format($leaveDays, 2), $vehicleEntries ? implode(' | ', $vehicleEntries) : 'No vehicle used', number_format($regularHours, 2), number_format($otHours, 2), number_format($totalHours, 2)];
        }

        $summaryRows = [];
        $summaryRows[] = ['Employee ID', 'Name', 'Department', 'Total Days', 'Weekend Days', 'Public Holiday Days', 'Present Days', 'Leave Days', 'Half Leave / Full Leave', 'Regular Hours', 'OT Hours', 'Worked Hours'];
        foreach ($summaryByEmployee as $summary) {
            $summaryRows[] = [
                $summary['empId'],
                $summary['name'],
                $summary['department'],
                (string) $summary['totalDays'],
                (string) $summary['weekendDays'],
                (string) $summary['holidayDays'],
                (string) $summary['presentDays'],
                number_format($summary['leaveDays'], 2),
                'Half: ' . number_format($summary['halfLeaveDays'], 2) . ' / Full: ' . number_format($summary['fullLeaveDays'], 2),
                number_format($summary['regularHours'], 2),
                number_format($summary['otHours'], 2),
                number_format($summary['totalHours'], 2),
            ];
        }

        $xlsx = xlsx_build_package('Attendance', $rows, [
            ['title' => 'Summary', 'rows' => $summaryRows],
        ]);
        output_xlsx_download('factory_attendance_report_' . $start . '_to_' . $end . '.xlsx', $xlsx);

    case 'vehicleUsageRecords':
        $start = sanitize($_GET['start'] ?? date('Y-m-01'));
        $end = sanitize($_GET['end'] ?? date('Y-m-d'));
        $department = sanitize($_GET['department'] ?? 'All');
        $viewerEmpId = '';
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => true, 'records' => []]);
            }
            $department = 'All';
        }

        if (!ensure_vehicle_punches_table($mysqli)) {
            respond(['success' => true, 'records' => []]);
        }

        $params = [$start, $end . ' 23:59:59'];
        $query = 'SELECT v.emp_id, e.name, e.department, v.timestamp, v.vehicle_name, v.vehicle_purpose, v.session_token, v.session_type FROM vehicle_punches v JOIN employees e ON v.emp_id = e.id WHERE v.timestamp BETWEEN ? AND ?';
        if ($department !== 'All') {
            $query .= ' AND e.department = ?';
            $params[] = $department;
        }
        if ($viewerEmpId !== '') {
            $query .= ' AND e.id = ?';
            $params[] = $viewerEmpId;
        }
        $query .= ' ORDER BY v.timestamp DESC, v.emp_id ASC';

        $stmt = $mysqli->prepare($query);
        if ($department !== 'All' && $viewerEmpId !== '') {
            $stmt->bind_param('ssss', $params[0], $params[1], $params[2], $params[3]);
        } elseif ($department !== 'All' || $viewerEmpId !== '') {
            $stmt->bind_param('sss', $params[0], $params[1], $params[2]);
        } else {
            $stmt->bind_param('ss', $params[0], $params[1]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        $sessionMap = [];
        foreach ($rows as $row) {
            $token = $row['session_token'];
            if (!isset($sessionMap[$token])) {
                $sessionMap[$token] = [
                    'empId' => $row['emp_id'],
                    'name' => $row['name'],
                    'department' => $row['department'],
                    'sessionToken' => $token,
                    'vehicleName' => $row['vehicle_name'],
                    'vehiclePurpose' => $row['vehicle_purpose'],
                    'startTimestamp' => null,
                    'endTimestamp' => null,
                    'status' => 'In progress',
                ];
            }
            if (($row['session_type'] ?? '') === 'start') {
                $sessionMap[$token]['startTimestamp'] = $row['timestamp'];
            } elseif (($row['session_type'] ?? '') === 'end') {
                $sessionMap[$token]['endTimestamp'] = $row['timestamp'];
            }
        }

        $records = [];
        foreach ($sessionMap as $session) {
            if (!$session['startTimestamp']) {
                continue;
            }
            [$durationMinutes, $durationText] = vehicle_session_duration_parts($session['startTimestamp'], $session['endTimestamp'] ?? '');
            $records[] = [
                'empId' => $session['empId'],
                'name' => $session['name'],
                'department' => $session['department'],
                'sessionToken' => $session['sessionToken'],
                'date' => substr($session['startTimestamp'], 0, 10),
                'startTime' => substr($session['startTimestamp'], 11),
                'endTime' => $session['endTimestamp'] ? substr($session['endTimestamp'], 11) : '',
                'startTimestamp' => $session['startTimestamp'],
                'endTimestamp' => $session['endTimestamp'] ?? '',
                'vehicleName' => $session['vehicleName'],
                'vehiclePurpose' => $session['vehiclePurpose'],
                'status' => $session['endTimestamp'] ? 'Completed' : 'In progress',
                'durationMinutes' => $durationMinutes,
                'durationText' => $durationText,
            ];
        }

        usort($records, static function ($left, $right) {
            return strcmp($right['date'] . ' ' . $right['startTime'], $left['date'] . ' ' . $left['startTime']);
        });

        respond(['success' => true, 'records' => $records]);
        break;

    case 'updateVehicleUsageSession':
        require_admin();
        $sessionToken = sanitize($_POST['sessionToken'] ?? '');
        $empId = sanitize($_POST['empId'] ?? '');
        $startTimestamp = parse_datetime_input($_POST['startTimestamp'] ?? '');
        $endRaw = trim((string) ($_POST['endTimestamp'] ?? ''));
        $endTimestamp = $endRaw === '' ? '' : parse_datetime_input($endRaw);
        $vehicleName = sanitize($_POST['vehicleName'] ?? '');
        $vehiclePurpose = sanitize($_POST['vehiclePurpose'] ?? '');

        if (!$sessionToken || !$empId || !$startTimestamp || !$vehicleName || !$vehiclePurpose) {
            respond(['success' => false, 'message' => 'Session token, employee ID, from time, vehicle number, and purpose are required.']);
        }
        if (!validate_employee_id($empId)) {
            respond(['success' => false, 'message' => 'Invalid employee ID format.']);
        }
        if ($endRaw !== '' && !$endTimestamp) {
            respond(['success' => false, 'message' => 'Enter a valid to time or leave it blank.']);
        }
        if ($endTimestamp && strtotime($endTimestamp) < strtotime($startTimestamp)) {
            respond(['success' => false, 'message' => 'To time cannot be earlier than from time.']);
        }
        if (mb_strlen($vehicleName) > 100) {
            respond(['success' => false, 'message' => 'Vehicle number cannot exceed 100 characters.']);
        }
        if (mb_strlen($vehiclePurpose) > 255) {
            respond(['success' => false, 'message' => 'Vehicle purpose cannot exceed 255 characters.']);
        }
        if (!ensure_vehicle_punches_table($mysqli)) {
            respond(['success' => false, 'message' => 'Vehicle session storage is not ready.']);
        }

        $empCheck = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
        $empCheck->bind_param('s', $empId);
        $empCheck->execute();
        $empCheck->bind_result($empExists);
        $empExists = 0;
        $empCheck->fetch();
        $empCheck->close();
        if (!$empExists) {
            respond(['success' => false, 'message' => 'Employee not found.']);
        }

        $mysqli->begin_transaction();
        $updateStart = $mysqli->prepare('UPDATE vehicle_punches SET emp_id = ?, timestamp = ?, vehicle_name = ?, vehicle_purpose = ? WHERE session_token = ? AND session_type = "start"');
        $updateStart->bind_param('sssss', $empId, $startTimestamp, $vehicleName, $vehiclePurpose, $sessionToken);
        $updateStart->execute();

        $existingEnd = $mysqli->prepare('SELECT COUNT(*) FROM vehicle_punches WHERE session_token = ? AND session_type = "end"');
        $existingEnd->bind_param('s', $sessionToken);
        $existingEnd->execute();
        $existingEnd->bind_result($endExists);
        $endExists = 0;
        $existingEnd->fetch();
        $existingEnd->close();

        if ($endTimestamp) {
            if ($endExists) {
                $updateEnd = $mysqli->prepare('UPDATE vehicle_punches SET emp_id = ?, timestamp = ?, vehicle_name = ?, vehicle_purpose = ? WHERE session_token = ? AND session_type = "end"');
                $updateEnd->bind_param('sssss', $empId, $endTimestamp, $vehicleName, $vehiclePurpose, $sessionToken);
                $updateEnd->execute();
            } else {
                $insertEnd = $mysqli->prepare('INSERT INTO vehicle_punches (emp_id, timestamp, vehicle_name, vehicle_purpose, session_token, session_type) VALUES (?, ?, ?, ?, ?, "end")');
                $insertEnd->bind_param('sssss', $empId, $endTimestamp, $vehicleName, $vehiclePurpose, $sessionToken);
                $insertEnd->execute();
            }
        } else {
            $deleteEnd = $mysqli->prepare('DELETE FROM vehicle_punches WHERE session_token = ? AND session_type = "end"');
            $deleteEnd->bind_param('s', $sessionToken);
            $deleteEnd->execute();
        }

        $mysqli->commit();
        respond(['success' => true, 'message' => 'Vehicle session updated successfully.']);
        break;

    case 'deleteVehicleUsageSession':
        require_admin();
        $sessionToken = sanitize($_REQUEST['sessionToken'] ?? '');
        $empId = sanitize($_REQUEST['empId'] ?? '');
        $startTimestamp = parse_datetime_input($_REQUEST['startTimestamp'] ?? '');
        $endRaw = trim((string) ($_REQUEST['endTimestamp'] ?? ''));
        $endTimestamp = $endRaw === '' ? '' : parse_datetime_input($endRaw);
        $vehicleName = sanitize($_REQUEST['vehicleName'] ?? '');
        $vehiclePurpose = sanitize($_REQUEST['vehiclePurpose'] ?? '');
        if (!$sessionToken) {
            respond(['success' => false, 'message' => 'Session token is required.']);
        }
        if (!ensure_vehicle_punches_table($mysqli)) {
            respond(['success' => false, 'message' => 'Vehicle session storage is not ready.']);
        }

        $stmt = $mysqli->prepare('DELETE FROM vehicle_punches WHERE session_token = ?');
        $stmt->bind_param('s', $sessionToken);
        $stmt->execute();
        $deletedRows = $stmt->affected_rows;

        if ($deletedRows === 0 && $empId && $startTimestamp && $vehicleName && $vehiclePurpose) {
            $fallbackSql = 'DELETE FROM vehicle_punches WHERE emp_id = ? AND vehicle_name = ? AND vehicle_purpose = ? AND timestamp BETWEEN ? AND ?';
            $fallbackStmt = $mysqli->prepare($fallbackSql);
            $dayStart = substr($startTimestamp, 0, 10) . ' 00:00:00';
            $dayEnd = $endTimestamp ?: (substr($startTimestamp, 0, 10) . ' 23:59:59');
            $fallbackStmt->bind_param('sssss', $empId, $vehicleName, $vehiclePurpose, $dayStart, $dayEnd);
            $fallbackStmt->execute();
            $deletedRows = max($deletedRows, $fallbackStmt->affected_rows);
        }

        if ($deletedRows === 0) {
            respond(['success' => false, 'message' => 'No vehicle session rows were found to delete.']);
        }

        respond(['success' => true, 'message' => 'Vehicle session deleted successfully.']);
        break;

    case 'exportVehicleUsage':
        $start = sanitize($_GET['start'] ?? date('Y-m-01'));
        $end = sanitize($_GET['end'] ?? date('Y-m-d'));
        $department = sanitize($_GET['department'] ?? 'All');
        $viewerEmpId = '';
        if (is_viewer_role()) {
            $viewerEmpId = get_logged_in_employee_id($mysqli);
            if ($viewerEmpId === '') {
                respond(['success' => false, 'message' => 'Viewer account is not linked to an employee profile.']);
            }
            $department = 'All';
        }

        if (!ensure_vehicle_punches_table($mysqli)) {
            $rows = [['Employee ID', 'Name', 'Department', 'Date', 'From Time', 'To Time', 'Duration', 'Vehicle Number', 'Purpose', 'Status']];
            $xlsx = xlsx_build_package('Vehicle Usage', $rows);
            output_xlsx_download('vehicle_usage_report_' . $start . '_to_' . $end . '.xlsx', $xlsx);
        }

        $params = [$start, $end . ' 23:59:59'];
        $query = 'SELECT v.emp_id, e.name, e.department, v.timestamp, v.vehicle_name, v.vehicle_purpose, v.session_token, v.session_type FROM vehicle_punches v JOIN employees e ON v.emp_id = e.id WHERE v.timestamp BETWEEN ? AND ?';
        if ($department !== 'All') {
            $query .= ' AND e.department = ?';
            $params[] = $department;
        }
        if ($viewerEmpId !== '') {
            $query .= ' AND e.id = ?';
            $params[] = $viewerEmpId;
        }
        $query .= ' ORDER BY v.timestamp DESC, v.emp_id ASC';

        $stmt = $mysqli->prepare($query);
        if ($department !== 'All' && $viewerEmpId !== '') {
            $stmt->bind_param('ssss', $params[0], $params[1], $params[2], $params[3]);
        } elseif ($department !== 'All' || $viewerEmpId !== '') {
            $stmt->bind_param('sss', $params[0], $params[1], $params[2]);
        } else {
            $stmt->bind_param('ss', $params[0], $params[1]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);

        $sessionMap = [];
        foreach ($data as $row) {
            $token = $row['session_token'];
            if (!isset($sessionMap[$token])) {
                $sessionMap[$token] = [
                    'empId' => $row['emp_id'],
                    'name' => $row['name'],
                    'department' => $row['department'],
                    'vehicleName' => $row['vehicle_name'],
                    'vehiclePurpose' => $row['vehicle_purpose'],
                    'startTime' => null,
                    'endTime' => null,
                ];
            }
            if (($row['session_type'] ?? '') === 'start') {
                $sessionMap[$token]['startTime'] = $row['timestamp'];
            } elseif (($row['session_type'] ?? '') === 'end') {
                $sessionMap[$token]['endTime'] = $row['timestamp'];
            }
        }

        $rows = [['Employee ID', 'Name', 'Department', 'Date', 'From Time', 'To Time', 'Duration', 'Vehicle Number', 'Purpose', 'Status']];
        foreach ($sessionMap as $session) {
            if (!$session['startTime']) {
                continue;
            }
            [$durationMinutes, $durationText] = vehicle_session_duration_parts($session['startTime'], $session['endTime'] ?? '');
            $rows[] = [
                $session['empId'],
                $session['name'],
                $session['department'],
                substr($session['startTime'], 0, 10),
                substr($session['startTime'], 11),
                $session['endTime'] ? substr($session['endTime'], 11) : '',
                $durationText,
                $session['vehicleName'],
                $session['vehiclePurpose'],
                $session['endTime'] ? 'Completed' : 'In progress',
            ];
        }

        $xlsx = xlsx_build_package('Vehicle Usage', $rows);
        output_xlsx_download('vehicle_usage_report_' . $start . '_to_' . $end . '.xlsx', $xlsx);

    case 'downloadEmployeeTemplate':
        require_admin();
        $templateRows = [
            ['Employee ID', 'Full Name', 'Post / Designation', 'Department', 'HR Attendance Access (1=Yes 0=No)'],
            ['EMP101', 'Ram Bahadur', 'Machine Operator', 'Production', '0'],
            ['EMP102', 'Sita Thapa', 'HR Officer', 'Office', '1'],
        ];
        $xlsx = xlsx_build_package('Staff Import', $templateRows);
        output_xlsx_download('staff_import_template.xlsx', $xlsx);

    case 'bulkImportEmployees':
        require_admin();
        if (empty($_FILES['xlsxFile']['tmp_name']) || !is_uploaded_file($_FILES['xlsxFile']['tmp_name'])) {
            respond(['success' => false, 'message' => 'No file uploaded.']);
        }
        $ext = strtolower(pathinfo($_FILES['xlsxFile']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            respond(['success' => false, 'message' => 'Only .xlsx files are accepted.']);
        }

        $zipBytes = file_get_contents($_FILES['xlsxFile']['tmp_name']);
        if (!$zipBytes) {
            respond(['success' => false, 'message' => 'Could not read uploaded file.']);
        }

        $allRows = xlsx_parse_rows($zipBytes);
        if ($allRows === null || count($allRows) < 2) {
            respond(['success' => false, 'message' => 'Could not parse the xlsx file. Make sure sheet1 has data.']);
        }

        // Detect header row and column mapping.
        $header = array_map(function($v) {
            return strtolower(trim(preg_replace('/[^a-z0-9]/i', '', (string)$v)));
        }, $allRows[0]);

        $colMap = [];
        $aliases = [
            'id'          => ['employeeid','id','empid','staffid','code'],
            'name'        => ['fullname','name','employeename','staffname'],
            'designation' => ['designation','post','position','role'],
            'department'  => ['department','dept'],
            'hr'          => ['hrattendanceaccess10yesno0no','hrattendanceaccess','hraccess','caneditattendance','hr'],
        ];
        foreach ($aliases as $key => $opts) {
            foreach ($header as $i => $col) {
                if (in_array($col, $opts, true)) {
                    $colMap[$key] = $i;
                    break;
                }
            }
        }
        if (!isset($colMap['id']) || !isset($colMap['name'])) {
            respond(['success' => false, 'message' => 'Required columns not found. Header must contain Employee ID and Full Name.']);
        }

        $withDesig   = ensure_designation_column($mysqli);
        $withHrPerm  = ensure_hr_permission_column($mysqli);
        $added = 0; $updated = 0; $skipped = 0; $errors = [];

        for ($i = 1; $i < count($allRows); $i++) {
            $row = $allRows[$i];
            $empId = sanitize($row[$colMap['id']] ?? '');
            $name  = sanitize($row[$colMap['name']] ?? '');
            if (!$empId || !$name) { $skipped++; continue; }

            $designation = $withDesig && isset($colMap['designation']) ? sanitize($row[$colMap['designation']] ?? '') : '';
            $rawDept     = strtolower(trim($row[$colMap['department']] ?? ''));
            $department  = ($rawDept === 'office') ? 'Office' : 'Production';
            $hrRaw       = isset($colMap['hr']) ? trim($row[$colMap['hr']] ?? '') : '0';
            $canEdit     = (in_array(strtolower($hrRaw), ['1','yes','y'], true)) ? 1 : 0;

            $chk = $mysqli->prepare('SELECT COUNT(*) FROM employees WHERE id = ?');
            $chk->bind_param('s', $empId); $chk->execute();
            $chk->bind_result($exists); $chk->fetch(); $chk->close();

            if ($exists) {
                $setCols = 'name = ?, ' . ($withDesig ? 'designation = ?, ' : '') . 'department = ?' . ($withHrPerm ? ', can_edit_attendance = ?' : '');
                $sql = "UPDATE employees SET {$setCols} WHERE id = ?";
                $st = $mysqli->prepare($sql);
                if ($withDesig && $withHrPerm)   $st->bind_param('sssis', $name, $designation, $department, $canEdit, $empId);
                elseif ($withDesig)              $st->bind_param('ssss', $name, $designation, $department, $empId);
                elseif ($withHrPerm)             $st->bind_param('ssis', $name, $department, $canEdit, $empId);
                else                             $st->bind_param('sss', $name, $department, $empId);
                if ($st->execute()) $updated++; else { $skipped++; $errors[] = "Row ".($i+1).": update failed for $empId."; }
                $st->close();
            } else {
                if ($withDesig && $withHrPerm) {
                    $st = $mysqli->prepare('INSERT INTO employees (id,name,designation,department,can_edit_attendance) VALUES (?,?,?,?,?)');
                    $st->bind_param('ssssi', $empId, $name, $designation, $department, $canEdit);
                } elseif ($withDesig) {
                    $st = $mysqli->prepare('INSERT INTO employees (id,name,designation,department) VALUES (?,?,?,?)');
                    $st->bind_param('ssss', $empId, $name, $designation, $department);
                } elseif ($withHrPerm) {
                    $st = $mysqli->prepare('INSERT INTO employees (id,name,department,can_edit_attendance) VALUES (?,?,?,?)');
                    $st->bind_param('sssi', $empId, $name, $department, $canEdit);
                } else {
                    $st = $mysqli->prepare('INSERT INTO employees (id,name,department) VALUES (?,?,?)');
                    $st->bind_param('sss', $empId, $name, $department);
                }
                if ($st->execute()) $added++; else { $skipped++; $errors[] = "Row ".($i+1).": insert failed for $empId."; }
                $st->close();
            }

            $syncError = '';
            if (!upsert_employee_login_user($mysqli, $empId, $designation, $canEdit, $syncError)) {
                $errors[] = "Row " . ($i + 1) . ": login sync failed for $empId. " . $syncError;
            }
        }
        respond([
            'success' => true,
            'message' => "Import done: {$added} added, {$updated} updated, {$skipped} skipped.",
            'added'   => $added, 'updated' => $updated, 'skipped' => $skipped,
            'errors'  => $errors,
        ]);
        break;

    default:
        respond(['success' => false, 'message' => 'Invalid API action.']);
}
