<?php
session_start();
include('../../../config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Check if admin is logged in and is master admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : null;
    // FIX 1: Capture Report ID
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    
    $verify_status = isset($_POST['verify_status']) ? (int)$_POST['verify_status'] : null;
    $account_no = isset($_POST['account_no']) ? trim($_POST['account_no']) : null;
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : null;
    $date = isset($_POST['date']) ? trim($_POST['date']) : null;
    if ($date === '') $date = null;

    // Enhanced server-side expiration check
    $is_expired_server = false;
    if (!empty($date)) {
        $statement_date = DateTime::createFromFormat('Y-m-d', $date);
        $current_date = new DateTime();
        
        if ($statement_date !== false) {
            $interval = $current_date->diff($statement_date);
            $months_diff = $interval->y * 12 + $interval->m;
            
            // Check if statement is more than 3 months old OR in the future
            if ($months_diff >= 3 || $interval->invert === 0) {
                $is_expired_server = true;
                error_log("Server-side expiration check: Statement date $date is expired (months diff: $months_diff)");
            }
        }
    }

    // Force reject if bank statement is expired
    $client_is_expired = isset($_POST['is_expired']) && $_POST['is_expired'] == '1';
    if (($client_is_expired || $is_expired_server) && $verify_status === 1) {
        $verify_status = 0; // Force reject
        error_log("Auto-rejecting expired bank statement for user: $user_id");
        $_POST['is_expired'] = '1';
    }

    // Validate inputs
    if (empty($user_id) || empty($report_id) || $verify_status === null) {
        $_SESSION['error'] = "All fields are required";
        header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" . urlencode($report_id));
        exit();
    }

    // FIX 2: Removed the "SELECT report_id" query block. We rely on POST data.

    // Helper function
    function mysqli_stmt_bind_param_array($stmt, $types, $values) {
        $params = array_merge(array($types), $values);
        $refs = array();
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        return call_user_func_array(array($stmt, 'bind_param'), $refs);
    }

    $attemptSuccess = false;
    $attemptErrors = array();

    // Build update query
    $cols = array();
    $types = "";
    $values = array();

    $cols[] = "bankStatement_verify = ?";
    $types .= "i";
    $values[] = $verify_status;

    if ($account_no !== null) {
        $cols[] = "account_no = ?";
        $types .= "s";
        $values[] = $account_no;
    }

    if ($bank_name !== null) {
        $cols[] = "bank_name = ?";
        $types .= "s";
        $values[] = $bank_name;
    }

    if ($date !== null) {
        $cols[] = "`date` = ?";
        $types .= "s";
        $values[] = $date;
    }

    // FIX 3: Use report_id directly in WHERE clause
    $sql = "UPDATE bank_statement SET " . implode(", ", $cols) . " WHERE report_id = ?";
    $types .= "i";
    $values[] = $report_id;

    $stmt2 = mysqli_prepare($db, $sql);
    if (!$stmt2) {
        $attemptErrors[] = "Prepare failed: " . mysqli_error($db);
    } else {
        if (mysqli_stmt_bind_param_array($stmt2, $types, $values)) {
            if (mysqli_stmt_execute($stmt2)) {
                $attemptSuccess = true;
            } else {
                $attemptErrors[] = "Exec failed: " . mysqli_stmt_error($stmt2);
            }
        } else {
            $attemptErrors[] = "Bind failed";
        }
        mysqli_stmt_close($stmt2);
    }

    if ($attemptSuccess) {
        $final_is_expired = $client_is_expired || $is_expired_server;
        
        if ($final_is_expired && $verify_status === 0) {
            $_SESSION['success'] = "Bank statement rejected automatically (expired). Email sent to user.";
        } else {
            $_SESSION['success'] = "Bank statement verification updated successfully!";
        }
        
        // Send email if expired
        if ($final_is_expired) {
            require '../../../vendor/autoload.php';
            
            $basePath = dirname(dirname(dirname(__DIR__)));
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
            
            $userEmail = $_POST['user_email'];
            $userName = $_POST['user_name'];
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USER'];
                $mail->Password = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = 'ssl';
                $mail->Port = 465;
                $mail->Timeout = 10;
                $mail->SMTPDebug = 0;
                
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom($_ENV['SMTP_USER'], 'UTP VCR');
                $mail->addAddress($userEmail, $userName);
                $mail->isHTML(true);
                $mail->Subject = 'Action Required: Update Your Bank Statement';
                $mail->Body = "
                    <p>Dear <strong>$userName</strong>,</p>
                    <p>We noticed that your bank statement is more than 3 months old and has expired.</p>
                    <p>Please upload a <strong>new bank statement</strong> that is less than 3 months old.</p>
                    <br><p>Regards,<br>UTP VCR</p>
                ";

                if ($mail->send()) {
                    $_SESSION['success'] .= " Email sent.";
                } else {
                    $_SESSION['success'] .= " (Email failed)";
                }

            } catch (Exception $e) {
                error_log('PHPMailer Exception: ' . $e->getMessage());
            }
        }
    } else {
        $_SESSION['error'] = "Failed to update bank statement. " . implode(", ", $attemptErrors);
    }

    // FIX 4: Redirect back with report_id
    header("Location: view_user.php?user_id=" .urlencode( $user_id) . "&report_id=" .urlencode($report_id) );
    exit();
} else {
    header("Location: homepage.php");
    exit();
}
?>