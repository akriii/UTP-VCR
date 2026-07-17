<?php 

error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../config.php"; 
session_start(); 




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']); 
    $password = $_POST['password'];

    
    if (!$db) {
        die("Database connection failed.");
    }

    
    $stmt = mysqli_prepare($db, "SELECT admin_id, admin_name, admin_email, admin_role, admin_password FROM admin WHERE admin_email = ?");
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($db));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) == 1) {
    mysqli_stmt_bind_result($stmt, $admin_id, $admin_name, $admin_email, $admin_role, $hashed_password);
    mysqli_stmt_fetch($stmt);

    
    $password_verify_result = password_verify($password, $hashed_password);

    if ($password_verify_result) {
        
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['admin_email'] = $admin_email;
        $_SESSION['admin_name'] = $admin_name;
        $_SESSION['admin_role'] = $admin_role;

        if ($admin_role == 'master_admin') {
            header("Location: Master_Admin/homepage.php");
        }
        elseif ($admin_role == 'kyc6_admin') {
            header("Location: KYC6_Admin/homepage.php");
        }
        elseif ($admin_role == 'ae_admin') {
            header("Location: AE/homepage.php");
        }
        exit();
    } else {
        // If password verification fails, try direct comparison
        if ($password == $hashed_password) {
            // Password is correct (plain text)
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin_id;
            $_SESSION['admin_email'] = $admin_email;
            $_SESSION['admin_name'] = $admin_name;
            $_SESSION['admin_role'] = $admin_role;

            if ($admin_role == 'master_admin') {
                header("Location: Master_Admin/homepage.php");
            }
            elseif ($admin_role == 'kyc6_admin') {
                header("Location: KYC6_Admin/homepage.php");
            }
            elseif ($admin_role == 'ae_admin') {
                header("Location: AE/homepage.php");
            }
            exit();
        } else {
            echo "<script>alert('Invalid email or password');</script>";
        }
    }
} else {
        echo "<script>alert('Invalid email or password');</script>";
    }

    mysqli_stmt_close($stmt);
}
?> 

<!DOCTYPE html>
<html>
<head>
<title>Admin Login</title>
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

    
    .login-wrapper {
        position: relative; 
    }

    
    .logo-header {
        position: absolute;
        bottom: 100%;
        left: 0;
        width: 100%;
        text-align: center;
        margin-bottom: 25px; 
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
    }


    .system-title {
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        font-size: 24px;
        font-weight: 700;
        color: #2e3a7a; 
        text-transform: uppercase; 
        letter-spacing: 2px; 
        margin-top: 15px; 
        margin-bottom: 0;
        text-shadow: 1px 1px 0px rgba(255,255,255,0.5); 
    }

    
    .container h2 { 
        text-align: center; 
        margin-bottom: 25px; 
        font-size: 20px; 
        color: #666;     
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .container { 
        width: 400px; 
        background: #fff; 
        padding: 35px; 
        border-radius: 10px; 
        box-shadow: 0 0 15px rgba(0,0,0,0.1); 
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

    
    .password-container {
        position: relative;
        width: 100%;
    }

    .password-toggle {
        position: absolute;
        right: 8px;
        top: 35%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #666;
        width: 40px; 
        height: 40px; 
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .password-toggle:hover {
        color: #414e8dff;
        background-color: #f0f0f0;
    }

    .password-toggle:active {
        background-color: #e0e0e0;
        transform: translateY(-50%) scale(0.95);
    }

    .password-toggle i {
        font-size: 20px; 
    }

    #password {
        padding-right: 40px; 
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="container">
            
            <div class="logo-header"> 
                <img src="../../image/utp_logo.png" alt="UTP Logo" style="width:200px;"> <div class="system-title">UTP Vendor Code Requistion</div>
            </div>
            
            <h2>Admin Login</h2>

            <form method="POST" action="">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>

                <label>Password</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="forgot"><a href="forgot_password.php">Forgot password?</a></div>

                <button type="submit">Login</button>
                
                <div class="signup-link">
                    Don't have an account? <a href="signup_admin.php">Request Admin Access</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = togglePassword.querySelector('i');

            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'text') {
                    eyeIcon.className = 'fas fa-eye-slash';
                    eyeIcon.title = 'Hide password';
                } else {
                    eyeIcon.className = 'fas fa-eye';
                    eyeIcon.title = 'Show password';
                }
            });

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
