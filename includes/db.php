<?php
require_once __DIR__ . '/ssl.php';

$host = 'localhost';
$dbname = 'sportmart_db';
$username = 'root';
$password = ''; 

$dbOptions = [];
$dbSslCa = getenv('SPORTMART_DB_SSL_CA');
if ($dbSslCa && defined('PDO::MYSQL_ATTR_SSL_CA')) {
    $dbOptions[PDO::MYSQL_ATTR_SSL_CA] = $dbSslCa;
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $dbOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
}

try {
    $pdo = new PDO( "mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $dbOptions );
    $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    $pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
    $pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
} catch (PDOException $e) {
    die("Database connection failed.");
}

require_once __DIR__ . '/encryption.php';