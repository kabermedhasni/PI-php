<?php
require_once '../includes/db.php';

try {
    // Get JSON data from the request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate input
    if (!$data || !isset($data['professor_id']) || !isset($data['day']) || !isset($data['time_slot'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $professor_id = $data['professor_id'];
    $day = $data['day'];
    $timeSlot = $data['time_slot'];
    $current_year = $data['year'] ?? null;
    $current_group = $data['group'] ?? null;
    
    // If we have subgroup data, extract it
    $is_split = (bool)($data['is_split'] ?? false);
    $split_type = $data['split_type'] ?? null;
    $subgroup = $data['subgroup'] ?? null;
    $professor2_id = $data['professor2_id'] ?? null;
    
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

    // Check if the professor is scheduled elsewhere at this time
    // Modified query to work without timetable_subgroups table
    $stmt = $pdo->prepare("
        SELECT t.*, y.name as year_name, g.name as group_name, s.name as subject_name, u.name as professor_name,
               t.is_split, t.split_type, t.subgroup, t.subgroup1, t.subgroup2, 
               t.professor2_id, u2.name as professor2_name
        FROM `timetables` t
        JOIN `years` y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        LEFT JOIN `subjects` s ON t.subject_id = s.id
        LEFT JOIN `users` u ON t.professor_id = u.id
        LEFT JOIN `users` u2 ON t.professor2_id = u2.id
        WHERE (t.professor_id = ? OR t.professor2_id = ?) AND t.day = ? AND t.time_slot = ?
    ");
    $stmt->execute([$professor_id, $professor_id, $day, $timeSlot]);
    
    $conflicts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Skip current year/group if provided (we're editing this slot)
        if ($current_year_id && $current_group_id && 
            $row['year_id'] == $current_year_id && 
            $row['group_id'] == $current_group_id) {
            continue;
        }
        
        // Check for subgroup conflicts
        $hasSubgroupConflict = false;
        
        // If editing a split class with same_time option
        if ($is_split && $split_type === 'same_time' && $row['is_split']) {
            // If both the existing entry and new entry have split groups in same time
            if ($row['split_type'] === 'same_time') {
                // This is a conflict - professor can't teach two different subgroups simultaneously
                $hasSubgroupConflict = true;
            } 
            // If existing entry has single_group option
            else if ($row['split_type'] === 'single_group') {
                // Check if we're trying to schedule the same professor for both subgroups
                if ($professor_id == $professor2_id) {
                    $hasSubgroupConflict = true;
                }
            }
        } 
        // If editing a split class with single_group option
        else if ($is_split && $split_type === 'single_group' && $row['is_split']) {
            // Check if there's overlap with existing subgroups
            if ($row['split_type'] === 'same_time') {
                // Conflict if the same professor is teaching both subgroups
                if ($row['professor_id'] == $professor_id && $row['professor2_id'] == $professor_id) {
                    $hasSubgroupConflict = true;
                }
            }
            // If existing also has single_group, check if same subgroup
            else if ($row['split_type'] === 'single_group' && $row['subgroup'] === $subgroup) {
                $hasSubgroupConflict = true;
            }
        }
        // Standard conflict - professor already scheduled at this time (non-subgroup case)
        else {
            $hasSubgroupConflict = true;
        }
        
        if ($hasSubgroupConflict) {
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
                } else if ($row['split_type'] === 'single_group') {
                    $conflictInfo['subgroup'] = $row['subgroup'];
                }
            }
            
            $conflicts[] = $conflictInfo;
        }
    }
    
    // If we have a second professor, check their availability too
    if ($professor2_id && $professor2_id != $professor_id) {
        $stmt2 = $pdo->prepare("
            SELECT t.*, y.name as year_name, g.name as group_name, s.name as subject_name, u.name as professor_name,
                   t.is_split, t.split_type, t.subgroup, t.subgroup1, t.subgroup2, 
                   t.professor2_id, u2.name as professor2_name
            FROM `timetables` t
            JOIN `years` y ON t.year_id = y.id
            JOIN `groups` g ON t.group_id = g.id
            LEFT JOIN `subjects` s ON t.subject_id = s.id
            LEFT JOIN `users` u ON t.professor_id = u.id
            LEFT JOIN `users` u2 ON t.professor2_id = u2.id
            WHERE (t.professor_id = ? OR t.professor2_id = ?) AND t.day = ? AND t.time_slot = ?
        ");
        $stmt2->execute([$professor2_id, $professor2_id, $day, $timeSlot]);
        
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            // Skip current year/group if provided (we're editing this slot)
            if ($current_year_id && $current_group_id && 
                $row['year_id'] == $current_year_id && 
                $row['group_id'] == $current_group_id) {
                continue;
            }
            
            // For second professor, any scheduling is a conflict
            $conflictInfo = [
                'year' => $row['year_name'],
                'group' => $row['group_name'],
                'day' => $row['day'],
                'time' => $row['time_slot'],
                'subject' => $row['subject_name'] ?? 'Unknown subject',
                'professor' => $row['professor_name'] ?? 'Unknown professor',
                'room' => $row['room'],
                'is_professor2_conflict' => true
            ];
            
            // Add subgroup info if available
            if ($row['is_split']) {
                $conflictInfo['is_split'] = true;
                $conflictInfo['split_type'] = $row['split_type'];
                
                if ($row['split_type'] === 'same_time') {
                    $conflictInfo['subgroup1'] = $row['subgroup1'];
                    $conflictInfo['subgroup2'] = $row['subgroup2'];
                    $conflictInfo['professor2'] = $row['professor2_name'] ?? 'Unknown professor';
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
            'message' => 'Professor is already scheduled at this time',
            'conflicts' => $conflicts
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'available' => true,
            'message' => 'Professor is available at this time'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in check_professor_availability.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 