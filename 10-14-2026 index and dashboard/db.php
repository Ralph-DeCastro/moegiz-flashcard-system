<?php
$DB_HOST = '127.0.0.1';
$DB_PORT = 3308;          // palit port pag iba port sa pc niyo
$DB_NAME = 'moegiz';
$DB_USER = 'root';
$DB_PASS = '';

// false bago mag deploy
$DEBUG = true;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    if ($DEBUG) {
        exit('Database error (' . $e->getCode() . '): ' . htmlspecialchars($e->getMessage()));
    }
    exit('Could not connect to the database. Please check db.php settings.');
}
