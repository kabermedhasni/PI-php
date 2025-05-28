<?php
require_once '../includes/db.php';

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!$data || !isset($data['year']) || !isset($data['group']) || !isset($data['data'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
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
    
    // First, get all existing entries (both published and unpublished)
    $existingStmt = $pdo->prepare("
        SELECT * FROM `timetables` 
        WHERE year_id = ? AND group_id = ?
    ");
    $existingStmt->execute([$year_id, $group_id]);
    $existingEntries = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create maps for entries by day and time slot
    $entriesMap = [];
    foreach ($existingEntries as $entry) {
        $key = $entry['day'] . '_' . $entry['time_slot'];
        if (!isset($entriesMap[$key])) {
            $entriesMap[$key] = [];
        }
        $entriesMap[$key][] = $entry;
    }
    
    // Delete all entries for this year/group - we'll recreate them with the correct published status
    $deleteStmt = $pdo->prepare("DELETE FROM `timetables` WHERE year_id = ? AND group_id = ?");
    $deleteStmt->execute([$year_id, $group_id]);
    
    // Process each day and time slot from the data
    $insertStmt = $pdo->prepare(
        "INSERT INTO `timetables` (year_id, group_id, day, time_slot, subject_id, professor_id, room, class_type, is_published) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    
    foreach ($data['data'] as $day => $timeSlots) {
        foreach ($timeSlots as $timeSlot => $course) {
            if (!empty($course)) {
                // Insert the course data as published
                $insertStmt->execute([
                    $year_id,
                    $group_id,
                    $day,
                    $timeSlot,
                    $course['subject_id'] ?? null,
                    $course['professor_id'] ?? null,
                    $course['room'] ?? null,
                    $course['class_type'] ?? null
                ]);
            }
        }
    }
    
    // Commit changes
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable published successfully',
        'is_published' => true,
        'has_draft_changes' => false
    ]);
    
} catch (PDOException $e) {
    // Rollback on exception
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in publish_timetable.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 