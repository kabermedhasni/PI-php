<?php
require_once '../includes/db.php';

try {
    // Get JSON data from the request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate input
    if (!$data || !isset($data['year']) || !isset($data['group']) || !isset($data['day']) || !isset($data['time_slot'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $year = $data['year'];
    $group = $data['group'];
    $day = $data['day'];
    $timeSlot = $data['time_slot'];
    
    // Get year_id and group_id
    $yearStmt = $pdo->prepare("SELECT id FROM `years` WHERE name = ?");
    $yearStmt->execute([$year]);
    $year_id = $yearStmt->fetchColumn();
    
    $groupStmt = $pdo->prepare("SELECT id FROM `groups` WHERE name = ? AND year_id = ?");
    $groupStmt->execute([$group, $year_id]);
    $group_id = $groupStmt->fetchColumn();
    
    if (!$year_id || !$group_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid year or group']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete the specific class entry - both published and unpublished versions
    $deleteStmt = $pdo->prepare("
        DELETE FROM `timetables` 
        WHERE year_id = ? AND group_id = ? AND day = ? AND time_slot = ?
    ");
    $result = $deleteStmt->execute([$year_id, $group_id, $day, $timeSlot]);
    
    // Check if any rows were affected
    $rowCount = $deleteStmt->rowCount();
    
    if ($result && $rowCount > 0) {
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Class deleted successfully',
            'deleted_count' => $rowCount
        ]);
    } else {
        // Rollback on failure
        $pdo->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'No class found to delete with the given criteria'
        ]);
    }
} catch (PDOException $e) {
    // Rollback on exception
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in delete_class.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 