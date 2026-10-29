<?php
// The one database connection for the whole site: every page does
//     require_once __DIR__ . '/db.php';
// and then uses $conn.
//
// On hosting, put the real login in honeyart-db.php in the private folder (see config.php):
//     <?php return ['host' => 'localhost', 'user' => '...', 'password' => '...', 'name' => '...', 'port' => 3306];
// Without that file the MAMP defaults below are used.

require_once __DIR__ . '/config.php';

$dbConfig = (is_readable(PRIVATE_DIR . '/honeyart-db.php') ? require PRIVATE_DIR . '/honeyart-db.php' : [])
    + ['host' => 'localhost', 'user' => 'root', 'password' => 'root', 'name' => 'honey_art_db', 'port' => 8889];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($dbConfig['host'], $dbConfig['user'], $dbConfig['password'], $dbConfig['name'], (int) $dbConfig['port']);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Details go to the server log, never to the visitor
    error_log('HoneyArt DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('The site is temporarily unavailable. Please try again in a few minutes.');
}
unset($dbConfig);
