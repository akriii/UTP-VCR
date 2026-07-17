<?php 

// --- Error reporting for debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "../../config.php"; 
session_start(); 

// If already logged in, go to the SINGLE HOMEPAGE
if (isset($_SESSION['user_type'])) { 
    header("Location: user_homePage.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- Using Null Coalescence (??) for safety ---
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Passwords should not be trimmed

    if (!$db) {
        die("Database connection failed.");
    }

    // Fetch user info
    $stmt = $db->prepare("
        SELECT u.user_id, u.user_type, u.user_email, u.user_password,
        CASE
            WHEN u.user_type = 'student' THEN s.student_full_name
            WHEN u.user_type = 'company' THEN c.company_name
            WHEN u.user_type = 'external_appointment' THEN e.external_full_name
            ELSE NULL
        END AS full_name
        FROM user u
        LEFT JOIN student s ON u.user_id = s.user_id
        LEFT JOIN company c ON u.user_id = c.user_id
        LEFT JOIN external_appointment e ON u.user_id = e.user_id
        WHERE u.user_email = ?
    ");
    
    if (!$stmt) {
        die("Prepare failed: " . $db->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {

        $stmt->bind_result($user_id, $user_type, $user_email, $stored_password, $full_name);
        $stmt->fetch();

        // Debug Logs
        error_log("🔍 USER LOGIN ATTEMPT - Email: " . $email);
        error_log("🔍 Input password: " . $password);
        error_log("🔍 Stored password: " . $stored_password);

        // =======================================================
        //  HERE IS YOUR LOGIC (IT'S ALREADY WORKING!)
        // =======================================================

        // --- STEP 1: CHECK FOR A HASHED PASSWORD ---
        // This is the correct, modern, and secure way to check.
        $is_hashed_password_correct = password_verify($password, $stored_password);
        error_log("🔍 password_verify(): " . ($is_hashed_password_correct ? "TRUE" : "FALSE"));

        if ($is_hashed_password_correct) {
            
            // --- LOGIN SUCCESS (HASHED) ---
            error_log("✅ USER LOGIN SUCCESS (HASHED) TYPE: " . $user_type);
            
            session_regenerate_id(true);
            $_SESSION['email'] = $user_email;
            $_SESSION['fullname'] = $full_name;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'user'; // General role for all user types


            //trigger kyc6 reminder
            require_once "kyc6_reminder.php";
            $reminder = new KYC6Reminder($db);
            $reminder->sendPendingReminders();

            header("Location: user_homePage.php");
            exit();
        } 
        
        // --- STEP 2: CHECK FOR A PLAIN TEXT PASSWORD (FALLBACK) ---
        // This only runs if the hashed check (Step 1) failed.
        error_log("❌ password_verify failed. Trying plain text check...");

        if ($password === $stored_password) {
            
            // --- LOGIN SUCCESS (PLAIN TEXT) ---
            error_log("✅ USER LOGIN SUCCESS (PLAIN TEXT) TYPE: " . $user_type);

            // === CRITICAL SECURITY UPGRADE: RE-HASH THE PASSWORD ===
            // Since we logged in with plain text, we MUST update the database
            // with a new, secure hash. This is a one-time fix per user.
            error_log("!!! SECURITY: Re-hashing plain text password for user_id: " . $user_id);
            $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Run the update query
            $rehash_stmt = $db->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
            if($rehash_stmt) {
                $rehash_stmt->bind_param("si", $new_hashed_password, $user_id);
                $rehash_stmt->execute();
                $rehash_stmt->close();
                error_log("✅ SECURITY: Password for user_id: $user_id has been re-hashed.");
            } else {
                error_log("❌ SECURITY: FAILED to re-hash password for user_id: $user_id. DB Error: " . $db->error);
            }
            // === END SECURITY UPGRADE ===

            // Now, set the session and log them in
            session_regenerate_id(true);
            $_SESSION['email'] = $user_email;
            $_SESSION['fullname'] = $full_name;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'user'; // General role for all user types


            //trigger kyc6 reminder
            require_once "kyc6_reminder.php";
            $reminder = new KYC6Reminder($db);
            $reminder->sendPendingReminders();

            header("Location: user_homePage.php");
            exit();
        }

        // --- STEP 3: BOTH METHODS FAILED ---
        // If it's not a valid hash and not a valid plain text match, then the password is wrong.
        error_log("❌ BOTH METHODS FAILED for user: " . $email);
        echo "<script>alert('Invalid email or password');</script>";

    } else {
        error_log("❌ User email not found: " . $email);
        echo "<script>alert('Invalid email or password');</script>";
    }

    $stmt->close();
}
?>


<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<!-- Add Font Awesome CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
     body { 
        font-family: Arial, sans-serif;
        background: #f5f5f5;
        display: flex; 
        justify-content: center; 
        align-items: center; 
        height: 100vh; 
        margin: 0; 
    }

    .container { 
        width: 400px; 
        background: #fff; 
        padding: 35px; 
        border-radius: 10px; 
        box-shadow: 0 0 15px rgba(0,0,0,0.1); 
        position: relative;
    }
        
    h2 { 
        text-align: center; 
        margin-bottom: 25px; 
        font-size: 26px; 
        color: #333;
    }

    input[type="text"], input[type="email"], input[type="password"] { 
        width: 100%; 
        padding: 12px; 
        margin-bottom: 18px; 
        border: 1px solid #ccc; 
        border-radius: 6px; 
        font-size: 16px; 
        box-sizing: border-box; 
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: #414e8dff;
        border: none;
        border-radius: 6px;
        color: white;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s;
    }

    button:hover {
        background-color: #2e3a7a;
    }

    .signup-link {
        text-align: center;
        margin-top: 15px;
        font-size: 14px;
    }

    .signup-link a {
        color: #414e8dff;
        text-decoration: none;
    }

    .signup-link a:hover {
        text-decoration: underline;
    }

    label {
        font-size: 14px;
        font-weight: bold;
        color: #333;
        display: block;
        margin-bottom: 5px;
    }

    .forgot {
        font-size: 13px;
        margin-bottom: 10px;
        text-align: right; 
    }
    .forgot a {
        color: #414e8dff;
        text-decoration: none;
    }
    .forgot a:hover {
        text-decoration: underline;
    }

    /* Password Container Styles */
    .password-container {
        position: relative;
        width: 100%;
        margin-bottom: 5px;
    }

    #password {
        width: 100%;
        padding: 12px 45px 12px 12px; /* Extra right padding for the eye icon */
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 16px;
        box-sizing: border-box;
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        top: 35%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #666;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .password-toggle:hover {
        color: #414e8dff;
    }

    .password-toggle:active {
        transform: translateY(-50%) scale(0.95);
    }

    .password-toggle i {
        font-size: 18px;
    }
</style>
</head>
<body>



    <div class="container">
    <!-- Add Logo -->
    <div style="text-align: center; margin-bottom: 0px;">
        <img src="../../image/UTP.png" alt="UTP Logo" style="max-width: 200px; height: auto;">
    </div>

        <h2>UTP VCR Login</h2>
        <form method="POST" action="login.php">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>

            <label for="password">Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="forgot"><a href="reset_password.php">Forgot password?</a></div>

            <button type="submit">Login</button>
        </form>
        <div class="signup-link">
            Don't have an account? <a href="signUp.php">Sign up here</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');

            togglePassword.addEventListener('click', function() {
                // Toggle the password field type
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle the eye icon
                if (type === 'text') {
                    eyeIcon.className = 'fas fa-eye-slash';
                    eyeIcon.title = 'Hide password';
                } else {
                    eyeIcon.className = 'fas fa-eye';
                    eyeIcon.title = 'Show password';
                }
            });

            // Optional: Hide password when clicking outside
            document.addEventListener('click', function(event) {
                if (!togglePassword.contains(event.target) && !passwordInput.contains(event.target)) {
                    passwordInput.setAttribute('type', 'password');
                    eyeIcon.className = 'fas fa-eye';
                    eyeIcon.title = 'Show password';
                }
            });
        });
    </script>
</body>
</html>
