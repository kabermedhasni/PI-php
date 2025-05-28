<?php
session_start();
require_once '../includes/db.php';

// Restore the role check for security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get professor ID from request
$professor_id = isset($_GET['professor_id']) ? intval($_GET['professor_id']) : 0;

if ($professor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid professor ID']);
    exit;
}

try {
    // Get all subjects for this professor
    $stmt = $pdo->prepare("
        SELECT s.id, s.name 
        FROM subjects s
        JOIN professor_subjects ps ON s.id = ps.subject_id
        WHERE ps.professor_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$professor_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add default color for each subject
    foreach ($subjects as &$subject) {
        // Use grey as default color for CM
        $subject['color'] = '#6b7280';
    }
    
    // Return the subjects as JSON
    echo json_encode(['success' => true, 'subjects' => $subjects]);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in get_professor_subjects.php: ' . $e->getMessage());
    error_log('SQL State: ' . $e->errorInfo[0]);
    error_log('Error Code: ' . $e->errorInfo[1]);
    error_log('Message: ' . $e->errorInfo[2]);
    
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 