<?php
session_start();
require_once '../../core/db.php';

// Function to reset auto-increment to prevent gaps
function resetAutoIncrement($pdo) {
    try {
        // Get the maximum ID
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM users");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        
        // Set auto-increment to max_id + 1
        $pdo->exec("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
        return true;
    } catch (PDOException $e) {
        error_log("Failed to reset auto-increment: " . $e->getMessage());
        return false;
    }
}

// Verify admin authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if this is a POST request with a user ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // Don't allow deleting self
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte']);
        exit;
    }
    
    try {
        // Start a transaction
        $pdo->beginTransaction();
        
        // First check if user exists and get their role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new PDOException("L'utilisateur n'existe pas");
        }
        
        // If professor, delete entries from professor_subjects first
        if ($user['role'] === 'professor') {
            $stmt = $pdo->prepare("DELETE FROM professor_subjects WHERE professor_id = ?");
            $stmt->execute([$user_id]);
        }
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$user_id]);
        
        if ($result) {
            // Commit the transaction first
            $pdo->commit();
            
            // Reset auto-increment after successful deletion
            resetAutoIncrement($pdo);
            
            echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
        } else {
            throw new PDOException("Échec de la suppression de l'utilisateur");
        }
    } catch (PDOException $e) {
        // Rollback the transaction in case of an error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
} 