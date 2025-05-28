<?php
require_once '../includes/db.php';

try {
    // Get JSON data from the request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate input
    if (!$data || !isset($data['room']) || !isset($data['day']) || !isset($data['time_slot'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $room = $data['room'];
    $day = $data['day'];
    $timeSlot = $data['time_slot'];
    $current_year = $data['year'] ?? null;
    $current_group = $data['group'] ?? null;
    
    // Get current year_id and group_id if provided
    $current_year_id = null;
    $current_group_id = null;
    
    if ($current_year && $current_group) {
        // Get year ID
        $yearStmt = $pdo->prepare("SELECT id FROM `years` WHERE name = ?");
        $yearStmt->execute([$current_year]);
        $current_year_id = $yearStmt->fetchColumn();
        
        // Get group ID
        $groupStmt = $pdo->prepare("SELECT id FROM `groups` WHERE name = ? AND year_id = ?");
        $groupStmt->execute([$current_group, $current_year_id]);
        $current_group_id = $groupStmt->fetchColumn();
    }

    // Check if the room is scheduled elsewhere at this time
    $stmt = $pdo->prepare("
        SELECT t.*, y.name as year_name, g.name as group_name, s.name as subject_name, u.name as professor_name
        FROM `timetables` t
        JOIN `years` y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        LEFT JOIN `subjects` s ON t.subject_id = s.id
        LEFT JOIN `users` u ON t.professor_id = u.id
        WHERE t.room = ? AND t.day = ? AND t.time_slot = ?
    ");
    $stmt->execute([$room, $day, $timeSlot]);
    
    $conflicts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Skip current year/group if provided (we're editing this slot)
        if ($current_year_id && $current_group_id && 
            $row['year_id'] == $current_year_id && 
            $row['group_id'] == $current_group_id) {
            continue;
        }
        
        $conflicts[] = [
            'year' => $row['year_name'],
            'group' => $row['group_name'],
            'day' => $row['day'],
            'time' => $row['time_slot'],
            'subject' => $row['subject_name'] ?? 'Unknown subject',
            'professor' => $row['professor_name'] ?? 'Unknown professor',
            'room' => $row['room']
        ];
    }
    
    if (count($conflicts) > 0) {
        echo json_encode([
            'success' => true,
            'available' => false,
            'message' => 'Room is already scheduled at this time',
            'conflicts' => $conflicts
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'available' => true,
            'message' => 'Room is available at this time'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in check_room_availability.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 