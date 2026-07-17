<?php
include('../../../config.php');
session_start();

// If the user is already logged in, redirect them
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

// Check if database connection exists
if (!$db) {
    die("Database connection failed. Please check your config.php file.");
}

// Import required classes
require '../../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Function to send verification email
// Function to send verification email
function sendVerificationCode($email, $password) {
    try {
        // Load environment variables - FIXED PATH
        $basePath = dirname(dirname(dirname(__DIR__)));
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();

        $mail = new PHPMailer(true);
        
        // SMTP configuration - USE THE WORKING SETTINGS
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Disable debug for production
        $mail->SMTPDebug = 0;
        $mail->Timeout = 30;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($_ENV['SMTP_USER'], 'UTP VCR');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'AE Email Is Activated - UTP VCR';
        $mail->Body = "
            <h3>UTP VCR - Email Activated</h3>
            <p>Your email has been activated by Master Admin of UTP VCR.</p>
            <p>Please login using your registered UTP official email.</p>
            <p>Below is your password.</p>
            <p>Password: <strong style='font-size: 24px; color: #414e8d;'>$password</strong></p>
            <br><p>Regards,<br>UTP VCR</p>
        ";
        
        $mail->AltBody = "Your password is: $password. Login using your UTP official email.";

        error_log("📧 SENDING EMAIL TO: $email");
        error_log("📧 PASSWORD: $password");
        
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ EMAIL SENT SUCCESSFULLY TO: $email");
        } else {
            error_log("❌ EMAIL FAILED: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ MAILER EXCEPTION: " . $e->getMessage());
        return false;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $admin_role = "ae_admin";
    $staff_id = trim($_POST['staff_id']);
    $re_enter_password = $_POST['re_enter_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Please enter a valid email address.');</script>";
    } elseif (strlen($password) < 6) {
        echo "<script>alert('Password must be at least 6 characters.');</script>";
    } elseif ($password !== $re_enter_password) {
        echo "<script>alert('Passwords do not match. Please try again.');</script>";
    } else {
        // Check if email already exists
        $check = mysqli_prepare($db, "SELECT admin_email FROM admin WHERE admin_email = ?");
        if (!$check) {
            die("Prepare failed: " . mysqli_error($db));
        }
        
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            echo "<script>alert('Email already registered.');</script>";
        } else {
            // Hash the password for database storage
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert new admin
            $stmt = mysqli_prepare($db, "INSERT INTO admin (admin_name, admin_role, admin_email, admin_password, staff_id) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                die("Prepare failed: " . mysqli_error($db));
            }
            
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $admin_role, $email, $hashed, $staff_id);

            if (mysqli_stmt_execute($stmt)) {
                // Send email with the actual password (not hashed)
                $email_sent = sendVerificationCode($email, $password);
                
                if ($email_sent) {
                    echo "<script>
                        alert('AE Admin account created successfully! Password has been sent to $email');
                        window.location='homepage.php';
                    </script>";
                } else {
                    echo "<script>
                        alert('AE Admin account created but failed to send password email. Please contact the AE admin directly.');
                        window.location='homepage.php';
                    </script>";
                }
                exit();
            } else {
                echo "<script>alert('Error: " . addslashes(mysqli_stmt_error($stmt)) . "');</script>";
            }

            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add AE Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        -webkit-tap-highlight-color: transparent;
    }
    
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        touch-action: manipulation;
    }
    
    /* Sidebar Styles */
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
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.2em;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1001;
        transition: all 0.3s ease;
    }
    
    .toggle-btn:hover {
        background: rgb(0, 60, 110);
        transform: scale(1.05);
    }
    
    /* Main Container */
    .container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        padding: 40px;
        width: 100%;
        max-width: 500px;
        position: relative;
        z-index: 1;
        margin-left: 0;
        transition: all 0.3s ease;
    }
    
    #sidebar.active ~ .container {
        margin-left: 280px;
    }
    
    h2 {
        text-align: center;
        margin-bottom: 30px;
        font-size: 28px;
        color: #333;
        font-weight: 600;
        background: rgb(0, 74, 132);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    input, select {
        width: 100%;
        padding: 15px;
        border: 2px solid #e1e5e9;
        border-radius: 10px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    input:focus, select:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .code-section {
        display: flex;
        gap: 10px;
    }
    
    .code-section input {
        flex: 1;
    }
    
    .btn-send-code {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 15px 20px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .btn-send-code:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-send-code:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* Password Match Styles */
    .password-match {
        color: #28a745;
        font-size: 12px;
        margin-top: 5px;
        margin-bottom: 10px;
        display: none;
        font-weight: 500;
    }
    
    .password-mismatch {
        color: #dc3545;
        font-size: 12px;
        margin-top: 5px;
        margin-bottom: 10px;
        display: none;
        font-weight: 500;
    }
    
    /* Submit Button */
    .submit-btn {
        width: 100%;
        padding: 15px;
        background: rgb(0, 74, 132);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }
    
    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }
    
    .submit-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* Alert Messages */
    .alert {
        padding: 15px;
        margin: 20px 0;
        border-radius: 10px;
        display: none;
        font-weight: 500;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* Loading */
    .loading {
        display: none;
        color: #667eea;
        text-align: center;
        margin: 10px 0;
        font-weight: 500;
    }
    
    /* Timer */
    .timer {
        font-size: 12px;
        color: #dc3545;
        margin-top: 5px;
        margin-bottom: 15px;
        text-align: center;
        font-weight: bold;
    }
    
    /* Overlay */
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
    
    /* Responsive Design */
    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .container {
            padding: 30px 25px;
            margin: 0;
        }
        
        #sidebar.active ~ .container {
            margin-left: 0;
            transform: translateX(280px);
        }
        
        h2 {
            font-size: 24px;
        }
        
        input, select {
            padding: 12px;
            font-size: 16px;
        }
        
        .toggle-btn {
            top: 15px;
            left: 15px;
            width: 45px;
            height: 45px;
        }
    }
    
    @media (max-width: 480px) {
        .container {
            padding: 25px 20px;
        }
        
        h2 {
            font-size: 22px;
        }
        
        input, select {
            padding: 12px;
            font-size: 16px;
        }
        
        .code-section {
            flex-direction: column;
        }
        
        .btn-send-code {
            width: 100%;
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
        
        .container {
            padding: 20px 15px;
        }
        
        h2 {
            font-size: 20px;
        }
    }
    </style>
</head>
<body>
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
        <a href="notification.php">
            <i class="fas fa-bell"></i>
            <span>Notification</span>
        </a>
        <a href="add_AE.php"class="active">
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

<div class="container">
    <h2>AE Admin Registration</h2>
    
    <!-- Alert messages -->
    <div id="alertMessage" class="alert"></div>
    
    <form action="add_AE.php" method="POST" id="signupForm">
        <div class="form-group">
            <label for="full_name">Full Name:</label>
            <input type="text" name="full_name" id="full_name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="staff_id">Staff ID:</label>
            <input type="text" name="staff_id" id="staff_id" required value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required minlength="6">
        </div>
        
        <div class="form-group">
            <label for="re_enter_password">Re-Enter Password:</label>
            <input type="password" name="re_enter_password" id="re_enter_password" required>
            <div id="passwordMatchMessage" class="password-match">Passwords match!</div>
            <div id="passwordMismatchMessage" class="password-mismatch">Passwords do not match!</div>
        </div>

        <button type="submit" name="signup" id="submitBtn" class="submit-btn">Register AE Admin</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const email = document.getElementById('email');
    const alertMessage = document.getElementById('alertMessage');

    // Real-time password matching validation
    const password = document.getElementById('password');
    const reEnterPassword = document.getElementById('re_enter_password');
    const matchMessage = document.getElementById('passwordMatchMessage');
    const mismatchMessage = document.getElementById('passwordMismatchMessage');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('signupForm');
    
    function validatePasswords() {
        if (password.value === '' || reEnterPassword.value === '') {
            matchMessage.style.display = 'none';
            mismatchMessage.style.display = 'none';
            submitBtn.disabled = false;
            return;
        }
        
        if (password.value === reEnterPassword.value) {
            matchMessage.style.display = 'block';
            mismatchMessage.style.display = 'none';
            submitBtn.disabled = false;
        } else {
            matchMessage.style.display = 'none';
            mismatchMessage.style.display = 'block';
            submitBtn.disabled = true;
        }
    }
    
    password.addEventListener('input', validatePasswords);
    reEnterPassword.addEventListener('input', validatePasswords);

    // Show alert message
    function showAlert(message, type) {
        alertMessage.textContent = message;
        alertMessage.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
        alertMessage.style.display = 'block';
        
        setTimeout(() => {
            alertMessage.style.display = 'none';
        }, 5000);
    }

    // Form submission validation
    form.addEventListener('submit', function(e) {
        if (password.value !== reEnterPassword.value) {
            e.preventDefault();
            showAlert('Passwords do not match. Please check your entries.', 'error');
            return false;
        }
    });

    // Initialize
    validatePasswords();
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
</body>
</html>