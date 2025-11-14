<?php
session_start();
require_once '../includes/db.php';

// Function to reset auto-increment to prevent gaps
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

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Reset auto-increment on page load
resetAutoIncrement($pdo);

// Variables pour les messages
$success_message = '';
$error_message = '';

// Traitement du formulaire de création d'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';
    $group_id = isset($_POST['group_id']) && !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    
    // Validation
    if (empty($email) || empty($password) || empty($role) || empty($username)) {
        $error_message = "Tous les champs sont obligatoires.";
    } else if ($role === 'student' && empty($group_id)) {
        $error_message = "Vous devez sélectionner un groupe pour un étudiant.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            if ($check_stmt->fetch()) {
                $error_message = "Cet email est déjà utilisé.";
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Début de la transaction
                $pdo->beginTransaction();
                
                // Insérer le nouvel utilisateur
                // Ajouter le champ 'name' avec la valeur de l'email pour résoudre l'erreur
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role, group_id, name) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $hashed_password, $role, ($role === 'student' ? $group_id : null), $username]);
                
                $user_id = $pdo->lastInsertId();
                
                // Si c'est un professeur, gérer les matières assignées
                if ($role === 'professor' && isset($_POST['subject_ids']) && is_array($_POST['subject_ids'])) {
                    $subject_ids = $_POST['subject_ids'];
                    
                    $stmt = $pdo->prepare("INSERT INTO professor_subjects (professor_id, subject_id) VALUES (?, ?)");
                    
                    foreach ($subject_ids as $subject_id) {
                        $stmt->execute([$user_id, $subject_id]);
                    }
                }
                
                // Valider la transaction
                $pdo->commit();
                
                $success_message = "Utilisateur créé avec succès.";
                
                // Réinitialiser les valeurs du formulaire
                $email = '';
                $password = '';
                $role = '';
                $group_id = null;
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $error_message = "Erreur lors de la création de l'utilisateur : " . $e->getMessage();
            error_log("Erreur de création d'utilisateur : " . $e->getMessage());
        }
    }
}

// Récupérer les années pour le formulaire (pour les étudiants)
try {
    // Order by year value instead of alphabetically by name
    $stmt = $pdo->query("SELECT id, name FROM years ORDER BY id");
    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des années: " . $e->getMessage();
    error_log("Erreur de récupération des années : " . $e->getMessage());
    $years = [];
}

// Récupérer les groupes pour le formulaire
try {
    // Récupérer les groupes avec leur année associée
    $stmt = $pdo->query("SELECT g.id, g.name, g.year_id, y.name AS year_name 
                        FROM `groups` g 
                        JOIN years y ON g.year_id = y.id 
                        ORDER BY y.id, g.name");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiser les groupes par année
    $groupsByYear = [];
    foreach ($groups as $group) {
        if (!isset($groupsByYear[$group['year_id']])) {
            $groupsByYear[$group['year_id']] = [];
        }
        $groupsByYear[$group['year_id']][] = [
            'id' => $group['id'],
            'name' => $group['name'],
            'display_name' => $group['name'] . ' (' . $group['year_name'] . ')'
        ];
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des groupes: " . $e->getMessage();
    error_log("Erreur de récupération des groupes : " . $e->getMessage());
    $groups = [];
    $groupsByYear = [];
}

// Récupérer les matières pour le formulaire
try {
    $stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des matières: " . $e->getMessage();
    error_log("Erreur de récupération des matières : " . $e->getMessage());
    $subjects = [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <link rel="stylesheet" href="../assets/css/pages/manage_users.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Gestion des Utilisateurs</h1>
                <div class="header-actions">
                    <a href="check_users.php" class="header-btn">Liste des Utilisateurs</a>
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
    
    <main>
        <div id="toast-notification" class="toast <?php echo !empty($success_message) ? 'toast-success' : (!empty($error_message) ? 'toast-error' : ''); ?>">
            <div class="toast-content">
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
        
        <div class="form-container">
            <div class="form-header">
                <div class="form-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <div class="form-header-text">
                    <h2>Création d'un Nouvel Utilisateur</h2>
                    <p>Remplissez le formulaire pour créer un compte utilisateur</p>
                </div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <input type="email" id="email" name="email" required class="with-icon"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                            placeholder="Adresse email de l'utilisateur">
                    </div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input type="text" id="username" name="username" required class="with-icon" placeholder="Nom d'utilisateur">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="input-wrapper password-field">
                        <div class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" required class="with-icon" placeholder="Mot de passe">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <img src="../assets/images/eye-show-svgrepo-com.svg" alt="Toggle Password" id="toggle-icon">
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">Rôle</label>
                    <input type="hidden" id="role" name="role" required>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="role-dropdown">
                            <span id="selected-role">Sélectionner un rôle</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="width: 1.2rem; height: 1.2rem; color: #999;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="role-menu">
                            <div class="dropdown-item" data-value="admin">Admin</div>
                            <div class="dropdown-item" data-value="professor">Professeur</div>
                            <div class="dropdown-item" data-value="student">Étudiant</div>
                        </div>
                    </div>
                </div>

                <!-- Professor fields (hidden by default) -->
                <div id="professor-fields" class="form-section">
                    <div class="form-group">
                        <label class="form-label">Matières assignées</label>
                        <div class="subjects-grid">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="custom-checkbox">
                                    <input type="checkbox" 
                                        id="subject-<?php echo $subject['id']; ?>" 
                                        name="subject_ids[]" 
                                        value="<?php echo $subject['id']; ?>">
                                    <span class="checkbox-icon"></span>
                                    <label for="subject-<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div id="student-fields" class="form-section">
                    <div class="form-group">
                        <label class="form-label">Année</label>
                        <input type="hidden" id="student-year-id" name="year_id">
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="year-dropdown">
                                <span id="selected-year">Sélectionner une année</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="width: 1.2rem; height: 1.2rem; color: #999;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="year-menu">
                                <?php foreach ($years as $year): ?>
                                    <div class="dropdown-item" data-id="<?php echo $year['id']; ?>" data-name="<?php echo htmlspecialchars($year['name']); ?>"><?php echo htmlspecialchars($year['name']); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Groupe</label>
                        <input type="hidden" id="student-group-id" name="group_id">
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="group-dropdown" disabled>
                                <span id="selected-group">Sélectionner une année d'abord</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" style="width: 1.2rem; height: 1.2rem; color: #999;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="group-menu"></div>
                        </div>
                    </div>
                </div>

                <div class="submit-container">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        window.manageUsersConfig = <?php echo json_encode([
            'years' => $years,
            'groupsByYear' => $groupsByYear,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="../assets/js/manage_users.js"></script>
</body>
</html>
