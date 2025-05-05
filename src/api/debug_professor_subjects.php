<?php
session_start();
require_once '../includes/db.php';

// No security checks for debugging
$professor_id = isset($_GET['professor_id']) ? intval($_GET['professor_id']) : 21; // Default to 21 if not provided

echo "<h1>Debug Professor Subjects API</h1>";
echo "<p>Testing for professor_id: $professor_id</p>";

try {
    // Build the query
    $query = "
        SELECT s.id, s.name 
        FROM subjects s
        INNER JOIN professor_subjects ps ON s.id = ps.subject_id
        WHERE ps.professor_id = ?
        ORDER BY s.name
    ";
    
    echo "<h2>Query:</h2>";
    echo "<pre>" . str_replace("?", $professor_id, $query) . "</pre>";
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute([$professor_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Results:</h2>";
    if (empty($subjects)) {
        echo "<p>No subjects found for professor ID $professor_id</p>";
    } else {
        echo "<p>Found " . count($subjects) . " subjects</p>";
        echo "<pre>" . print_r($subjects, true) . "</pre>";
    }
    
    // Show how we would normally format the response
    $response = [
        'success' => true,
        'subjects' => $subjects
    ];
    
    echo "<h2>JSON Response that would be sent:</h2>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    
} catch (PDOException $e) {
    echo "<h2>Database Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 