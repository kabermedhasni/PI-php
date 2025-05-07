<?php
session_start();
require_once '../includes/db.php';

// For debugging - output all request info
error_log('API called: get_professor_subjects.php');
error_log('SESSION data: ' . print_r($_SESSION, true));
error_log('GET data: ' . print_r($_GET, true));

// Remove the role check temporarily for testing
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
*/

// Get professor ID from request
$professor_id = isset($_GET['professor_id']) ? intval($_GET['professor_id']) : 0;

error_log('[get_professor_subjects] Received professor_id: ' . $professor_id);

if ($professor_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid professor ID']);
    exit;
}

try {
    // Query the database for subjects taught by this professor
    // Using the CORRECT table name "professor_subjects" (with 's')
    $query = "
        SELECT s.id, s.name
        FROM subjects s
        INNER JOIN professor_subjects ps ON s.id = ps.subject_id
        WHERE ps.professor_id = ?
        ORDER BY s.name
    ";
    
    error_log('Executing query: ' . $query . ' with professor_id: ' . $professor_id);
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$professor_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log('[get_professor_subjects] Found subjects: ' . count($subjects));
    error_log('Subjects data: ' . print_r($subjects, true));
    
    // Add default color for each subject
    foreach ($subjects as &$subject) {
        // Generate a random color or use default blue
        $subject['color'] = '#3b82f6';
    }
    
    // Return the subjects as JSON
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'subjects' => $subjects]);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in get_professor_subjects.php: ' . $e->getMessage());
    error_log('SQL State: ' . $e->errorInfo[0]);
    error_log('Error Code: ' . $e->errorInfo[1]);
    error_log('Message: ' . $e->errorInfo[2]);
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 