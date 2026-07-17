<?php
session_start();
include "../../config.php";

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$uploadDir = "../../documents/ic_picture/";

// Make sure folder exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ===== 1. VALIDATE IC NUMBER =====
if (!isset($_POST['ic_no']) || empty(trim($_POST['ic_no']))) {
    $_SESSION['upload_error'] = "IC number is required.";
    header("Location: user_homePage.php");
    exit();
}

$ic_no = trim($_POST['ic_no']);

// Validate IC number format (Malaysian IC format: 901231-01-1234)
/*if (!preg_match('/^[0-9]{6}-[0-9]{2}-[0-9]{4}$/', $ic_no)) {
    $_SESSION['upload_error'] = "Invalid IC number format. Please use format: 901231-01-1234";
    header("Location: user_homePage.php");
    exit();
}*/

// ===== 2. CHECK IF FILE WAS UPLOADED PROPERLY =====
if (!isset($_FILES['ic_picture']) || $_FILES['ic_picture']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "No file selected or upload error occurred.";
    header("Location: user_homePage.php");
    exit();
}

// ===== 3. VALIDATE FILE TYPE =====
$fileExtension = pathinfo($_FILES['ic_picture']['name'], PATHINFO_EXTENSION);

// Restrict file types for security
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
    $_SESSION['upload_error'] = "Invalid file type. Only JPG, PNG, or PDF allowed.";
    header("Location: user_homePage.php");
    exit();
}

// ===== 4. GET USER_ID =====
// Make email safe to use in filename
$safeEmail = preg_replace('/[^a-zA-Z0-9]/', '_', $email);

// Get user_id from database
$user_stmt = $db->prepare("SELECT user_id FROM user WHERE user_email = ?");
$user_stmt->bind_param("s", $email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_row = $user_result->fetch_assoc();
$user_id = $user_row['user_id'];
$user_stmt->close();

// ===== 5. CHECK IF USER ALREADY HAS IC PICTURE FOR CURRENT APPLICATION =====
$check_stmt = $db->prepare("SELECT ic_picture, ic_no FROM user WHERE user_id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$user_data = $check_result->fetch_assoc();
$check_stmt->close();

if (!empty($user_data['ic_picture'])) {
    $_SESSION['upload_error'] = "You have already submitted your IC copy for this application.";
    header("Location: user_homePage.php");
    exit();
}

// ===== 6. CREATE REPORT IF IT DOESN'T EXIST =====
$report_sql = "SELECT report_id FROM report WHERE user_id = ? ORDER BY report_id DESC LIMIT 1";
$report_stmt = $db->prepare($report_sql);
$report_stmt->bind_param("i", $user_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

$report_id = null;

if ($report_result->num_rows > 0) {
    // Use existing report
    $report_row = $report_result->fetch_assoc();
    $report_id = $report_row['report_id'];
} else {
    // CREATE NEW REPORT (as per your requirement)
    $create_report = $db->prepare("INSERT INTO report (user_id) VALUES (?)");
    $create_report->bind_param("i", $user_id);
    
    if ($create_report->execute()) {
        $report_id = $create_report->insert_id;
        
        // ===== INITIALIZE KYC6 STATUS =====
        $kyc6_sql = "INSERT INTO kyc6 (report_id, status, created_date) VALUES (?, 'Pending', NOW())";
        $kyc6_stmt = $db->prepare($kyc6_sql);
        $kyc6_stmt->bind_param("i", $report_id);
        $kyc6_stmt->execute();
        $kyc6_stmt->close();

    
        // Send immediate reminder to KYC6 admins
        require_once "kyc6_reminder.php";
        $reminder = new KYC6Reminder($db);
        $reminder->sendPendingReminders();
        
    } else {
        $_SESSION['upload_error'] = "Failed to create application record.";
        header("Location: user_homePage.php");
        exit();
    }
    $create_report->close();
}
$report_stmt->close();

// ===== 7. CREATE UNIQUE FILENAME AND UPLOAD FILE =====
$dateTime = date("Ymd_His");
$newFileName = "ic_copy_" . $safeEmail . "_" . $dateTime . "." . $fileExtension;
$targetPath = $uploadDir . $newFileName;

// Move uploaded file from temporary location to destination folder
if (move_uploaded_file($_FILES['ic_picture']['tmp_name'], $targetPath)) {
    // ===== 8. SAVE FILE PATH AND IC NUMBER TO DATABASE =====
    $relativeFilePath = "ic_picture/" . $newFileName;

    $update_stmt = $db->prepare("UPDATE user SET ic_picture = ?, ic_no = ? WHERE user_id = ?");
    $update_stmt->bind_param("ssi", $relativeFilePath, $ic_no, $user_id);

    if ($update_stmt->execute()) {
        // SUCCESS: File uploaded and path saved to database
        $_SESSION['upload_success'] = "IC uploaded successfully! Background check (KYC6) has been initiated.";
        $_SESSION['uploaded_file_name'] = $newFileName;
    } else {
        // ERROR: File uploaded but database update failed
        $_SESSION['upload_error'] = "Failed to save file path and IC number in the database.";
    }

    $update_stmt->close();

} else {
    // ERROR: File upload failed
    $_SESSION['upload_error'] = "Failed to upload the file. Please try again.";
}

// ===== 9. REDIRECT BACK TO HOMEPAGE =====
header("Location: user_homePage.php");
exit();
?>