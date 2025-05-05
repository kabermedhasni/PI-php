<?php
require_once '../includes/db.php';

// This script should only be accessible to administrators or locally
$is_local = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';
if (!$is_local) {
    echo "This script can only be run locally for security reasons.";
    exit;
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, email, password, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) === 0) {
        echo "<h2>No users found in the database.</h2>";
        echo "<p>You might need to add users first.</p>";
        exit;
    }
    
    echo "<h2>Users in Database</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Password Status</th></tr>";
    
    foreach ($users as $user) {
        $password_info = password_get_info($user['password']);
        $is_hashed = $password_info['algo'] !== 0;
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        
        if ($is_hashed) {
            echo "<td style='color: green;'>Hashed</td>";
        } else {
            echo "<td style='color: red;'>Plain Text</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<p>For password security issues, use the <a href='fix_passwords.php'>fix_passwords.php</a> utility.</p>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 