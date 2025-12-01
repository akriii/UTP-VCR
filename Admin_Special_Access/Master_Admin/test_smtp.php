<?php
// test_smtp2.php - Test different SMTP configurations
echo "<h2>Testing Different SMTP Configurations</h2>";
echo "<pre>";

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$configs = [
    [
        'name' => 'TLS on Port 587',
        'secure' => PHPMailer::ENCRYPTION_STARTTLS,
        'port' => 587,
        'host' => 'smtp.gmail.com'
    ],
    [
        'name' => 'SSL on Port 465',
        'secure' => PHPMailer::ENCRYPTION_SMTPS,
        'port' => 465,
        'host' => 'smtp.gmail.com'
    ],
    [
        'name' => 'TLS with IP Address',
        'secure' => PHPMailer::ENCRYPTION_STARTTLS,
        'port' => 587,
        'host' => '173.194.76.108' // Gmail's IP
    ],
    [
        'name' => 'Alternative Host',
        'secure' => PHPMailer::ENCRYPTION_STARTTLS,
        'port' => 587,
        'host' => 'smtp.gmail.com',
        'options' => [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]
    ]
];

foreach ($configs as $config) {
    echo "\n🔧 Testing: {$config['name']}\n";
    echo "Host: {$config['host']}, Port: {$config['port']}, Secure: {$config['secure']}\n";
    
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = $config['secure'];
    $mail->Port = $config['port'];
    $mail->Timeout = 10;
    
    if (isset($config['options'])) {
        $mail->SMTPOptions = $config['options'];
    }
    
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "  [$level]: $str\n";
    };
    
    try {
        $mail->setFrom($_ENV['SMTP_USER'], 'Test');
        $mail->addAddress($_ENV['SMTP_USER']);
        $mail->Subject = "Test: {$config['name']}";
        $mail->Body = "Testing {$config['name']}";
        
        if ($mail->send()) {
            echo "  ✅ SUCCESS: Connected and sent!\n";
            break; // Stop if one works
        }
    } catch (Exception $e) {
        echo "  ❌ FAILED: " . $e->getMessage() . "\n";
    }
    echo "----------------------------------------\n";
}

echo "</pre>";
?>