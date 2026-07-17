<?php
session_start();
include('../../../config.php');

// Check if admin is logged in and is master admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : null;
    // FIX 1: Capture Report ID directly
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    $verify_status = isset($_POST['verify_status']) ? (int)$_POST['verify_status'] : null;

    // Debug logging
    error_log("Updating appointment letter for user: $user_id, report: $report_id, status: $verify_status");

    // Validate inputs
    if (empty($user_id) || empty($report_id) || $verify_status === null) {
        $_SESSION['error'] = "All fields are required";
        header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" . urlencode($report_id));
        exit();
    }

    // FIX 2: Removed the "SELECT report_id" query block.

    // FIX 3: Simplified Update Logic (Direct update)
    $sql = "UPDATE appointment_letter SET appointmentLetter_verify = ? WHERE report_id = ?";
    $stmt = mysqli_prepare($db, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $verify_status, $report_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Appointment letter verification updated successfully!";
            error_log("Successfully updated appointment letter for report ID: $report_id");
        } else {
            $_SESSION['error'] = "Failed to update verification: " . mysqli_stmt_error($stmt);
            error_log("Failed to execute query: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        $err = mysqli_error($db);
        error_log("Failed to prepare statement: $err");
        $_SESSION['error'] = "Database error: " . $err;
    }

    // FIX 4: Redirect with Report ID
    header("Location: view_user.php?user_id=" .urlencode( $user_id) . "&report_id=" .urlencode($report_id) );
    exit();
} else {
    header("Location: homepage.php");
    exit();
}
?>