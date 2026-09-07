<?php
session_start();
include "../../config.php";
require_once "../../s3.php";

// Get user email from session
$email = $_SESSION['email'];

// Single query using JOIN to get declaration file
$sql = "SELECT df.declaration_file, df.date_signed, df.signed_by
        FROM declaration_form df 
        JOIN report r ON df.report_id = r.report_id 
        JOIN user u ON r.user_id = u.user_id 
        WHERE u.user_email = ? 
        ORDER BY df.report_id DESC 
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $filePath = $row['declaration_file'];
    $date_signed = $row['date_signed'];
    $signed_by = $row['signed_by'];

    // Hand the browser a short-lived pre-signed S3 link so the PDF opens inline
    $key = s3_key($filePath, 'declaration_form');
    $url = $key !== '' ? s3_presigned_url($key, false) : false;

    if ($url !== false && s3_exists($key)) {
        header('Location: ' . $url, true, 302);
        exit();
    } else {
        // If the object isn't in the bucket, show error page
        showErrorPage("PDF file not found in cloud storage: " . htmlspecialchars($filePath));
    }
} else {
    showErrorPage("No declaration form found for this user.");
}

$stmt->close();

function showErrorPage($message) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Declaration Form Not Found</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 48px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #002776ff;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fa-solid fa-file-exclamation"></i>
            </div>
            <h2>Declaration Form Not Available</h2>
            <p><?php echo $message; ?></p>
            <a href="user_homePage.php" class="btn">
                <i class="fa-solid fa-home"></i> Return to Home
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>