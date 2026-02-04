<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$nama_database = $_ENV['DB_NAME'];

$db = mysqli_connect($host, $user, $password, $nama_database);

if (!$db) {
    die("Failed to connect to database: " . mysqli_connect_error());
}
