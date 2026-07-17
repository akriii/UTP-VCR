<?php
session_start();
include "../../config.php";
require_once "pdf_extractor.php"; // Include our new extractor

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$uploadDir = "../../documents/bank_statement/";

// Ensure upload folder exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Check if file was uploaded
if (!isset($_FILES['financial_file']) || $_FILES['financial_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = "Please select a file to upload.";
    header("Location: user_homePage.php");
    exit();
}

// Get file extension
$fileExtension = strtolower(pathinfo($_FILES['financial_file']['name'], PATHINFO_EXTENSION));

// Only allow PDF files for text extraction
if ($fileExtension !== 'pdf') {
    $_SESSION['upload_error'] = "Please upload a PDF file for bank statements to enable automatic data extraction.";
    header("Location: user_homePage.php");
    exit();
}

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

// ===== 3. CHECK IF BANK STATEMENT ALREADY EXISTS FOR THIS REPORT =====
$check_bank_stmt = $db->prepare("SELECT statement_file, bankStatement_verify FROM bank_statement WHERE report_id = ?");
$check_bank_stmt->bind_param("i", $report_id);
$check_bank_stmt->execute();
$check_bank_result = $check_bank_stmt->get_result();

$existing_bank_record = null;
if ($check_bank_result->num_rows > 0) {
    $existing_bank_record = $check_bank_result->fetch_assoc();
    
    if ($existing_bank_record['bankStatement_verify'] !== 0) {
        $_SESSION['upload_error'] = "You have already submitted your bank statement for this application.";
        $check_bank_stmt->close();
        header("Location: user_homePage.php");
        exit();
    }
}
$check_bank_stmt->close();

// ===== 4. CREATE UNIQUE FILENAME AND UPLOAD =====
$dateTime = date("Ymd_His");
$newFileName = "bank_statement_" . $safeEmail . "_" . $dateTime . ".pdf";
$targetPath = $uploadDir . $newFileName;

if (move_uploaded_file($_FILES['financial_file']['tmp_name'], $targetPath)) {
    
    // ===== 5. EXTRACT INFORMATION FROM PDF =====
$extractor = new BankStatementExtractor();
$extractionResult = $extractor->extractBankInfo($targetPath);

$account_no = null;
$bank_name = trim($_POST['bank_name']);  // GET FROM USER SELECTION
$statement_date = null;

if ($extractionResult['success']) {
    $account_no = $extractionResult['account_number'];
    // Bank name comes from user selection above, NOT extraction
    $statement_date = $extractionResult['statement_date'];
    
    // Log extraction results for debugging
    error_log("PDF Extraction Results for user $email:");
    error_log("Account: " . ($account_no ?? 'Not found'));
    error_log("Bank (from form): " . $bank_name);  // This is from user selection
    error_log("Date: " . ($statement_date ?? 'Not found'));
} else {
    error_log("PDF Extraction Failed: " . $extractionResult['error']);
}

// ===== 6. SAVE TO DATABASE =====
$relativeFilePath = "bank_statement/" . $newFileName;

if ($existing_bank_record) {
    // UPDATE existing record
    $update_bank = $db->prepare("UPDATE bank_statement SET statement_file = ?, account_no = ?, bank_name = ?, date = ?, bankStatement_verify = NULL WHERE report_id = ?");
    $update_bank->bind_param("ssssi", $relativeFilePath, $account_no, $bank_name, $statement_date, $report_id);
    
    if ($update_bank->execute()) {
        $message = "Bank statement re-uploaded successfully!";
        if ($account_no) {
            $message .= " We automatically extracted your account information.";
        }
        $_SESSION['upload_success'] = $message;
    } else {
        $_SESSION['upload_error'] = "Database error: Unable to update bank statement.";
    }
    $update_bank->close();
} else {
    // INSERT new record
    $insert_bank = $db->prepare("INSERT INTO bank_statement (report_id, statement_file, account_no, bank_name, date, bankStatement_verify) VALUES (?, ?, ?, ?, ?, NULL)");
    $insert_bank->bind_param("issss", $report_id, $relativeFilePath, $account_no, $bank_name, $statement_date);
    
    if ($insert_bank->execute()) {
        $message = "Bank statement uploaded successfully!";
        if ($account_no) {
            $message .= " We automatically extracted your account information.";
        }
        $_SESSION['upload_success'] = $message;
    } else {
        $_SESSION['upload_error'] = "Database error: Unable to save bank statement.";
    }
    $insert_bank->close();
}

// Store extraction results in session for display
$_SESSION['extraction_results'] = [
    'account_number' => $account_no,
    'bank_name' => $bank_name,  // This shows the MANUAL selection
    'statement_date' => $statement_date,
    'success' => $extractionResult['success']
];
    
} else {
    $_SESSION['upload_error'] = "Failed to upload file.";
}

// ===== 7. REDIRECT TO HOMEPAGE =====
header("Location: user_homePage.php");
exit();
?>