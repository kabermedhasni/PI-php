<?php
require_once '../includes/db.php';

// Add color column to subjects table if it doesn't exist
try {
    // First check if the color column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM subjects LIKE 'color'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // The column doesn't exist, so add it
        $pdo->exec("ALTER TABLE subjects ADD COLUMN color VARCHAR(10) DEFAULT '#3b82f6'");
        echo "Added color column to subjects table.<br>";
        
        // Assign different colors to different subjects for better visualization
        $colors = [
            '#3b82f6', // blue
            '#10b981', // green
            '#f59e0b', // yellow
            '#ef4444', // red
            '#8b5cf6', // purple
            '#ec4899', // pink
            '#14b8a6', // teal
            '#f97316'  // orange
        ];
        
        // Get all subjects
        $stmt = $pdo->query("SELECT id FROM subjects");
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Assign colors to subjects
        foreach ($subjects as $index => $subject) {
            $color = $colors[$index % count($colors)];
            $updateStmt = $pdo->prepare("UPDATE subjects SET color = ? WHERE id = ?");
            $updateStmt->execute([$color, $subject['id']]);
        }
        
        echo "Assigned colors to subjects.<br>";
    } else {
        echo "Color column already exists in subjects table.<br>";
    }
} catch (PDOException $e) {
    echo "Error adding color column: " . $e->getMessage() . "<br>";
}

// Check the status column in timetable_entries
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM timetable_entries LIKE 'status'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // The column doesn't exist, so add it
        $pdo->exec("ALTER TABLE timetable_entries ADD COLUMN status ENUM('draft', 'published') DEFAULT 'draft'");
        echo "Added status column to timetable_entries table.<br>";
    } else {
        echo "Status column already exists in timetable_entries table.<br>";
    }
} catch (PDOException $e) {
    echo "Error adding status column: " . $e->getMessage() . "<br>";
}

// Check the created_by column in timetable_entries
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM timetable_entries LIKE 'created_by'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // The column doesn't exist, so add it
        $pdo->exec("ALTER TABLE timetable_entries ADD COLUMN created_by INT DEFAULT 1");
        echo "Added created_by column to timetable_entries table.<br>";
    } else {
        echo "created_by column already exists in timetable_entries table.<br>";
    }
} catch (PDOException $e) {
    echo "Error adding created_by column: " . $e->getMessage() . "<br>";
}

// Display a success message
echo "<br>Database schema update complete. You can now <a href='../views/admin_timetable.php'>go to the admin timetable page</a>."; 