<?php
session_start();
require_once '../includes/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Return unauthorized error
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
sleep(1);
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Use TRUNCATE TABLE instead of DELETE - it's faster and automatically resets auto-increment
        $stmt = $pdo->prepare("TRUNCATE TABLE timetables");
        $stmt->execute();
        
        // Return success response
        echo json_encode(['success' => true, 'message' => 'All timetables cleared successfully']);
        exit;
        
    } catch (PDOException $e) {
        // Log the error
        error_log("Database error in clear_timetables.php: " . $e->getMessage());
        
        // Return error response
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} else {
    // If not a POST request, return error
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
} 