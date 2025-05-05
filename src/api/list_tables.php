<?php
// This script lists all tables in the database
require_once '../includes/db.php';

echo "<h2>All Tables in Database</h2>";

try {
    // Get all tables in the database
    $query = "SHOW TABLES";
    $stmt = $pdo->query($query);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<p>No tables found in database.</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
    // Try to find a table that looks like it might contain professor-subject relationships
    echo "<h2>Looking for Professor-Subject Relationships</h2>";
    
    foreach ($tables as $table) {
        // Skip users and subjects tables
        if ($table == 'users' || $table == 'subjects') {
            continue;
        }
        
        // Get the table structure
        $query = "DESCRIBE `$table`";
        $stmt = $pdo->query($query);
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Look for professor_id and subject_id columns
        $hasProfessorId = false;
        $hasSubjectId = false;
        
        foreach ($columns as $column) {
            if (strpos($column, 'professor_id') !== false) {
                $hasProfessorId = true;
            }
            if (strpos($column, 'subject_id') !== false) {
                $hasSubjectId = true;
            }
        }
        
        // If this table has both columns, show its contents
        if ($hasProfessorId && $hasSubjectId) {
            echo "<p>Found potential table: <strong>$table</strong></p>";
            
            // Show table structure
            $query = "DESCRIBE `$table`";
            $stmt = $pdo->query($query);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Show table contents
            $query = "SELECT * FROM `$table` LIMIT 10";
            $stmt = $pdo->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Table Contents (up to 10 rows):</h3>";
            if (empty($rows)) {
                echo "<p>No data in table.</p>";
            } else {
                echo "<table border='1'>";
                // Header row
                echo "<tr>";
                foreach (array_keys($rows[0]) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                
                // Data rows
                foreach ($rows as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>$value</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<p>Database Error: " . $e->getMessage() . "</p>";
} 