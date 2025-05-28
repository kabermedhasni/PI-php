<?php
require_once '../includes/db.php';

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data received'
    ]);
    exit;
}

if (!isset($data['year']) || !isset($data['group']) || !isset($data['data'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required data (year, group, or timetable data)'
    ]);
    exit;
}

try {
    // Get year ID
    $yearStmt = $pdo->prepare("SELECT id FROM `years` WHERE name = ?");
    $yearStmt->execute([$data['year']]);
    $year_id = $yearStmt->fetchColumn();
    
    if (!$year_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Year not found: ' . $data['year']
        ]);
        exit;
    }
    
    // Get group ID
    $groupStmt = $pdo->prepare("SELECT id FROM `groups` WHERE name = ? AND year_id = ?");
    $groupStmt->execute([$data['group'], $year_id]);
    $group_id = $groupStmt->fetchColumn();
    
    if (!$group_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Group not found: ' . $data['group']
        ]);
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
    
    // Create maps for published and unpublished entries
    $publishedMap = [];
    $unpublishedMap = [];
    foreach ($existingEntries as $entry) {
        $key = $entry['day'] . '_' . $entry['time_slot'];
        if ($entry['is_published'] == 1) {
            $publishedMap[$key] = $entry;
        } else {
            $unpublishedMap[$key] = $entry;
        }
    }
    
    // Delete only unpublished entries - we'll recreate them
    $deleteStmt = $pdo->prepare("
        DELETE FROM `timetables` 
        WHERE year_id = ? AND group_id = ? AND is_published = 0
    ");
    $deleteStmt->execute([$year_id, $group_id]);
    
    // Track which days and time slots we've processed from the incoming data
    $processedSlots = [];

    // Process each day and time slot from the incoming data
    foreach ($data['data'] as $day => $timeSlots) {
        if (!is_array($timeSlots)) {
            continue;
        }
        
        foreach ($timeSlots as $timeSlot => $course) {
            if (empty($course)) {
                continue; // Skip empty slots
            }
            
            $key = $day . '_' . $timeSlot;
            $processedSlots[$key] = true;
            
            // Check if this slot already exists in published entries
            if (isset($publishedMap[$key])) {
                $existing = $publishedMap[$key];
                
                // If there are changes, create a draft version
                if ($existing['subject_id'] != ($course['subject_id'] ?? null) ||
                    $existing['professor_id'] != ($course['professor_id'] ?? null) ||
                    $existing['room'] != ($course['room'] ?? null)) {
                    
                    // Insert a new unpublished version
                    $insertStmt = $pdo->prepare("
                        INSERT INTO `timetables` 
                        (year_id, group_id, day, time_slot, subject_id, professor_id, room, class_type, is_published)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                    ");
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
            } else {
                // Insert new entry as unpublished
                $insertStmt = $pdo->prepare("
                    INSERT INTO `timetables` 
                    (year_id, group_id, day, time_slot, subject_id, professor_id, room, class_type, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
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
    
    // Check for published entries
    $publishedCheckStmt = $pdo->prepare("
        SELECT COUNT(*) FROM `timetables` 
        WHERE year_id = ? AND group_id = ? AND is_published = 1
    ");
    $publishedCheckStmt->execute([$year_id, $group_id]);
    $has_published_version = $publishedCheckStmt->fetchColumn() > 0;
    
    // Check for draft changes
    $draftCheckStmt = $pdo->prepare("
        SELECT COUNT(*) FROM `timetables` 
        WHERE year_id = ? AND group_id = ? AND is_published = 0
    ");
    $draftCheckStmt->execute([$year_id, $group_id]);
    $has_draft_changes = $draftCheckStmt->fetchColumn() > 0;
    
    // Commit the transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable saved successfully',
        'is_published' => $has_published_version,
        'has_draft_changes' => $has_draft_changes
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 