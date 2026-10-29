<?php
// Practical 14 database connections for the existing WT project.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db_connect(string $database): mysqli {
    $conn = new mysqli('localhost', 'root', '', $database);
    $conn->set_charset('utf8mb4');
    return $conn;
}

try {
    $dashboardConn = db_connect('studenthub');
    $authConn = db_connect('secure_login');
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database connection failed. Check that MySQL is running and the studenthub and secure_login databases exist.');
}
