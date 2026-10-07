<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    error_log('DB connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check src/config.php and that MySQL is running.');
}
