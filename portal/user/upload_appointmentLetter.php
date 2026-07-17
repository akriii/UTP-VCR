<?php
session_start();
include "../../config.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$uploadDir = "../../documents/appointment_file/";

// Ensure upload folder exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Check if file was uploaded
if (!isset($_FILES['appointment_file']) || $_FILES['appointment_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Please select a file to upload.";
    header("Location: user_homePage.php");
    exit();
}

// Get file extension
$fileExtension = pathinfo($_FILES['appointment_file']['name'], PATHINFO_EXTENSION);

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

// ===== 2. CHECK IF USER IS EXTERNAL APPOINTMENT =====
$user_type_stmt = $db->prepare("SELECT user_type FROM user WHERE user_id = ?");
$user_type_stmt->bind_param("i", $user_id);
$user_type_stmt->execute();
$user_type_result = $user_type_stmt->get_result();
$user_type_row = $user_type_result->fetch_assoc();
$user_type = $user_type_row['user_type'];
$user_type_stmt->close();

if ($user_type !== 'external_appointment') {
    $_SESSION['upload_error'] = "This feature is only available for external appointments.";
    header("Location: user_homePage.php");
    exit();
}

// ===== 3. GET LATEST REPORT_ID FOR THIS USER =====
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

// ===== 4. CHECK IF APPOINTMENT LETTER ALREADY EXISTS FOR THIS REPORT =====
$check_appointment_stmt = $db->prepare("SELECT appointment_file, appointmentLetter_verify FROM appointment_letter WHERE report_id = ?");
$check_appointment_stmt->bind_param("i", $report_id);
$check_appointment_stmt->execute();
$check_appointment_result = $check_appointment_stmt->get_result();

$existing_appointment_record = null;
if ($check_appointment_result->num_rows > 0) {
    $existing_appointment_record = $check_appointment_result->fetch_assoc();
    
    // CORRECTED: Only prevent upload if the existing record is APPROVED (appointmentLetter_verify = 1)
    // Allow re-upload if rejected (appointmentLetter_verify = 0) or pending (NULL)
    if ($existing_appointment_record['appointmentLetter_verify'] === 1) {
        $_SESSION['upload_error'] = "You have already submitted your appointment letter for this application and it has been approved. No need to re-upload.";
        $check_appointment_stmt->close();
        header("Location: user_homePage.php");
        exit();
    }
}
$check_appointment_stmt->close();

// ===== 5. CREATE UNIQUE FILENAME =====
$dateTime = date("Ymd_His");
$newFileName = "appointment_letter_" . $safeEmail . "_" . $dateTime . "." . $fileExtension;
$targetPath = $uploadDir . $newFileName;

// ===== 6. MOVE UPLOADED FILE =====
if (move_uploaded_file($_FILES['appointment_file']['tmp_name'], $targetPath)) {

    // ===== 7. SAVE FILE PATH TO DATABASE =====
    $relativeFilePath = "appointment_file/" . $newFileName;

    if ($existing_appointment_record) {
        // UPDATE existing record (re-upload after rejection or while pending)
        $update_appointment = $db->prepare("UPDATE appointment_letter SET appointment_file = ?, appointmentLetter_verify = NULL WHERE report_id = ?");
        $update_appointment->bind_param("si", $relativeFilePath, $report_id);
        
        if ($update_appointment->execute()) {
            if ($existing_appointment_record['appointmentLetter_verify'] === 0) {
                $_SESSION['upload_success'] = "Appointment letter re-uploaded successfully after rejection! Waiting for admin verification.";
            } else {
                $_SESSION['upload_success'] = "Appointment letter updated successfully! Waiting for admin verification.";
            }
        } else {
            $_SESSION['upload_error'] = "Database error: Unable to update appointment letter.";
        }
        $update_appointment->close();
    } else {
        // INSERT new record
        $insert_appointment = $db->prepare("INSERT INTO appointment_letter (report_id, appointment_file, appointmentLetter_verify) VALUES (?, ?, NULL)");
        $insert_appointment->bind_param("is", $report_id, $relativeFilePath);

        if ($insert_appointment->execute()) {
            $_SESSION['upload_success'] = "Appointment letter uploaded successfully! Waiting for admin verification.";
        } else {
            $_SESSION['upload_error'] = "Database error: Unable to save appointment letter.";
        }
        $insert_appointment->close();
    }

} else {
    $_SESSION['upload_error'] = "Failed to upload file. Please try again.";
}

// ===== 8. REDIRECT TO HOMEPAGE =====
header("Location: user_homePage.php");
exit();
?>