<?php

require '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

class KYC6Reminder {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function sendPendingReminders() {
        try {
            // Load email settings
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->load();
            
            // Get pending applications
            $pending_applications = $this->getPendingApplications();
            
            if (empty($pending_applications)) {
                return; // No pending applications, do nothing
            }
            
            // Get KYC6 admins
            $kyc6_admins = $this->getKYC6Admins();
            
            if (empty($kyc6_admins)) {
                return; // No KYC6 admins found
            }
            
            // Send email to each KYC6 admin
            foreach ($kyc6_admins as $admin) {
                $this->sendReminderEmail($admin, $pending_applications);
            }
            
        } catch (Exception $e) {
            error_log("KYC6 Reminder Error: " . $e->getMessage());
        }
    }
    
    private function getPendingApplications() {
        $sql = "SELECT 
                    r.report_id,
                    u.user_email,
                    u.user_type,
                    k.created_date,
                    DATEDIFF(CURDATE(), DATE(k.created_date)) as days_pending,
                    CASE 
                        WHEN u.user_type = 'student' THEN s.student_full_name
                        WHEN u.user_type = 'external_appointment' THEN e.external_full_name
                        WHEN u.user_type = 'company' THEN c.company_name
                    END as user_name
                FROM kyc6 k
                JOIN report r ON k.report_id = r.report_id
                JOIN user u ON r.user_id = u.user_id
                LEFT JOIN student s ON u.user_id = s.user_id
                LEFT JOIN external_appointment e ON u.user_id = e.user_id
                LEFT JOIN company c ON u.user_id = c.user_id
                WHERE k.status = 'Pending'
                AND k.created_date IS NOT NULL
                ORDER BY k.created_date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        $stmt->close();
        
        return $applications;
    }
    
    private function getKYC6Admins() {
        $sql = "SELECT admin_name, admin_email 
                FROM admin 
                WHERE admin_role = 'kyc6_admin' 
                AND admin_email IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
        $stmt->close();
        
        return $admins;
    }
    
    private function sendReminderEmail($admin, $applications) {
        try {
            $mail = new PHPMailer(true);
            
            // Email settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->SMTPDebug = 0;
            
            $mail->setFrom($_ENV['SMTP_USER'], 'UTP VCR System');
            $mail->addAddress($admin['admin_email'], $admin['admin_name']);
            
            $mail->isHTML(false); // Plain text email, not HTML
            $mail->Subject = "KYC6 Pending Applications Reminder - " . date('d/m/Y');
            
            // Simple plain text email
            $mail->Body = $this->generateEmailBody($applications);
            
            $mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateEmailBody($applications) {
        $email_body = "Dear KYC6 Admin,\n\n";
        $email_body .= "You have " . count($applications) . " pending KYC6 applications requiring verification.\n\n";
        
        $email_body .= "PENDING APPLICATIONS:\n";
        $email_body .= "=====================================================================================\n";
        
        foreach ($applications as $app) {
            $email_body .= "Application ID: " . $app['report_id'] . "\n";
            $email_body .= "User: " . $app['user_name'] . " (" . $app['user_type'] . ")\n";
            $email_body .= "Email: " . $app['user_email'] . "\n";
            $email_body .= "Submitted: " . date('d/m/Y', strtotime($app['created_date'])) . "\n";
            $email_body .= "Days Pending: " . $app['days_pending'] . " days\n";
            $email_body .= "------------------------------------------------------------------------------------\n";
        }
        
        $email_body .= "\nACTION REQUIRED:\n";
        $email_body .= "Please process these KYC6 verifications within 2-3 business days.\n\n";
        
        $email_body .= "Current Date: " . date('d/m/Y') . "\n\n";
        
        $email_body .= "Thank you,\n";
        $email_body .= "UTP VCR System\n";
        $email_body .= "This is an automated reminder.";
        
        return $email_body;
    }
}
?>