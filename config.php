<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$nama_database = $_ENV['DB_NAME'];
$port = $_ENV['DB_PORT'];

// 2. Add $port as the 5th argument in mysqli_connect
$db = mysqli_connect($host, $user, $password, $nama_database, $port);

if (!$db) {
    die("Failed to connect to database: " . mysqli_connect_error());
}
