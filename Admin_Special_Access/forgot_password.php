<?php
include('../config.php');
session_start();

// If the user is already logged in, redirect them
if (isset($_SESSION['admin_id'])) {
    header("Location: homepage.php");
    exit();
}

// Check if database connection exists
if (!$db) {
    die("Database connection failed. Please check your config.php file.");
}

// Import required classes
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

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
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        
        $mail->SMTPDebug = 0;
        $mail->Timeout = 30;
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($_ENV['SMTP_USER'], 'UTP VCR');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Verification Code - UTP VCR';
        $mail->Body = "
            <h3>UTP VCR - Verification Code</h3>
            <p>Your 6-digit verification code is: <strong style='font-size: 24px; color: #414e8d;'>$code</strong></p>
            <p>This code will expire in 10 minutes.</p>
            <br><p>Regards,<br>UTP VCR</p>
        ";
        
        $mail->AltBody = "Your verification code is: $code. This code will expire in 10 minutes.";

        error_log("📧 SENDING EMAIL TO: $email");
        error_log("📧 CODE: $code");
        
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


if (isset($_POST['ajax_send_code'])) {
    $email = trim($_POST['email']);
    $response = ['success' => false, 'message' => ''];
    
    error_log("=== AJAX REQUEST RECEIVED ===");
    error_log("Email: $email");
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        error_log("❌ Invalid email format");
    } else {
        
        $verification_code = generateVerificationCode();
        $expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_expiry'] = $expiry_time;
        
        error_log("Generated code: $verification_code");
        
        $email_sent = sendVerificationCode($email, $verification_code);
        
        error_log("Email sent result: " . ($email_sent ? 'TRUE' : 'FALSE'));
        
        if ($email_sent) {
            $response['success'] = true;
            $response['message'] = 'Verification code sent to your email! Check your inbox and spam folder.';
            error_log("✅ SUCCESS: Code sent to $email");
        } else {
            $response['message'] = 'Failed to send verification code. Please try again.';
            error_log("❌ FAILED: Could not send to $email");
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $re_enter_password = $_POST['re_enter_password'];
    $entered_code = trim($_POST['code']);

  
    if (!isset($_SESSION['verification_code']) || !isset($_SESSION['verification_email'])) {
        echo "<script>alert('Please request a verification code first.');</script>";
    } elseif ($_SESSION['verification_email'] !== $email) {
        echo "<script>alert('Email does not match the one we sent the code to.');</script>";
    } elseif (time() > strtotime($_SESSION['code_expiry'])) {
        echo "<script>alert('Verification code has expired. Please request a new one.');</script>";
    } elseif ($_SESSION['verification_code'] !== $entered_code) {
        echo "<script>alert('Invalid verification code. Please try again.');</script>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Please enter a valid email address.');</script>";
    } elseif (strlen($password) < 6) {
        echo "<script>alert('Password must be at least 6 characters.');</script>";
    } elseif ($password !== $re_enter_password) {
        echo "<script>alert('Passwords do not match. Please try again.');</script>";
    } else {
        
    $hashed = password_hash($password, PASSWORD_DEFAULT);

         
    $stmt = mysqli_prepare($db, "UPDATE admin SET admin_password = ? WHERE admin_email = ?");
    if (!$stmt) {
        die("Prepare failed: " . mysqli_error($db));
    }
    mysqli_stmt_bind_param($stmt, "ss", $hashed, $email);

        if (mysqli_stmt_execute($stmt)) {
               
                
            
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['code_expiry']);
                
            echo "<script>alert('Password reset successful.'); window.location='login_admin.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error: " . addslashes(mysqli_stmt_error($stmt)) . "');</script>";
        }

        mysqli_stmt_close($stmt);
        }
    
    }

?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
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
            width: 450px;
            background: #fff;
            padding: 35px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 26px;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            box-sizing: border-box;
        }
        button {
            padding: 12px;
            background: #414e8dff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }
        button:hover {
            background: #2e3a7a;
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        p {
            text-align: center;
            font-size: 14px;
            margin-top: 10px;
        }
        a {
            color: #414e8dff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        label {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        .password-match {
            color: green;
            font-size: 12px;
            margin-top: -10px;
            margin-bottom: 10px;
            display: none;
        }
        .password-mismatch {
            color: red;
            font-size: 12px;
            margin-top: -10px;
            margin-bottom: 10px;
            display: none;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .code-section {
            display: flex;
            gap: 10px;
        }
        .code-section input {
            flex: 1;
        }
        .btn-send-code {
            width: auto;
            height: 45px;
            white-space: nowrap;
            padding: 12px 20px;
        }
        .timer {
            font-size: 12px;
            color: #d9534f;
            margin-top: -10px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .submit-btn {
            width: 100%;
        }
        .loading {
            display: none;
            color: #414e8dff;
            text-align: center;
            margin: 10px 0;
        }
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            display: none;
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
    </style>
</head>
<body>
<div class="container">
    <h2>Password Reset</h2>
    
    
   
    <div id="alertMessage" class="alert"></div>
    
    <form action="forgot_password.php" method="POST" id="resetPassForm">
        
        <label for="email">Email:</label>
        <div class="code-section">
            <input type="email" name="email" id="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <button type="button" id="sendCodeBtn" class="btn-send-code">Send Code</button>
        </div>
        <div id="timer" class="timer"></div>
        <div id="loading" class="loading">Sending code, please wait...</div>

        <label for="code">6-Digit Verification Code:</label>
        <input type="text" name="code" id="code" required maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">

        <label for="password">New Password:</label>
        <input type="password" name="password" id="password" required minlength="6">
        
        <label for="re_enter_password">Re-Enter Password:</label>
        <input type="password" name="re_enter_password" id="re_enter_password" required>
        <div id="passwordMatchMessage" class="password-match">Passwords match!</div>
        <div id="passwordMismatchMessage" class="password-mismatch">Passwords do not match!</div>

        <button type="submit" name="signup" id="submitBtn" class="submit-btn">Reset Password</button>
    </form>
    <p>Already have an account? <a href="login_admin.php">Login here</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const email = document.getElementById('email');
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    const timer = document.getElementById('timer');
    const loading = document.getElementById('loading');
    const alertMessage = document.getElementById('alertMessage');
    let countdown;

    // Real-time password matching validation
    const password = document.getElementById('password');
    const reEnterPassword = document.getElementById('re_enter_password');
    const matchMessage = document.getElementById('passwordMatchMessage');
    const mismatchMessage = document.getElementById('passwordMismatchMessage');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('resetPassForm');
    
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
        alertMessage.textContent = message;
        alertMessage.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
        alertMessage.style.display = 'block';
        
        setTimeout(() => {
            alertMessage.style.display = 'none';
        }, 5000);
    }

    
    email.addEventListener('input', function() {
        const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
        sendCodeBtn.disabled = !emailValid;
    });

    
    sendCodeBtn.addEventListener('click', function() {
        const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
        
        if (!emailValid) {
            showAlert('Please enter a valid email address before sending code.', 'error');
            return false;
        }
        
        
        loading.style.display = 'block';
        this.disabled = true;
        this.textContent = 'Sending...';
        
        
        const formData = new FormData();
        formData.append('ajax_send_code', 'true');
        formData.append('email', email.value);
        
        fetch('forgot_password.php', {
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

    
    form.addEventListener('submit', function(e) {
        if (password.value !== reEnterPassword.value) {
            e.preventDefault();
            showAlert('Passwords do not match. Please check your entries.', 'error');
            return false;
        }
    });

    
    validatePasswords();
    
    // If we have an active timer from previous submission, restart it
    <?php if (isset($_SESSION['code_expiry']) && time() < strtotime($_SESSION['code_expiry'])): ?>
        const timeLeft = Math.floor((<?php echo strtotime($_SESSION['code_expiry']); ?> - <?php echo time(); ?>) / 1000);
        if (timeLeft > 0) {
            startTimer(timeLeft);
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = 'Code Sent';
        }
    <?php endif; ?>
});
</script>
</body>
</html>