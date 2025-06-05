<?php
require_once '../includes/db.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get all distinct year and group combinations that have timetable entries
    $stmt = $pdo->prepare("
        SELECT DISTINCT year_id, group_id
        FROM `timetables`
    ");
    $stmt->execute();
    $yearGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $published_count = 0;
    $published_items = [];
    
    // For each year/group combination, publish its timetable
    foreach ($yearGroups as $yearGroup) {
        $year_id = $yearGroup['year_id'];
        $group_id = $yearGroup['group_id'];
        
        // Get year and group names for response
        $yearNameStmt = $pdo->prepare("SELECT name FROM `years` WHERE id = ?");
        $yearNameStmt->execute([$year_id]);
        $year_name = $yearNameStmt->fetchColumn();
        
        $groupNameStmt = $pdo->prepare("SELECT name FROM `groups` WHERE id = ?");
        $groupNameStmt->execute([$group_id]);
        $group_name = $groupNameStmt->fetchColumn();
        
        // Get all timetable entries for this year/group
        $entriesStmt = $pdo->prepare("
            SELECT * FROM `timetables`
            WHERE year_id = ? AND group_id = ?
        ");
        $entriesStmt->execute([$year_id, $group_id]);
        $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a map of entries by day and time slot
        $entriesMap = [];
        foreach ($entries as $entry) {
            $key = $entry['day'] . '_' . $entry['time_slot'];
            
            // For each time slot, prioritize unpublished entries (drafts)
            if (!isset($entriesMap[$key]) || $entry['is_published'] == 0) {
                $entriesMap[$key] = $entry;
            }
        }
        
        // If we have entries to publish
        if (!empty($entriesMap)) {
            // Delete all existing entries for this year/group
            $deleteStmt = $pdo->prepare("
                DELETE FROM `timetables` 
                WHERE year_id = ? AND group_id = ?
            ");
            $deleteStmt->execute([$year_id, $group_id]);
            
            // Insert all entries as published
            $insertStmt = $pdo->prepare("
                INSERT INTO `timetables` 
                (year_id, group_id, day, time_slot, subject_id, professor_id, room, class_type, is_published,
                is_split, split_type, professor2_id, subject2_id, room2, subgroup1, subgroup2, subgroup)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($entriesMap as $entry) {
                $insertStmt->execute([
                    $year_id,
                    $group_id,
                    $entry['day'],
                    $entry['time_slot'],
                    $entry['subject_id'],
                    $entry['professor_id'],
                    $entry['room'],
                    $entry['class_type'],
                    $entry['is_split'] ?? 0,
                    $entry['split_type'],
                    $entry['professor2_id'],
                    $entry['subject2_id'],
                    $entry['room2'],
                    $entry['subgroup1'],
                    $entry['subgroup2'],
                    $entry['subgroup']
                ]);
            }
            
            $published_count++;
            $published_items[] = $year_name . '-' . $group_name;
        }
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $published_count > 0 ? 
            'Tous les emplois du temps ont été publiés avec succès (' . $published_count . ' emplois du temps)' : 
            'Aucun emploi du temps à publier',
        'published_count' => $published_count,
        'published' => $published_items
    ]);
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error in publish_all_timetables.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 