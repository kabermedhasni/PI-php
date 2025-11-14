<?php
/**
 * API Endpoint: Delete Timetable
 * 
 * Deletes a specific timetable for a given year and group
 * POST request expecting JSON with year and group parameters
 */
require_once '../../core/db.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Not authorized
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$data || !isset($data['year']) || !isset($data['group'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data received'
    ]);
    exit;
}

try {
    // Get year_id and group_id from the database based on their names
    $yearStmt = $pdo->prepare("SELECT id FROM `years` WHERE name = ?");
    $yearStmt->execute([$data['year']]);
    $year_id = $yearStmt->fetchColumn();
    
    $groupStmt = $pdo->prepare("SELECT id FROM `groups` WHERE name = ? AND year_id = ?");
    $groupStmt->execute([$data['group'], $year_id]);
    $group_id = $groupStmt->fetchColumn();
    
    if (!$year_id || !$group_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid year or group']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete both published and unpublished entries
    $deleteStmt = $pdo->prepare("DELETE FROM `timetables` WHERE year_id = ? AND group_id = ?");
    $result = $deleteStmt->execute([$year_id, $group_id]);
    
    if ($result) {
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Timetable deleted successfully'
        ]);
    } else {
        // Rollback on failure
        $pdo->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete timetable'
        ]);
    }
} catch (PDOException $e) {
    // Rollback on exception
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in delete_timetable.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 