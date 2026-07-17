<?php
session_start();
include "../../config.php";

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$user_type = $_SESSION['user_type'];
$message = "";

// ===== Fetch User Info Based on User Type =====
$sql = "SELECT u.user_id, u.user_type, u.user_email, u.ic_no 
        FROM user u 
        WHERE u.user_email = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ===== Fetch User Type Specific Information =====
$user_info = null;
if ($user_type === 'student') {
    $info_sql = "SELECT student_full_name, programme, student_id FROM student WHERE user_id = ?";
} elseif ($user_type === 'external_appointment') {
    $info_sql = "SELECT external_full_name, external_company_name, position, ae_name FROM external_appointment WHERE user_id = ?";
} elseif ($user_type === 'company') {
    $info_sql = "SELECT company_name, ssm_number FROM company WHERE user_id = ?";
}

$info_stmt = $db->prepare($info_sql);
$info_stmt->bind_param("i", $user['user_id']);
$info_stmt->execute();
$info_result = $info_stmt->get_result();
$user_info = $info_result->fetch_assoc();
$info_stmt->close();

// ===== Handle Update Request =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ic_no = trim($_POST['ic_no']);
    
    // Update user table
    $update_user = $db->prepare("UPDATE user SET ic_no = ? WHERE user_email = ?");
    $update_user->bind_param("ss", $ic_no, $email);
    
    // Update user type specific table
    if ($user_type === 'student') {
        $student_full_name = trim($_POST['student_full_name']);
        $programme = trim($_POST['programme']);
        
        $update_specific = $db->prepare("UPDATE student SET student_full_name = ?, programme = ? WHERE user_id = ?");
        $update_specific->bind_param("ssi", $student_full_name, $programme, $user['user_id']);
        
    } elseif ($user_type === 'external_appointment') {
        $external_full_name = trim($_POST['external_full_name']);
        $external_company_name = trim($_POST['external_company_name']);
        $position = trim($_POST['position']);
        $ae_name = trim($_POST['ae_name']);
        
        $update_specific = $db->prepare("UPDATE external_appointment SET external_full_name = ?, external_company_name = ?, position = ?, ae_name = ? WHERE user_id = ?");
        $update_specific->bind_param("ssssi", $external_full_name, $external_company_name, $position, $ae_name, $user['user_id']);
        
    } elseif ($user_type === 'company') {
        $company_name = trim($_POST['company_name']);
        $ssm_number = trim($_POST['ssm_number']);
        
        $update_specific = $db->prepare("UPDATE company SET company_name = ?, ssm_number = ? WHERE user_id = ?");
        $update_specific->bind_param("ssi", $company_name, $ssm_number, $user['user_id']);
    }
    
    // Execute updates
    $user_updated = $update_user->execute();
    $specific_updated = $update_specific->execute();
    
    if ($user_updated || $specific_updated) {
        $message = "✅ Profile updated successfully!";
        
        // Refetch updated user info
        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Refetch updated specific info
        $info_stmt = $db->prepare($info_sql);
        $info_stmt->bind_param("i", $user['user_id']);
        $info_stmt->execute();
        $info_result = $info_stmt->get_result();
        $user_info = $info_result->fetch_assoc();
        $info_stmt->close();
        
        // Update session fullname
        if ($user_type === 'student') {
            $_SESSION['fullname'] = $user_info['student_full_name'];
        } elseif ($user_type === 'external_appointment') {
            $_SESSION['fullname'] = $user_info['external_full_name'];
        } elseif ($user_type === 'company') {
            $_SESSION['fullname'] = $user_info['company_name'];
        }
        
    } else {
        $message = "❌ Error updating profile.";
    }
    
    $update_user->close();
    $update_specific->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
  :root{
      --sidebar-width: 250px;
      --sidebar-bg: #002776ff;
      --content-bg: #f5f6f7;
      --accent: #1A252F;
    }
    *{box-sizing:border-box}
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
        padding: 30px 20px; 
        transition: margin-left 0.3s ease; /* Add transition */
        min-height: 100vh;
    }

    /* ADD THESE CSS RULES FOR TOGGLE FUNCTIONALITY */
    body.sidebar-closed .sidebar {
        transform: translateX(calc(-1 * var(--sidebar-width)));
    }
    
    body.sidebar-closed .main-content {
        margin-left: 0;
    }

    .profile-card {
        background: white;
        max-width: 600px;
        margin: 60px auto;
        margin-top: 5px;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        text-align: center;
    }
    .avatar {
        width: 100px; height: 100px; background: #ddd;
        border-radius: 50%; margin: 0 auto 15px;
        display: flex; align-items: center; justify-content: center;
        font-size: 40px; color: #777;
    }
    .profile-card h2 { margin: 10px 0; }
    .profile-card p { color: #666; margin-bottom: 20px; }
    .profile-card form { text-align: left; margin-top: 20px; }
    .profile-card label { display: block; margin-top: 12px; margin-bottom: 5px; font-weight: bold; }
    .profile-card input, .profile-card select {
        width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;
        font-size: 14px;
    }
    .profile-card button {
        width: 100%; padding: 12px; margin-top: 20px;
        background: #3498db; border: none; color: white; border-radius: 8px; cursor: pointer;
        font-size: 16px;
    }
    .profile-card button:hover { background: #2980b9; }
    .message { 
        margin-top: 15px; padding: 10px; 
        border-radius: 8px; text-align:center; 
        font-weight: bold;
    }
    .success { background: #e8f5e9; color: #2e7d32; }
    .error { background: #ffebee; color: #c62828; }

    .user-type-badge {
        background: #002776ff;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        margin-left: 10px;
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
    #toggle-btn:focus { outline: 2px solid rgba(0,0,0,0.08); }

    .upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #002776ff 0%, #001a5e 100%);
    color: #fff;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
    font-weight: 600;
}

.upload-btn:hover {
    background: linear-gradient(135deg, #001a5e 0%, #000d3d 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 39, 118, 0.3);
}

    /* Responsive design for mobile */
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
    }

    </style>
</head>
<body>
    <div class="sidebar">
         <div class="logo-container" style="text-align: center; margin-bottom: 0px; padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2);">
        <img src="../../image/UTP2.png" alt="UTP Logo" style="max-width: 160px; height: auto;">
    </div>
        <h2>Customer Panel</h2>
        <a href="user_homePage.php"><i class="fa-solid fa-house"></i> Home</a>
        <a href="view_declaration.php"><i class="fa-solid fa-pen-nib"></i> View Declaration Form</a>
        <a href="user_profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main-content">
    

        <button id="toggle-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="profile-card">
              <div style="text-align: center; margin-bottom: 0px;">
        <img src="../../image/logo.png" alt="UTP Logo" style="max-width: 200px; height: auto;">
    </div>
            <h2>
                <?php 
                if ($user_type === 'student') {
                    echo htmlspecialchars($user_info['student_full_name'] ??'');
                } elseif ($user_type === 'external_appointment') {
                    echo htmlspecialchars($user_info['external_full_name']??'');
                } elseif ($user_type === 'company') {
                    echo htmlspecialchars($user_info['company_name']??'');
                }
                ?>
                <span class="user-type-badge"><?php echo ucfirst($user_type); ?></span>
            </h2>
            <p><?php echo htmlspecialchars($user['user_email']??''); ?></p>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Common Fields for All User Types -->
                <label>Email</label>
                <input type="email" value="<?php echo htmlspecialchars($user['user_email']??''); ?>" disabled>
                <small style="color: #666;">Email cannot be changed</small>

                <label>IC Number</label>
                <input type="text" name="ic_no" value="<?php echo htmlspecialchars($user['ic_no']??''); ?>" disabled>
                <small style="color: #666;">IC cannot be changed</small>
                <!-- User Type Specific Fields -->
                <?php if ($user_type === 'student'): ?>
                    <label>Full Name</label>
                    <input type="text" name="student_full_name" value="<?php echo htmlspecialchars($user_info['student_full_name']??''); ?>" required>

                    <label>Student ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['student_id']??''); ?>" disabled>
                    <small style="color: #666;">Student ID cannot be changed</small>

                    <label>Programme</label>
                    <select name="programme" required>
                        <option value="Bachelor of Computer Engineering (Hons)" <?php if ($user_info['programme'] == "Bachelor of Computer Engineering (Hons)") echo 'selected'; ?>>Bachelor of Computer Engineering (Hons)</option>
                        <option value="Bachelor of Electrical & Electronics Engineering (Hons)" <?php if ($user_info['programme'] == "Bachelor of Electrical & Electronics Engineering (Hons)") echo 'selected'; ?>>Bachelor of Electrical & Electronics Engineering (Hons)</option>
                        <option value="Bachelor of Materials Engineering (Hons)" <?php if ($user_info['programme'] == "Bachelor of Materials Engineering (Hons)") echo 'selected'; ?>>Bachelor of Materials Engineering (Hons)</option>
                        <option value="Bachelor of Mechanical Engineering (Hons)" <?php if ($user_info['programme'] == "Bachelor of Mechanical Engineering (Hons)") echo 'selected'; ?>>Bachelor of Mechanical Engineering (Hons)</option>
                        <option value="Bachelor of Petroleum Engineering (Hons)" <?php if ($user_info['programme'] == "Bachelor of Petroleum Engineering (Hons)") echo 'selected'; ?>>Bachelor of Petroleum Engineering (Hons)</option>
                        <option value="Bachelor of Science (Hons) Applied Chemistry" <?php if ($user_info['programme'] == "Bachelor of Science (Hons) Applied Chemistry") echo 'selected'; ?>>Bachelor of Science (Hons) Applied Chemistry</option>
                        <option value="Bachelor of Science (Hons) Petroleum Geoscience" <?php if ($user_info['programme'] == "Bachelor of Science (Hons) Petroleum Geoscience") echo 'selected'; ?>>Bachelor of Science (Hons) Petroleum Geoscience</option>
                        <option value="Bachelor of Integrated Engineering(Hons)" <?php if ($user_info['programme'] == "Bachelor of Integrated Engineering(Hons)") echo 'selected'; ?>>Bachelor of Integrated Engineering(Hons)</option>
                        <option value="Bachelor of Information Technology (Hons)" <?php if ($user_info['programme'] == "Bachelor of Information Technology (Hons)") echo 'selected'; ?>>Bachelor of Information Technology (Hons)</option>
                        <option value="Bachelor in Business Management (Hons)" <?php if ($user_info['programme'] == "Bachelor in Business Management (Hons)") echo 'selected'; ?>>Bachelor in Business Management (Hons)</option>
                        <option value="Bachelor of Computer Science (Hons)" <?php if ($user_info['programme'] == "Bachelor of Computer Science (Hons)") echo 'selected'; ?>>Bachelor of Computer Science (Hons)</option>
                        <option value="Bachelor of Information Systems (Hons)" <?php if ($user_info['programme'] == "Bachelor of Information Systems (Hons)") echo 'selected'; ?>>Bachelor of Information Systems (Hons)</option>
                        
                    </select>



                <?php elseif ($user_type === 'external_appointment'): ?>
                    <label>Full Name</label>
                    <input type="text" name="external_full_name" value="<?php echo htmlspecialchars($user_info['external_full_name']??''); ?>" required>

                    <label>Company Name</label>
                    <input type="text" name="external_company_name" value="<?php echo htmlspecialchars($user_info['external_company_name']??''); ?>" required>

                    <label>Position</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($user_info['position']??''); ?>" required>

                    <label>AE Admin</label>
                    <select name="ae_name" required>
                        <option value="">-- Select AE Admin --</option>
                        <?php
                        $ae_query = "SELECT admin_id, admin_name FROM admin WHERE admin_role = 'ae_admin'";
                        $ae_result = mysqli_query($db, $ae_query);
                        while ($ae_admin = mysqli_fetch_assoc($ae_result)) {
                            $selected = ($user_info['ae_name'] == $ae_admin['admin_name']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($ae_admin['admin_name'])."' $selected>".htmlspecialchars($ae_admin['admin_name'])."</option>";
                        }
                        ?>
                    </select>

                <?php elseif ($user_type === 'company'): ?>
                    <label>Company Name</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($user_info['company_name']); ?>" required>

                    <label>SSM Number</label>
                    <input type="text" name="ssm_number" value="<?php echo htmlspecialchars($user_info['ssm_number']); ?>" required>
                <?php endif; ?>

                <button type="submit">Update Profile</button>

                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee;">
                <a href="change_password.php" class="upload-btn" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; background: #002776ff; color: white; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fa-solid fa-key"></i> Change Password
                </a>
                <p style="margin-top: 10px; color: #666; font-size: 14px;">
                <i class="fa-solid fa-shield"></i> Secure password change with email verification
                </p>
            </div>

            </form>
        </div>
    </div>

    <script>
    const toggleBtn = document.getElementById('toggle-btn');
    toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-closed');
        
        // Update button icon
        const icon = toggleBtn.querySelector('i');
        if (document.body.classList.contains('sidebar-closed')) {
            icon.className = 'fa-solid fa-bars';
            toggleBtn.innerHTML = '<i class="fa-solid fa-bars"></i> ';
        } else {
            icon.className = 'fa-solid fa-times';
            toggleBtn.innerHTML = '<i class="fa-solid fa-times"></i> ';
        }
    });
    </script>
</body>
</html>