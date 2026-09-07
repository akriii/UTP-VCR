<?php
session_start();
include('../../../config.php');
require_once('../../../s3.php');

// Check permissions
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = trim($_POST['user_id']); 
    $report_id = $_POST['report_id']; 
    $vendor_code = isset($_POST['vendor_code']) ? trim($_POST['vendor_code']) : null;
    $expired_date = date('Y-m-d', strtotime('+2 year')); 
    $admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : null;
    
    // Default fallback for filename
    $user_ic_string = "User_" . $user_id; 

    // 1. Get User IC & Info
    $userQuery = "SELECT ic_no, ic_picture, user_type FROM user WHERE user_id = ?";
    $stmtUser = mysqli_prepare($db, $userQuery);
    $userData = [];
    
    if ($stmtUser) {
        mysqli_stmt_bind_param($stmtUser, "i", $user_id);
        mysqli_stmt_execute($stmtUser);
        $resUser = mysqli_stmt_get_result($stmtUser);
        $userData = mysqli_fetch_assoc($resUser);
        mysqli_stmt_close($stmtUser);

        if ($userData && !empty($userData['ic_no'])) {
            $user_ic_string = $userData['ic_no'];
        }
    }

    // 2. Update Database
    $sql = "UPDATE report SET vendor_code = ?, admin_id = ?, expired_date = ?, completed_date = NOW() WHERE report_id = ?";
    $stmt = mysqli_prepare($db, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sisi", $vendor_code, $admin_id, $expired_date, $report_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['success'] = "Vendor code updated successfully!";
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($db);
    }

    // ==========================================
    // 3. ZIP FILE CREATION (SMART PATH CHECK)
    // ==========================================
    
    $zip = new ZipArchive();
    
    // Sanitize filename
    $clean_ic = preg_replace('/[^A-Za-z0-9_\-]/', '', $user_ic_string);
    $zipFileName = "UTPVCR_" . $clean_ic . "_" . date("F_Y") . ".zip";
    
    // The archive is assembled in the system temp folder, then uploaded to S3.
    $zipPath = tempnam(sys_get_temp_dir(), 'utpzip_') . '.zip';

    // Temp copies pulled down from S3, removed once the archive is closed.
    $tempDownloads = [];

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

        // --- SMART FILE ADDER FUNCTION ---
        // Pulls the object out of S3 into a temp file, then adds it to the archive.
        function addSmartFile($zip, $folder, $dbFilename) {
            global $tempDownloads;

            if (empty($dbFilename)) return;

            // Accepts both DB shapes: "bank_statement/file.pdf" and bare "file.pdf"
            $key = s3_key($dbFilename, $folder);
            if ($key === '') {
                error_log("ZIP ERROR: Unusable file reference '$dbFilename' for folder '$folder'");
                return;
            }

            $localPath = s3_download_temp($key);
            if ($localPath === false) {
                error_log("ZIP ERROR: Could not fetch '$key' from S3.");
                return;
            }

            $tempDownloads[] = $localPath;
            $zip->addFile($localPath, basename($key));
        }

        // 1. IC Picture (User Table)
        if (!empty($userData['ic_picture'])) {
            addSmartFile($zip,"ic_picture", $userData['ic_picture']);
        }

        // 2. KYC6
        $q = mysqli_query($db, "SELECT KYC6_file FROM kyc6 WHERE report_id = '$report_id'");
        if ($row = mysqli_fetch_assoc($q)) {
            addSmartFile($zip,"kyc6", $row['KYC6_file']);
        }

        // 3. Bank Statement
        $q = mysqli_query($db, "SELECT statement_file FROM bank_statement WHERE report_id = '$report_id'");
        if ($row = mysqli_fetch_assoc($q)) {
            addSmartFile($zip,"bank_statement", $row['statement_file']);
        }

        // 4. Declaration Form
        $q = mysqli_query($db, "SELECT declaration_file FROM declaration_form WHERE report_id = '$report_id'");
        if ($row = mysqli_fetch_assoc($q)) {
            addSmartFile($zip,"declaration_form", $row['declaration_file']);
        }

        // 5. FYP Form (Student)
        if (($userData['user_type'] ?? '') == 'student') {
            $q = mysqli_query($db, "SELECT fyp_form_file FROM fyp_form_02 WHERE report_id = '$report_id'");
            if ($row = mysqli_fetch_assoc($q)) {
                addSmartFile($zip,"fyp_form_file", $row['fyp_form_file']);
            }
        }

        // 6. Appointment Letter (External)
        if (($userData['user_type'] ?? '') == 'external_appointment') {
            $q = mysqli_query($db, "SELECT appointment_file FROM appointment_letter WHERE report_id = '$report_id'");
            if ($row = mysqli_fetch_assoc($q)) {
                addSmartFile($zip,"appointment_file", $row['appointment_file']);
            }
        }
        
        $zip->close();

        // Clean up the temp copies now that they are inside the archive
        foreach ($tempDownloads as $tempFile) {
            @unlink($tempFile);
        }

        if (file_exists($zipPath)) {
            $zipKey = "generated_zips/" . $zipFileName;

            if (s3_put_file($zipPath, $zipKey, 'application/zip')) {
                $_SESSION['download_zip'] = $zipFileName;
            } else {
                $_SESSION['error'] = "Zip file could not be uploaded to cloud storage.";
            }

            @unlink($zipPath);
        } else {
            $_SESSION['error'] = "Zip file was not created.";
        }

    } else {
        $_SESSION['error'] = "Failed to initialize Zip archive.";
    }

    header("Location: view_user.php?user_id=" . urlencode($user_id) . "&report_id=" . urlencode($report_id));
    exit();

} else {
    header("Location: homepage.php");
    exit();
}
?>