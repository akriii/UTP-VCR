<?php 
session_start();
include('../../../config.php');

//Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

$sql = "
SELECT 
    ra.request_id,
    ra.request_message,
    ra.request_id,
    ra.request_name,
    ra.request_email,
    ra.request_status,
    ra.request_staff_id,
    ra.request_role,
    ra.admin_id,
    a.admin_name 
FROM request_admin ra
LEFT JOIN admin a ON a.admin_id = ra.admin_id
WHERE ra.request_status = 'Pending'
ORDER BY ra.request_id DESC
";

$result = mysqli_query($db, $sql);

if (!$result) {
    die("Database query failed: " . mysqli_error($db));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Admin Request</title>

   <style>
    * {
        box-sizing: border-box;
    }
    body {
        background: #f5f5f5;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    /* Sidebar Styles - FIXED Z-INDEX */
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
        top: 20px;
        left: 20px;
        background: rgb(0, 74, 132);
        border: 2px solid white;
        color: white;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.2em;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1001; /* Above sidebar */
        transition: all 0.3s ease;
    }
    
    .toggle-btn:hover {
        background: rgb(0, 60, 110);
        transform: scale(1.05);
    }
    
    /* Main Content Styles - PROPER CENTERING */
    .main-content {
        padding: 20px;
        transition: all 0.3s ease;
        min-height: 100vh;
        width: 100%;
        position: relative;
        z-index: 1; /* Below sidebar */
    }
    
    .content-wrapper {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        margin: 0 auto; /* Center the box */
        max-width: 1200px; /* Limit maximum width */
        width: 100%;
        position: relative;
        z-index: 1;
    }
    
    /* Page Header */
    .page-header {
        margin-bottom: 30px;
        text-align: center;
        padding: 20px 0;
    }
    
    .page-header h3 {
        color: #004a84;
        font-weight: 600;
        margin: 0;
        font-size: 1.8em;
    }
    
    .page-header hr {
        border: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #004a84, transparent);
        margin: 15px auto;
        width: 80%;
    }
    
    /* Content Section */
    h4 {
        color: #004a84;
        font-weight: 600;
        margin-bottom: 20px;
        font-size: 1.4em;
    }
    
    /* Table Styles */
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    th, td {
        text-align: left;
        padding: 15px;
        border-bottom: 1px solid #e3e6f0;
    }
    
    th {
        background: linear-gradient(135deg, #004a84, #003366);
        color: white;
        font-weight: 600;
        font-size: 0.95em;
    }
    
    td {
        background: white;
        font-size: 0.9em;
    }
    
    /* Action Buttons */
    .button {
        padding: 8px 16px;
        border-radius: 6px;
        color: white;
        text-decoration: none;
        display: inline-block;
        margin: 2px;
        font-size: 0.85em;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .button.approve {
        background: linear-gradient(135deg, #28a745, #1e7e34);
    }
    
    .button.approve:hover {
        background: linear-gradient(135deg, #1e7e34, #155724);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .button.reject {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }
    
    .button.reject:hover {
        background: linear-gradient(135deg, #c82333, #a71e2a);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    /* Status badges */
    .badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: capitalize;
    }
    
    /* Status row colors */
    .status-pending {
        background-color: #fffdf6 !important;
        border-left: 4px solid #ffc107;
    }
    
    .status-approved {
        background-color: #f8fff9 !important;
        border-left: 4px solid #28a745;
    }
    
    .status-rejected {
        background-color: #fff8f8 !important;
        border-left: 4px solid #dc3545;
    }
    
    .action-cell {
        white-space: nowrap;
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .text-muted {
        color: #6c757d !important;
        font-style: italic;
    }
    
    /* DataTables Customization */
    .dataTables_wrapper {
        position: relative;
        z-index: 1;
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
    
    /* Empty state styling */
    .dataTables_empty {
        text-align: center;
        padding: 40px !important;
        color: #6c757d;
        font-style: italic;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .content-wrapper {
            padding: 20px;
            margin: 10px;
            width: calc(100% - 20px);
        }
        
        .page-header h3 {
            font-size: 1.5em;
        }
        
        .action-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        th, td {
            padding: 10px 8px;
            font-size: 0.85em;
        }
    }
    
    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
        
        .content-wrapper {
            padding: 15px;
            margin: 5px;
            width: calc(100% - 10px);
        }
        
        .page-header h3 {
            font-size: 1.3em;
        }
        
        .button {
            padding: 6px 12px;
            font-size: 0.8em;
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
        <span class="brand-text">UTP VCR</span>
        <span class="role-text">Admin Panel</span>
    </div>

    <div class="sidebar-menu">
        <a href="homepage.php" >
            <i class="fas fa-home"></i>
            <span>Homepage</span>
        </a>
        <a href="history.php">
            <i class="fas fa-history"></i>
            <span>Archive</span>
        </a>
        <a href="notification.php"class="active">
            <i class="fas fa-bell"></i>
            <span>Notification</span>
        </a>
        <a href="add_AE.php">
            <i class="fas fa-user-plus"></i>
            <span>Add AE</span>
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
                <h3>ADMIN REQUEST NOTIFICATIONS</h3>
                <hr>
            </div>

            <h4>Pending Admin Requests</h4>
            
            <table id="dataTable" class="display">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Request Status</th>
                        <th>Message</th>
                        <th>Request Role</th>
                        <th>Staff ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $status = htmlspecialchars($row['request_status']);
                        $message = htmlspecialchars($row['request_message']);
                        $fullname = htmlspecialchars($row['request_name']);
                        $email = htmlspecialchars($row['request_email']);
                        $role = htmlspecialchars($row['request_role'] );
                        $staff_id = htmlspecialchars($row['request_staff_id'] );
                        
                        // Add status class for row coloring
                        $statusClass = "status-" . strtolower($status);

                        echo "<tr class='$statusClass'>";
                        echo "<td>" . htmlspecialchars($row['request_id']) . "</td>";
                        echo "<td>$fullname</td>";
                        echo "<td>$email</td>";
                        echo "<td><span class='badge'>" . ucfirst($status) . "</span></td>";
                        echo "<td>$message</td>";
                        echo "<td>$role</td>";
                        echo "<td>$staff_id</td>";
                        echo "<td class='action-cell'>";
                        
                        if (strtolower($status) == 'pending') {
                            echo "<a href='approve_admin.php?request_id=" . urlencode($row['request_id']) . "' class='button approve'>Approve</a>";
                            echo "<a href='reject_admin.php?request_id=" . urlencode($row['request_id']) . "' class='button reject'>Reject</a>";
                        } else {
                            echo "<span class='text-muted'>Processed</span>";
                        }
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                  
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
 <script>
    $(document).ready(function () {
    var table = $('#dataTable').DataTable({
        pageLength: 10,
        lengthChange: false,
        responsive: true,
        language: {
            emptyTable: "No notification found.",
            info: "Showing _START_ to _END_ of _TOTAL_ deleted users",
            infoEmpty: "Showing 0 to 0 of 0 notification",
            infoFiltered: "(filtered from _MAX_ total notification)",
            search: "Search:",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        dom: '<"top"lf>rt<"bottom"ip><"clear">'
    });

    // Filter buttons
    $('.filter-btn').on('click', function () {
        var status = $(this).data('status').toLowerCase();
        if (status) {
            table.column(3).search(status, true, false).draw(); // column 3 = Status column
        } else {
            table.column(3).search('').draw(); // show all
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


</script>
</body>
</html>