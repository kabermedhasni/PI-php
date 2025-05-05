<?php
/**
 * Path Validation Utility
 * 
 * This script checks that all required files and directories exist after the
 * code reorganization. It helps identify any missing files or incorrect paths.
 */

echo "<h1>Path Validation Check</h1>";
echo "<p>This utility checks that all required files and directories exist after the code reorganization.</p>";

// Define required directories
$requiredDirs = [
    'src/admin',
    'src/api',
    'src/assets',
    'src/assets/css', 
    'src/assets/js',
    'src/assets/images',
    'src/core',
    'src/includes',
    'src/models',
    'src/timetable_data',
    'src/utils',
    'src/views'
];

// Define required files
$requiredFiles = [
    'src/index.php',
    'src/includes/db.php',
    'src/admin/index.php',
    'src/api/get_timetable.php',
    'src/api/save_timetable.php',
    'src/api/publish_timetable.php',
    'src/views/login.php',
    'src/views/logout.php',
    'src/views/admin_timetable.php',
    'src/views/timetable_view.php',
    'src/views/professor.php',
    'src/views/student.php',
    'src/utils/clear_timetables.php',
    'src/utils/fix_passwords.php',
    'src/assets/css/style.css',
    'src/assets/js/main.js',
    '.htaccess'
];

// Check directories
echo "<h2>Checking Directories</h2>";
echo "<ul>";
foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "<li>✅ <strong>{$dir}</strong> exists</li>";
    } else {
        echo "<li>❌ <strong>{$dir}</strong> does not exist!</li>";
    }
}
echo "</ul>";

// Check files
echo "<h2>Checking Files</h2>";
echo "<ul>";
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<li>✅ <strong>{$file}</strong> exists</li>";
    } else {
        echo "<li>❌ <strong>{$file}</strong> does not exist!</li>";
    }
}
echo "</ul>";

// Check database connection
echo "<h2>Testing Database Connection</h2>";
try {
    require_once '../includes/db.php';
    echo "<p>✅ Successfully connected to the database.</p>";
    
    // Check for years and groups tables
    $yearsStmt = $pdo->query("SELECT COUNT(*) FROM `years`");
    $groupsStmt = $pdo->query("SELECT COUNT(*) FROM `groups`");
    
    $yearCount = $yearsStmt->fetchColumn();
    $groupCount = $groupsStmt->fetchColumn();
    
    echo "<p>Found {$yearCount} years and {$groupCount} groups in the database.</p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Suggest next steps
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>If any files or directories are missing, create them according to the README.md structure.</li>";
echo "<li>Verify that the .htaccess file is properly configured for your server.</li>";
echo "<li>Test the application by logging in with different user roles.</li>";
echo "</ol>";
?> 