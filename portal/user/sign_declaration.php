<?php
error_reporting(E_ALL & ~E_DEPRECATED);
session_start();
include "../../config.php";

// Autoload Composer dependencies
require '../../vendor/autoload.php';

// Use Dompdf namespace
use Dompdf\Dompdf;
use Dompdf\Options;

$email = $_SESSION['email'];
$message = '';

// ===== 1. GET USER_ID AND CHECK FOR EXISTING DECLARATION =====
$user_stmt = $db->prepare("SELECT user_id FROM user WHERE user_email = ?");
$user_stmt->bind_param("s", $email);
$user_stmt->execute();
$userResult = $user_stmt->get_result();
$userRow = $userResult->fetch_assoc();
$user_id = $userRow['user_id'];
$user_stmt->close();

// ===== 2. GET LATEST REPORT_ID FOR THIS USER =====
$report_stmt = $db->prepare("SELECT report_id FROM report WHERE user_id = ? ORDER BY report_id DESC LIMIT 1");
$report_stmt->bind_param("i", $user_id);
$report_stmt->execute();
$reportCheck = $report_stmt->get_result();

$declarationExists = false;
$declarationVerified = null;
$canSign = true; // Default to allowing signature

if ($reportCheck && $reportCheck->num_rows > 0) {
    $reportRow = $reportCheck->fetch_assoc();
    $report_id = $reportRow['report_id'];
    
    // Check if declaration already exists for this report
    $declarationCheck = $db->prepare("SELECT declaration_file, declaration_verify FROM declaration_form WHERE report_id = ?");
    $declarationCheck->bind_param("i", $report_id);
    $declarationCheck->execute();
    $declarationResult = $declarationCheck->get_result();
    
    if ($declarationResult && $declarationResult->num_rows > 0) {
        $declarationRow = $declarationResult->fetch_assoc();
        $declarationExists = true;
        $declarationVerified = $declarationRow['declaration_verify'];
        
        // Allow re-signing ONLY if declaration was rejected (declaration_verify = 0)
        if ($declarationVerified === 0) {
            $canSign = true; // Can re-sign if rejected
        } else if ($declarationVerified === 1) {
            // Declaration is approved - CANNOT sign again
            $canSign = false;
            echo "<script>
                    alert('Your declaration form has already been approved and cannot be modified.');
                    window.location.href = 'user_homePage.php';
                  </script>";
            exit();
        } else {
            // Declaration is pending (NULL) - CANNOT sign again
            $canSign = false;
            echo "<script>
                    alert('You have already submitted your declaration form. Please wait for admin verification.');
                    window.location.href = 'user_homePage.php';
                  </script>";
            exit();
        }
    }
    $declarationCheck->close();
} else {
    $message = "Please create an application first by uploading your IC.";
}
$report_stmt->close();

// ===== 3. HANDLE FORM SUBMISSION =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && $canSign) {
    
    // Check all three checkboxes
    $confirm1 = isset($_POST['confirm1']) ? 1 : 0;
    $confirm2 = isset($_POST['confirm2']) ? 1 : 0;
    $confirm3 = isset($_POST['confirm3']) ? 1 : 0;
    $signed_by = trim($_POST['signed_by']);

    if ($confirm1 && $confirm2 && $confirm3 && !empty($signed_by)) {
        $current_date = date('Y-m-d H:i:s');
        $formatted_date = date('F j, Y');

        // Configure Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        // Create HTML content for PDF (your existing HTML content)
        $html = '
        <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            color: #000000;
            line-height: 1.3;
            margin: 15px;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0;
            color: #000000;
        }
        .header p {
            margin: 3px 0 0 0;
            font-size: 10pt;
            color: #000000;
        }
        h2 {
            font-size: 12pt;
            color: #000000;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        p {
            text-align: justify;
            margin: 8px 0;
        }
        ul {
            margin-left: 15px;
            padding-left: 0;
        }
        li {
            margin-bottom: 6px;
           
        }
        .confirmed {
            color: #27ae60;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 20px;
            border-top: 1px solid #000000;
            padding-top: 10px;
        }
        .signature-line {
            margin-top: 10px;
        }
        .footer {
            font-size: 7pt;
            color: #000000;
            border-top: 1px solid #000000;
            padding-top: 3px;
            margin-top: 15px;
            text-align: center;
        }
        .content-section {
            margin-bottom: 10px;
        }
        .compact-break {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SELF DECLARATION FORM</h1><br><br>
        <p>ITPSB - Universiti Teknologi PETRONAS</p>
    </div>

    <div class="content-section">
        <p>The ITPSB(UTP) Code of Conduct and Business Ethics ("CoBE") sets out the standards of behaviour and
        ethical conduct expected of each individual to whom the CoBE applies with respect to ethics and integrity 
        and relevant laws. As part of our commitment, ITPSB expects its counterparty including contractors, subcontractors,
        consultants, agents, representatives, job applicants, interns and others performing work or services for or on behalf
        of the Group to comply with all applicable laws and the relevant parts of the CoBE and any other relevant guidelines 
        or manual when performing such work or services.</p><br>

        <p>Therefore, before ITPSB engages a counterparty, we are obligated to conduct appropriate counterparty due diligence to 
        understand the background of our prospective counterparty to ensure that we are dealing with parties that subscribe to 
        the same values and ethical standards of integrity as ITPSB as well as a requirement for the counterparty to fill up this
        self-declaration form. The ITPSB Code of Conduct and Business Ethics is available at Governance (utp.edu.my).</p><br><br>
    </div>

    <div class="content-section">
        <h2>DECLARATION FORM</h2><br>

        <p>I hereby understand and acknowledge that ITPSB observes good business conduct and is committed to adhere to all laws 
        and regulations wherever it operates, guided by the ITPSB Code of Conduct and Business Ethics ("CoBE"). Accordingly, 
        I hereby confirm and declare that, to the best of my knowledge, that:–</p><br>

        <ul>
            <li><span class="confirmed">' . ($confirm1 ? '✓ ' : '') . '</span>I am not the target or subject of any sanctions.</li>
            <li><span class="confirmed">' . ($confirm2 ? '✓ ' : '') . '</span>I have not engaged in any conduct/activity that would result in breach of any sanctions or becoming a target or subject of sanctions.</li>
            <li><span class="confirmed">' . ($confirm3 ? '✓ ' : '') . '</span>I have not been the subject of any convictions, prosecutions, or pending investigations related to bribery, corruption, or money laundering regulations.</li>
        </ul>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            <p><strong>Signed by:</strong></p>
            <p style="margin-top: 3px;"></p>
            <p><strong>' . htmlspecialchars($signed_by) . '</strong></p>
        </div>

        <div class="signature-line">
            <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
            <p><strong>Date:</strong> ' . $formatted_date . '</p>
        </div>
    </div>

    <div class="footer">
        <p>This document was electronically generated and signed on ' . $current_date . '</p>
        <p>ITPSB UTP </p>
    </div>
</body>
</html>';

        // Load HTML content and render PDF
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // PDF content and filename
        $pdf_data = $dompdf->output();
        
        // Create unique filename
        $safeEmail = preg_replace('/[^a-zA-Z0-9]/', '', $email);
        $pdf_filename = 'declaration_' . $safeEmail . '_' . date('Ymd_His') . '.pdf';

        // CORRECT FOLDER: declaration_form
        $folder_path = '../../documents/declaration_form/';
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0755, true);
        }
        
        $file_path = $folder_path . $pdf_filename;

        // Save file to folder
        if (file_put_contents($file_path, $pdf_data)) {
            // Insert or Update into declaration_form table
            $relative_file_path = "declaration_form/" . $pdf_filename;
            
            if ($declarationExists) {
                // UPDATE existing record (re-sign after rejection)
                $sql = "UPDATE declaration_form SET date_signed = NOW(), signed_by = ?, declaration_file = ?, declaration_verify = NULL WHERE report_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssi", $signed_by, $relative_file_path, $report_id);
            } else {
                // INSERT new record
                $sql = "INSERT INTO declaration_form (report_id, date_signed, signed_by, declaration_file, declaration_verify) VALUES (?, NOW(), ?, ?, NULL)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iss", $report_id, $signed_by, $relative_file_path);
            }

            if ($stmt->execute()) {
                $message = "Declaration " . ($declarationExists ? "re-signed" : "submitted") . " successfully! PDF has been generated and stored.";
                
                // Trigger automatic download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $pdf_filename . '"');
                echo $pdf_data;
                exit();
            } else {
                $message = "Error saving to database: " . $db->error;
            }
            $stmt->close();
        } else {
            $message = "Error: Could not save PDF file.";
        }
    } else {
        $message = "Please confirm all statements and provide your name.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vendor Dashboard - Declaration Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
        :root{
            --sidebar-width: 250px;
            --sidebar-bg: #002776ff;
            --content-bg: #f5f6f7;
            --accent: #1A252F;
        }
        *{box-sizing:border-box}
        body {
            margin: 50px;
            font-family: Arial, Helvetica, sans-serif;
            background-color: var(--content-bg);
            transition: background-color .3s;
            min-height:100vh;
            overflow-x: hidden;
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

    .sidebar a i {
        width: 20px;
        text-align: center;
    }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin-left 0.3s;
            min-height: 100vh;
        }

        #toggle-btn {
            background: transparent;
            border: none;
            font-size: 22px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 6px;
        }

        body.sidebar-closed .sidebar { transform: translateX(calc(-1 * var(--sidebar-width))); }
        body.sidebar-closed .main-content { margin-left: 0; }

        .declaration-form-box {
            max-width: 1450px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .declaration-form ul {
            list-style: none;
            padding: 0;
        }
        .declaration-form li {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .declaration-form input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .declaration-form input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button[type="submit"] {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button[type="submit"]:hover { background: #0056b3; }

        .declaration-form p {
            max-width: 1400px;
            margin: 0;
            text-align: justify;
            line-height: 1.6;
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            white-space: normal;
        }

        .status-alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .status-rejected {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="sidebar">

        <!-- Logo with better styling -->
    <div class="logo-container" style="text-align: center; margin-bottom: 0px; padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2);">
        <img src="../../image/UTP2.png" alt="UTP Logo" style="max-width: 160px; height: auto;">
    </div>
        <h2>Customer Panel</h2>
        <a href="user_homePage.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="user_profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="view_declaration.php"><i class="fa-solid fa-pen-nib"></i> View Declaration Form</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main-content">
        <button id="toggle-btn"><i class="fa-solid fa-bars"></i></button>

        <div class="declaration-form">
            <h1>SELF DECLARATION FORM</h1>
            <div class="declaration-form">
            <p>The ITPSB(UTP) Code of Conduct and Business Ethics ("CoBE") sets out the standards of behaviour and
                ethical conduct expected of each individual to whom the CoBE applies with respect to ethics and integrity 
                and relevant laws. As part of our commitment, ITPSB expects its counterparty including contractors, subcontractors,
                consultants, agents, representatives, job applicants, interns and others performing work or services for or on behalf
                of the Group to comply with all applicable laws and the relevant parts of the CoBE and any other relevant guidelines 
                or manual when performing such work or services.</p>
            <br>
            <p>Therefore, before ITPSB engages a counterparty, we are obligated to conduct appropriate counterparty due diligence to 
                understand the background of our prospective counterparty to ensure that we are dealing with parties that subscribe to 
                the same values and ethical standards of integrity as ITPSB as well as a requirement for the counterparty to fill up this
                self-declaration form. The ITPSB Code of Conduct and Business Ethics is available at Governance (utp.edu.my).</p>

            <div class="declaration-form-box">
                <h2>Declaration Form</h2>

                <!-- Status Messages -->
                <?php if ($declarationExists): ?>
                    <?php if ($declarationVerified === 0): ?>
                        <div class="status-alert status-rejected">
                            <i class="fa-solid fa-exclamation-triangle"></i> 
                            <strong>Declaration Rejected</strong>
                            <p>Your previous declaration was rejected. Please review and re-sign the declaration form.</p>
                        </div>
                    <?php elseif ($declarationVerified === 1): ?>
                        <div class="status-alert status-approved">
                            <i class="fa-solid fa-check-circle"></i> 
                            <strong>Declaration Approved</strong>
                            <p>Your declaration has been approved. No further action required.</p>
                        </div>
                    <?php else: ?>
                        <div class="status-alert status-pending">
                            <i class="fa-solid fa-clock"></i> 
                            <strong>Declaration Pending</strong>
                            <p>Your declaration is awaiting admin verification. You can still update it if needed.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div style="padding: 15px; margin: 20px 0; border-radius: 5px; background: <?php echo strpos($message, 'successfully') !== false ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strpos($message, 'successfully') !== false ? '#155724' : '#721c24'; ?>; border: 1px solid <?php echo strpos($message, 'successfully') !== false ? '#c3e6cb' : '#f5c6cb'; ?>;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$declarationExists || $declarationVerified === 0 || $declarationVerified === null): ?>
                <form method="POST" id="declarationForm">
                    <p>I hereby understand and acknowledge that ITPSB observes good business conduct and is committed to adhere to all laws 
                        and regulations wherever it operates, guided by the ITPSB Code of Conduct and Business Ethics ("CoBE"). Accordingly, 
                        I hereby confirm and declare that, to the best of my knowledge, that:-</p>

                    <ul>
                        <li>
                            <input type="checkbox" name="confirm1" value="1" id="confirm1">
                            <label for="confirm1">I am not the target or subject of any sanctions.</label>
                        </li>
                        <li>
                            <input type="checkbox" name="confirm2" value="1" id="confirm2">
                            <label for="confirm2">I have not engaged in any conduct/activity that would result in breach of any sanctions or becoming a target or subject of sanctions.</label>
                        </li>
                        <li>
                            <input type="checkbox" name="confirm3" value="1" id="confirm3">
                            <label for="confirm3">I have not been the subject of any convictions, prosecutions, or pending investigations related to bribery, corruption, or money laundering regulations.</label>
                        </li>
                    </ul>

                    <label for="signed_by"><strong>Signed by:</strong></label>
                    <input type="text" name="signed_by" id="signed_by" placeholder="Enter your full name" required>

                    <p><strong>Date:</strong> <span id="date_display"></span></p>

                    <button type="submit">
                        <i class="fa-solid fa-file-pdf"></i> 
                        <?php echo $declarationExists ? 'Re-sign Declaration & Generate PDF' : 'Submit Declaration & Generate PDF'; ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    const today = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById("date_display").innerText = today.toLocaleDateString('en-GB', options);

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('declarationForm').addEventListener('submit', function(e) {
            const c1 = document.querySelector('input[name="confirm1"]').checked;
            const c2 = document.querySelector('input[name="confirm2"]').checked;
            const c3 = document.querySelector('input[name="confirm3"]').checked;
            const name = document.querySelector('#signed_by').value.trim();

            if (!c1 || !c2 || !c3 || name === '') {
                e.preventDefault();
                alert('Please tick all checkboxes and enter your name before submitting.');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Generating PDF...';
            submitBtn.disabled = true;
        });

        // TOGGLE BUTTON FUNCTION
        document.getElementById('toggle-btn').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-closed');
        });
    });
    </script>
</body>
</html>