<?php
session_start();
include "../../config.php";
require_once "../../s3.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$uploadFolder = "fyp_form_file"; // S3 key prefix

// Check if file was uploaded
if (!isset($_FILES['fyp_form_file']) || $_FILES['fyp_form_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Please select a file to upload.";
    header("Location: user_homePage.php");
    exit();
}

// Get file extension
$fileExtension = pathinfo($_FILES['fyp_form_file']['name'], PATHINFO_EXTENSION);

// Make email safe for filename
$safeEmail = preg_replace('/[^a-zA-Z0-9]/', '_', $email);

// ===== 1. GET USER_ID =====
$stmt = $db->prepare("SELECT user_id FROM user WHERE user_email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['upload_error'] = "User not found.";
    header("Location: user_homePage.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
$user_id = $user['user_id'];

// ===== 2. GET LATEST REPORT_ID FOR THIS USER =====
$report_stmt = $db->prepare("SELECT report_id FROM report WHERE user_id = ? ORDER BY report_id DESC LIMIT 1");
$report_stmt->bind_param("i", $user_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

if ($report_result->num_rows === 0) {
    $_SESSION['upload_error'] = "Please create an application first by uploading your IC.";
    header("Location: user_homePage.php");
    exit();
}

$report = $report_result->fetch_assoc();
$report_id = $report['report_id'];
$report_stmt->close();

// ===== 3. CHECK IF FYP FORM 02 ALREADY EXISTS FOR THIS REPORT =====
$check_fyp_stmt = $db->prepare("SELECT fyp_form_file, fypForm_verify FROM fyp_form_02 WHERE report_id = ?");
$check_fyp_stmt->bind_param("i", $report_id);
$check_fyp_stmt->execute();
$check_fyp_result = $check_fyp_stmt->get_result();

$existing_form_record = null;
if ($check_fyp_result->num_rows > 0) {
    $existing_form_record = $check_fyp_result->fetch_assoc();
    
    // CORRECTED: Only prevent upload if the existing record is APPROVED (fypForm_verify = 1)
    // Allow re-upload if rejected (fypForm_verify = 0) or pending (NULL)
    if ($existing_form_record['fypForm_verify'] === 1) {
        $_SESSION['upload_error'] = "You have already submitted your FYP Form 02 for this application and it has been approved. No need to re-upload.";
        $check_fyp_stmt->close();
        header("Location: user_homePage.php");
        exit();
    }
}
$check_fyp_stmt->close();

// ===== 4. CREATE UNIQUE FILENAME =====
$dateTime = date("Ymd_His");
$newFileName = "fypForm02_" . $safeEmail . "_" . $dateTime . "." . $fileExtension;
$relativeFilePath = $uploadFolder . "/" . $newFileName;

// ===== 5. UPLOAD FILE TO S3 =====
if (s3_put_file($_FILES['fyp_form_file']['tmp_name'], $relativeFilePath, $_FILES['fyp_form_file']['type'])) {

    // ===== 6. SAVE FILE PATH TO DATABASE =====
    if ($existing_form_record) {
        // UPDATE existing record (re-upload after rejection or while pending)
        $update_fyp = $db->prepare("UPDATE fyp_form_02 SET fyp_form_file = ?, fypForm_verify = NULL WHERE report_id = ?");
        $update_fyp->bind_param("si", $relativeFilePath, $report_id);
        
        if ($update_fyp->execute()) {
            if ($existing_form_record['fypForm_verify'] === 0) {
                $_SESSION['upload_success'] = "FYP Form 02 re-uploaded successfully after rejection! Waiting for admin verification.";
            } else {
                $_SESSION['upload_success'] = "FYP Form 02 updated successfully! Waiting for admin verification.";
            }
        } else {
            $_SESSION['upload_error'] = "Database error: Unable to update FYP Form 02.";
        }
        $update_fyp->close();
    } else {
        // INSERT new record
        $insert_fyp = $db->prepare("INSERT INTO fyp_form_02 (report_id, fyp_form_file, fypForm_verify) VALUES (?, ?, NULL)");
        $insert_fyp->bind_param("is", $report_id, $relativeFilePath);

        if ($insert_fyp->execute()) {
            $_SESSION['upload_success'] = "FYP Form 02 uploaded successfully! Waiting for admin verification.";
        } else {
            $_SESSION['upload_error'] = "Database error: Unable to save file path.";
        }
        $insert_fyp->close();
    }

} else {
    $_SESSION['upload_error'] = "Failed to upload file to cloud storage.";
}

// ===== 7. REDIRECT TO HOMEPAGE =====
header("Location: user_homePage.php");
exit();
?>