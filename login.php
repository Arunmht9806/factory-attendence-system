<?php
session_start();
require_once __DIR__ . '/db.php';

const LOGIN_RUNTIME_SCHEMA_VERSION = '2026-07-01-runtime-3';

function login_runtime_schema_is_current(mysqli $mysqli): bool {
    $metaTable = $mysqli->query("SHOW TABLES LIKE 'app_runtime_metadata'");
    if (!$metaTable || $metaTable->num_rows === 0) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT meta_value FROM app_runtime_metadata WHERE meta_key = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $key = 'schema_version';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (string)($row['meta_value'] ?? '') === LOGIN_RUNTIME_SCHEMA_VERSION;
}

// Ensure users table exists and auto-create admin if no users exist yet.
$mysqli = db_connect();
$runtimeSchemaCurrent = login_runtime_schema_is_current($mysqli);
$tableExists = $runtimeSchemaCurrent;

if (!$tableExists) {
    $check = $mysqli->query("SHOW TABLES LIKE 'users'");
    $tableExists = $check && $check->num_rows > 0;
}

if (!$tableExists) {
    $createSql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','hr','it','viewer') NOT NULL DEFAULT 'viewer',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $tableExists = $mysqli->query($createSql) === true;
} elseif (!$runtimeSchemaCurrent) {
    $roleCheck = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleType = $roleCheck ? (string)($roleCheck->fetch_assoc()['Type'] ?? '') : '';
    if ($roleType && (strpos($roleType, "'hr'") === false || strpos($roleType, "'it'") === false)) {
        $mysqli->query("ALTER TABLE users MODIFY role ENUM('admin','hr','it','viewer') NOT NULL DEFAULT 'viewer'");
    }
}

if ($tableExists && !$runtimeSchemaCurrent) {
    $cnt = $mysqli->query('SELECT COUNT(*) AS cnt FROM users');
    if ($cnt && (int)($cnt->fetch_assoc()['cnt'] ?? 0) === 0) {
        $defaultHash = password_hash('admin123', PASSWORD_BCRYPT);
        $defaultUser = 'admin';
        $defaultRole = 'admin';
        $insert = $mysqli->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        if ($insert) {
            $insert->bind_param('sss', $defaultUser, $defaultHash, $defaultRole);
            $insert->execute();
            $insert->close();
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Login service is not ready. Please try again.';
        }
        if ($error !== '') {
            // Skip auth attempt when query preparation fails.
        } else {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result && password_verify($password, $result['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $result['id'];
            $_SESSION['username']  = $result['username'];
            $_SESSION['role']      = $result['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Factory Attendance System</title>
    <link rel="icon" type="image/png" href="assets/images/wellhope-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-900 to-indigo-950 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl mb-4 p-1.5 shadow-lg">
                <img src="assets/images/wellhope-logo.png" alt="Wellhope Logo" class="w-full h-full object-contain rounded-xl">
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">NEPAL WELLHOPE AGRI TECH</h1>
            <p class="text-blue-300 text-sm mt-1">Attendance Management System</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold text-slate-800 mb-6">Sign In</h2>

            <?php if ($error): ?>
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">User ID</label>
                    <input type="text" name="username" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full p-3 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Password</label>
                    <input type="password" name="password" required
                           class="w-full p-3 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg text-sm transition shadow">
                    Sign In
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-slate-400">
                 <span class="font-mono font-semibold text-slate-600"></span>
            </p>
        </div>
    </div>
</body>
</html>
