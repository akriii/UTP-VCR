<?php
ob_start();
// Enable error reporting to see what is breaking
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // This forces SQL errors to show up

include('../../config.php');
session_start();


// Import required classes
require '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;


// Handle AJAX Send Code request - THIS MUST BE AT THE TOP
if (isset($_POST['ajax_send_code'])) {
    $email = trim($_POST['email']);
    $response = ['success' => false, 'message' => ''];
    
    error_log("=== AJAX REQUEST RECEIVED ===");
    error_log("Email: $email");
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        error_log("❌ Invalid email format");
    } else {
        // Check if email already exists
        $check = mysqli_prepare($db, "SELECT user_email FROM user WHERE user_email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $response['message'] = 'Email already registered. Please login.';
            error_log("❌ Email already exists");
        } else {
            // Generate and store verification code
            $verification_code = generateVerificationCode();
            $expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store code in session
            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['verification_email'] = $email;
            $_SESSION['code_expiry'] = $expiry_time;
            
            error_log("Generated code: $verification_code");
            
            // Send verification email
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
        mysqli_stmt_close($check);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// If the user is already logged in, redirect them
if (isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

// Check if database connection exists
if (!$db) {
    die("Database connection failed. Please check your config.php file.");
}



// Function to generate 6-digit code
function generateVerificationCode() {
    return sprintf("%06d", mt_rand(0, 999999));
}

// Function to send verification email
function sendVerificationCode($email, $code) {
    try {
        // Load environment variables 
         $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->safeLoad();


        $mail = new PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Disable debug for production
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


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $user_type = trim($_POST['userType']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $re_enter_password = $_POST['re_enter_password'];
    $entered_code = trim($_POST['code']);

    // Validate verification code
    if (!isset($_SESSION['verification_code']) || !isset($_SESSION['verification_email'])) {
        $error = 'Please request a verification code first.';
    } elseif ($_SESSION['verification_email'] !== $email) {
        $error = 'Email does not match the one we sent the code to.';
    } elseif (time() > strtotime($_SESSION['code_expiry'])) {
        $error = 'Verification code has expired. Please request a new one.';
    } elseif ($_SESSION['verification_code'] !== $entered_code) {
        $error = 'Invalid verification code. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $re_enter_password) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        // Check if email already exists
        $check = mysqli_prepare($db, "SELECT user_email FROM user WHERE user_email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = 'Email already registered. Please login.';
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            mysqli_begin_transaction($db);
            
            try {
                // Insert into user table
                $user_stmt = mysqli_prepare($db, "INSERT INTO user (user_type, user_email, user_password) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($user_stmt, "sss", $user_type, $email, $hashed_password);
                
                if (!mysqli_stmt_execute($user_stmt)) {
                    throw new Exception("User insertion failed: " . mysqli_stmt_error($user_stmt));
                }
                
                $user_id = mysqli_insert_id($db);
                mysqli_stmt_close($user_stmt);
                
                // Insert into specific user type table
                if ($user_type === 'student') {
                    $student_full_name = trim($_POST['student_full_name']);
                    $student_id = trim($_POST['student_id']);
                    $programme = trim($_POST['programme']);
                    
                    $student_stmt = mysqli_prepare($db, "INSERT INTO student (user_id, student_full_name, programme, student_id) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($student_stmt, "isss", $user_id, $student_full_name, $programme, $student_id);
                    
                    if (!mysqli_stmt_execute($student_stmt)) {
                        throw new Exception("Student insertion failed: " . mysqli_stmt_error($student_stmt));
                    }
                    mysqli_stmt_close($student_stmt);
                    
                } elseif ($user_type === 'company') {
                    $company_name = trim($_POST['company_name']);
                    $ssm_number = trim($_POST['ssm_number']);
                    
                    $company_stmt = mysqli_prepare($db, "INSERT INTO company (user_id, company_name, ssm_number) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($company_stmt, "iss", $user_id, $company_name, $ssm_number);
                    
                    if (!mysqli_stmt_execute($company_stmt)) {
                        throw new Exception("Company insertion failed: " . mysqli_stmt_error($company_stmt));
                    }
                    mysqli_stmt_close($company_stmt);
                    
                } elseif ($user_type === 'external_appointment') {
                    $external_full_name = trim($_POST['external_full_name']);
                    $external_company_name = trim($_POST['external_company_name']);
                    $position = trim($_POST['position']);
                    $ae_name = trim($_POST['ae_name']);
                    
                    $external_stmt = mysqli_prepare($db, "INSERT INTO external_appointment (user_id, external_full_name, external_company_name, position, ae_name) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($external_stmt, "issss", $user_id, $external_full_name, $external_company_name, $position, $ae_name);
                    
                    if (!mysqli_stmt_execute($external_stmt)) {
                        throw new Exception("External appointment insertion failed: " . mysqli_stmt_error($external_stmt));
                    }
                    mysqli_stmt_close($external_stmt);
                }
                
                // Commit transaction
                mysqli_commit($db);
                
                // Clear verification session
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['code_expiry']);
                
                $success = 'Registration successful! You can now login.';
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($db);
                $error = 'Error: ' . $e->getMessage();
            }
        }
        mysqli_stmt_close($check);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>User Registration</title>
   <style>
    body {

    font-family: Arial, sans-serif;
    background: #f5f5f5;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh; /* Changed from height to min-height */
    margin: 0;
    padding: 20px; /* Added padding for mobile */
}
.container {
    width: 450px; /* Increased from 360px to 400px */
    background: #fff;
    padding: 25px; /* Slightly increased padding */
    border-radius: 8px;
    box-shadow: 0 0 12px rgba(0,0,0,0.1);
    max-width: 90vw; /* Prevent overflow on mobile */
    box-sizing: border-box; /* Include padding in width */
}
h2 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 20px; /* Slightly smaller */
    color: #333;
    line-height: 1.3; /* Better text wrapping */
}
input, select {
    width: 100%;
    padding: 10px;
    margin-bottom: 14px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    box-sizing: border-box; /* Important for width calculation */
    max-width: 100%; /* Prevent overflow */
}
button {
    padding: 10px; /* Reduced from 12px */
    background: #414e8dff;
    color: white;
    border: none;
    border-radius: 5px; /* Reduced from 6px */
    font-size: 14px; /* Reduced from 16px */
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
    font-size: 12px; /* Reduced from 14px */
    margin-top: 8px; /* Reduced from 10px */
}
a {
    color: #414e8dff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
label {
    font-size: 12px; /* Reduced from 14px */
    font-weight: bold;
    color: #333;
    display: block;
    margin-bottom: 4px; /* Reduced from 5px */
}
.password-match {
    color: green;
    font-size: 11px; /* Reduced from 12px */
    margin-top: -8px; /* Reduced from -10px */
    margin-bottom: 8px; /* Reduced from 10px */
    display: none;
}
.password-mismatch {
    color: red;
    font-size: 11px; /* Reduced from 12px */
    margin-top: -8px; /* Reduced from -10px */
    margin-bottom: 8px; /* Reduced from 10px */
    display: none;
}
.info-box {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 5px; /* Reduced from 6px */
    padding: 12px; /* Reduced from 15px */
    margin-bottom: 16px; /* Reduced from 20px */
    font-size: 12px; /* Reduced from 14px */
}
.code-section {
    display: flex;
    gap: 8px; /* Reduced from 10px */
}
.code-section input {
    flex: 1;
}
.btn-send-code {
    width: auto;
    height: 40px; /* Reduced from 45px */
    white-space: nowrap;
    padding: 10px 16px; /* Reduced from 12px 20px */
}
.timer {
    font-size: 11px; /* Reduced from 12px */
    color: #d9534f;
    margin-top: -8px; /* Reduced from -10px */
    margin-bottom: 12px; /* Reduced from 15px */
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
    margin: 8px 0; /* Reduced from 10px 0 */
}
.alert {
    padding: 8px; /* Reduced from 10px */
    margin: 8px 0; /* Reduced from 10px 0 */
    border-radius: 4px; /* Reduced from 5px */
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
.user-type-form {
    display: none;
}
.user-type-form.active {
    display: block;
    animation: fadeIn 0.5s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
    </style>

</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 10px;">
        <img src="../../image/UTP.png" alt="UTP Logo" style="max-width: 200px; height: auto;">
    </div>
        <h2>UTP VCR User Registration</h2>
        <div class="info-box"><strong>Note:</strong> Please select your user type and complete the registration form.</div>
        <div id="alertMessage" class="alert"></div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success" style="display:block;"><?php echo htmlspecialchars($success); ?></div>
            <script>setTimeout(function(){ window.location.href = 'login.php'; }, 3000);</script>
        <?php endif; ?>

        <form action="signUp.php" method="POST" id="signupForm" novalidate>
            <!-- User Type Selection -->
            <label for="userType">User Type:</label>
            <select id="userType" name="userType" required>
                <option value="">-- Select User Type --</option>
                <option value="student" <?php echo (isset($_POST['userType']) && $_POST['userType'] == 'student') ? 'selected' : ''; ?>>Student</option>
                <option value="external_appointment" <?php echo (isset($_POST['userType']) && $_POST['userType'] == 'external_appointment') ? 'selected' : ''; ?>>External Appointment</option>
                <option value="company" <?php echo (isset($_POST['userType']) && $_POST['userType'] == 'company') ? 'selected' : ''; ?>>Company</option>
            </select>

            <!-- Student Form (removed static required attributes; JS will set required) -->
            <div id="studentForm" class="user-type-form">
                <label for="studentFullName">Full Name:</label>
                <input type="text" name="student_full_name" id="studentFullName" value="<?php echo isset($_POST['student_full_name']) ? htmlspecialchars($_POST['student_full_name']) : ''; ?>">

                <label for="studentId">Student ID:</label>
                <input type="text" name="student_id" id="studentId" maxlength="10" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">

                <label for="studentProgramme">Programme:</label>
                <select name="programme" id="studentProgramme" required>
                <option value="">-- Select Programme --</option>
                <option value="Bachelor of Computer Engineering (Hons)">Bachelor of Computer Engineering (Hons)</option>
                <option value="Bachelor of Electrical & Electronics Engineering (Hons)">Bachelor of Electrical & Electronics Engineering (Hons)</option>
                <option value="Bachelor of Materials Engineering (Hons)">Bachelor of Materials Engineering (Hons)</option>
                <option value="Bachelor of Mechanical Engineering (Hons)">Bachelor of Mechanical Engineering (Hons)</option>
                <option value="Bachelor of Petroleum Engineering (Hons)">Bachelor of Petroleum Engineering (Hons)</option>
                <option value="Bachelor of Science (Hons) Applied Chemistry">Bachelor of Science (Hons) Applied Chemistry</option>
                <option value="Bachelor of Science (Hons) Petroleum Geoscience">Bachelor of Science (Hons) Petroleum Geoscience</option>
                <option value="Bachelor of Integrated Engineering(Hons)">Bachelor of Integrated Engineering(Hons)</option>
                <option value="Bachelor of Information Technology (Hons)">Bachelor of Information Technology (Hons)</option>
                <option value="Bachelor in Business Management (Hons)">Bachelor in Business Management (Hons)</option>
                <option value="Bachelor of Computer Science (Hons)">Bachelor of Computer Science (Hons)</option>
                <option value="Bachelor of Information Systems (Hons)">Bachelor of Information Systems (Hons)</option>
                
    
</select>

            </div>

            <!-- Company Form -->
            <div id="companyForm" class="user-type-form">
                <label for="companyName">Company Name:</label>
                <input type="text" name="company_name" id="companyName" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">

                <label for="ssmNumber">SSM Number:</label>
                <input type="text" name="ssm_number" id="ssmNumber" maxlength="20" value="<?php echo isset($_POST['ssm_number']) ? htmlspecialchars($_POST['ssm_number']) : ''; ?>">
            </div>

            <!-- External Appointment Form -->
            <div id="externalForm" class="user-type-form">
                <label for="externalFullName">Full Name:</label>
                <input type="text" name="external_full_name" id="externalFullName" value="<?php echo isset($_POST['external_full_name']) ? htmlspecialchars($_POST['external_full_name']) : ''; ?>">

                <label for="externalCompanyName">Company Name:</label>
                <input type="text" name="external_company_name" id="externalCompanyName" value="<?php echo isset($_POST['external_company_name']) ? htmlspecialchars($_POST['external_company_name']) : ''; ?>">

                <label for="externalPosition">Position:</label>
                <input type="text" name="position" id="externalPosition" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">

                <label for="aeName">Select AE Admin:</label>
                <select id="aeName" name="ae_name">
                    <option value="">-- Select AE Admin --</option>
                    <?php
                    $ae_query = "SELECT admin_id, admin_name FROM admin WHERE admin_role = 'ae_admin'";
                    $ae_result = mysqli_query($db, $ae_query);
                    while ($ae_admin = mysqli_fetch_assoc($ae_result)) {
                        $selected = (isset($_POST['ae_name']) && $_POST['ae_name'] == $ae_admin['admin_name']) ? 'selected' : '';
                        echo "<option value='".htmlspecialchars($ae_admin['admin_name'])."' $selected>".htmlspecialchars($ae_admin['admin_name'])."</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Email Verification Section -->
            <label for="email">Email:</label>
            <div class="code-section">
                <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                <button type="button" id="sendCodeBtn" class="btn-send-code" disabled>Send Code</button>
            </div>
            <div id="timer" class="timer"></div>
            <div id="loading" class="loading">Sending code, please wait...</div>

            <label for="code">6-Digit Verification Code:</label>
            <input type="text" name="code" id="code" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit code" value="<?php echo isset($_POST['code']) ? htmlspecialchars($_POST['code']) : ''; ?>">

            <label for="password">Password:</label>
            <input type="password" name="password" id="password" minlength="6" maxlength="20">

            <label for="re_enter_password">Re-Enter Password:</label>
            <input type="password" name="re_enter_password" id="re_enter_password">
            <div id="passwordMatchMessage" class="password-match">Passwords match!</div>
            <div id="passwordMismatchMessage" class="password-mismatch">Passwords do not match!</div>

            <button type="submit" name="signup" id="submitBtn" class="submit-btn">Sign Up</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('userType');
            const studentForm = document.getElementById('studentForm');
            const companyForm = document.getElementById('companyForm');
            const externalForm = document.getElementById('externalForm');
            const email = document.getElementById('email');
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            const timer = document.getElementById('timer');
            const loading = document.getElementById('loading');
            const alertMessage = document.getElementById('alertMessage');
            const password = document.getElementById('password');
            const reEnterPassword = document.getElementById('re_enter_password');
            const matchMessage = document.getElementById('passwordMatchMessage');
            const mismatchMessage = document.getElementById('passwordMismatchMessage');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('signupForm');

            let countdown;

            // Set required attributes dynamically (so hidden fields don't block validation)
            function setRequiredForActive(userType) {
                // Clear all required
                const allInputs = document.querySelectorAll('#studentForm input, #companyForm input, #externalForm input, #externalForm select');
                allInputs.forEach(el => el.required = false);

                if (userType === 'student') {
                    document.getElementById('studentFullName').required = true;
                    document.getElementById('studentId').required = true;
                    document.getElementById('studentProgramme').required = true;
                } else if (userType === 'company') {
                    document.getElementById('companyName').required = true;
                    document.getElementById('ssmNumber').required = true;
                } else if (userType === 'external_appointment') {
                    document.getElementById('externalFullName').required = true;
                    document.getElementById('externalCompanyName').required = true;
                    document.getElementById('externalPosition').required = true;
                    document.getElementById('aeName').required = true;
                }
            }

            // Show/hide user type forms based on selection
            function updateUserForms() {
                studentForm.classList.remove('active');
                companyForm.classList.remove('active');
                externalForm.classList.remove('active');

                const val = userTypeSelect.value;
                if (val === 'student') {
                    studentForm.classList.add('active');
                } else if (val === 'company') {
                    companyForm.classList.add('active');
                } else if (val === 'external_appointment') {
                    externalForm.classList.add('active');
                }
                setRequiredForActive(val);
            }

            userTypeSelect.addEventListener('change', updateUserForms);
            updateUserForms(); // init

            // Real-time password matching validation
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
                    const secondsVal = timeLeft % 60;
                    const seconds = secondsVal.toString().padStart(2, '0');
                    timer.textContent = `Code expires in: ${minutes}:${seconds}`;
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
                setTimeout(() => { alertMessage.style.display = 'none'; }, 5000);
            }

            // Enable/disable send code button based on email validity
            function updateSendCodeState() {
                const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
                sendCodeBtn.disabled = !emailValid;
            }
            email.addEventListener('input', updateSendCodeState);
            updateSendCodeState();

            // Handle Send Code button with AJAX
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

                fetch('signUp.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    loading.style.display = 'none';
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        sendCodeBtn.textContent = 'Code Sent';
                        sendCodeBtn.disabled = true;
                        startTimer(600); // 10 minutes
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
                // client-side checks
                if (!userTypeSelect.value) {
                    e.preventDefault();
                    showAlert('Please select a user type.', 'error');
                    return false;
                }

                // Let HTML5 validation run for required fields we've set dynamically.
                // Custom validation for password match:
                if (password.value !== reEnterPassword.value) {
                    e.preventDefault();
                    showAlert('Passwords do not match. Please check your entries.', 'error');
                    return false;
                }
            });

            // If we have an active timer from previous submission, restart it
            <?php if (isset($_SESSION['code_expiry']) && time() < strtotime($_SESSION['code_expiry'])): ?>
                (function(){
                    const timeLeft = Math.floor(<?php echo strtotime($_SESSION['code_expiry']); ?> - <?php echo time(); ?>);
                    if (timeLeft > 0) {
                        startTimer(timeLeft);
                        sendCodeBtn.disabled = true;
                        sendCodeBtn.textContent = 'Code Sent';
                    }
                })();
            <?php endif; ?>
        });
    </script>
</body>
</html>
