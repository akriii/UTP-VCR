<?php
session_start();
include('../../config.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}

if (isset($_GET['request_id'])) {
    $requestID = mysqli_real_escape_string($db, $_GET['request_id']);
    $admin_id = $_SESSION['admin_id']; // Get the logged-in admin's ID

    // Get admin request info
    $query = "SELECT * FROM request_admin WHERE request_id = '$requestID'";
    $res = mysqli_query($db, $query);

    if ($res && mysqli_num_rows($res) > 0) {
        $req = mysqli_fetch_assoc($res);

        $fullname = mysqli_real_escape_string($db, $req['request_name']);  
        $email = mysqli_real_escape_string($db, $req['request_email']);
        $password = mysqli_real_escape_string($db, $req['request_password']);
        $role = mysqli_real_escape_string($db, $req['request_role'] );
        $staff_id = mysqli_real_escape_string($db, $req['request_staff_id'] );

        // Insert into admin table
        $insert = "
            INSERT INTO admin (admin_name, admin_email, admin_password, admin_role, staff_id)
            VALUES ('$fullname', '$email', '$password', '$role', '$staff_id')
        ";
        $insertResult = mysqli_query($db, $insert);

        if ($insertResult) {
            // Update request status WITH admin_id who approved it
            $update = "UPDATE request_admin SET request_status = 'Approved', admin_id = '$admin_id' WHERE request_id = '$requestID'";
            mysqli_query($db, $update);


            header('Location: notification.php?success=1');
            exit();
        } else {
            echo "Error inserting into admin table: " . mysqli_error($db);
        }

    } else {
        echo "Request not found.";
    }
} else {
    echo "No request ID provided.";
}
?>