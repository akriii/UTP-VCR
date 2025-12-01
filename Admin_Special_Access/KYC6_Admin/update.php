<?php
session_start();
include('../../config.php');
require '../../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load environment variables
$basePath = dirname(dirname(__DIR__));
$dotenv = Dotenv::createImmutable($basePath);
$dotenv->load();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userID = $_POST['user_id'] ?? '';
    // FIX 1: Capture the report_id from the hidden input
    $reportID = $_POST['report_id'] ?? ''; 
    $status = $_POST['status'] ?? '';

    // Check admin session
    if ($_SESSION['admin_role'] != 'kyc6_admin') {
        header("Location: ../login_admin.php");
        exit();
    }
    $adminID = $_SESSION['admin_id'];

    // Debug: Check what data we're receiving
    error_log("=== UPDATE DEBUG ===");
    error_log("User ID: $userID, Report ID: $reportID, Status: $status");

    // Debug: Check connection
    if (!$db) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Start transaction for data integrity
    mysqli_begin_transaction($db);

    try {
        // Define Current Date
        $currentDate = date('Y-m-d');

        // ==============================
        // Logic to determine Report ID
        // ==============================
        
        // Only if reportID was NOT sent by the form, try to find it (Fallback logic)
        if (empty($reportID)) {
            $getReport = "SELECT report_id FROM report WHERE user_id = ? AND vendor_code IS NULL ORDER BY report_id DESC LIMIT 1";
            $stmt = mysqli_prepare($db, $getReport);
            if (!$stmt) {
                throw new Exception("Failed to prepare getReport query: " . mysqli_error($db));
            }
            mysqli_stmt_bind_param($stmt, "i", $userID);
            mysqli_stmt_execute($stmt);
            $reportResult = mysqli_stmt_get_result($stmt);
            $reportData = mysqli_fetch_assoc($reportResult);
            mysqli_stmt_close($stmt);
            
            $reportID = $reportData ? $reportData['report_id'] : null;
        }

        if (!$reportID) {
            throw new Exception("No report found to update.");
        }

        // ==============================
        // Check if kyc6 record exists, if not create it
        // ==============================
        $checkKyc6 = "SELECT report_id FROM kyc6 WHERE report_id = ?";
        $stmt = mysqli_prepare($db, $checkKyc6);
        mysqli_stmt_bind_param($stmt, "i", $reportID);
        mysqli_stmt_execute($stmt);
        $kyc6Result = mysqli_stmt_get_result($stmt);
        $kyc6Exists = mysqli_fetch_assoc($kyc6Result) ? true : false;
        mysqli_stmt_close($stmt);

        // If no kyc6 record exists, insert one WITH DATE
        if (!$kyc6Exists) {
            $insertKyc6 = "INSERT INTO kyc6 (report_id, status, approved_date) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($db, $insertKyc6);
            mysqli_stmt_bind_param($stmt, "iss", $reportID, $status, $currentDate);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create KYC6 record: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmt);
        }

        // ==============================
        // Handle KYC6 file upload
        // ==============================
        if (!empty($_FILES['kyc6']['name']) && $_FILES['kyc6']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../../kyc6/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            $fileExtension = pathinfo($_FILES['kyc6']['name'], PATHINFO_EXTENSION);
            $ekycFile = "kyc6_" . $userID . "_" . time() . "." . $fileExtension;
            $targetFilePath = $targetDir . $ekycFile;
            
            if (move_uploaded_file($_FILES["kyc6"]["tmp_name"], $targetFilePath)) {
                $updateEkyc = "UPDATE kyc6 SET KYC6_file = ? WHERE report_id = ?";
                $stmt = mysqli_prepare($db, $updateEkyc);
                if (!$stmt) {
                    throw new Exception("Failed to prepare updateKYC6 query: " . mysqli_error($db));
                }
                mysqli_stmt_bind_param($stmt, "si", $ekycFile, $reportID);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update KYC6 file: " . mysqli_error($db));
                }
                mysqli_stmt_close($stmt);
            } else {
                throw new Exception("Failed to upload KYC6 file.");
            }
        }

        // ==============================
        // Update KYC6 status AND approved_date
        // ==============================
        $updateReport = "UPDATE kyc6 SET status = ?, approved_date = ? WHERE report_id = ?";
        $stmt = mysqli_prepare($db, $updateReport);
        if (!$stmt) {
            throw new Exception("Failed to prepare updateReport query: " . mysqli_error($db));
        }
        
        mysqli_stmt_bind_param($stmt, "ssi", $status, $currentDate, $reportID);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating status in kyc6 table: " . mysqli_error($db));
        }
        mysqli_stmt_close($stmt);
        
        // ============================================================
        if ($status === 'Fail') {
            $updateReportCompleted = "UPDATE report SET completed_date = ? WHERE report_id = ?";
            $stmt = mysqli_prepare($db, $updateReportCompleted);
            if (!$stmt) {
                throw new Exception("Failed to prepare updateReportCompleted query: " . mysqli_error($db));
            }
            mysqli_stmt_bind_param($stmt, "si", $currentDate, $reportID);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating completed_date in report table: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmt);
        }
        // ============================================================

        // Commit transaction
        mysqli_commit($db);

        // ==============================
        // Send email notification
        // ==============================
        if (in_array($status, ['Pass', 'Fail', 'Alert'])) {
            
            // Get user details
            $userType = "SELECT user_type FROM user WHERE user_id = ? LIMIT 1";
            $stmt1 = mysqli_prepare($db, $userType);
            mysqli_stmt_bind_param($stmt1, "i", $userID);
            mysqli_stmt_execute($stmt1);
            $typeResult = mysqli_stmt_get_result($stmt1);
            $typeData = mysqli_fetch_assoc($typeResult);
            mysqli_stmt_close($stmt1);
            
            // Build query based on user type
            if ($typeData['user_type'] == "student") {
                $userQuery = "SELECT u.user_email, u.ic_no AS IC, s.student_full_name AS full_name FROM user u JOIN student s ON u.user_id = s.user_id WHERE u.user_id = ?";
            } elseif ($typeData['user_type'] == "external_appointment") {
                $userQuery = "SELECT u.user_email, u.ic_no AS IC, e.external_full_name AS full_name FROM user u JOIN external_appointment e ON u.user_id = e.user_id WHERE u.user_id = ?";
            } elseif ($typeData['user_type'] == "company") {
                $userQuery = "SELECT u.user_email, u.ic_no AS IC, c.company_name AS full_name FROM user u JOIN company c ON u.user_id = c.user_id WHERE u.user_id = ?";
            } else {
                $userQuery = "SELECT email, full_name, ic_no AS IC FROM user WHERE user_id = ?";
            }

            $stmt = mysqli_prepare($db, $userQuery);
            mysqli_stmt_bind_param($stmt, "i", $userID);
            mysqli_stmt_execute($stmt);
            $userResult = mysqli_stmt_get_result($stmt);
            $userData = mysqli_fetch_assoc($userResult);
            mysqli_stmt_close($stmt);

            if ($userData) {
                $userEmail = $userData['user_email'];
                $userName = $userData['full_name'];
                $userIC = $userData['IC'];

                $mail = new PHPMailer(true);
                try {
                    // SMTP config
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USER'];
                    $mail->Password = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = 'ssl';
                    $mail->Port = 465;
                    
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    
                    $mail->SMTPDebug = 0; // Turn off debug for production
                    $mail->setFrom($_ENV['SMTP_USER'], 'UTP VCR');
                    $mail->addAddress($userEmail, $userName);
                    $mail->addReplyTo($_ENV['SMTP_USER'], 'UTP VCR');
                    $mail->isHTML(true);

                    if ($status === 'Pass' || $status === 'Alert') {
                        $mail->Subject = 'KYC6 Verification Result: Approved';
                        $mail->Body = "
                            <p>Dear <strong>$userName</strong>,</p>
                            <p>Your KYC6 verification (IC: $userIC) has been <strong>approved</strong> by our admin team.</p>
                            <p>Please proceed with the next steps in your application process.</p>
                            <br><p>Regards,<br>UTP VCR</p>
                        ";
                    } elseif ($status === 'Fail') {
                        $mail->Subject = 'KYC6 Verification Result: Rejected';
                        $mail->Body = "
                            <p>Dear <strong>$userName</strong>,</p>
                            <p>We regret to inform you that your KYC6 verification (IC: $userIC) has been <strong>rejected</strong>.</p>
                            <p>Please contact our admin for clarification or re-submit your documents.</p>
                            <br><p>Regards,<br>UTP VCR</p>
                        ";
                    }

                    $mail->send();
                } catch (Exception $e) {
                    error_log('PHPMailer Exception: ' . $e->getMessage());
                }
            }
        }

        // FIX 2: Redirect back WITH report_id
        echo "<script>alert('Record updated successfully!'); window.location.href='view_user.php?user_id=$userID&report_id=$reportID';</script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($db);
        // FIX 3: Redirect back WITH report_id on error too
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.location.href='view_user.php?user_id=$userID&report_id=$reportID';</script>";
        exit();
    }
}
?>