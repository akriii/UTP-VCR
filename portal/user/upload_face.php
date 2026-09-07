<?php
session_start();
include "../../config.php"; // Adjust path to your config file
require_once "../../s3.php";

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['image'])) {
    $email = $_SESSION['email'];
    
    // 1. Get User ID
    $stmt = $db->prepare("SELECT user_id FROM user WHERE user_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    $stmt->close();

    // 2. Process Base64 Image
    $img = $data['image'];
    $img = str_replace('data:image/png;base64,', '', $img);
    $img = str_replace(' ', '+', $img);
    $data = base64_decode($img);

    // 3. Generate Filename and store the capture in S3
    $fileName = 'face_' . $user_id . '_' . time() . '.png';
    $dbPath = 'face_captures/' . $fileName; // Object key, also stored in the DB

    if (s3_put_data($dbPath, $data, 'image/png')) {
        // 5. Update Database
        $updateStmt = $db->prepare("UPDATE user SET face_recognition = ? WHERE user_id = ?");
        $updateStmt->bind_param("si", $dbPath, $user_id);
        
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Face captured successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $updateStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image to cloud storage']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No image data received']);
}
?>