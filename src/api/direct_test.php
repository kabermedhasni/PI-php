<?php
// Direct database test with no API involved
require_once '../includes/db.php';

// Professor ID to test
$professor_id = 21;

echo "<h1>Direct Test for Professor ID: $professor_id</h1>";

try {
    // First get professor info
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Professor Information:</h2>";
    echo "<pre>" . print_r($professor, true) . "</pre>";
    
    // Check the professor_subjects table directly for this professor
    $stmt = $pdo->prepare("SELECT * FROM professor_subjects WHERE professor_id = ?");
    $stmt->execute([$professor_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Professor Subject Assignments:</h2>";
    if (empty($assignments)) {
        echo "<p>No assignments found in professor_subjects table for this professor.</p>";
    } else {
        echo "<pre>" . print_r($assignments, true) . "</pre>";
    }
    
    // Try the JOIN query
    $query = "
        SELECT s.id, s.name 
        FROM subjects s
        INNER JOIN professor_subjects ps ON s.id = ps.subject_id
        WHERE ps.professor_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$professor_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>JOIN Query Result:</h2>";
    if (empty($subjects)) {
        echo "<p>No subjects found via JOIN query.</p>";
    } else {
        echo "<pre>" . print_r($subjects, true) . "</pre>";
    }
    
    // Display the raw SQL query for manual verification
    echo "<h2>SQL Query:</h2>";
    echo "<pre>" . str_replace("?", $professor_id, $query) . "</pre>";
    
    // Show all tables in database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>All Tables in Database:</h2>";
    echo "<pre>" . print_r($tables, true) . "</pre>";
    
    // Also check if we get results when querying the professor_subjects table directly
    $stmt = $pdo->query("SELECT * FROM professor_subjects LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Sample Data from professor_subjects Table (up to 5 rows):</h2>";
    echo "<pre>" . print_r($rows, true) . "</pre>";
    
} catch (PDOException $e) {
    echo "<h2>Database Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Code: " . $e->getCode() . "</p>";
    
    // Try to get more information about the error
    if ($e->errorInfo) {
        echo "<p>Error Info: " . print_r($e->errorInfo, true) . "</p>";
    }
} 