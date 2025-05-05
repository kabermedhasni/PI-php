<?php
session_start();
require_once '../includes/db.php';

// Check if user is admin and verify POST request
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../views/login.php?error=invalid_access");
    exit;
}

// Add a small delay to make any spinner visible
sleep(1);

// Define the timetable data directory
$dir = '../timetable_data';

// Check if the directory exists
if (is_dir($dir)) {
    // Get all json files in the directory
    $files = glob($dir . '/*.json');
    
    // Delete each file
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Redirect back to admin dashboard with success message
header("Location: ../admin/index.php?status=cleared");
exit;
?> 