<?php
session_start();
include('../../config.php');

// Check if admin is logged in and is master admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : null;
    // FIX 1: Capture the specific report_id
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    $verify_status = isset($_POST['verify_status']) ? (int)$_POST['verify_status'] : null;

    // Validate inputs
    if (empty($user_id) || empty($report_id) || $verify_status === null) {
        $_SESSION['error'] = "All fields are required";
        header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" . urlencode($report_id));
        exit();
    }

    // FIX 2: Removed the "SELECT report_id" query block completely.
    
    // FIX 3: Simplified Update Logic (Directly update using report_id)
    $sql = "UPDATE fyp_form_02 SET fypForm_verify = ? WHERE report_id = ?";
    $stmt = mysqli_prepare($db, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $verify_status, $report_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "FYP Form verification updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update FYP Form verification: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Database prepare error: " . mysqli_error($db);
    }

    // FIX 4: Redirect back with specific report_id
    header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" .urlencode($report_id));
    exit();
} else {
    header("Location: homepage.php");
    exit();
}
?>