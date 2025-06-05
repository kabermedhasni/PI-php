<?php
require_once '../includes/db.php';

// Get parameters from query parameters
$year = isset($_GET['year']) ? urldecode($_GET['year']) : null;
$group = isset($_GET['group']) ? urldecode($_GET['group']) : null;
$admin = isset($_GET['admin']) && $_GET['admin'] === 'true';
$professor_id = isset($_GET['professor_id']) ? $_GET['professor_id'] : null;

// Check if we have the required parameters
if (!$professor_id && (!$year || !$group)) {
    echo json_encode(['success' => false, 'message' => 'Either professor_id OR year and group parameters are required']);
    exit;
}

// Helper function to get subject name from ID
function getSubjectName($subject_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM `subjects` WHERE id = ?");
    $stmt->execute([$subject_id]);
    return $stmt->fetchColumn() ?? 'Unknown Subject';
}

// Helper function to get professor name from ID
function getProfessorName($professor_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM `users` WHERE id = ? AND role = 'professor'");
    $stmt->execute([$professor_id]);
    return $stmt->fetchColumn() ?? 'Unknown Professor';
}

try {
    if ($professor_id) {
        // PROFESSOR VIEW - Get all timetable entries for this professor
        $stmt = $pdo->prepare("
            SELECT t.*, y.name as year_name, g.name as group_name
            FROM `timetables` t
            JOIN `years` y ON t.year_id = y.id
            JOIN `groups` g ON t.group_id = g.id
            WHERE (t.professor_id = ? OR t.professor2_id = ?) AND t.is_published = 1
            ORDER BY t.day, t.time_slot
        ");
        $stmt->execute([$professor_id, $professor_id]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize data by day and time slot
        $all_timetable_data = [];
        
        foreach ($entries as $entry) {
            $entry['subject'] = $entry['subject_id'] ? getSubjectName($entry['subject_id']) : null;
            $entry['professor'] = $entry['professor_id'] ? getProfessorName($entry['professor_id']) : null;
            $entry['year'] = $entry['year_name'];
            $entry['group'] = $entry['group_name'];
            
            // Add second subject and professor names if available
            if ($entry['professor2_id']) {
                $entry['professor2'] = getProfessorName($entry['professor2_id']);
            }
            if ($entry['subject2_id']) {
                $entry['subject2'] = getSubjectName($entry['subject2_id']);
            }
            
            if (!isset($all_timetable_data[$entry['day']])) {
                $all_timetable_data[$entry['day']] = [];
            }
            if (!isset($all_timetable_data[$entry['day']][$entry['time_slot']])) {
                $all_timetable_data[$entry['day']][$entry['time_slot']] = [];
            }
            
            $all_timetable_data[$entry['day']][$entry['time_slot']][] = $entry;
        }
        
        if (!empty($all_timetable_data)) {
            echo json_encode([
                'success' => true,
                'data' => $all_timetable_data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No timetable entries found for this professor'
            ]);
        }
    } else {
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
        
        // For admin view, get both published and unpublished entries
        // For regular view, get only published entries
        if ($admin) {
            // Get all entries - both published and unpublished
            $stmt = $pdo->prepare("
                SELECT * FROM `timetables` 
                WHERE year_id = ? AND group_id = ?
                ORDER BY day, time_slot, is_published ASC
            ");
            $stmt->execute([$year_id, $group_id]);
            $allEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process entries to prioritize unpublished versions over published ones
            $entries = [];
            $processedSlots = [];
            
            // First add all unpublished entries
            foreach ($allEntries as $entry) {
                $key = $entry['day'] . '_' . $entry['time_slot'];
                if ($entry['is_published'] == 0) {
                    $entries[] = $entry;
                    $processedSlots[$key] = true;
                }
            }
            
            // Then add published entries that don't have an unpublished version
            foreach ($allEntries as $entry) {
                $key = $entry['day'] . '_' . $entry['time_slot'];
                if ($entry['is_published'] == 1 && !isset($processedSlots[$key])) {
                    $entries[] = $entry;
                }
            }
        } else {
            // Non-admin view - only show published entries
            $stmt = $pdo->prepare("
                SELECT * FROM `timetables` 
                WHERE year_id = ? AND group_id = ? AND is_published = 1
                ORDER BY day, time_slot
            ");
            $stmt->execute([$year_id, $group_id]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Check for published entries
        $publishedCheckStmt = $pdo->prepare("
            SELECT COUNT(*) FROM `timetables` 
            WHERE year_id = ? AND group_id = ? AND is_published = 1
        ");
        $publishedCheckStmt->execute([$year_id, $group_id]);
        $has_published = $publishedCheckStmt->fetchColumn() > 0;
        
        // Check for draft changes
        $has_draft_changes = false;
        if ($admin && $has_published) {
            $draftCheckStmt = $pdo->prepare("
                SELECT COUNT(*) FROM `timetables` 
                WHERE year_id = ? AND group_id = ? AND is_published = 0
            ");
            $draftCheckStmt->execute([$year_id, $group_id]);
            $has_draft_changes = $draftCheckStmt->fetchColumn() > 0;
        }
        
        if (!empty($entries)) {
            // Organize data by day and time slot
            $timetable_data = [];
            
            foreach ($entries as $entry) {
                if (!isset($timetable_data[$entry['day']])) {
                    $timetable_data[$entry['day']] = [];
                }
                
                // Add subject and professor names
                $entry['subject'] = $entry['subject_id'] ? getSubjectName($entry['subject_id']) : null;
                $entry['professor'] = $entry['professor_id'] ? getProfessorName($entry['professor_id']) : null;
                
                // Add second subject and professor names if available
                if ($entry['professor2_id']) {
                    $entry['professor2'] = getProfessorName($entry['professor2_id']);
                }
                if ($entry['subject2_id']) {
                    $entry['subject2'] = getSubjectName($entry['subject2_id']);
                }
                
                // Store entry in the appropriate time slot
                $timetable_data[$entry['day']][$entry['time_slot']] = $entry;
            }
            
            echo json_encode([
                'success' => true,
                'year' => $year,
                'group' => $group,
                'data' => $timetable_data,
                'is_published' => $has_published,
                'has_draft_changes' => $has_draft_changes,
                'last_modified' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'year' => $year,
                'group' => $group,
                'data' => [],
                'is_published' => $has_published,
                'has_draft_changes' => $has_draft_changes,
                'message' => 'No timetable entries found'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 