<?php
require_once '../core/db.php';
session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth.php");
    exit;
}

// Handle password change submission
$message = '';
$messageType = '';
$selectedUser = null;
$searchResults = [];

// Search for a user
if (isset($_POST['search_user']) && isset($_POST['user_search'])) {
    $user_search = trim($_POST['user_search']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, role, email FROM users WHERE name = ?");
        $stmt->execute([$user_search]);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($searchResults)) {
            $message = "Aucun utilisateur trouvé avec c'est username: " . htmlspecialchars($user_search);
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Erreur de recherche: " . $e->getMessage();
        $messageType = "error";
    }
}

// Select a specific user
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, role, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selectedUser) {
            $message = "Utilisateur non trouvé.";
            $messageType = "error";
        }
} catch (PDOException $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = "error";
    }
}

// Change password
if (isset($_POST['change_password']) && isset($_POST['user_id']) && isset($_POST['new_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    // Hash the password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashed_password, $user_id]);
        
        if ($result) {
            $message = "Le mot de passe a été modifié avec succès.";
            $messageType = "success";
            
            // Get the updated user info
            $stmt = $pdo->prepare("SELECT id, name, role, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Échec de la modification du mot de passe.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier les Mots de Passe</title>
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <link rel="stylesheet" href="../assets/css/pages/password_reset.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Modifier les Mots de Passe</h1>
                <a href="../admin/index.php" class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </header>
    
    <main>
        <?php if ($message): ?>
            <div id="toast" class="toast <?php echo $messageType === 'success' ? 'toast-success' : 'toast-error'; ?>">
                <div class="toast-content">
                    <?php if ($messageType === 'success'): ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    <?php else: ?>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    <?php endif; ?>
                    <?php echo $message; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="page-header">
                <div class="icon-container">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <div class="header-text">
                    <h2>Modification des Mots de Passe</h2>
                    <p>Recherchez un utilisateur par son username puis modifiez son mot de passe</p>
                </div>
            </div>
            
            <?php if (!$selectedUser): ?>
                <!-- Step 1: Search for a user -->
                <form method="post" action="">
                    <div class="form-group">
                        <label for="user_search" class="form-label">Username utilisateur:</label>
                        <div class="input-wrapper">
                            <div class="input-icon-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            </div>
                            <input type="text" name="user_search" id="user_search" placeholder="Entrez le username à rechercher" required>
                        </div>
                    </div>
                    <button type="submit" name="search_user" class="btn btn-primary btn-full">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Rechercher
                    </button>
                </form>
                
                <!-- Show search results if any -->
                <?php if (isset($searchResults) && !empty($searchResults)): ?>
                    <div class="table-container">
                        <h3 class="table-title">Résultats de recherche</h3>
                        <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Rôle</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($searchResults as $user): 
                                            // Set role badge class
                                            $role_class = 'badge-student';
                                            if ($user['role'] === 'admin') {
                                                $role_class = 'badge-admin';
                                            } elseif ($user['role'] === 'professor') {
                                                $role_class = 'badge-professor';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                </td>
                                                <td>
                                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </td>
                                                <td>
                                                    <span class="role-badge <?php echo $role_class; ?>">
                                                        <?php echo htmlspecialchars($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?user_id=<?php echo $user['id']; ?>" class="table-link">Sélectionner</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Step 2: Change password for selected user -->
                <div>
                    <div class="info-box">
                        <div class="info-box-content">
                            <div class="info-box-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="info-box-text">
                                <h3 class="info-box-title">Modification du mot de passe pour:</h3>
                                <div class="info-box-details">
                                    <span class="info-box-username"><?php echo htmlspecialchars($selectedUser['name']); ?></span>
                                    <span class="role-badge <?php
                                        if ($selectedUser['role'] === 'admin') echo 'badge-admin';
                                        elseif ($selectedUser['role'] === 'professor') echo 'badge-professor';
                                        else echo 'badge-student';
                                    ?>">
                                        <?php echo htmlspecialchars($selectedUser['role']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <input type="hidden" name="user_id" value="<?php echo $selectedUser['id']; ?>">
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">Nouveau mot de passe:</label>
                            <div class="input-wrapper password-field">
                                <input type="password" name="new_password" id="new_password" autocomplete="new-password" required placeholder="Entrez le nouveau mot de passe">
                                <button type="button" class="toggle-password">
                                    <img src="../assets/images/eye-show-svgrepo-com.svg" alt="Toggle Password">
                                </button>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                Modifier le mot de passe
                            </button>
                            <a href="fix_passwords.php" class="btn btn-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Nouvelle recherche
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/fix_passwords.js"></script>
</body>
</html> 