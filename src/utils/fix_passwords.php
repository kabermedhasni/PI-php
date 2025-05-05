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
    
    echo "<h2>Password Diagnosis for " . count($users) . " users</h2>";
    
    $hashed_count = 0;
    $plaintext_count = 0;
    $unknown_count = 0;
    $fixed_count = 0;
    
    // Check each user's password
    foreach ($users as $user) {
        $password_info = password_get_info($user['password']);
        $is_hashed = $password_info['algo'] !== 0;
        
        echo "<div style='margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;'>";
        echo "<p><strong>User:</strong> " . htmlspecialchars($user['email']) . " (ID: " . $user['id'] . ", Role: " . $user['role'] . ")</p>";
        
        if ($is_hashed) {
            echo "<p style='color: green;'>Password is properly hashed using algorithm: " . $password_info['algoName'] . "</p>";
            $hashed_count++;
        } else {
            // Likely a plaintext password
            echo "<p style='color: red;'>Password appears to be stored as plain text!</p>";
            
            // Ask for confirmation before fixing
            echo "<form method='post' action='fix_passwords.php'>";
            echo "<input type='hidden' name='user_id' value='" . $user['id'] . "'>";
            echo "<input type='hidden' name='current_password' value='" . htmlspecialchars($user['password']) . "'>";
            echo "<button type='submit' name='fix_password'>Hash this password properly</button>";
            echo "</form>";
            
            $plaintext_count++;
        }
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h3>Summary:</h3>";
    echo "<p>Total users: " . count($users) . "</p>";
    echo "<p>Users with properly hashed passwords: " . $hashed_count . "</p>";
    echo "<p>Users with plaintext passwords: " . $plaintext_count . "</p>";
    echo "<p>Users with unknown password format: " . $unknown_count . "</p>";
    
    // Add a simple form to create a test user if needed
    echo "<hr>";
    echo "<h3>Create Test User</h3>";
    echo "<form method='post' action='fix_passwords.php'>";
    echo "<p><input type='email' name='new_email' placeholder='Email' required></p>";
    echo "<p><input type='password' name='new_password' placeholder='Password' required></p>";
    echo "<p><select name='new_role' required>";
    echo "<option value='admin'>Admin</option>";
    echo "<option value='professor'>Professor</option>";
    echo "<option value='student'>Student</option>";
    echo "</select></p>";
    echo "<p><button type='submit' name='create_user'>Create User</button></p>";
    echo "</form>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}

// Handle password fix
if (isset($_POST['fix_password']) && isset($_POST['user_id']) && isset($_POST['current_password'])) {
    $user_id = $_POST['user_id'];
    $current_password = $_POST['current_password'];
    
    // Hash the password
    $hashed_password = password_hash($current_password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user_id]);
        
        if ($result) {
            echo "<p style='color: green;'>Password for user ID " . $user_id . " has been properly hashed!</p>";
            echo "<p><a href='fix_passwords.php'>Refresh</a> to see the updated status.</p>";
        } else {
            echo "<p style='color: red;'>Failed to update password for user ID " . $user_id . "</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
    }
}

// Handle user creation
if (isset($_POST['create_user']) && isset($_POST['new_email']) && isset($_POST['new_password']) && isset($_POST['new_role'])) {
    $email = $_POST['new_email'];
    $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $role = $_POST['new_role'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
        $result = $stmt->execute([$email, $password, $role]);
        
        if ($result) {
            echo "<p style='color: green;'>New " . htmlspecialchars($role) . " user created with email: " . htmlspecialchars($email) . "</p>";
            echo "<p><a href='fix_passwords.php'>Refresh</a> to see the updated user list.</p>";
        } else {
            echo "<p style='color: red;'>Failed to create new user</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
    }
}
?> 