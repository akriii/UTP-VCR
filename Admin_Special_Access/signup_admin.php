<?php

include('../config.php');
session_start();



if (isset($_SESSION['admin_id'])) {
    header("Location: homepage.php");
    exit();
}


if (!$db) {
    die("Database connection failed. Please check your config.php file.");
}


require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;


function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}



function sendVerificationCode($email, $code) {
    try {

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
        
        
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug (Level $level): $str");
        };
        

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
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    

    register_shutdown_function(function() {
        if (error_get_last()) {
            $error = error_get_last();
            if ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
                
                error_log("PHP Error in AJAX handler: " . $error['message']);
            }
        }
    });

    $email = trim($_POST['email']);
    $response = ['success' => false, 'message' => ''];

    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please enter a valid email address.';
            echo json_encode($response);
            exit();
        }

        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $_SESSION['verification_code'] = $verification_code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_expiry'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $mail_sent = sendVerificationCode($email, $verification_code);

        if ($mail_sent) {
            $response['success'] = true;
            $response['message'] = 'Verification code sent! Check your inbox and spam folder.';
        } else {
            $response['message'] = 'Failed to send verification code. Please try again.';
        }
    } catch (Exception $e) {
        error_log("Exception in AJAX handler: " . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again.';
    }

    echo json_encode($response);
    exit();
}



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $full_name = trim($_POST['full_name']?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password']?? '';
    $admin_role = $_POST['admin_role']?? '';
    $staff_id = trim($_POST['staff_id']?? '');
    $re_enter_password = $_POST['re_enter_password']?? '';
    $entered_code = trim($_POST['code']?? '');

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
        
        $check = mysqli_prepare($db, "SELECT admin_email FROM admin WHERE admin_email = ? 
                                  UNION SELECT request_email FROM request_admin WHERE request_email = ? AND request_status = 'pending'");
        if (!$check) {
            die("Prepare failed: " . mysqli_error($db));
        }
        
        
        mysqli_stmt_bind_param($check, "ss", $email, $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            echo "<script>alert('Email already registered or has a pending request. Please login or wait for approval.');</script>";
        } else {
            
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            
            $stmt = mysqli_prepare($db, "INSERT INTO request_admin (request_name, request_role, request_email, request_password,request_staff_id, request_status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            if (!$stmt) {
                die("Prepare failed: " . mysqli_error($db));
            }
            
            
            mysqli_stmt_bind_param($stmt, "sssss", $full_name, $admin_role, $email, $hashed, $staff_id);

            if (mysqli_stmt_execute($stmt)) {
                
                $request_id = mysqli_insert_id($db);
                
                
                $message = "$full_name has requested to become an admin.";
                $notification_stmt = mysqli_prepare($db, "UPDATE request_admin SET request_message = ? WHERE request_id = ?");
                if ($notification_stmt) {
                    mysqli_stmt_bind_param($notification_stmt, "si", $message, $request_id);
                    mysqli_stmt_execute($notification_stmt);
                    mysqli_stmt_close($notification_stmt);
                }
                
                
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['code_expiry']);
                
                echo "<script>alert('Your request has been sent for approval.'); window.location='login_admin.php';</script>";
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
    <title>Admin Sign Up</title>
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
    <h2>Admin Account Request</h2>
    
    <div class="info-box">
        <strong>Note:</strong> This is a request form. Your account will be activated after admin approval.
    </div>
    

    <div id="alertMessage" class="alert"></div>
    
    <form action="signup_admin.php" method="POST" id="signupForm">
        <label for="full_name">Full Name:</label>
        <input type="text" name="full_name" id="full_name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">

        <label for="email">Email:</label>
        <div class="code-section">
            <input type="email" name="email" id="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <button type="button" id="sendCodeBtn" class="btn-send-code">Send Code</button>
        </div>
        <div id="timer" class="timer"></div>
        <div id="loading" class="loading">Sending code, please wait...</div>

        <label for="code">6-Digit Verification Code:</label>
        <input type="text" name="code" id="code" required maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code">

        <label for="admin_role">Admin Role:</label>
        <select name="admin_role" id="admin_role" required>
        <option value="">Select Role</option>
        <option value="master_admin">Master Admin</option>
        <option value="kyc6_admin">KYC6 Admin</option>   
        </select>

        <label for="full_name">Staff ID:</label>
        <input type="text" name="staff_id" id="staff_id" required value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>">


        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required minlength="6">
        
        <label for="re_enter_password">Re-Enter Password:</label>
        <input type="password" name="re_enter_password" id="re_enter_password" required>
        <div id="passwordMatchMessage" class="password-match">Passwords match!</div>
        <div id="passwordMismatchMessage" class="password-mismatch">Passwords do not match!</div>

        <button type="submit" name="signup" id="submitBtn" class="submit-btn">Request Admin Access</button>
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

    // Enable/disable send code button based on email validity
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
        
        // Show loading and disable button
        loading.style.display = 'block';
        this.disabled = true;
        this.textContent = 'Sending...';
        
        // Send AJAX request
        const formData = new FormData();
        formData.append('ajax_send_code', 'true');
        formData.append('email', email.value);
        
        fetch('signup_admin.php?t=' + new Date().getTime(), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            
            if (data.success) {
                showAlert(data.message, 'success');
                sendCodeBtn.textContent = 'Code Sent';
                // Start timer for 10 minutes
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
        if (password.value !== reEnterPassword.value) {
            e.preventDefault();
            showAlert('Passwords do not match. Please check your entries.', 'error');
            return false;
        }
    });

    
    validatePasswords();
    
    
      <?php if (isset($_SESSION['code_expiry']) && time() < strtotime($_SESSION['code_expiry'])) { ?>
        const timeLeft = Math.floor((<?php echo strtotime($_SESSION['code_expiry']); ?> - <?php echo time(); ?>) / 1000);
        if (timeLeft > 0) {
            startTimer(timeLeft);
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = 'Code Sent';
        }
    <?php } ?>
});
</script>
</body>
</html>