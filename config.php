<?php
/**
 * UTP Vendor Hub - Vendor Code Requisition System
 * * @package  UTP_VCR
 * @author   Muhammad Akmal Fikri Bin Asma Zaki  <mrakri15@gmail.com> and Nurin Jazlina Binti Rashid Ridha
 * @version  1.0.0
 * @access   Internal UTP Use Only
 * @copyright 2025 Muhammad Akmal Fikri Bin Asma Zaki and Nurin Jazlina Binti Rashid Ridha / UTP
 * * This file is part of the UTP Vendor Hub system.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 */

$host = "localhost";
$user = "root";
$password = "";
$nama_database = "utp_vendor_hub";

$db = mysqli_connect($host, $user, $password, $nama_database);

if( !$db ){
    die("Failed to connect to database " . mysqli_connect_error());
}
?>
