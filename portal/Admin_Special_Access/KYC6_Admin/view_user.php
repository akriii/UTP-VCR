<?php 
session_start();
include('../../../config.php');

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'kyc6_admin') {
    header("Location: ../login_admin.php");
    exit();
}

$adminName = "";

// Get admin name from session
if (isset($_SESSION['admin_id'])) {
    $adminID = $_SESSION['admin_id'];
    $sql = "SELECT admin_name FROM admin WHERE admin_id = '$adminID'";
    $result = mysqli_query($db, $sql);
    if ($row = mysqli_fetch_assoc($result)) {
        $adminName = $row['admin_name'];
    }
}
if (!isset($_GET['user_id']) || !isset($_GET['report_id'])) {
    die("No user or report selected.");
}

$userID = mysqli_real_escape_string($db, $_GET['user_id']);
$reportID = mysqli_real_escape_string($db, $_GET['report_id']);

// Get user details and report status
$userQuery = "
    SELECT u.user_id AS uid, u.*,  k.status, b.bankStatement_verify, d.declaration_verify, s.student_full_name AS student_name,d.date_signed AS declaration_date, r.vendor_code,
           ea.external_full_name AS ea_name, c.company_name AS company_name, al.appointmentLetter_verify AS ea_form_verify, f.fypForm_verify, k.*, r.*
    FROM user u
    LEFT JOIN external_appointment ea ON u.user_id = ea.user_id
    LEFT JOIN student s ON u.user_id = s.user_id
    LEFT JOIN company c ON u.user_id = c.user_id
    LEFT JOIN report r ON u.user_id = r.user_id
    LEFT JOIN kyc6 k ON r.report_id = k.report_id
    LEFT JOIN bank_statement b ON r.report_id = b.report_id
    LEFT JOIN declaration_form d ON r.report_id = d.report_id
    LEFT JOIN appointment_letter al ON r.report_id = al.report_id
    LEFT JOIN fyp_form_02 f ON r.report_id = f.report_id
    WHERE r.is_deleted = 0
      
    AND r.report_id = '" . $reportID . "'
";
$userResult = mysqli_query($db, $userQuery);

// Check for query errors
if (!$userResult) {
    die("Database query failed: " . mysqli_error($db));
}

$user = mysqli_fetch_assoc($userResult);
if (!$user) {
    die("User not found.");
}

// Ensure user_id is available (map uid back to user_id to keep existing code intact)
if (isset($user['uid']) && (!isset($user['user_id']) || $user['user_id'] == '')) {
    $user['user_id'] = $user['uid'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"> <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <title>View User Details</title>

    <style>
    * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }
    body {
        background: #f5f5f5;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        touch-action: manipulation;
    }
    
    /* Sidebar Styles - Mobile Optimized */
     #sidebar {
            background: #001a5e; /* Deep UTP Blue */
            color: white;
            height: 100vh;
            position: fixed;
            left: -280px;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.3);
            overflow-y: auto;
            width: 280px;
            display: flex;
            flex-direction: column;
        }
        
        #sidebar.active {
            left: 0;
        }
        
        /* Branding Section */
        .sidebar-brand {
            padding: 25px 15px;
            text-align: center;
            background: rgba(0,0,0,0.1); /* Slight darkness to separate header */
            margin-bottom: 10px;
        }

        .sidebar-brand img {
            width: 100px;
            margin-bottom: 15px;
            background: white; 
            padding: 5px;
            border-radius: 8px;
        }

        .brand-text {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #fff;
            margin-bottom: 5px;
            display: block;
        }

        .role-text {
            font-size: 12px;
            color: #aeb9e6; /* Lighter blue for subtitle */
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Menu Links */
        .sidebar-menu {
            padding: 10px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #e0e0e0;
            padding: 15px 25px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-size: 14px;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            border-left: 4px solid #fdb913; /* UTP Gold/Yellow accent */
            text-decoration: none;
        }
        
        .sidebar-menu i {
            width: 30px;
            font-size: 1.1em;
            margin-right: 10px;
            text-align: center;
        }
    
    .toggle-btn {
        position: fixed;
        top: 15px;
        left: 15px;
        background: rgb(0, 74, 132);
        border: 2px solid white;
        color: white;
        border-radius: 50%;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.1em;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1001;
        transition: all 0.3s ease;
    }
    
    .toggle-btn:hover {
        background: rgb(0, 60, 110);
        transform: scale(1.05);
    }
    
    /* Main Content Styles */
    .main-content {
        padding: 15px 10px;
        transition: all 0.3s ease;
        min-height: 100vh;
        width: 100%;
        position: relative;
        z-index: 1;
    }
    
    .content-wrapper {
        background: white;
        border-radius: 10px;
        padding: 20px 15px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        margin: 0 auto;
        max-width: 1000px;
        width: 100%;
        position: relative;
        z-index: 1;
    }
    
    /* Page Header */
    .page-header {
        margin-bottom: 25px;
        text-align: center;
        padding: 15px 0;
    }
    
    .page-header h3 {
        color: #004a84;
        font-weight: 600;
        margin: 0;
        font-size: 1.4em;
        line-height: 1.3;
    }
    
    .page-header hr {
        border: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #004a84, transparent);
        margin: 12px auto;
        width: 90%;
    }
    
    /* User Details Section */
    .user-details-section {
        margin-bottom: 25px;
    }
    
    .user-details-section h4 {
        color: #004a84;
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 1.2em;
        border-bottom: 2px solid #e3e6f0;
        padding-bottom: 8px;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s ease;
    }
    
    .label-title {
        font-weight: 600;
        color: #333;
        width: 200px;
        min-width: 200px;
        font-size: 0.95em;
    }
    
    .detail-value {
        color: #555;
        font-size: 0.95em;
        flex: 1;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 25px;
    }
    
    input[type="text"], input[type="file"], select {
        width: 300px;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 16px; /* Prevents zoom on iOS */
        transition: all 0.3s ease;
    }
    
    input[type="text"]:focus, input[type="file"]:focus, select:focus {
        outline: none;
        border-color: #004a84;
        box-shadow: 0 0 5px rgba(0, 74, 132, 0.3);
    }
    
    /* Buttons */
    .submit-btn {
        margin-top: 10px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        text-decoration: none;
        background: linear-gradient(135deg, #004a84, #0066cc);
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        max-width: 200px;
    }
    
    .submit-btn:active {
        transform: translateY(1px);
    }
    
    .back-btn {
        margin-bottom: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        text-decoration: none;
        background: linear-gradient(135deg, #6c757d, #495057);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        min-height: 44px;
        width: 100%;
        max-width: 200px;
    }
    
    .back-btn:active {
        transform: translateY(1px);
    }
    
    /* Document Links */
    .doc-link {
        padding: 10px 15px;
        border-radius: 6px;
        color: white;
        text-decoration: none;
        background: linear-gradient(135deg, #28a745, #1e7e34);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85em;
        font-weight: 500;
        transition: all 0.3s ease;
        min-height: 40px;
        min-width: 120px;
        text-align: center;
    }
    
    .doc-link:active {
        transform: translateY(1px);
    }
    
    .no-doc {
        color: #6c757d;
        font-style: italic;
    }
    
    /* Current File Info */
    .current-file {
        margin-top: 8px;
        font-size: 0.85em;
        color: #666;
    }
    
    /* Mobile Overlay */
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
    }
    
    .overlay.active {
        display: block;
    }
    
    /* Mobile-specific improvements */
    @media (max-width: 768px) {
        .content-wrapper {
            padding: 15px;
            margin: 10px;
            width: calc(100% - 20px);
        }
        
        .page-header h3 {
            font-size: 1.3em;
        }
        
        .detail-row {
            flex-direction: column;
            gap: 8px;
            padding: 15px 0;
        }
        
        .label-title {
            width: 100%;
            min-width: auto;
            font-size: 0.95em;
            font-weight: 600;
        }
        
        input[type="text"], input[type="file"], select {
            width: 100%;
            max-width: 100%;
        }
        
        .doc-link, .back-btn, .submit-btn {
            width: 100%;
            margin: 5px 0;
        }
        
        .back-btn {
            margin-bottom: 15px;
        }
        
        /* Sidebar mobile improvements */
        #sidebar {
            width: 280px;
            left: -280px;
        }
        
        .sidebar-menu a {
            padding: 15px 20px;
            min-height: 50px;
        }
        
        .toggle-btn {
            top: 15px;
            left: 15px;
            width: 44px;
            height: 44px;
            font-size: 1.1em;
        }
        
        /* Improve dropdown for mobile */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'><path fill='%23333' d='M2 0L0 2h4zm0 5L0 3h4z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 35px;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 10px 5px;
        }
        
        .content-wrapper {
            padding: 12px;
            margin: 5px;
            width: calc(100% - 10px);
            border-radius: 8px;
        }
        
        .page-header h3 {
            font-size: 1.2em;
        }
        
        .page-header hr {
            margin: 10px auto;
            width: 90%;
        }
        
        .user-details-section h4 {
            font-size: 1.1em;
            margin-bottom: 15px;
        }
        
        .detail-row {
            padding: 12px 0;
            margin-bottom: 10px;
        }
        
        .label-title, .detail-value {
            font-size: 0.9em;
        }
        
        input[type="text"], input[type="file"], select {
            padding: 10px;
        }
        
        .current-file {
            font-size: 0.85em;
            margin-top: 8px;
        }
    }
    
    @media (max-width: 480px) {
        .page-header h3 {
            font-size: 1.1em;
        }
        
        .user-details-section h4 {
            font-size: 1em;
        }
        
        .detail-row {
            padding: 10px 0;
        }
        
        .label-title, .detail-value {
            font-size: 0.85em;
        }
        
        .doc-link, .back-btn, .submit-btn {
            padding: 10px 15px;
            font-size: 0.85em;
        }
        
        /* Even smaller sidebar for very small screens */
        #sidebar {
            width: 260px;
            left: -260px;
        }
        
        .sidebar-header {
            padding: 20px 15px;
        }
        
        .sidebar-header h2 {
            font-size: 1.3em;
        }
        
        .sidebar-menu a {
            padding: 12px 15px;
            font-size: 0.9em;
        }
    }
    
    /* Add touch improvements for all mobile devices */
    @media (hover: none) and (pointer: coarse) {
        .doc-link:active, .back-btn:active, .submit-btn:active,
        .sidebar-menu a:active {
            transform: translateY(1px);
        }
        
        .sidebar-menu a:active {
            background-color: rgba(255,255,255,0.25);
        }
    }
</style>
</head>

<body>
    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar -->
    <div id="sidebar">
    <div class="sidebar-brand"> 
        <img src="../../../image/utp_logo.png" alt="UTP Logo"> 
        <span class="brand-text">UTP Vendor Hub</span>
        <span class="role-text">Admin Panel</span>
    </div>

    <div class="sidebar-menu">
        <a href="homepage.php" class="active">
            <i class="fas fa-home"></i>
            <span>Homepage</span>
        </a>
        <a href="history.php">
            <i class="fas fa-history"></i>
            <span>Archive</span>
        </a>
        
        <a href="logout.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

    <!-- Toggle Button -->
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <a href="homepage.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Homepage
            </a>

            <div class="page-header">
                <h3>User Details</h3>
                <hr>
            </div>

            <!-- User Information Section -->
            <div class="user-details-section">
                <h4>User Information</h4>
                <div class="detail-row">
                    <span class="label-title">Name:</span>
                    <span class="detail-value">
                    <?php
                    if($user['user_type'] == 'student') {
                        echo htmlspecialchars($user['student_name'] ??'');
                    } elseif ($user['user_type'] == 'external_appointment') {
                        echo htmlspecialchars($user['ea_name']??'');
                    } elseif ($user['user_type'] == 'company') {
                        echo htmlspecialchars($user['company_name']??'');
                    } else {
                        echo "N/A";
                    }
                    ?>
                    </span>
                   
                </div>
                <div class="detail-row">
                    <span class="label-title">Email:</span>
                    <span class="detail-value"><?= htmlspecialchars($user['user_email']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label-title">IC No:</span>
                    <span class="detail-value"><?= htmlspecialchars($user['ic_no'])?? 'N/A' ?></span>
                </div>
                <div class="detail-row">
                    <span class="label-title">IC Picture:</span>
                    <span class="detail-value">
                        <?php if (!empty($user['ic_picture'])): ?>
                            <a href="../../../documents/<?= htmlspecialchars($user['ic_picture'])?? 'N/A' ?>" target="_blank" class="doc-link">
                                <i class="fas fa-eye"></i> View Document
                            </a>
                        <?php else: ?>
                            <span class="no-doc">Not uploaded</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label-title">Face Capture:</span>
                    <span class="detail-value">
                        <?php if (!empty($user['face_recognition'])): ?>
                            <a href="../../../documents/<?= htmlspecialchars($user['face_recognition'])?? 'N/A' ?>" target="_blank" class="doc-link">
                                <i class="fas fa-eye"></i> View Document
                            </a>
                        <?php else: ?>
                            <span class="no-doc">Not uploaded</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
           <!-- Uploaded Documents Section -->
<div class="user-details-section">
    <h4>Upload Documents</h4>
    <form action="update.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['user_id']) ?>">
        <input type="hidden" name="report_id" value="<?= htmlspecialchars($reportID) ?>">
        <div class="form-group">
            <label for="status" class="label-title">KYC6 Status:</label>
            <select id="status" name="status" required>
                            <option value="">-- Select Status --</option>
                            <option value="Pass" <?= ($user['status'] == 'Pass') ? 'selected' : '' ?>>Pass</option>
                            <option value="Fail" <?= ($user['status'] == 'Fail') ? 'selected' : '' ?>>Fail</option>
                            <option value="Alert" <?= ($user['status'] == 'Alert') ? 'selected' : '' ?>>Alert</option>
            </select>
        </div>
        <div class="form-group">
            <label for="KYC6_file" class="label-title">Upload KYC6 Document:</label>
                <input type="file" id="kyc6" name="kyc6" accept=".pdf,.doc,.docx,.jpg,.png" />
                    <?php if (!empty($user['KYC6_file'])): ?>
                        <div class="current-file">
                            Current: <a href="../../../documents/kyc6/<?= htmlspecialchars($user['KYC6_file'])?? 'N/A' ?>" target="_blank" class="doc-link">
                            <i class="fas fa-eye"></i> View Current Document
                            </a>
                        </div>
                    <?php endif; ?>
        </div>
        

        <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Update User
            </button>
    </form>
</div>

   
             
</div>
</div>

<script>
// Sidebar functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    // Update toggle button icon
    const toggleIcon = document.querySelector('.toggle-btn i');
    if (sidebar.classList.contains('active')) {
        toggleIcon.className = 'fas fa-times';
    } else {
        toggleIcon.className = 'fas fa-bars';
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleIcon = document.querySelector('.toggle-btn i');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    toggleIcon.className = 'fas fa-bars';
}

// Close sidebar when pressing escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSidebar();
    }
});

// Close sidebar when window is resized to desktop size
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeSidebar();
    }
});

// Close sidebar when clicking on a link (mobile)
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', closeSidebar);
});

// Prevent form zoom on iOS
document.addEventListener('DOMContentLoaded', function() {
    if (/iPhone|iPad|iPod/.test(navigator.userAgent)) {
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                window.scrollTo(0, 0);
                document.body.scrollTop = 0;
            });
        });
    }
});
</script>

<script>
// Ensure user_id is always included when submitting verification forms
document.addEventListener('DOMContentLoaded', function() {
    const userIdValue = '<?= htmlspecialchars($user['user_id']) ?>';
    document.querySelectorAll('form[action="update_verification.php"]').forEach(form => {
        // if input[name="user_id"] missing, append hidden input
        if (!form.querySelector('input[name="user_id"]')) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'user_id';
            hidden.value = userIdValue;
            form.appendChild(hidden);
        }
        // Extra safety: ensure hidden exists just before submit (covers dynamic DOM changes)
        form.addEventListener('submit', function() {
            if (!form.querySelector('input[name="user_id"]')) {
                const hiddenSubmit = document.createElement('input');
                hiddenSubmit.type = 'hidden';
                hiddenSubmit.name = 'user_id';
                hiddenSubmit.value = userIdValue;
                form.appendChild(hiddenSubmit);
            }
        });
    });
});
</script>
</body>
</html>