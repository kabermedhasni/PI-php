<?php
require_once '../../core/db.php';

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
    
    // Extract subgroup information if available
    $is_split = (bool)($data['is_split'] ?? false);
    $split_type = $data['split_type'] ?? null;
    $subgroup = $data['subgroup'] ?? null;
    $room2 = $data['room2'] ?? null;
    
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
        SELECT t.*, y.name as year_name, g.name as group_name, s.name as subject_name, u.name as professor_name,
               t.is_split, t.split_type, t.subgroup, t.subgroup1, t.subgroup2,
               t.room2, s2.name as subject2_name, u2.name as professor2_name
        FROM `timetables` t
        JOIN `years` y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        LEFT JOIN `subjects` s ON t.subject_id = s.id
        LEFT JOIN `users` u ON t.professor_id = u.id
        LEFT JOIN `subjects` s2 ON t.subject2_id = s2.id
        LEFT JOIN `users` u2 ON t.professor2_id = u2.id
        WHERE (t.room = ? OR t.room2 = ?) AND t.day = ? AND t.time_slot = ?
    ");
    $stmt->execute([$room, $room, $day, $timeSlot]);
    
    $conflicts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Skip current year/group if provided (we're editing this slot)
        if ($current_year_id && $current_group_id && 
            $row['year_id'] == $current_year_id && 
            $row['group_id'] == $current_group_id) {
            continue;
        }
        
        // Check for room conflicts with respect to subgroups
        $hasRoomConflict = false;
        
        // If editing a split class with same_time option (two rooms)
        if ($is_split && $split_type === 'same_time') {
            if ($row['is_split'] && $row['split_type'] === 'same_time') {
                // Check if either of our rooms conflicts with either of their rooms
                if ($room === $row['room'] || ($room2 && $room2 === $row['room']) || 
                    ($room === $row['room2']) || ($room2 && $room2 === $row['room2'])) {
                    $hasRoomConflict = true;
                }
            } else {
                // Conflict with a regular class or single subgroup
                if ($room === $row['room'] || ($room2 && $room2 === $row['room'])) {
                    $hasRoomConflict = true;
                }
            }
        }
        // If editing a split class with single_group option (one room)
        else if ($is_split && $split_type === 'single_group') {
            if ($row['is_split'] && $row['split_type'] === 'same_time') {
                // Check if our room conflicts with either of their rooms
                if ($room === $row['room'] || $room === $row['room2']) {
                    $hasRoomConflict = true;
                }
            } 
            // If existing is also a single subgroup, only conflict if same room
            else {
                if ($room === $row['room']) {
                    $hasRoomConflict = true;
                }
            }
        }
        // Standard conflict - regular class or editing non-split
        else {
            // Check if our room is used anywhere
            if ($room === $row['room'] || $room === $row['room2']) {
                $hasRoomConflict = true;
            }
        }
        
        if ($hasRoomConflict) {
            $conflictInfo = [
                'year' => $row['year_name'],
                'group' => $row['group_name'],
                'day' => $row['day'],
                'time' => $row['time_slot'],
                'subject' => $row['subject_name'] ?? 'Unknown subject',
                'professor' => $row['professor_name'] ?? 'Unknown professor',
                'room' => $row['room']
            ];
            
            // Add subgroup info if available
            if ($row['is_split']) {
                $conflictInfo['is_split'] = true;
                $conflictInfo['split_type'] = $row['split_type'];
                
                if ($row['split_type'] === 'same_time') {
                    $conflictInfo['subgroup1'] = $row['subgroup1'];
                    $conflictInfo['subgroup2'] = $row['subgroup2'];
                    $conflictInfo['professor2'] = $row['professor2_name'] ?? 'Unknown professor';
                    $conflictInfo['subject2'] = $row['subject2_name'] ?? 'Unknown subject';
                    $conflictInfo['room2'] = $row['room2'];
                } else if ($row['split_type'] === 'single_group') {
                    $conflictInfo['subgroup'] = $row['subgroup'];
                }
            }
            
            $conflicts[] = $conflictInfo;
        }
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