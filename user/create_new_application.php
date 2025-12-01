

<?php
session_start();
include "../config.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

// DEBUG: Check what's happening
error_log("=== CREATE NEW APPLICATION DEBUG ===");
error_log("User ID: $user_id");

// ===== ENHANCED: Check if user has any INCOMPLETE applications =====
$pending_sql = "SELECT report_id FROM report WHERE user_id = ? AND completed_date IS NULL ORDER BY report_id DESC LIMIT 1";
$pending_stmt = $db->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();

error_log("Pending applications (incomplete) found: " . $pending_result->num_rows);

if ($pending_result->num_rows > 0) {
    $pending_row = $pending_result->fetch_assoc();
    error_log("Pending report ID (incomplete): " . $pending_row['report_id']);
    
    $_SESSION['message'] = "You already have a pending application. Please complete your current application before creating a new one.";
    $_SESSION['message_type'] = 'error';
    $pending_stmt->close();
    header("Location: user_homePage.php");
    exit();
}
$pending_stmt->close();

error_log("No pending applications found, checking KYC6 cooling period...");

// ===== CHECK KYC6 FAIL COOLING PERIOD =====
$kyc6_cooling_sql = "SELECT k.approved_date 
                     FROM kyc6 k 
                     JOIN report r ON k.report_id = r.report_id 
                     WHERE r.user_id = ? AND k.status = 'Fail' AND k.approved_date IS NOT NULL 
                     ORDER BY k.approved_date DESC LIMIT 1";
$kyc6_cooling_stmt = $db->prepare($kyc6_cooling_sql);
$kyc6_cooling_stmt->bind_param("i", $user_id);
$kyc6_cooling_stmt->execute();
$kyc6_cooling_result = $kyc6_cooling_stmt->get_result();

error_log("KYC6 Fail records found: " . $kyc6_cooling_result->num_rows);

if ($kyc6_cooling_result->num_rows > 0) {
    $kyc6_cooling_row = $kyc6_cooling_result->fetch_assoc();
    $kyc6_approved_date = $kyc6_cooling_row['approved_date'];
    error_log("Last KYC6 Fail date: $kyc6_approved_date");
    
    $kyc6_next_eligible_date = date('Y-m-d', strtotime($kyc6_approved_date . " + 6 months"));
    $kyc6_days_remaining = ceil((strtotime($kyc6_next_eligible_date) - time()) / (60 * 60 * 24));
    $kyc6_can_create_new = ($kyc6_days_remaining <= 0);
    
    error_log("KYC6 Next eligible date: $kyc6_next_eligible_date");
    error_log("KYC6 Days remaining: $kyc6_days_remaining");
    error_log("KYC6 Can create new: " . ($kyc6_can_create_new ? 'YES' : 'NO'));
    
    if (!$kyc6_can_create_new) {
        $_SESSION['message'] = "You are in KYC6 cooling period. You can create a new application in $kyc6_days_remaining days.";
        $_SESSION['message_type'] = 'error';
        $kyc6_cooling_stmt->close();
        header("Location: user_homePage.php");
        exit();
    }
}
$kyc6_cooling_stmt->close();

error_log("Creating new application...");

// Create new application
try {
    // Start transaction
    mysqli_begin_transaction($db);
    
    // Create new report
    $create_report = $db->prepare("INSERT INTO report (user_id) VALUES (?)");
    $create_report->bind_param("i", $user_id);
    
    if (!$create_report->execute()) {
        throw new Exception("Failed to create application record: " . $create_report->error);
    }
    
    $report_id = $create_report->insert_id;
    $create_report->close();
    
    error_log("New report created with ID: $report_id");
    
    // Initialize KYC6 status
    $kyc6_sql = "INSERT INTO kyc6 (report_id, status, created_date) VALUES (?, 'Pending', NOW())";
    $kyc6_stmt = $db->prepare($kyc6_sql);
    $kyc6_stmt->bind_param("i", $report_id);

    
    if (!$kyc6_stmt->execute()) {
        throw new Exception("Failed to initialize KYC6: " . $kyc6_stmt->error);
    }
    $kyc6_stmt->close();
    
    // Clear any previous IC upload AND face recognition for new application
    $clear_user_data = $db->prepare("UPDATE user SET ic_picture = NULL, face_recognition = NULL WHERE user_id = ?");
    $clear_user_data->bind_param("i", $user_id);
    $clear_user_data->execute();
    $clear_user_data->close();
    
    // Commit transaction
    mysqli_commit($db);
    
    $_SESSION['message'] = "New application created successfully! Please start by uploading your IC copy.";
    $_SESSION['message_type'] = 'success';
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($db);
    $_SESSION['message'] = "Error creating application: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

header("Location: user_homePage.php");
exit();
?>