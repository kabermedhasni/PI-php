<?php
session_start();
require_once '../includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only admin can perform write operations
$canEdit = ($_SESSION['role'] === 'admin');

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch timetable entries
        fetchTimetable();
        break;
    case 'POST':
        // Save timetable entries
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        saveTimetable();
        break;
    case 'DELETE':
        // Delete timetable entry
        if (!$canEdit) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }
        deleteTimetableEntry();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Fetch timetable entries based on user role and filters
 */
function fetchTimetable() {
    global $pdo;
    
    try {
        $userRole = $_SESSION['role'];
        $userId = $_SESSION['user_id'];
        
        // Base query
        $query = "
            SELECT 
                t.id, 
                t.day_of_week, 
                t.start_time, 
                t.end_time, 
                t.room,
                s.id as subject_id, 
                s.name as subject_name, 
                s.code as subject_code,
                p.id as professor_id, 
                p.email as professor_email,
                g.id as group_id, 
                g.name as group_name,
                y.id as year_id, 
                y.name as year_name
            FROM timetable_entries t
            JOIN subjects s ON t.subject_id = s.id
            JOIN users p ON t.professor_id = p.id
            JOIN groups g ON t.group_id = g.id
            JOIN years y ON g.year_id = y.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filter based on role
        if ($userRole === 'professor') {
            // Professors see only their classes
            $query .= " AND t.professor_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'student') {
            // Students see only their group's classes
            // First, get student's group
            $stmt = $pdo->prepare("SELECT group_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && $student['group_id']) {
                $query .= " AND t.group_id = ?";
                $params[] = $student['group_id'];
            } else {
                // Student not assigned to a group
                echo json_encode(['error' => 'Student not assigned to a group', 'entries' => []]);
                return;
            }
        } elseif ($userRole === 'admin') {
            // Admins can filter by year and group names
            if (isset($_GET['year']) && $_GET['year']) {
                $yearName = trim($_GET['year']);
                // Look up the year ID from the name
                $yearStmt = $pdo->prepare("SELECT id FROM years WHERE name = ?");
                $yearStmt->execute([$yearName]);
                $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($year) {
                    $yearId = $year['id'];
                    $query .= " AND y.id = ?";
                    $params[] = $yearId;
                    
                    if (isset($_GET['group']) && $_GET['group']) {
                        $groupName = trim($_GET['group']);
                        // Look up the group ID from the name and year
                        $groupStmt = $pdo->prepare("SELECT id FROM groups WHERE name = ? AND year_id = ?");
                        $groupStmt->execute([$groupName, $yearId]);
                        $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($group) {
                            $groupId = $group['id'];
                            $query .= " AND g.id = ?";
                            $params[] = $groupId;
                        }
                    }
                }
            }
            // Legacy code for backward compatibility - uses IDs directly
            elseif (isset($_GET['year_id']) && $_GET['year_id']) {
                $yearId = $_GET['year_id'];
                $query .= " AND y.id = ?";
                $params[] = $yearId;
                
                if (isset($_GET['group_id']) && $_GET['group_id']) {
                    $groupId = $_GET['group_id'];
                    $query .= " AND g.id = ?";
                    $params[] = $groupId;
                }
            }
        }
        
        // Order by day and time
        $query .= " ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), t.start_time";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add a default color for each entry
        foreach ($entries as &$entry) {
            $entry['subject_color'] = '#3b82f6'; // Default blue color
        }
        
        echo json_encode(['success' => true, 'entries' => $entries]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Save a timetable entry
 */
function saveTimetable() {
    global $pdo;
    
    try {
        // Get POST data
        $json = file_get_contents('php://input');
        $input = json_decode($json, true);
        
        // Check if input is valid
        if (!$input || !isset($input['entries']) || !is_array($input['entries'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input format']);
            return;
        }
        
        $entries = $input['entries'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        $results = [];
        $errors = [];
        
        // Check if the table has status and created_by columns
        $hasStatusColumn = false;
        $hasCreatedByColumn = false;
        
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM timetable_entries LIKE 'status'");
            $stmt->execute();
            $hasStatusColumn = ($stmt->rowCount() > 0);
            
            $stmt = $pdo->prepare("SHOW COLUMNS FROM timetable_entries LIKE 'created_by'");
            $stmt->execute();
            $hasCreatedByColumn = ($stmt->rowCount() > 0);
        } catch (PDOException $e) {
            // Just continue, assuming the columns don't exist
        }
        
        foreach ($entries as $data) {
            // Check required fields
            if (!isset($data['subject_id']) || !isset($data['professor_id'])) {
                $errors[] = 'Missing required fields: subject_id, professor_id';
                continue;
            }
            
            // Get year and group IDs if names are provided
            $groupId = null;
            
            if (isset($data['year_name']) && isset($data['group_name'])) {
                // Look up year ID
                $yearStmt = $pdo->prepare("SELECT id FROM years WHERE name = ?");
                $yearStmt->execute([$data['year_name']]);
                $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$year) {
                    $errors[] = "Year not found: " . $data['year_name'];
                    continue;
                }
                
                $yearId = $year['id'];
                
                // Look up group ID
                $groupStmt = $pdo->prepare("SELECT id FROM groups WHERE name = ? AND year_id = ?");
                $groupStmt->execute([$data['group_name'], $yearId]);
                $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$group) {
                    $errors[] = "Group not found: " . $data['group_name'] . " in year " . $data['year_name'];
                    continue;
                }
                
                $groupId = $group['id'];
            } else if (isset($data['group_id'])) {
                // If group_id is provided directly, use it
                $groupId = $data['group_id'];
            } else {
                $errors[] = "Missing group information";
                continue;
            }
            
            // Process start and end times
            $startTime = isset($data['start_time']) ? $data['start_time'] : null;
            $endTime = isset($data['end_time']) ? $data['end_time'] : null;
            
            if (!$startTime || !$endTime) {
                $errors[] = "Missing time information";
                continue;
            }
            
            // Check if entry exists
            if (isset($data['id']) && $data['id']) {
                // Build update query based on available columns
                $query = "UPDATE timetable_entries SET subject_id = ?, professor_id = ?, group_id = ?, 
                          day_of_week = ?, start_time = ?, end_time = ?, room = ?";
                
                $params = [
                    $data['subject_id'],
                    $data['professor_id'],
                    $groupId,
                    $data['day_of_week'],
                    $startTime,
                    $endTime,
                    $data['room'] ?? null
                ];
                
                // Add status if column exists
                if ($hasStatusColumn) {
                    $query .= ", status = ?";
                    $params[] = $data['status'] ?? 'draft';
                }
                
                $query .= " WHERE id = ?";
                $params[] = $data['id'];
                
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);
                
                if (!$result) {
                    error_log("Update failed: " . implode(", ", $stmt->errorInfo()));
                    $errors[] = "Update failed for entry ID " . $data['id'];
                    continue;
                }
                
                $results[] = [
                    'id' => $data['id'],
                    'message' => 'Timetable entry updated successfully'
                ];
            } else {
                // Build insert query based on available columns
                $query = "INSERT INTO timetable_entries (subject_id, professor_id, group_id, day_of_week, start_time, end_time, room";
                $valuesSql = "VALUES (?, ?, ?, ?, ?, ?, ?";
                
                $params = [
                    $data['subject_id'],
                    $data['professor_id'],
                    $groupId,
                    $data['day_of_week'],
                    $startTime,
                    $endTime,
                    $data['room'] ?? null
                ];
                
                // Add status if column exists
                if ($hasStatusColumn) {
                    $query .= ", status";
                    $valuesSql .= ", ?";
                    $params[] = $data['status'] ?? 'draft';
                }
                
                // Add created_by if column exists
                if ($hasCreatedByColumn) {
                    $query .= ", created_by";
                    $valuesSql .= ", ?";
                    $params[] = $_SESSION['user_id'];
                }
                
                $query .= ") " . $valuesSql . ")";
                
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute($params);
                
                if (!$result) {
                    error_log("Insert failed: " . implode(", ", $stmt->errorInfo()));
                    $errors[] = "Insert failed for new entry";
                    continue;
                }
                
                $entryId = $pdo->lastInsertId();
                $results[] = [
                    'id' => $entryId,
                    'message' => 'Timetable entry added successfully'
                ];
            }
        }
        
        // Check if there were any errors
        if (!empty($errors)) {
            // Roll back transaction if any errors occurred
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'errors' => $errors
            ]);
            return;
        }
        
        // Commit transaction if no errors
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Timetable entries saved successfully',
            'results' => $results
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in saveTimetable: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error in saveTimetable: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete a timetable entry
 */
function deleteTimetableEntry() {
    global $pdo;
    
    try {
        // Get entry ID
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Entry ID is required']);
            return;
        }
        
        // Delete the entry
        $stmt = $pdo->prepare("DELETE FROM timetable_entries WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Entry not found']);
            return;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Timetable entry deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} 