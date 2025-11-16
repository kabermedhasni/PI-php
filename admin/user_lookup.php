<?php
require_once '../core/db.php';
session_start();

// This script should only be accessible from specific IPs
/*$client_ip = $_SERVER['REMOTE_ADDR'];
$allowed_ips = ['41.188.115.42'];

if (!in_array($client_ip, $allowed_ips)) {
    echo "This script can only be accessed from authorized devices.";
    exit;
}*/

// Auto-reset the auto-increment value to prevent gaps
function resetAutoIncrement($pdo) {
    try {
        // Get the maximum ID
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM users");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        
        // Get current auto-increment value
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'users'");
        $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentAutoIncrement = $tableStatus['Auto_increment'];
        
        // Only reset if there's a gap
        if ((int)$currentAutoIncrement > (int)$maxId + 1) {
            $pdo->exec("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Failed to reset auto-increment: " . $e->getMessage());
        return false;
    }
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth.php");
    exit;
}

// Automatically reset auto-increment on page load
resetAutoIncrement($pdo);

// Initialize message variables
$success_message = '';
$error_message = '';

// Process messages from the delete operation
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success_message = "L'utilisateur a été supprimé avec succès.";
} elseif (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, email, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier les Utilisateurs</title>
    <link rel="icon" href="../assets/images/logo-supnum.png" />
    <link rel="stylesheet" href="../assets/css/pages/user_lookup.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Vérifier les Utilisateurs</h1>
                <div class="header-actions">
                    <a href="user_management.php" class="header-btn">
                        Créer un Utilisateur
                    </a>
                    <a href="../admin/index.php" class="header-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour au Tableau de Bord
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Toast Notification -->
    <div id="toast-notification" class="toast <?php echo !empty($success_message) ? 'toast-success' : (!empty($error_message) ? 'toast-error' : ''); ?>" style="display: none;">
        <div style="display: flex; align-items: center;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <?php if (!empty($success_message)): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                <?php endif; ?>
            </svg>
            <span id="toast-message"><?php echo !empty($success_message) ? htmlspecialchars($success_message) : htmlspecialchars($error_message); ?></span>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="delete-modal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirmation de suppression</h3>
                <button type="button" id="close-modal" class="modal-close">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p id="delete-message" class="modal-text">Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete-user-email"></strong> ?</p>
                <div id="delete-multiple-info" class="modal-info hidden">
                    <p>Vous avez sélectionné <strong id="selected-count">0</strong> utilisateurs.</p>
                    <ul id="selected-users-list" class="selected-users-list">
                        <!-- Selected users will be listed here -->
                    </ul>
                </div>
                <p class="modal-warning">Cette action est irréversible.</p>
            </div>
            <div class="modal-actions">
                <button type="button" id="cancel-delete" class="modal-btn modal-btn-cancel">
                    Annuler
                </button>
                <button type="button" id="confirm-delete" class="modal-btn modal-btn-confirm">
                    Supprimer
                </button>
            </div>
        </div>
    </div>
    
    <main>
        <div class="content-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="card-title">Utilisateurs dans la Base de Données</h2>
            </div>
            
            <!-- Search Box -->
            <div class="search-container">
                <div class="search-wrapper">
                    <div class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input id="email-search" type="text" class="search-box" placeholder="Rechercher par email...">
                </div>
                <div class="search-info">
                    <p id="search-results" class="search-results">Affichage de <span id="count-display"><?php echo count($users); ?></span> utilisateurs</p>
                    
                    <button id="bulk-delete-btn" class="bulk-delete-btn" disabled>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer les sélectionnés (<span id="selected-counter">0</span>)
                    </button>
                </div>
            </div>
            
            <?php if (count($users) === 0): ?>
                <div class="alert alert-warning">
                    <div style="display: flex;">
                        <div class="alert-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="alert-content">
                            <p class="alert-text">
                                Aucun utilisateur trouvé dans la base de données.
                            </p>
                            <p class="alert-text" style="margin-top: 0.5rem;">
                                Vous devrez peut-être ajouter des utilisateurs d'abord.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <div class="custom-checkbox">
                                        <input type="checkbox" id="select-all-checkbox">
                                        <span class="checkbox-icon"></span>
                                    </div>
                                </th>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php foreach ($users as $user): 
                                // Set role badge class
                                $role_class = 'badge-student';
                                if ($user['role'] === 'admin') {
                                    $role_class = 'badge-admin';
                                } elseif ($user['role'] === 'professor') {
                                    $role_class = 'badge-professor';
                                }
                                
                                // Don't allow deletion of self
                                $is_current_user = $user['id'] == $_SESSION['user_id'];
                            ?>
                                <tr class="user-row" 
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                    <?php if ($is_current_user): ?>data-current-user="true"<?php endif; ?>>
                                    <td data-label="Sélection" class="checkbox-column">
                                        <?php if (!$is_current_user): ?>
                                        <div class="custom-checkbox">
                                        <input type="checkbox" 
                                                class="user-checkbox" 
                                                id="user-<?php echo htmlspecialchars($user['id']); ?>"
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                            <span class="checkbox-icon"></span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="font-medium email-cell" data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td data-label="Rôle">
                                        <span class="role-badge <?php echo $role_class; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <?php if (!$is_current_user): ?>
                                            <button 
                                                class="delete-btn" 
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>" 
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            >
                                                <svg class="" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Supprimer
                                            </button>
                                        <?php else: ?>
                                            <span class="current-badge">Compte actuel</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info" style="margin-top: 1.5rem;">
                    <p class="alert-text">
                        Pour les problèmes de sécurité des mots de passe, utilisez l'utilitaire 
                        <a href="password_reset.php" class="alert-link">Changer les mots de passe</a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/user_lookup.js"></script>
</body>
</html>
<?php
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>