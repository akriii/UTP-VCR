<?php
session_start();
include('../../../config.php'); // Adjust path if necessary

if (isset($_POST['restore_user'])) {
    $report_id = $_POST['report_id']; // Use report_id

    if (!empty($report_id)) {
        $stmt = $db->prepare("UPDATE report SET is_deleted = 0 WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);

        if ($stmt->execute()) {
            header("Location: history.php?msg=restored");
        } else {
            echo "Error restoring report.";
        }
        $stmt->close();
    }
}
?>