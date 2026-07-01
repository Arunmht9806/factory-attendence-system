<?php
// Database connection settings for local server
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'factory_attendance';

function db_connect() {
    static $mysqli = null;

    if ($mysqli instanceof mysqli) {
        try {
            if ($mysqli->ping()) {
                return $mysqli;
            }
        } catch (Throwable $e) {
            $mysqli = null;
        }
    }

    $mysqli = mysqli_init();
    if (!$mysqli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database initialization failed.']);
        exit;
    }

    $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
        $mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 30);
    }
    if (defined('MYSQLI_OPT_INT_AND_FLOAT_NATIVE')) {
        $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    }

    $connected = @$mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$connected || $mysqli->connect_errno) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $mysqli->connect_error]);
        exit;
    }

    $mysqli->set_charset('utf8mb4');
    $mysqli->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $mysqli;
}

function json_response($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function sanitize($value) {
    return trim($value);
}
