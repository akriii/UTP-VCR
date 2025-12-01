

<?php
session_start();
include "../config.php";

// If user is not logged in, redirect to login page
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Get user information from session
$email = $_SESSION['email'];
$user_type = $_SESSION['user_type'];
$fullname = $_SESSION['fullname'];


// ===== 1. GET USER ID =====
$user_id_stmt = $db->prepare("SELECT user_id FROM user WHERE user_email = ?");
$user_id_stmt->bind_param("s", $email);
$user_id_stmt->execute();
$user_id_result = $user_id_stmt->get_result();
$user_id_row = $user_id_result->fetch_assoc();
$user_id = $user_id_row['user_id'] ??'';
$user_id_stmt->close();

// ===== 2. GET LATEST REPORT_ID FOR THIS USER =====
$report_sql = "SELECT report_id, vendor_code, completed_date FROM report WHERE user_id = ? ORDER BY report_id DESC LIMIT 1";
$report_stmt = $db->prepare($report_sql);
$report_stmt->bind_param("i", $user_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

$current_report_id = null;
$vendor_code = "";
$completed_date = null;

if ($report_result->num_rows > 0) {
    $report_row = $report_result->fetch_assoc();
    $current_report_id = $report_row['report_id'];
    $vendor_code = $report_row['vendor_code'] ?? "";
    $completed_date = $report_row['completed_date'] ?? null;
}
$report_stmt->close();



// ===== 3. KYC6 FAIL COOLING PERIOD CHECK =====
$kyc6_cooling_active = false;
$kyc6_days_remaining = 0;
$kyc6_next_eligible_date = "";

$kyc6_cooling_sql = "SELECT k.approved_date 
                     FROM kyc6 k 
                     JOIN report r ON k.report_id = r.report_id 
                     WHERE r.user_id = ? AND k.status = 'Fail' AND k.approved_date IS NOT NULL 
                     ORDER BY k.approved_date DESC LIMIT 1";
$kyc6_cooling_stmt = $db->prepare($kyc6_cooling_sql);
$kyc6_cooling_stmt->bind_param("i", $user_id);
$kyc6_cooling_stmt->execute();
$kyc6_cooling_result = $kyc6_cooling_stmt->get_result();

if ($kyc6_cooling_result->num_rows > 0) {
    $kyc6_cooling_row = $kyc6_cooling_result->fetch_assoc();
    $kyc6_approved_date = $kyc6_cooling_row['approved_date'];
    
    $kyc6_next_eligible_date = date('Y-m-d', strtotime($kyc6_approved_date . " + 6 months"));
    $kyc6_days_remaining = ceil((strtotime($kyc6_next_eligible_date) - time()) / (60 * 60 * 24));
    $kyc6_cooling_active = ($kyc6_days_remaining > 0);
}
$kyc6_cooling_stmt->close();

// ===== 4. CHECK FOR PENDING/INCOMPLETE APPLICATIONS =====
$has_pending_application = false;
$pending_sql = "SELECT report_id FROM report WHERE user_id = ? AND completed_date IS NULL ORDER BY report_id DESC LIMIT 1";
$pending_stmt = $db->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$has_pending_application = ($pending_result->num_rows > 0);
$pending_stmt->close();

// ===== 5. GET USER-SPECIFIC INFORMATION =====
$user_info = null;
if ($user_type === 'student') {
    $info_sql = "SELECT student_full_name, programme, student_id FROM student WHERE user_id = ?";
} elseif ($user_type === 'external_appointment') {
    $info_sql = "SELECT external_full_name, external_company_name, position, ae_name FROM external_appointment WHERE user_id = ?";
} elseif ($user_type === 'company') {
    $info_sql = "SELECT company_name, ssm_number FROM company WHERE user_id = ?";
}

$info_stmt = $db->prepare($info_sql);
$info_stmt->bind_param("i", $user_id);
$info_stmt->execute();
$info_result = $info_stmt->get_result();
$user_info = $info_result->fetch_assoc();
$info_stmt->close();

$current_ic = "";
$current_face = ""; // Initialize face variable

$ic_check_sql = "SELECT ic_picture, face_recognition FROM user WHERE user_id = ?";
$ic_stmt = $db->prepare($ic_check_sql);
$ic_stmt->bind_param("i", $user_id);
$ic_stmt->execute();
$ic_result = $ic_stmt->get_result();

if ($ic_result->num_rows > 0) {
    $ic_row = $ic_result->fetch_assoc();
    $current_ic = $ic_row['ic_picture'] ?? "";
    // Check if face_recognition column has data
    $current_face = $ic_row['face_recognition'] ?? ""; 
}
$ic_stmt->close();

$icUploaded = !empty($current_ic);
$faceCaptured = !empty($current_face); // True only if face image exists in DB

// ===== 7. CHECK KYC6 STATUS =====
$kyc6_status = "Pending";
$kyc6_sql = "SELECT status FROM kyc6 WHERE report_id = ? ORDER BY report_id DESC LIMIT 1";
$kyc6_stmt = $db->prepare($kyc6_sql);

if ($current_report_id) {
    $kyc6_stmt->bind_param("i", $current_report_id);
    $kyc6_stmt->execute();
    $kyc6_result = $kyc6_stmt->get_result();
    if ($kyc6_result->num_rows > 0) {
        $kyc6_row = $kyc6_result->fetch_assoc();
        $kyc6_status = $kyc6_row['status'] ?? "Pending";
    }
}
$kyc6_stmt->close();

// ===== 8. CHECK USER-SPECIFIC DOCUMENTS =====

// For STUDENTS: Check FYP Form 02
$fypForm02Uploaded = false;
$fypForm02FileName = '';
$fyp_verify_status = null; 

if ($user_type === 'student' && $current_report_id) {
    $fyp_sql = "SELECT fyp_form_file, fypForm_verify FROM fyp_form_02 WHERE report_id = ?";
    $fyp_stmt = $db->prepare($fyp_sql);
    $fyp_stmt->bind_param("i", $current_report_id);
    $fyp_stmt->execute();
    $fyp_result = $fyp_stmt->get_result();
    if ($fyp_result->num_rows > 0) {
        $fyp_row = $fyp_result->fetch_assoc();
        $fypForm02Uploaded = !empty($fyp_row['fyp_form_file']);
        $fypForm02FileName = basename($fyp_row['fyp_form_file']);
        $fyp_verify_status = $fyp_row['fypForm_verify']; 
    }
    $fyp_stmt->close();
}

// For EXTERNAL APPOINTMENT: Check Appointment Letter
$appointmentLetterUploaded = false;
$appointmentLetterFileName = '';
$appointment_verify_status = null; 

if ($user_type === 'external_appointment' && $current_report_id) {
    $appointment_sql = "SELECT appointment_file, appointmentLetter_verify FROM appointment_letter WHERE report_id = ?";
    $appointment_stmt = $db->prepare($appointment_sql);
    $appointment_stmt->bind_param("i", $current_report_id);
    $appointment_stmt->execute();
    $appointment_result = $appointment_stmt->get_result();
    if ($appointment_result->num_rows > 0) {
        $appointment_row = $appointment_result->fetch_assoc();
        $appointmentLetterUploaded = !empty($appointment_row['appointment_file']);
        $appointmentLetterFileName = basename($appointment_row['appointment_file']);
        $appointment_verify_status = $appointment_row['appointmentLetter_verify']; 
    }
    $appointment_stmt->close();
}

// ===== 9. CHECK BANK STATEMENT =====
$bankStatementUploaded = false;
$bankStatementFileName = '';
$bankStatementVerified = null;

if ($current_report_id) {
    $bank_sql = "SELECT statement_file, bankStatement_verify, date FROM bank_statement WHERE report_id = ?";
    $bank_stmt = $db->prepare($bank_sql);
    $bank_stmt->bind_param("i", $current_report_id);
    $bank_stmt->execute();
    $bank_result = $bank_stmt->get_result();
    if ($bank_result->num_rows > 0) {
        $bank_row = $bank_result->fetch_assoc();
        $bankStatementUploaded = !empty($bank_row['statement_file']);
        $bankStatementFileName = basename($bank_row['statement_file']);
        $bankStatementVerified = $bank_row['bankStatement_verify'];
    }
    $bank_stmt->close();
}

// ===== 10. CHECK DECLARATION FORM =====
$declarationExists = false;
$declarationVerified = null;

if ($current_report_id) {
    $declaration_sql = "SELECT declaration_file, declaration_verify FROM declaration_form WHERE report_id = ?";
    $declaration_stmt = $db->prepare($declaration_sql);
    $declaration_stmt->bind_param("i", $current_report_id);
    $declaration_stmt->execute();
    $declaration_result = $declaration_stmt->get_result();
    if ($declaration_result->num_rows > 0) {
        $declaration_row = $declaration_result->fetch_assoc();
        $declarationExists = true;
        $declarationVerified = $declaration_row['declaration_verify'];
    }
    $declaration_stmt->close();
}

//visibality check
$step1_completed = ($icUploaded && $faceCaptured); 
$step2_completed = ($kyc6_status === 'Pass' || $kyc6_status === 'Alert');
$step3_completed = false;
if ($user_type === 'student') {
    $step3_completed = $fypForm02Uploaded; // Removed approval check
} elseif ($user_type === 'external_appointment') {
    $step3_completed = $appointmentLetterUploaded; // Removed approval check
} elseif ($user_type === 'company') {
    $step3_completed = true; 
}


$step4_completed = $bankStatementUploaded; 
$step5_completed = $declarationExists; 
$step6_completed = !empty($vendor_code);

// VISIBILITY LOGIC 
$step1_visible = true; 
$step2_visible = $step1_completed; 
$step3_visible = $step2_completed; 
$step4_visible = $step3_completed; 
$step5_visible = $step4_completed; 
$step6_visible = $step5_completed;


$step1_visible = true; 
// Step 2 is ONLY visible if Step 1 is fully completed (IC + Face)
$step2_visible = $step1_completed; 
$step3_visible = $step2_completed; 
$step4_visible = $step3_completed; 
$step5_visible = $step4_completed; 
$step6_visible = $step5_completed;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <style>
    /* Enhanced CSS styles - */
:root {
        --sidebar-width: 250px;
        --sidebar-bg: #002776ff;
        --content-bg: #f5f6f7;
        --accent: #1A252F;
        --primary: #002776ff;
        --success: #28a745;
        --warning: #f1c40f;
        --danger: #e74c3c;
        --info: #17a2b8;
    }
    
    * { 
        box-sizing: border-box; 
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        min-height: 100vh;
        overflow-x: hidden;
        line-height: 1.6;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, var(--sidebar-bg) 0%, #001a5e 100%);
        color: #fff;
        padding: 25px 20px;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
    }
    
    .sidebar h2 { 
        margin: 0 0 25px; 
        font-size: 22px; 
        text-align: center;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .sidebar a { 
        color: #fff; 
        text-decoration: none; 
        padding: 12px 15px;
        border-radius: 8px; 
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .sidebar a:hover { 
        background: rgba(255,255,255,0.15);
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .sidebar a i {
        width: 20px;
        text-align: center;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 30px;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        min-height: 100vh;
    }

    #toggle-btn {
        background: #001a5e;
        color: white;
        border: none;
        font-size: 18px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 10px;
        margin-bottom: 25px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 39, 118, 0.2);
    }

    #toggle-btn:hover {
        background: #001a5e;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 39, 118, 0.3);
    }

    body.sidebar-closed .sidebar { 
        transform: translateX(calc(-1 * var(--sidebar-width))); 
    }
    
    body.sidebar-closed .main-content { 
        margin-left: 0; 
    }

    .step-box {
        background: #fff;
        border: 1px solid #e1e5eb;
        border-radius: 16px;
        padding: 25px;
        margin: 25px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .step-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary);
        opacity: 0.7;
    }

    .step-box:not(.hidden):hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(-3px);
        border-color: #c8d0e0;
    }

    /* Hidden steps styling */
    .step-box.hidden {
        display: none;
    }

    .step-box.locked {
        opacity: 0.6;
        background: #f8f9fa;
        border: 2px dashed #ccc;
    }

    .step-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .step-number {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #6c757d;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .step-number.completed {
        background: var(--success);
        transform: scale(1.1);
    }

    .step-number.current {
        background: var(--primary);
        box-shadow: 0 0 0 4px rgba(0, 39, 118, 0.2);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 39, 118, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(0, 39, 118, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 39, 118, 0); }
    }

    .step-header h3 {
        color: var(--primary);
        font-size: 1.4em;
        margin: 0;
        flex-grow: 1;
    }

    .status-box {
        padding: 10px 16px;
        border-radius: 8px;
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .status-box.pending { 
        background: linear-gradient(135deg, var(--warning) 0%, #e6b400 100%);
    }
    
    .status-box.pass { 
        background: linear-gradient(135deg, var(--success) 0%, #1e7e34 100%);
    }
    
    .status-box.fail { 
        background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
    }
    
    .status-box.alert { 
        background: linear-gradient(135deg, var(--info) 0%, #138496 100%);
    }

    .upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 24px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary) 0%, #001a5e 100%);
        color: #fff;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 15px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0, 39, 118, 0.3);
    }
    
    .upload-btn:hover { 
        background: linear-gradient(135deg, #001a5e 0%, #000d3d 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 39, 118, 0.4);
    }
    
    .upload-btn:disabled { 
        background: #cccccc; 
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .application-status {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        padding: 30px;
        border-radius: 16px;
        margin: 25px 0;
        border-left: 6px solid var(--primary);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
    
    .cooling-period {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border: 1px solid #ffeaa7;
        padding: 20px;
        border-radius: 12px;
        margin: 15px 0;
        border-left: 4px solid #f1c40f;
    }
    
    .user-type-badge {
        background: linear-gradient(135deg, var(--primary) 0%, #414e8d 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        display: inline-block;
        margin-left: 12px;
        box-shadow: 0 2px 8px rgba(0, 39, 118, 0.3);
    }

    .file-status {
        margin-top: 15px;
        padding: 15px;
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s ease;
    }
    
    .file-approved { 
        background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
        border-left: 4px solid var(--success);
    }
    
    .file-rejected { 
        background: linear-gradient(135deg, #fde8e8 0%, #f8d7da 100%);
        border-left: 4px solid var(--danger);
    }
    
    .file-pending { 
        background: linear-gradient(135deg, #fef9e7 0%, #fff3cd 100%);
        border-left: 4px solid var(--warning);
    }

    .file-status:hover {
        transform: translateX(5px);
    }

    .message {
        padding: 16px 20px;
        border-radius: 12px;
        margin: 20px 0;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border: none;
    }
    
    .message-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }
    
    .message-error {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    .completion-badge {
        background: linear-gradient(135deg, var(--success) 0%, #1e7e34 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-left: 12px;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Form enhancements */
    input[type="file"] {
        padding: 12px;
        border: 2px dashed #d1d9e6;
        border-radius: 10px;
        background: #f8f9fa;
        transition: all 0.3s ease;
        width: 100%;
        max-width: 400px;
    }

    input[type="file"]:hover {
        border-color: var(--primary);
        background: #fff;
    }

    input[type="file"]:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(0, 39, 118, 0.1);
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .main-content {
            padding: 20px 15px;
            margin-left: 0;
        }
        
        .sidebar {
            transform: translateX(-100%);
        }
        
        body.sidebar-closed .sidebar {
            transform: translateX(0);
        }
        
        .step-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .step-box {
            padding: 20px;
        }
    }

    /* Progress indicator */
    .progress-container {
        background: #fff;
        padding: 25px;
        border-radius: 16px;
        margin: 25px 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .progress-bar {
        height: 8px;
        background: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        margin: 15px 0;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
        border-radius: 10px;
        transition: width 0.5s ease;
    }
    
</style>
</head>

<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">

        <!-- Logo with better styling -->
    <div class="logo-container" style="text-align: center; margin-bottom: 0px; padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2);">
        <img src="../image/UTP2.png" alt="UTP Logo" style="max-width: 160px; height: auto;">
    </div>
        <h2>Customer Panel</h2>
        <a href="user_homePage.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="view_declaration.php"><i class="fa-solid fa-pen-nib"></i> View Declaration Form</a>
        <a href="user_profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <button id="toggle-btn"><i class="fa-solid fa-bars"></i></button>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo ($_SESSION['message_type'] ?? '') === 'success' ? 'message-success' : 'message-error'; ?>">
                <?php 
                echo htmlspecialchars($_SESSION['message']); 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
<div class="application-status">
    
    <div style=" margin-bottom: 10px;">
            <img src="../image/logo.png" alt="UTP Logo" style="max-width: 250px; height: auto;">
        </div>

    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['fullname'] ??''); ?> 
        <span class="user-type-badge"><?php echo ucfirst($user_type); ?></span>
    </h1>
    
    <!-- Display User Information -->
    <?php if ($user_type === 'student' && $user_info): ?>
        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($user_info['student_id']); ?></p>
        <p><strong>Programme:</strong> <?php echo htmlspecialchars($user_info['programme']); ?></p>
    <?php elseif ($user_type === 'external_appointment' && $user_info): ?>
        <p><strong>Company:</strong> <?php echo htmlspecialchars($user_info['external_company_name']); ?></p>
        <p><strong>Position:</strong> <?php echo htmlspecialchars($user_info['position']); ?></p>
        <p><strong>AE Admin:</strong> <?php echo htmlspecialchars($user_info['ae_name']); ?></p>
    <?php elseif ($user_type === 'company' && $user_info): ?>
        <p><strong>SSM Number:</strong> <?php echo htmlspecialchars($user_info['ssm_number']); ?></p>
    <?php endif; ?>
</div>


<!-- Application Management Section -->
<div class="step-box">
    <h3>Application Management</h3>
    
    <?php if ($has_pending_application): ?>
        <div class="file-status file-pending">
            <i class='fa-solid fa-clock'></i> <strong>Application In Progress</strong>
            <br><small>You have a pending application waiting for vendor code. Please complete your current application.</small>
        </div>
    <?php elseif ($kyc6_cooling_active): ?>
        <div class="cooling-period">
            <i class="fa-solid fa-clock"></i> 
            <strong>KYC6 Cooling Period Active</strong>
            <br>Your previous application failed KYC6 verification. You can create a new application in <?php echo $kyc6_days_remaining; ?> days.
            <br><small>Next eligible date: <?php echo $kyc6_next_eligible_date; ?></small>
        </div>
    <?php else: ?>
        <form method="POST" action="create_new_application.php">
            <button type="submit" class="upload-btn" style="padding: 12px 24px; font-size: 16px;">
                <i class="fa-solid fa-plus"></i> Create New Application
            </button>
            <p><small>You can create a new vendor application</small></p>
        </form>
    <?php endif; ?>
</div>
       <!-- Step 1: IC Upload (Always Visible) -->
<div class="step-box <?php echo !$step1_visible ? 'hidden' : ''; ?>">
    <div class="step-header">
        <div class="step-number <?php echo $step1_completed ? 'completed' : 'current'; ?>">1</div>
        <h3>Step 1: Identity Verification</h3>
        <?php if ($step1_completed): ?>
            <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
        <?php endif; ?>
    </div>

    <div style="<?php echo $icUploaded ? 'opacity: 0.5;' : ''; ?>">
        <h4 style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">A. Upload a copy of IC / Passport</h4>
        <?php if ($icUploaded): ?>
            <div class="file-status file-approved" style="margin-bottom: 30px;">
                <i class="fa-solid fa-check"></i> <strong>File Uploaded Successfully</strong>
                <br><a href="../<?php echo htmlspecialchars($current_ic); ?>" target="_blank">View uploaded file</a>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" action="upload_ic.php" style="margin-bottom: 30px;">
                <div style="margin-bottom: 15px;">
                    <label for="ic_no" style="display: block; margin-bottom: 8px;">IC / Passport Number:</label>
                    <input type="text" id="ic_no" name="ic_no" placeholder="901231-01-1234"  style="padding: 12px; border: 2px solid #d1d9e6; border-radius: 8px; width: 100%;" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="ic_picture" style="display: block; margin-bottom: 8px;">IC / passport Copy:</label>
                    <input type="file" name="ic_picture" accept=".pdf,.jpg,.jpeg,.png" required style="padding: 12px; border: 2px dashed #d1d9e6; border-radius: 8px; width: 100%;">
                </div>
                <button type="submit" class="upload-btn"><i class="fa-solid fa-upload"></i> Upload IC / Passport</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($icUploaded): ?>
        <div style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
            <h4 style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">B. Face Verification</h4>
            
            <?php if ($faceCaptured): ?>
                <div class="file-status file-approved">
                    <i class="fa-solid fa-user-check"></i> <strong>Face captured successfully</strong>
                    <br><img src="../<?php echo htmlspecialchars($current_face); ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%; margin-top: 10px; border: 2px solid #28a745;">
                </div>
                <p style="color: green; margin-top: 15px; font-weight: bold;">
                    <i class="fa-solid fa-arrow-down"></i> You can now proceed to Step 2 below.
                </p>
            <?php else: ?>
                <p><strong>Action Required:</strong> Please take a photo of your face to unlock Step 2.</p>
                <div id="camera-container" style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="position: relative; display: inline-block; width: 100%; max-width: 320px; height: 240px; background: #000; border-radius: 8px; overflow: hidden; margin-bottom: 15px;">
                        <video id="video" style="width: 100%; height: 100%; object-fit: cover;" autoplay playsinline></video>
                        <canvas id="canvas" style="display:none;"></canvas>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button type="button" id="start-camera" class="upload-btn" style="background: #17a2b8;">
                            <i class="fa-solid fa-camera"></i> Start Camera
                        </button>
                        <button type="button" id="capture-btn" class="upload-btn" style="display:none;">
                            <i class="fa-solid fa-camera-retro"></i> Capture
                        </button>
                        <button type="button" id="retake-btn" class="upload-btn" style="background: #ffc107; color: #000; display:none;">
                            <i class="fa-solid fa-rotate-left"></i> Retake
                        </button>
                    </div>
                    <p id="camera-error" style="color: red; display: none; margin-top: 10px;"></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

        <!-- Step 2: KYC6 Background Check (Visible after Step 1) -->
        <div class="step-box <?php echo !$step2_visible ? 'hidden' : ''; ?>">
            <div class="step-header">
                <div class="step-number <?php echo $step2_completed ? 'completed' : 'current'; ?>">2</div>
                <h3>Step 2: KYC6 Background Check</h3>
                <?php if ($step2_completed): ?>
                    <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
                <?php endif; ?>
            </div>
            <p>Please wait 1–2 business days for verification by KYC6 Admin.</p>
            <div class="status-box <?php echo strtolower($kyc6_status); ?>">
                Status: <?php echo htmlspecialchars($kyc6_status); ?>
            </div>
            <?php if ($kyc6_status === 'Pass' || $kyc6_status === 'Alert'): ?>
                <p style="color: green;"><i class="fa-solid fa-check-circle"></i> Background check completed successfully.</p>
            <?php elseif ($kyc6_status === 'Fail'): ?>
                <p style="color: red;"><i class="fa-solid fa-exclamation-triangle"></i> Please contact support for assistance.</p>
            <?php endif; ?>
        </div>

        <!-- Step 3: User-Specific Document (Visible after Step 2) -->
<div class="step-box <?php echo !$step3_visible ? 'hidden' : ''; ?>">
    <div class="step-header">
        <div class="step-number <?php echo $step3_completed ? 'completed' : 'current'; ?>">3</div>
        <h3>Step 3: 
            <?php if ($user_type === 'student'): ?>
                Submit FYP Form 02
            <?php elseif ($user_type === 'external_appointment'): ?>
                Submit Appointment Letter
            <?php elseif ($user_type === 'company'): ?>
                Submit Company Documents
            <?php endif; ?>
        </h3>
        <?php if ($step3_completed): ?>
            <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
        <?php endif; ?>
    </div>

    <?php if ($user_type === 'student'): ?>
        <!-- STUDENT: FYP Form 02 -->
        <p><strong>Note:</strong> Upload your FYP Form 02 below.</p>
        
        <?php if ($fypForm02Uploaded): ?>
            <?php if ($fyp_verify_status === 1): ?>
                <!-- Approved -->
                <div class="file-status file-approved">
                    <i class="fa-solid fa-file"></i> 
                    <strong>FYP Form 02 Uploaded</strong>
                    <br>
                    <a href="../fyp_form_file/<?php echo htmlspecialchars($fypForm02FileName); ?>" target="_blank">View uploaded file</a>
                    <p style="color: green; margin-top: 10px;">
                        <i class="fa-solid fa-check-circle"></i> 
                        Your FYP Form 02 has been approved.
                    </p>
                </div>
            <?php elseif ($fyp_verify_status === 0): ?>
                <!-- Rejected - Show Re-upload Option -->
                <div class="file-status file-rejected">
                    <i class="fa-solid fa-file"></i> 
                    <strong>FYP Form 02 Rejected</strong>
                    <br>
                    <a href="../fyp_form_file/<?php echo htmlspecialchars($fypForm02FileName); ?>" target="_blank">View uploaded file</a>
                    
                    <div style="margin-top: 10px; padding: 10px; background: #f8d7da; border-radius: 5px;">
                        <p style="color: #721c24; margin: 0;">
                            <i class="fa-solid fa-exclamation-triangle"></i> 
                            Your FYP Form 02 was rejected. Please upload a new one.
                        </p>
                        <form method="POST" enctype="multipart/form-data" action="upload_fypForm02.php" style="margin-top: 10px;">
                            <input type="file" name="fyp_form_file" accept=".pdf,.jpg,.jpeg,.png" required>
                            <br><br>
                            <button type="submit" class="upload-btn">
                                <i class="fa-solid fa-upload"></i> Re-upload FYP Form 02
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Pending Verification -->
                <div class="file-status file-pending">
                    <i class="fa-solid fa-file"></i> 
                    <strong>FYP Form 02 Uploaded</strong>
                    <br>
                    <a href="../fyp_form_file/<?php echo htmlspecialchars($fypForm02FileName); ?>" target="_blank">View uploaded file</a>
                    <p style="color: #856404; margin-top: 10px;">
                        <i class="fa-solid fa-clock"></i> 
                        Waiting for admin verification. Please check back later.
                    </p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- No FYP Form 02 Uploaded Yet -->
            <form method="POST" enctype="multipart/form-data" action="upload_fypForm02.php">
                <input type="file" name="fyp_form_file" accept=".pdf,.jpg,.jpeg,.png" required>
                <br><br>
                <button type="submit" class="upload-btn">
                    <i class="fa-solid fa-upload"></i> Upload FYP Form 02
                </button>
            </form>
        <?php endif; ?>

    <?php elseif ($user_type === 'external_appointment'): ?>
    <!-- EXTERNAL: Appointment Letter -->
    <p><strong>Note:</strong> Upload your appointment letter below.</p>
    
    <?php if ($appointmentLetterUploaded): ?>
        <?php if ($appointment_verify_status === 1): ?>
            <!-- Approved -->
            <div class="file-status file-approved">
                <i class="fa-solid fa-file"></i> 
                <strong>Appointment Letter Uploaded</strong>
                <br>
                <a href="../appointment_file/<?php echo htmlspecialchars($appointmentLetterFileName); ?>" target="_blank">View uploaded file</a>
                <p style="color: green; margin-top: 10px;">
                    <i class="fa-solid fa-check-circle"></i> 
                    Your appointment letter has been approved.
                </p>
            </div>
        <?php elseif ($appointment_verify_status === 0): ?>
            <!-- Rejected - Show Re-upload Option -->
            <div class="file-status file-rejected">
                <i class="fa-solid fa-file"></i> 
                <strong>Appointment Letter Rejected</strong>
                <br>
                <a href="../appointment_file/<?php echo htmlspecialchars($appointmentLetterFileName); ?>" target="_blank">View uploaded file</a>
                
                <div style="margin-top: 10px; padding: 10px; background: #f8d7da; border-radius: 5px;">
                    <p style="color: #721c24; margin: 0;">
                        <i class="fa-solid fa-exclamation-triangle"></i> 
                        Your appointment letter was rejected. Please upload a new one.
                    </p>
                    <form method="POST" enctype="multipart/form-data" action="upload_appointmentLetter.php" style="margin-top: 10px;">
                        <input type="file" name="appointment_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <br><br>
                        <button type="submit" class="upload-btn">
                            <i class="fa-solid fa-upload"></i> Re-upload Appointment Letter
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Pending Verification -->
            <div class="file-status file-pending">
                <i class="fa-solid fa-file"></i> 
                <strong>Appointment Letter Uploaded</strong>
                <br>
                <a href="../appointment_file/<?php echo htmlspecialchars($appointmentLetterFileName); ?>" target="_blank">View uploaded file</a>
                <p style="color: #856404; margin-top: 10px;">
                    <i class="fa-solid fa-clock"></i> 
                    Waiting for admin verification. Please check back later.
                </p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Appointment Letter Uploaded Yet -->
        <form method="POST" enctype="multipart/form-data" action="upload_appointmentLetter.php">
            <input type="file" name="appointment_file" accept=".pdf,.jpg,.jpeg,.png" required>
            <br><br>
            <button type="submit" class="upload-btn">
                <i class="fa-solid fa-upload"></i> Upload Appointment Letter
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
</div>
    

        <!-- Step 4: Bank Statement (Visible after Step 3) -->
<div class="step-box <?php echo !$step4_visible ? 'hidden' : ''; ?>">
    <div class="step-header">
        <div class="step-number <?php echo $step4_completed ? 'completed' : 'current'; ?>">4</div>
        <h3>Step 4: Upload Bank Statement</h3>
        <?php if ($step4_completed && $bankStatementVerified !== 0): ?>
            <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
        <?php endif; ?>
    </div>
    
    <?php if ($bankStatementUploaded): ?>
        <?php if ($bankStatementVerified === 0): ?>
            <!-- Bank Statement Rejected - Show Re-upload Option -->
            <div class="file-status file-rejected">
                <i class="fa-solid fa-file"></i> 
                <strong>Bank Statement Rejected</strong>
                <br>
                <a href="../bank_statement/<?php echo htmlspecialchars($bankStatementFileName); ?>" target="_blank">View uploaded file</a>
                
                <div style="margin-top: 10px; padding: 10px; background: #f8d7da; border-radius: 5px;">
                    <p style="color: #721c24; margin: 0;">
                        <i class="fa-solid fa-exclamation-triangle"></i> 
                        Your bank statement was rejected. Please upload a new one.
                    </p>
                    <form method="POST" enctype="multipart/form-data" action="upload_financial.php" style="margin-top: 10px;">
                        <input type="file" name="financial_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        
                        <div style="margin: 15px 0;">
                            <label for="bank_name" style="display: block; margin-bottom: 8px; font-weight: bold;">
                                <i class="fa-solid fa-building"></i> Select Your Bank:
                            </label>
                            <select name="bank_name" id="bank_name" required style="padding: 12px; border: 2px solid #d1d9e6; border-radius: 8px; width: 100%; font-size: 15px;">
                                <option value="">-- Please Select Your Bank --</option>
                                <option value="Maybank">Maybank</option>
                                <option value="CIMB Bank">CIMB Bank</option>
                                <option value="Public Bank">Public Bank</option>
                                <option value="RHB Bank">RHB Bank</option>
                                <option value="Hong Leong Bank">Hong Leong Bank</option>
                                <option value="AmBank">AmBank</option>
                                <option value="OCBC Bank">OCBC Bank</option>
                                <option value="Standard Chartered">Standard Chartered</option>
                                <option value="HSBC Bank">HSBC Bank</option>
                                <option value="Bank Rakyat">Bank Rakyat</option>
                                <option value="Bank Islam">Bank Islam</option>
                                <option value="Affin Bank">Affin Bank</option>
                                <option value="Alliance Bank">Alliance Bank</option>
                                <option value="Other">Other Bank</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="upload-btn">
                            <i class="fa-solid fa-upload"></i> Re-upload Bank Statement
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif ($bankStatementVerified === 1): ?>
            <!-- Bank Statement Approved -->
            <div class="file-status file-approved">
                <i class="fa-solid fa-file-signature"></i> 
                <strong>Bank Statement Approved</strong>
                <br>
                <a href="view_declaration.php" target="_blank">View Declaration Form</a>
                <p style="color: green; margin-top: 10px;">
                    <i class="fa-solid fa-check-circle"></i> 
                    Your bank statement has been approved and cannot be modified.
                </p>
            </div>
        <?php else: ?>
            <!-- Bank Statement Uploaded (Pending or Approved) -->
            <div class="file-status file-pending">
                    <i class="fa-solid fa-file"></i> 
                    <strong>Bank Statement Uploaded</strong>
                    <br>
                    <a href="../bank_statement/<?php echo htmlspecialchars($fypForm02FileName); ?>" target="_blank">View uploaded file</a>
                    <p style="color: #856404; margin-top: 10px;">
                        <i class="fa-solid fa-clock"></i> 
                        Waiting for admin verification. Please check back later.
                </p>
            </div>
           
            <!-- extraction result (temporary)-->
            <?php if (isset($_SESSION['extraction_results'])): ?>
                <div class="extraction-results" style="margin-top: 15px; padding: 15px; background: #e8f4fd; border-radius: 8px; border-left: 4px solid #2196F3;">
                    <h4 style="margin: 0 0 10px 0; color: #1976D2;">
                        <i class="fa-solid fa-file-lines"></i> Extracted Information
                    </h4>
                    <?php $results = $_SESSION['extraction_results']; ?>
                    
                    <?php if ($results['success']): ?>
                        <?php if ($results['account_number']): ?>
                            <p style="margin: 5px 0;"><strong>Account Number:</strong> <?php echo htmlspecialchars($results['account_number']); ?></p>
                        <?php else: ?>
                            <p style="margin: 5px 0; color: #FF9800;"><strong>Account Number:</strong> Not found in document</p>
                        <?php endif; ?>
                        
                        <?php if ($results['bank_name']): ?>
                            <p style="margin: 5px 0;"><strong>Bank Name:</strong> <?php echo htmlspecialchars($results['bank_name']); ?></p>
                        <?php else: ?>
                            <p style="margin: 5px 0; color: #FF9800;"><strong>Bank Name:</strong> Not found in document</p>
                        <?php endif; ?>
                        
                        <?php if ($results['statement_date']): ?>
                            <p style="margin: 5px 0;"><strong>Statement Date:</strong> <?php echo htmlspecialchars($results['statement_date']); ?></p>
                        <?php else: ?>
                            <p style="margin: 5px 0; color: #FF9800;"><strong>Statement Date:</strong> Not found in document</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #F44336;">We couldn't read the PDF document. Please ensure it's not password protected.</p>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['extraction_results']); ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Bank Statement Uploaded Yet -->
        <form method="POST" enctype="multipart/form-data" action="upload_financial.php">
            <input type="file" name="financial_file" accept=".pdf,.jpg,.jpeg,.png" required>
            
            <div style="margin: 15px 0;">
                <label for="bank_name" style="display: block; margin-bottom: 8px; font-weight: bold;">
                    <i class="fa-solid fa-building"></i> Select Your Bank:
                </label>
                <select name="bank_name" id="bank_name" required style="padding: 12px; border: 2px solid #d1d9e6; border-radius: 8px; width: 100%; font-size: 15px;">
                    <option value="">-- Please Select Your Bank --</option>
                    <option value="Maybank">Maybank</option>
                    <option value="CIMB Bank">CIMB Bank</option>
                    <option value="Public Bank">Public Bank</option>
                    <option value="RHB Bank">RHB Bank</option>
                    <option value="Hong Leong Bank">Hong Leong Bank</option>
                    <option value="AmBank">AmBank</option>
                    <option value="OCBC Bank">OCBC Bank</option>
                    <option value="Standard Chartered">Standard Chartered</option>
                    <option value="HSBC Bank">HSBC Bank</option>
                    <option value="Bank Rakyat">Bank Rakyat</option>
                    <option value="Bank Islam">Bank Islam</option>
                    <option value="Affin Bank">Affin Bank</option>
                    <option value="Alliance Bank">Alliance Bank</option>
                    <option value="Other">Other Bank</option>
                </select>
            </div>
            
            <button type="submit" class="upload-btn">
                <i class="fa-solid fa-upload"></i> Upload Bank Statement
            </button>
        </form>
    <?php endif; ?>
    
    <!-- Display upload messages for this section -->
    <?php if (isset($_SESSION['upload_error'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_SESSION['upload_error']); unset($_SESSION['upload_error']); ?></p>
    <?php endif; ?>
</div>

        <!-- Step 5: Declaration Form (Visible after Step 4) -->
<div class="step-box <?php echo !$step5_visible ? 'hidden' : ''; ?>">
    <div class="step-header">
        <div class="step-number <?php echo $step5_completed ? 'completed' : 'current'; ?>">5</div>
        <h3>Step 5: Sign Declaration Form</h3>
        <?php if ($step5_completed): ?>
            <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
        <?php endif; ?>
    </div>
    
    <?php if ($declarationExists): ?>
        <?php if ($declarationVerified === 1): ?>
            <!-- Approved - Cannot modify -->
            <div class="file-status file-approved">
                <i class="fa-solid fa-file-signature"></i> 
                <strong>Declaration Form Approved</strong>
                <br>
                <a href="view_declaration.php" target="_blank">View Declaration Form</a>
                <p style="color: green; margin-top: 10px;">
                    <i class="fa-solid fa-check-circle"></i> 
                    Your declaration form has been approved and cannot be modified.
                </p>
            </div>
        <?php elseif ($declarationVerified === 0): ?>
            <!-- Rejected - Must re-sign -->
            <div class="file-status file-rejected">
                <i class="fa-solid fa-file-signature"></i> 
                <strong>Declaration Form Rejected</strong>
                <br>
                <a href="view_declaration.php" target="_blank">View rejected declaration</a>
                
                <div style="margin-top: 10px; padding: 10px; background: #f8d7da; border-radius: 5px;">
                    <p style="color: #721c24; margin: 0;">
                        <i class="fa-solid fa-exclamation-triangle"></i> 
                        Your declaration form was rejected. You must sign a new declaration form.
                    </p>
                    <div style="margin-top: 10px;">
                        <a href="sign_declaration.php" class="upload-btn">
                            <i class="fa-solid fa-signature"></i> Sign New Declaration Form
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Pending Verification -->
            <div class="file-status file-pending">
                <i class="fa-solid fa-file-signature"></i> 
                <strong>Declaration Form Submitted</strong>
                <br>
                <a href="view_declaration.php" target="_blank">View Declaration Form</a>
                <p style="color: #856404; margin-top: 10px;">
                    <i class="fa-solid fa-clock"></i> 
                    Waiting for admin verification. Please check back later.
                </p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Declaration Submitted Yet - Can sign for the first time -->
        <a href="sign_declaration.php" class="upload-btn">
            <i class="fa-solid fa-signature"></i> Sign Declaration Form
        </a>
    <?php endif; ?>
</div>

        <!-- Step 6: Vendor Code (Visible after Step 5) -->
        <div class="step-box <?php echo !$step6_visible ? 'hidden' : ''; ?>">
            <div class="step-header">
                <div class="step-number <?php echo $step6_completed ? 'completed' : 'current'; ?>">6</div>
                <h3>Step 6: Vendor Code</h3>
                <?php if ($step6_completed): ?>
                    <span class="completion-badge"><i class="fa-solid fa-check"></i> Completed</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($vendor_code)): ?>
                <div style="padding: 15px; background: #e8f5e8; border-radius: 8px; border-left: 4px solid #27ae60;">
                    <p style="margin: 0; font-size: 18px; color: #155724;">
                        <i class="fa-solid fa-check-circle"></i> 
                        <strong>Your Vendor Code: </strong>
                        <span style="font-size: 20px; font-weight: bold;"><?php echo htmlspecialchars($vendor_code); ?></span>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #155724;">
                        <i class="fa-solid fa-calendar-check"></i> 
                        Completed on: <?php echo date('F j, Y', strtotime($completed_date)); ?>
                    </p>
                </div>
            <?php else: ?>
                <p><i class="fa-solid fa-hourglass-half"></i> Your vendor code will be generated after all documents are verified and approved.</p>
            <?php endif; ?>
        </div>
    

    <script>
        // Simple sidebar toggle
        document.getElementById('toggle-btn').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-closed');
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const startBtn = document.getElementById('start-camera');
    const captureBtn = document.getElementById('capture-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const errorMsg = document.getElementById('camera-error');
    let stream = null;

    if(startBtn) {
        startBtn.addEventListener('click', async () => {
            try {
                // Request camera (prefer front/user facing)
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'user' }, 
                    audio: false 
                });
                video.srcObject = stream;
                startBtn.style.display = 'none';
                captureBtn.style.display = 'inline-flex';
                errorMsg.style.display = 'none';
            } catch (err) {
                console.error("Camera error:", err);
                errorMsg.textContent = "Could not access camera. Please allow camera permissions.";
                errorMsg.style.display = 'block';
            }
        });

        captureBtn.addEventListener('click', () => {
            // Draw video frame to canvas
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            // Convert to Base64
            const dataURL = canvas.toDataURL('image/png');
            
            // Freeze video to show captured state
            video.pause();
            captureBtn.style.display = 'none';
            
            // Confirm upload
            if(confirm("Are you happy with this photo? Click OK to save.")) {
                uploadFaceImage(dataURL);
            } else {
                video.play();
                captureBtn.style.display = 'inline-flex';
            }
        });
    }

    function uploadFaceImage(base64Image) {
        fetch('upload_face.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: base64Image })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Face captured successfully!");
                // Stop camera stream
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                location.reload(); // Reload page to update steps
            } else {
                alert("Error: " + data.message);
                video.play();
                captureBtn.style.display = 'inline-flex';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred uploading the image.");
        });
    }
});
</script>
</body>
</html>