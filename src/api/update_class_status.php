<?php
require_once '../includes/db.php';

// Check if user is professor or admin
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'professor' && $_SESSION['role'] !== 'admin')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!$data || !isset($data['id']) || !isset($data['status']) || !isset($data['professor_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data (id, status, or professor_id)'
    ]);
    exit;
}

// Ensure the user is the professor for this class or an admin
if ($_SESSION['role'] === 'professor' && $_SESSION['user_id'] != $data['professor_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'You can only update your own classes'
    ]);
    exit;
}

// Validate status (must be 'cancel', 'reschedule', or 'reset')
$status = $data['status'];
if ($status !== 'cancel' && $status !== 'reschedule' && $status !== 'reset') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status. Must be "cancel", "reschedule", or "reset"'
    ]);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update the appropriate column based on the status
    if ($status === 'cancel') {
        $stmt = $pdo->prepare("UPDATE timetables SET is_canceled = 1, is_reschedule = 0 WHERE id = ?");
    } else if ($status === 'reschedule') {
        $stmt = $pdo->prepare("UPDATE timetables SET is_reschedule = 1, is_canceled = 0 WHERE id = ?");
    } else { // reset
        $stmt = $pdo->prepare("UPDATE timetables SET is_reschedule = 0, is_canceled = 0 WHERE id = ?");
    }
    
    $stmt->execute([$data['id']]);
    
    // Commit transaction
    $pdo->commit();
    
    // Determine the appropriate success message
    $message = '';
    if ($status === 'cancel') {
        $message = 'Class successfully canceled';
    } else if ($status === 'reschedule') {
        $message = 'Class successfully marked for rescheduling';
    } else { // reset
        $message = 'Class status reset successfully';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 