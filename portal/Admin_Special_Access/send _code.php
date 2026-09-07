<?php

session_start();
require '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

function sendVerificationCode($email, $code) {
    try {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        $mail = new PHPMailer(true);
        
        // SMTP configuration 
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port = 587; 
        
        
        $mail->SMTPDebug = 2; 
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

        error_log("=== EMAIL DEBUG ===");
        error_log("SMTP User: " . $_ENV['SMTP_USER']);
        error_log("Sending to: $email");
        error_log("Code: $code");
        
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Email sent successfully!");
        } else {
            error_log("❌ Email sending failed!");
            error_log("Error Info: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer Exception: " . $e->getMessage());
        return false;
    }
}


if (isset($_SESSION['verification_email']) && isset($_SESSION['verification_code'])) {
    $email = $_SESSION['verification_email'];
    $code = $_SESSION['verification_code'];
    
    error_log("Session data - Email: $email, Code: $code");
    
    
    $result = sendVerificationCode($email, $code);
    error_log("Final result: " . ($result ? 'true' : 'false'));
    return $result;
} else {
    error_log("❌ No verification data found in session");
    error_log("Session verification_email: " . (isset($_SESSION['verification_email']) ? $_SESSION['verification_email'] : 'NOT SET'));
    error_log("Session verification_code: " . (isset($_SESSION['verification_code']) ? $_SESSION['verification_code'] : 'NOT SET'));
    return false;
}
?>