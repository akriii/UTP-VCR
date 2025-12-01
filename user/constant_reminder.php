<?php
session_start();
require_once '../config.php';
require_once 'kyc6_reminder.php';

// Create reminder object
$reminder = new KYC6Reminder($db);

// Send reminders
$reminder->sendPendingReminders();

echo "Reminders sent at: " . date('Y-m-d H:i:s');
?>