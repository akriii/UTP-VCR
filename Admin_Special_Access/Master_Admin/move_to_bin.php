<?php
session_start();
include('../../config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the REPORT ID, not just the user ID
    $report_id = $_POST['report_id'];
    
    // Optional: Get user_id just for redirection purposes
    $user_id = $_POST['user_id']; 

    // Update the REPORT table, not the USER table
    $stmt = $db->prepare("UPDATE report SET is_deleted = 1 WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    
    if ($stmt->execute()) {
        // Success
        header("Location: homepage.php?msg=archived");
    } else {
        // Error
        echo "Error archiving report.";
    }
}
?>