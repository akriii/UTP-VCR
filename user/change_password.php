<?php
session_start();
include "../config.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Import required classes
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$email = $_SESSION['email'];
$message = '';
$message_type = '';

// Function to generate 6-digit code
function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

// Function to send verification email
function sendVerificationCode($email, $code) {
    try {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $mail = new PHPMailer(true);
        
        // SMTP configuration
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
        $mail->Subject = 'Password Change Verification Code - UTP VCR';
        $mail->Body = "
            <h3>UTP VCR - Password Change Verification</h3>
            <p>Your 6-digit verification code for password change is: <strong style='font-size: 24px; color: #414e8d;'>$code</strong></p>
            <p>This code will expire in 10 minutes.</p>
            <br><p>Regards,<br>UTP VCR</p>
        ";
        
        $mail->AltBody = "Your password change verification code is: $code. This code will expire in 10 minutes.";

        error_log("📧 SENDING PASSWORD CHANGE CODE TO: $email");
        error_log("📧 CODE: $code");
        
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ PASSWORD CHANGE CODE SENT SUCCESSFULLY TO: $email");
        } else {
            error_log("❌ PASSWORD CHANGE CODE FAILED: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ MAILER EXCEPTION: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX Send Code request
if (isset($_POST['ajax_send_code'])) {
    $response = ['success' => false, 'message' => ''];
    
    error_log("=== PASSWORD CHANGE AJAX REQUEST RECEIVED ===");
    error_log("Email: $email");
    
    // Generate and store verification code
    $verification_code = generateVerificationCode();
    $expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Store code in session
    $_SESSION['password_change_code'] = $verification_code;
    $_SESSION['password_change_email'] = $email;
    $_SESSION['password_change_expiry'] = $expiry_time;
    
    error_log("Generated password change code: $verification_code");
    
    // Send verification email
    $email_sent = sendVerificationCode($email, $verification_code);
    
    error_log("Email sent result: " . ($email_sent ? 'TRUE' : 'FALSE'));
    
    if ($email_sent) {
        $response['success'] = true;
        $response['message'] = 'Verification code sent to your email! Check your inbox and spam folder.';
        error_log("✅ SUCCESS: Password change code sent to $email");
    } else {
        $response['message'] = 'Failed to send verification code. Please try again.';
        error_log("❌ FAILED: Could not send password change code to $email");
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $entered_code = trim($_POST['code']);

    // Validate current password
    $check_stmt = $db->prepare("SELECT user_password FROM user WHERE user_email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $message = 'User not found.';
        $message_type = 'error';
    } else {
        $user_row = $check_result->fetch_assoc();
        $stored_password = $user_row['user_password'];
        
        // Check current password
        if (!password_verify($current_password, $stored_password) && $current_password !== $stored_password) {
            $message = 'Current password is incorrect.';
            $message_type = 'error';
        }
        // Validate verification code
        elseif (!isset($_SESSION['password_change_code']) || !isset($_SESSION['password_change_email'])) {
            $message = 'Please request a verification code first.';
            $message_type = 'error';
        } elseif ($_SESSION['password_change_email'] !== $email) {
            $message = 'Email does not match.';
            $message_type = 'error';
        } elseif (time() > strtotime($_SESSION['password_change_expiry'])) {
            $message = 'Verification code has expired. Please request a new one.';
            $message_type = 'error';
        } elseif ($_SESSION['password_change_code'] !== $entered_code) {
            $message = 'Invalid verification code. Please try again.';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters.';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match. Please try again.';
            $message_type = 'error';
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password
            $update_stmt = $db->prepare("UPDATE user SET user_password = ? WHERE user_email = ?");
            $update_stmt->bind_param("ss", $hashed_password, $email);

            if ($update_stmt->execute()) {
                // Clear verification session
                unset($_SESSION['password_change_code']);
                unset($_SESSION['password_change_email']);
                unset($_SESSION['password_change_expiry']);
                
                $message = 'Password changed successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating password. Please try again.';
                $message_type = 'error';
            }

            $update_stmt->close();
        }
    }
    $check_stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-bg: #002776ff;
            --content-bg: #f5f6f7;
            --accent: #1A252F;
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

        .password-container {
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 20px auto;
        }

        .password-container h2 {
            color: #002776ff;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        /* --- NUCLEAR FIX FOR PASSWORD EYE --- */
        /* 1. HIDE the browser's native password eye (Chrome/Edge) */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }

        /* 2. Set up the container */
        .password-input-container {
            position: relative;
            width: 100%;
        }

        /* 3. Push the input to the back */
        .password-input-container input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5eb;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s ease;
            padding-right: 45px;
            position: relative; /* Essential for z-index to work */
            z-index: 1;         /* Lower layer */
        }

        .form-group input:focus {
            outline: none;
            border-color: #002776ff;
        }

        /* 4. Push the button to the front */
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            color: #666;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            z-index: 99; /* High layer to force it on top */
        }

        .password-toggle:hover {
            color: #002776ff;
        }
        /* --- END FIX --- */

        .code-section {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .code-section input {
            flex: 1;
        }

        .btn-send-code {
            background: #002776ff;
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
            white-space: nowrap;
        }

        .btn-send-code:hover:not(:disabled) {
            background: #001a5e;
        }

        .btn-send-code:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        .submit-btn {
            width: 100%;
            background: #002776ff;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: #001a5e;
        }

        .submit-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        .timer {
            font-size: 12px;
            color: #e74c3c;
            margin-top: 5px;
            text-align: center;
            font-weight: bold;
        }

        .loading {
            display: none;
            color: #002776ff;
            text-align: center;
            margin: 10px 0;
        }

        .alert {
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 8px;
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

        .password-match {
            color: #27ae60;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .password-mismatch {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            body.sidebar-closed .sidebar {
                transform: translateX(0);
            }
            
            .code-section {
                flex-direction: column;
            }
            
            .btn-send-code {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-container" style="text-align: center; margin-bottom: 0px; padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2);">
            <img src="../image/UTP2.png" alt="UTP Logo" style="max-width: 160px; height: auto;">
        </div>
        <h2>Customer Panel</h2>
        <a href="user_homePage.php"><i class="fa-solid fa-house"></i> Home</a>
        
        <a href="view_declaration.php"><i class="fa-solid fa-pen-nib"></i> View Declaration Form</a>
        <a href="user_profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main-content">
        <button id="toggle-btn"><i class="fa-solid fa-bars"></i></button>

        <div class="password-container">
            <h2><i class="fa-solid fa-key"></i> Change Password</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="changePasswordForm">
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="password-toggle" data-target="current_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                        <button type="button" class="password-toggle" data-target="new_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="password-toggle" data-target="confirm_password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="passwordMatchMessage" class="password-match">Passwords match!</div>
                    <div id="passwordMismatchMessage" class="password-mismatch">Passwords do not match!</div>
                </div>
                <div class="password-input-container">
                <div class="form-group">
                    <label for="email">Email Verification:</label>
                    <div class="code-section">
                        <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                        <button type="button" id="sendCodeBtn" class="btn-send-code">Send Code</button>
                    </div>
                    <div id="timer" class="timer"></div>
                    <div id="loading" class="loading">Sending code, please wait...</div>
                </div></div>
                <div class="password-input-container">
                <div class="form-group">
                    <label for="code">6-Digit Verification Code:</label>
                    <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code" required>
                </div></div>

                <button type="submit" name="change_password" id="submitBtn" class="submit-btn">Change Password</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            const timer = document.getElementById('timer');
            const loading = document.getElementById('loading');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const matchMessage = document.getElementById('passwordMatchMessage');
            const mismatchMessage = document.getElementById('passwordMismatchMessage');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('changePasswordForm');
            let countdown;

            // --- NUCLEAR PASSWORD TOGGLE FIX (JS) ---
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation(); // Stop bubbling to avoid form submission

                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            // ----------------------------------------

            // Real-time password matching validation
            function validatePasswords() {
                if (newPassword.value === '' || confirmPassword.value === '') {
                    matchMessage.style.display = 'none';
                    mismatchMessage.style.display = 'none';
                    submitBtn.disabled = false;
                    return;
                }
                
                if (newPassword.value === confirmPassword.value) {
                    matchMessage.style.display = 'block';
                    mismatchMessage.style.display = 'none';
                    submitBtn.disabled = false;
                } else {
                    matchMessage.style.display = 'none';
                    mismatchMessage.style.display = 'block';
                    submitBtn.disabled = true;
                }
            }
            
            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);

            // Start countdown timer
            function startTimer(duration) {
                clearInterval(countdown);
                let timeLeft = duration;
                
                countdown = setInterval(function() {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    
                    timer.textContent = `Code expires in: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                    
                    if (--timeLeft < 0) {
                        clearInterval(countdown);
                        timer.textContent = 'Code expired. Please request a new one.';
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = 'Send Code';
                    }
                }, 1000);
            }

            // Show alert message
            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
                alertDiv.textContent = message;
                
                form.parentNode.insertBefore(alertDiv, form);
                
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Handle Send Code button with AJAX
            sendCodeBtn.addEventListener('click', function() {
                loading.style.display = 'block';
                this.disabled = true;
                this.textContent = 'Sending...';
                
                const formData = new FormData();
                formData.append('ajax_send_code', 'true');
                
                fetch('change_password.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    
                    if (data.success) {
                        showAlert(data.message, 'success');
                        sendCodeBtn.textContent = 'Code Sent';
                        startTimer(600);
                    } else {
                        showAlert(data.message, 'error');
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = 'Send Code';
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    showAlert('Network error. Please try again.', 'error');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = 'Send Code';
                    console.error('Error:', error);
                });
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    showAlert('New passwords do not match. Please check your entries.', 'error');
                    return false;
                }
            });

            // Initialize
            validatePasswords();
            
            // If we have an active timer from previous submission, restart it
            <?php if (isset($_SESSION['password_change_expiry']) && time() < strtotime($_SESSION['password_change_expiry'])): ?>
                const timeLeft = Math.floor((<?php echo strtotime($_SESSION['password_change_expiry']); ?> - <?php echo time(); ?>) / 1000);
                if (timeLeft > 0) {
                    startTimer(timeLeft);
                    sendCodeBtn.disabled = true;
                    sendCodeBtn.textContent = 'Code Sent';
                }
            <?php endif; ?>

            // Toggle sidebar
            document.getElementById('toggle-btn').addEventListener('click', function() {
                document.body.classList.toggle('sidebar-closed');
            });
        });
    </script>
</body>
</html>