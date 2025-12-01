<?php
session_start();
include('../../config.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] != 'master_admin') {
    header("Location: ../login_admin.php");
    exit();
}


if (isset($_GET['request_id'])) {
    $requestID = $_GET['request_id'];
    $admin_id = $_SESSION['admin_id']; // Get the logged-in admin's ID

    // Check if request exists
    $query = "SELECT * FROM request_admin WHERE request_id = '$requestID'";
    $res = mysqli_query($db, $query);

    if (mysqli_num_rows($res) > 0) {
        // Update request status to rejected WITH admin_id who rejected it
        $update = "UPDATE request_admin 
                   SET request_status = 'Rejected', admin_id = '$admin_id' 
                   WHERE request_id = '$requestID'";
        mysqli_query($db, $update);

        header('Location: notification.php');
        exit();
    } else {
        echo "Request not found.";
    }
} else {
    echo "No request ID provided.";
}
?>