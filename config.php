<?php


$host = "localhost";
$user = "root";
$password = "";
$nama_database = "utp_vendor_hub";

$db = mysqli_connect($host, $user, $password, $nama_database);

if( !$db ){
    die("Failed to connect to database " . mysqli_connect_error());
}
?>
