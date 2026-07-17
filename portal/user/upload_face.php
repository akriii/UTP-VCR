<?php
session_start();
include "../../config.php"; // Adjust path to your config file

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

    // 3. Create Directory if it doesn't exist
    $uploadDir = '../../documents/face_captures/'; // Make sure this folder exists or create it
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // 4. Generate Filename and Save
    $fileName = 'face_' . $user_id . '_' . time() . '.png';
    $filePath = $uploadDir . $fileName;
    $dbPath = 'face_captures/' . $fileName; // Path to store in DB

    if (file_put_contents($filePath, $data)) {
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
        echo json_encode(['success' => false, 'message' => 'Failed to save image']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No image data received']);
}
?>