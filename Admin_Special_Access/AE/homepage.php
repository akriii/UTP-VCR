<?php 

session_start();
include('../../config.php');

if ($_SESSION['admin_role'] != 'ae_admin') {
    header("Location: ../login_admin.php");
    exit();
}
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit();
}

// Default admin name
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

// Get user details and report status
$aeNameEscaped = mysqli_real_escape_string($db, $_SESSION['admin_name']);
$userQuery = "
    SELECT u.user_id AS uid, u.*, k.status, b.bankStatement_verify, d.declaration_verify, s.student_full_name AS student_name,r.*,
           ea.external_full_name AS ea_name, c.company_name AS company_name, al.appointmentLetter_verify AS ea_form_verify, f.fypForm_verify
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
      AND ea.ae_name = '" . $aeNameEscaped . "'
";

$userResult = mysqli_query($db, $userQuery);

if (!$userResult) {
    die("Database query failed: " . mysqli_error($db));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <title>Admin Homepage</title>

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
            max-width: 1200px;
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
        
        /* Content Section */
        h4 {
            color: #004a84;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        /* Filter Section */
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        
        .filter-btn {
            padding: 12px 18px;
            border-radius: 25px;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 90px;
            min-height: 44px;
            flex: 1;
            max-width: 120px;
        }
        
        .filter-btn:active {
            transform: translateY(1px);
        }
        
        .filter-all { 
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .filter-approved { 
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .filter-rejected { 
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .filter-alert { 
            background: linear-gradient(135deg, #ffc107, #e0a800);
            
        }
        .filter-pending { 
            background: linear-gradient(135deg, #666666ff, #535353ff);
           
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 600px;
        }
        
        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #e3e6f0;
            font-size: 0.85em;
        }
        
        th {
            background: linear-gradient(135deg, #004a84, #003366);
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        
        td {
            background: white;
        }
        
        /* Row status colors */
        .status-pass {
            background-color: #f8fff9 !important;
            border-left: 4px solid #28a745;
        }
        
        .status-fail {
            background-color: #fff8f8 !important;
            border-left: 4px solid #dc3545;
        }
        
        .status-pending {
            background-color: #ffffff !important;
            border-left: 4px solid #585858ff;
        }
        .status-alert {
            background-color: #ffffff !important;
            border-left: 4px solid #ffc107;
        }
        
        /* Action Buttons */
        .button {
            padding: 10px 15px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #004a84, #0066cc);
            display: inline-block;
            margin: 2px;
            font-size: 0.8em;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            min-height: 40px;
            min-width: 60px;
            text-align: center;
        }
        
        .button:active {
            transform: translateY(1px);
        }
        .warning-text {
        color: #000000ff;
        font-weight: 600;
        text-align: center;
        margin-bottom: 20px;
        padding: 12px;
        background: #fff3f3;
        border-radius: 8px;
        border-left: 4px solid #ffd930ff;
        font-size: 0.9em;
        line-height: 1.4;
    }
        
        .btn-complete {
            padding: 10px 15px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #28a745, #34ce57);
            border: none;
            cursor: pointer;
            margin: 2px;
            font-size: 0.8em;
            font-weight: 500;
            transition: all 0.3s ease;
            min-height: 40px;
            min-width: 80px;
            text-align: center;
            margin-left: 40px;
        }
        
        .btn-complete:active {
            transform: translateY(1px);
        }
        
        .action-cell {
            white-space: nowrap;
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
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
        
        /* DataTables Customization */
        .dataTables_wrapper {
            position: relative;
            z-index: 1;
        }
        
        .dataTables_empty {
            text-align: center;
            padding: 30px !important;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            .main-content {
                padding: 10px 5px;
            }
            
            .content-wrapper {
                padding: 15px 10px;
                margin: 5px;
                border-radius: 8px;
            }
            
            .page-header h3 {
                font-size: 1.2em;
            }
            
            .filter-section {
                padding: 12px 10px;
            }
            
            .filter-buttons {
                gap: 6px;
            }
            
            .filter-btn {
                min-width: 80px;
                padding: 10px 12px;
                font-size: 12px;
            }
            
            th, td {
                padding: 10px 6px;
                font-size: 0.8em;
            }
            
            .action-cell {
                flex-direction: column;
                gap: 4px;
                align-items: flex-start;
            }
            
            .button, .btn-complete {
                width: 100%;
                margin: 1px 0;
            }
        }
        
        @media (max-width: 480px) {
            .page-header h3 {
                font-size: 1.1em;
            }
            
            h4 {
                font-size: 1.1em;
            }
            
            .filter-btn {
                min-width: 70px;
                padding: 8px 10px;
                font-size: 11px;
            }
            
            th, td {
                padding: 8px 4px;
                font-size: 0.75em;
            }
            
            .button, .btn-complete {
                padding: 8px 12px;
                font-size: 0.75em;
            }
        }
        
        @media (max-width: 360px) {
            #sidebar {
                width: 260px;
                left: -260px;
            }
            
            .sidebar-menu a {
                padding: 12px 15px;
                font-size: 0.9em;
            }
            
            .page-header h3 {
                font-size: 1em;
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
        <img src="../../image/utp_logo.png" alt="UTP Logo"> 
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
            <div class="page-header">
                <h3>WELCOME ADMIN <?= htmlspecialchars($adminName) ?></h3>
                <hr>
            </div>

            <h4>Registered Users Under AE (External Appointment)</h4>
            <div class="warning-text">
                <i class="fas fa-exclamation-triangle"></i> 
                If theres no user shown here, it means there are no users registered under your name or the user is in <strong>ARCHIVE</strong>.
            </div>
            <!-- Filter Buttons -->
            <div class="filter-section">
                <div class="filter-buttons">
                    <button class="filter-btn filter-all" data-status="">All</button>
                    <button class="filter-btn filter-approved" data-status="Pass">Pass</button>
                    <button class="filter-btn filter-rejected" data-status="Fail">Fail</button>
                    <button class="filter-btn filter-alert" data-status="Alert">Alert</button>
                    <button class="filter-btn filter-pending" data-status="Pending">Pending</button>
                </div>
            </div>
            
            <!-- Table Container for Mobile Scrolling -->
            <div class="table-container">
                <table id="dataTable" class="display">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>KYC6 Status</th>
                            <th>Document Verify</th>
                            <th>Vendor Code</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
if (mysqli_num_rows($userResult) > 0) {
    while ($user = mysqli_fetch_assoc($userResult)) {
        $status = isset($user['status']) ? $user['status'] : '';
        $statusLower = strtolower(trim($status));
        $rowclass = 'status-' . $statusLower;

        echo "<tr class='$rowclass'>";
        
        // 1. User ID
        echo "<td>" . htmlspecialchars(isset($user['uid']) ? $user['uid'] : $user['user_id']) . "</td>";
        
        // 2. Name
        if ($user['user_type'] == 'student') {
            echo "<td>" . htmlspecialchars($user['student_name'] ?? '') . "</td>";
        } elseif ($user['user_type'] == 'external_appointment') {
            echo "<td>" . htmlspecialchars($user['ea_name'] ?? '') . "</td>";
        } elseif ($user['user_type'] == 'company') {
            echo "<td>" . htmlspecialchars($user['company_name'] ?? '') . "</td>";
        } else {
             echo "<td>Unknown</td>";
        }

        // 3. KYC6 Status
        echo "<td>";
        if ($user['status'] == 'Pass') {
            echo "<span style='color: green;'>PASS</span><br>";
        } elseif ($user['status'] == 'Alert') {
            echo "<span style='color: #cea323ff;'>ALERT</span><br>";
        } elseif ($user['status'] == 'Fail') {
            echo "<span style='color: red;'>FAIL</span><br>";
        } else {
            echo "<span style='color: gray;'>PENDING</span><br>";
        }
        echo "</td>";

        // 4. Document Verify
        echo "<td>";
        if ($user['bankStatement_verify'] == 1) {
            echo "<span style='color: green;'>✓ Bank Statement Verified</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Bank Statement Not Verified</span><br>";
        }

        if ($user['declaration_verify'] == 1) {
            echo "<span style='color: green;'>✓ Declaration Form Verified</span>";
        } else {
            echo "<span style='color: red;'>✗ Declaration Form Not Verified</span>";
        }

        if ($user['user_type'] == 'student') {
            if ($user['fypForm_verify'] == 1) {
                echo "<span style='color: green;'><br>✓ FYP Form Letter Verified</span>";
            } else {
                echo "<span style='color: red;'><br>✗ FYP Form Letter Not Verified</span>";
            }
        } elseif ($user['user_type'] == 'external_appointment') {
            if ($user['ea_form_verify'] == 1) {
                echo "<span style='color: green;'><br>✓ Appointment Letter Verified</span>";
            } else {
                echo "<span style='color: red;'><br>✗ Appointment Letter Not Verified</span>";
            }
        }
        echo "</td>";

        // 5. Vendor Code
        if (!empty($user['vendor_code'])){
            echo "<td>" . htmlspecialchars($user['vendor_code']) . "</td>";
        } else {
            echo "<td>Not Yet Created</td>";
        }

        // 6. Action (FIXED: Added report_id to the link)
        echo "<td class='action-cell'>";
        echo "<a href='view_user.php?user_id=" . urlencode(isset($user['uid']) ? $user['uid'] : $user['user_id']) . "&report_id=" . urlencode($user['report_id'] ?? '') . "' class='button'>View</a>";
        echo "</td>";
        
        echo "</tr>";
    }
}
?>
</tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    var table = $('#dataTable').DataTable({
        pageLength: 8,
        lengthChange: false,
        responsive: true,
        scrollX: true,
        language: {
            emptyTable: "No users registered.",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            infoEmpty: "Showing 0 to 0 of 0 users",
            infoFiltered: "(filtered from _MAX_ total users)",
            search: "Search:",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        dom: '<"top"f>rt<"bottom"lip><"clear">'
    });

    // Filter buttons
    $('.filter-btn').on('click', function () {
        var status = $(this).data('status').toLowerCase();
        if (status) {
            table.column(3).search(status, true, false).draw();
        } else {
            table.column(3).search('').draw();
        }
    });
});

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
</script>
</body>
</html>
