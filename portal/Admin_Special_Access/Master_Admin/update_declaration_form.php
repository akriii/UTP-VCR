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
    // FIX 1: Capture the specific report_id
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    $verify_status = isset($_POST['verify_status']) ? (int)$_POST['verify_status'] : null;

    // Validate inputs
    if (empty($user_id) || empty($report_id) || $verify_status === null) {
        $_SESSION['error'] = "All fields are required";
        // FIX 2: Ensure redirect has both IDs so it doesn't crash
        header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" . urlencode($report_id));
        exit();
    }

    // FIX 3: Removed the "SELECT report_id" query block completely.
    // We rely on the specific ID passed from the form.

    // FIX 4: Simplified Update Logic
    // No need for the 'variants' loop, just update the specific table directly
    $sql = "UPDATE declaration_form SET declaration_verify = ? WHERE report_id = ?";
    $stmt = mysqli_prepare($db, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $verify_status, $report_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Declaration form verification updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update declaration form verification: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Database prepare error: " . mysqli_error($db);
    }

    // FIX 5: Redirect back with specific report_id
    header("Location: view_user.php?user_id=" .urlencode( $user_id) . "&report_id=" .urlencode($report_id) );
    exit();
} else {
    header("Location: homepage.php");
    exit();
}
?>