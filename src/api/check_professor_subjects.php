<?php
// This is a diagnostic script to check subjects for a specific professor ID
require_once '../includes/db.php';

// Set the professor ID to check
$professor_id = 21; // Professor habeb

echo "<h2>Checking subjects for professor ID: $professor_id</h2>";

try {
    // First, check if this professor exists
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'professor'");
    $stmt->execute([$professor_id]);
    $professor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$professor) {
        echo "<p>Error: No professor found with ID $professor_id</p>";
        exit;
    }
    
    echo "<p>Professor found: {$professor['name']} ({$professor['email']})</p>";
    
    // Now check what entries exist in the professor_subject table
    $stmt = $pdo->prepare("SELECT * FROM professor_subject WHERE professor_id = ?");
    $stmt->execute([$professor_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assignments)) {
        echo "<p>No subject assignments found for this professor in professor_subject table.</p>";
    } else {
        echo "<p>Found " . count($assignments) . " subject assignments:</p>";
        echo "<ul>";
        foreach ($assignments as $assignment) {
            echo "<li>professor_id: {$assignment['professor_id']}, subject_id: {$assignment['subject_id']}</li>";
        }
        echo "</ul>";
    }
    
    // Finally, get the actual subject details
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.code 
        FROM subjects s
        INNER JOIN professor_subject ps ON s.id = ps.subject_id
        WHERE ps.professor_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$professor_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subjects)) {
        echo "<p>No subjects found for this professor after JOIN.</p>";
    } else {
        echo "<p>Found " . count($subjects) . " subjects after JOIN:</p>";
        echo "<ul>";
        foreach ($subjects as $subject) {
            echo "<li>ID: {$subject['id']}, Name: {$subject['name']}, Code: {$subject['code']}</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database Error: " . $e->getMessage() . "</p>";
} 