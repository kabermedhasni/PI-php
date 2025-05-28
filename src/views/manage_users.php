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
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        /* Improved transitions */
        input, select, button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Target inputs specifically */
        input, select {
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
        }
        
        /* Simple green focus style without animations */
        input:focus, select:focus {
            outline: none !important;
            border-color: #10b981 !important;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.3) !important;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-admin {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        
        .badge-professor {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-student {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        /* Animation for form sections */
        .form-section {
            position: relative;
            z-index: 10;
            max-height: 0;
            opacity: 0;
            transform: translateY(-20px);
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1),
                        opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        padding 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none; /* Disable interactions when hidden */
            visibility: hidden;   /* Hide from screen readers and tab order */
        }
        
        .form-section.visible {
            max-height: 3000px;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto; /* Re-enable interactions when visible */
            visibility: visible;  /* Make visible again */
        }
        
        /* Custom dropdown */
        .dropdown-container {
            position: relative;
            width: 100%;
            margin-bottom: 8px;
            z-index: 50; /* Higher z-index for dropdown containers */
        }

        .dropdown-button {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 8px 12px;
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            height: 42px;
            transition: all 0.3s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .dropdown-button:hover {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.1);
        }
        
        .dropdown-button[disabled] {
            cursor: not-allowed;
            background-color: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
        }
        
        .dropdown-button[disabled]:hover {
            border-color: #e2e8f0;
            box-shadow: none;
        }

        .dropdown-button svg {
            transition: transform 0.3s ease;
        }

        .dropdown-button.active svg {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            background-color: #fff;
            border-radius: 0.375rem;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.12);
            max-height: 200px;
            overflow-y: auto;
            opacity: 0;
            transform: translateY(-10px) rotateX(-5deg);
            transform-origin: top center;
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            visibility: hidden;
            display: none;
        }

        .dropdown-menu.open {
            display: block;
            visibility: visible;
            opacity: 1;
            transform: translateY(0) rotateX(0);
            pointer-events: auto;
            animation: menuAppear 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        .dropdown-menu.closing {
            display: block;
            animation: menuClose 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes menuAppear {
            0% {
                opacity: 0;
                transform: translateY(-10px) rotateX(-5deg);
                visibility: visible;
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0);
                visibility: visible;
            }
        }
        
        @keyframes menuClose {
            0% {
                opacity: 1;
                transform: translateY(0) rotateX(0);
                visibility: visible;
            }
            100% {
                opacity: 0;
                transform: translateY(-10px) rotateX(-5deg);
                visibility: hidden;
            }
        }
        
        /* Fix for dropdown animation jank */
        .dropdown-button, .dropdown-menu {
            will-change: transform, opacity;
            backface-visibility: hidden;
        }
        
        /* Prevent interaction during animations */
        .dropdown-menu.closing {
            pointer-events: none;
        }

        .dropdown-item {
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            overflow-x: hidden;
        }

        .dropdown-item:hover {
            background-color: #f0fdf4;
            /* transform: translateX(2px); */
            padding-left: 7px;
        }
        
        /* Custom Checkbox */
        .custom-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.375rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s ease;
            position: relative; /* Ensure proper stacking context */
        }
        
        .custom-checkbox:hover {
            background-color: #f0fdf4;
        }
        
        .custom-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 1.25rem; /* Match visible checkbox size */
            height: 1.25rem;
            cursor: pointer;
            left: 0.375rem; /* Match padding */
            margin: 0;
            z-index: 2; /* Above the visual elements but below labels */
        }
        
        .custom-checkbox .checkbox-icon {
            position: relative;
            display: inline-block;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #d1d5db;
            border-radius: 0.25rem;
            margin-right: 0.5rem;
            transition: all 0.2s ease;
            z-index: 1;
        }
        
        /* Ensure labels are clickable */
        .custom-checkbox label {
            z-index: 3;
            position: relative;
            cursor: pointer;
            padding-left: 0.5rem;
            flex: 1;
        }
        
        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon {
            background-color: #10b981;
            border-color: #10b981;
        }
        
        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon:after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 4px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            transition: all 0.2s ease;
        }
        
        .custom-checkbox input[type="checkbox"]:focus + .checkbox-icon {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            z-index: 2000;
            max-width: 350px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-success {
            background-color: #10b981;
            color: white;
        }
        
        .toast-error {
            background-color: #ef4444;
            color: white;
        }
        
        /* Fix button and other elements to not overlap dropdowns */
        button[type="submit"], 
        .toggle-password,
        .text-center a {
            position: relative;
            z-index: 5; /* Lower z-index than dropdowns */
        }
        
        /* Remove top margin from submit button section */
        form button[type="submit"] {
            margin-top: 0;
        }
        
        /* Fix submit button container */
        form > div:last-child {
            margin-top: 2rem !important;
            position: relative;
            z-index: 5;
        }

        /* Add specificity for different dropdown contexts */
        #student-fields .dropdown-container {
            z-index: 60;
        }
        
        /* Year dropdown needs higher z-index than group */
        #year-dropdown, 
        #year-menu {
            z-index: 70;
        }

        /* Add this to the style section */
        .year-dropdown-container {
            z-index: 70 !important;
        }
        
        /* Additional style to ensure dropdowns appear correctly */
        .dropdown-menu {
            z-index: 1000;
        }
        
        /* Ensure group menu appears above */
        #group-menu {
            z-index: 1001;
        }
        
        /* Fix submit button position */
        form .mt-\[200px\] {
            margin-top: 2rem !important;
            position: relative;
            z-index: 5;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-green-600 to-green-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Gestion des Utilisateurs</h1>
                <div class="flex space-x-4">
                    <a href="check_users.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm">Liste des Utilisateurs</a>
                    <a href="../admin/index.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour au Tableau de Bord
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <div id="toast-notification" class="toast <?php echo !empty($success_message) ? 'toast-success' : (!empty($error_message) ? 'toast-error' : ''); ?>" style="display: none;">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <?php if (!empty($success_message)): ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    <?php else: ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    <?php endif; ?>
                </svg>
                <span id="toast-message"><?php echo !empty($success_message) ? htmlspecialchars($success_message) : htmlspecialchars($error_message); ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-8 max-w-3xl mx-auto">
            <div class="flex items-center mb-6">
                <div class="bg-green-100 p-3 rounded-lg mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold">Création d'un Nouvel Utilisateur</h2>
                    <p class="text-gray-600 mt-1">Remplissez le formulaire pour créer un compte utilisateur</p>
                </div>
            </div>
            
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="action" value="create_user">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <div class="mt-1 relative rounded-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <input type="email" id="email" name="email" required 
                            class="pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                            placeholder="Adresse email de l'utilisateur">
                    </div>
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="mt-1 relative rounded-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input type="text" id="username" name="username" required 
                            class="pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md"
                            value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                            placeholder="Username de l'utilisateur">
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</span></label>
                    <div class="mt-1 relative rounded-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" required 
                            class="pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md"
                            placeholder="Mot de passe">
                        <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <img src="../assets/images/eye-show-svgrepo-com.svg" alt="Toggle Password" class="h-5 w-5">
                        </button>
                    </div>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="role-dropdown">
                            <span id="selected-role">Sélectionner un rôle</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="role-menu">
                            <div class="dropdown-item" data-value="admin">Administrateur</div>
                            <div class="dropdown-item" data-value="professor">Professeur</div>
                            <div class="dropdown-item" data-value="student">Étudiant</div>
                        </div>
                        <input type="hidden" id="role" name="role" required>
                    </div>
                </div>
                
                <!-- Champs pour les étudiants -->
                <div id="student-fields" class="form-section space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Informations Étudiant</h3>
                    
                    <div>
                        <label for="year_id" class="block text-sm font-medium text-gray-700 mb-1">Année</label>
                        <div class="dropdown-container year-dropdown-container">
                            <button type="button" class="dropdown-button" id="year-dropdown">
                                <span id="selected-year">Sélectionner une année</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="year-menu">
                                <?php foreach ($years as $year): ?>
                                    <div class="dropdown-item" data-value="<?php echo $year['id']; ?>">
                                        <?php echo htmlspecialchars($year['name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="year_id" name="year_id">
                        </div>
                    </div>
                    
                    <div>
                        <label for="group_id" class="block text-sm font-medium text-gray-700 mb-1">Groupe</label>
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="group-dropdown" disabled>
                                <span id="selected-group">Sélectionner une année d'abord</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="group-menu">
                                <!-- Les groupes seront ajoutés par JavaScript en fonction de l'année sélectionnée -->
                            </div>
                            <input type="hidden" id="group_id" name="group_id" required>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Obligatoire pour les étudiants</p>
                    </div>
                </div>
                
                <!-- Champs pour les professeurs -->
                <div id="professor-fields" class="form-section space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Matières Enseignées</h3>
                    
                    <div class="max-h-60 overflow-y-auto border border-gray-300 rounded-md p-4">
                        <?php foreach ($subjects as $subject): ?>
                            <div class="custom-checkbox">
                                <input type="checkbox" id="subject_<?php echo $subject['id']; ?>" name="subject_ids[]" 
                                    value="<?php echo $subject['id']; ?>" class="subject-checkbox">
                                <span class="checkbox-icon"></span>
                                <label for="subject_<?php echo $subject['id']; ?>" class="text-sm text-gray-700">
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 active:[transform:scale(0.99)] text-white font-medium py-2 px-4 rounded-md transition duration-300 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser les dropdowns
            initDropdowns();
            
            // Données pour les groupes par année
            const groupsByYear = <?php echo json_encode($groupsByYear); ?>;
            
            // Afficher le toast si nécessaire
            if ('<?php echo $success_message; ?>' || '<?php echo $error_message; ?>') {
                showToast('<?php echo $success_message ? "success" : "error"; ?>', 
                        '<?php echo addslashes($success_message ?: $error_message); ?>');
            }
            
            // Gérer les changements de rôle
            document.getElementById('role-dropdown').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Prevent multiple click glitches
                if (this.getAttribute('data-processing') === 'true') return;
                this.setAttribute('data-processing', 'true');
                
                toggleDropdown(this, document.getElementById('role-menu'));
                
                // Reset processing flag after animation completes
                setTimeout(() => {
                    this.removeAttribute('data-processing');
                }, 350);
            });
            
            // Gérer les sélections de rôle
            document.querySelectorAll('#role-menu .dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event bubbling
                    const role = this.getAttribute('data-value');
                    document.getElementById('selected-role').textContent = this.textContent;
                    document.getElementById('role').value = role;
                    
                    // Close the dropdown after selection
                    const button = document.getElementById('role-dropdown');
                    const menu = document.getElementById('role-menu');
                    button.classList.remove("active");
                    menu.classList.remove("open");
                    menu.classList.add("closing");
                    
                    setTimeout(() => {
                        menu.classList.remove("closing");
                        menu.style.display = "none";
                    }, 300);
                    
                    // Masquer tous les champs spécifiques
                    hideAllRoleFields();
                    
                    // Afficher les champs correspondant au rôle
                    if (role === 'student') {
                        showStudentFields();
                    } else if (role === 'professor') {
                        showProfessorFields();
                    }
                });
            });
            
            // Gestion du dropdown de l'année (pour les étudiants)
            document.getElementById('year-dropdown').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Prevent multiple click glitches
                if (this.getAttribute('data-processing') === 'true') return;
                this.setAttribute('data-processing', 'true');
                
                toggleDropdown(this, document.getElementById('year-menu'));
                
                // Reset processing flag after animation completes
                setTimeout(() => {
                    this.removeAttribute('data-processing');
                }, 350);
            });
            
            // Gestion des sélections d'année
            document.querySelectorAll('#year-menu .dropdown-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event bubbling
                    const yearId = this.getAttribute('data-value');
                    document.getElementById('selected-year').textContent = this.textContent;
                    document.getElementById('year_id').value = yearId;
                    
                    // Close the dropdown after selection
                    const button = document.getElementById('year-dropdown');
                    const menu = document.getElementById('year-menu');
                    button.classList.remove("active");
                    menu.classList.remove("open");
                    menu.classList.add("closing");
                    
                    setTimeout(() => {
                        menu.classList.remove("closing");
                        menu.style.display = "none";
                    }, 300);
                    
                    // Activer le dropdown de groupe
                    const groupDropdown = document.getElementById('group-dropdown');
                    groupDropdown.removeAttribute('disabled');
                    groupDropdown.style.backgroundColor = '#ffffff';
                    groupDropdown.style.cursor = 'pointer';
                    
                    // Réinitialiser la sélection de groupe
                    document.getElementById('selected-group').textContent = 'Sélectionner un groupe';
                    document.getElementById('group_id').value = '';
                    
                    // Remplir les groupes pour cette année
                    updateGroupsDropdown(yearId);
                });
            });
            
            // Gestion du dropdown de groupe
            document.getElementById('group-dropdown').addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Ne pas activer si désactivé
                if (this.hasAttribute('disabled')) {
                    return;
                }
                
                // Prevent multiple click glitches
                if (this.getAttribute('data-processing') === 'true') return;
                this.setAttribute('data-processing', 'true');
                
                toggleDropdown(this, document.getElementById('group-menu'));
                
                // Reset processing flag after animation completes
                setTimeout(() => {
                    this.removeAttribute('data-processing');
                }, 350);
            });
            
            // Toggle du mot de passe
            const toggleButton = document.querySelector('.toggle-password');
            if (toggleButton) {
                toggleButton.addEventListener('click', function() {
                    const input = document.getElementById('password');
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    
                    // Toggle de l'icône
                    const img = this.querySelector('img');
                    if (type === 'text') {
                        // Afficher l'icône "masquer" quand le mot de passe est visible
                        img.src = "../assets/images/eye-off-svgrepo-com.svg";
                    } else {
                        // Afficher l'icône "afficher" quand le mot de passe est masqué
                        img.src = "../assets/images/eye-show-svgrepo-com.svg";
                    }
                });
            }
            
            // Gestion des clicks sur les custom checkboxes
            document.querySelectorAll('.custom-checkbox').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    // Si le click n'est pas sur l'input lui-même
                    if (e.target.tagName !== 'INPUT') {
                        const input = this.querySelector('input[type="checkbox"]');
                        input.checked = !input.checked;
                        
                        // Empêcher l'événement de remonter
                        e.stopPropagation();
                        e.preventDefault();
                    }
                });
                
                // Make sure the label gets the click
                const label = checkbox.querySelector('label');
                if (label) {
                    label.addEventListener('click', function(e) {
                        const input = checkbox.querySelector('input[type="checkbox"]');
                        input.checked = !input.checked;
                        e.stopPropagation();
                        e.preventDefault();
                    });
                }
            });
            
            // Fermer les dropdown lors d'un clique à l'extérieur
            document.addEventListener('click', function(e) {
                // Don't close if clicking inside a dropdown or on a dropdown button
                if (e.target.closest('.dropdown-menu') || e.target.closest('.dropdown-button')) {
                    return;
                }
                closeAllDropdowns();
            });
            
            // Fonction pour initialiser les dropdowns
            function initDropdowns() {
                // Si on a des valeurs POST de la soumission précédente, restaurer l'état
                <?php if (isset($role) && !empty($role)): ?>
                    const savedRole = '<?php echo $role; ?>';
                    document.getElementById('selected-role').textContent = 
                        savedRole === 'admin' ? 'Administrateur' : 
                        (savedRole === 'professor' ? 'Professeur' : 'Étudiant');
                    document.getElementById('role').value = savedRole;
                    
                    if (savedRole === 'student') {
                        showStudentFields();
                        <?php if (isset($group_id) && !empty($group_id)): ?>
                            // TODO: Restore group selection
                        <?php endif; ?>
                    } else if (savedRole === 'professor') {
                        showProfessorFields();
                    }
                <?php endif; ?>
            }
            
            // Fonction pour basculer l'état du dropdown
            function toggleDropdown(button, menu) {
                // Check if the dropdown is already in a transition state
                if (menu.classList.contains("closing")) {
                    return false; // Don't do anything if the dropdown is in the middle of closing
                }
                
                // If the dropdown is already open, close it
                if (menu.classList.contains("open")) {
                    // Fermer le dropdown
                    button.classList.remove("active");
                    menu.classList.remove("open");
                    menu.classList.add("closing");
                    
                    // Après l'animation, cacher complètement
                    setTimeout(() => {
                        menu.classList.remove("closing");
                        menu.style.display = "none";
                    }, 300); // Correspond à la durée de l'animation
                    
                    return false;
                } else {
                    // Fermer tous les autres dropdowns d'abord
                    closeAllDropdowns();
                    
                    // Configure z-index for this dropdown
                    const allMenus = document.querySelectorAll('.dropdown-menu');
                    allMenus.forEach(m => {
                        m.style.zIndex = "1000";
                    });
                    
                    // Special handling for year and group dropdowns
                    if (button.id === 'year-dropdown') {
                        menu.style.zIndex = "1500";
                    } else if (button.id === 'group-dropdown') {
                        menu.style.zIndex = "1400";
                    } else if (button.id === 'role-dropdown') {
                        menu.style.zIndex = "1300";
                    }
                    
                    // Ouvrir le dropdown
                    button.classList.add("active");
                    menu.style.display = "block"; // Rendre visible d'abord
                    
                    // Forcer le repaint pour l'animation
                    void menu.offsetWidth;
                    
                    menu.classList.add("open");
                    return true;
                }
            }
            
            // Fonction pour fermer tous les dropdowns
            function closeAllDropdowns() {
                document.querySelectorAll(".dropdown-button.active").forEach(activeButton => {
                    const menu = activeButton.nextElementSibling;
                    if (menu && menu.classList.contains("open") && !menu.classList.contains("closing")) {
                        activeButton.classList.remove("active");
                        menu.classList.remove("open");
                        menu.classList.add("closing");
                        
                        setTimeout(() => {
                            menu.classList.remove("closing");
                            menu.style.display = "none";
                        }, 300);
                    }
                });
            }
            
            // Fonction pour masquer tous les champs spécifiques
            function hideAllRoleFields() {
                const studentFields = document.getElementById('student-fields');
                const professorFields = document.getElementById('professor-fields');
                
                studentFields.classList.remove('visible');
                professorFields.classList.remove('visible');
                
                // Réinitialiser les champs hidden
                document.getElementById('group_id').removeAttribute('required');
            }
            
            // Fonction pour afficher les champs étudiant
            function showStudentFields() {
                const studentFields = document.getElementById('student-fields');
                studentFields.classList.add('visible');
                
                // Ajouter required aux champs obligatoires
                document.getElementById('group_id').setAttribute('required', 'required');
            }
            
            // Fonction pour afficher les champs professeur
            function showProfessorFields() {
                const professorFields = document.getElementById('professor-fields');
                professorFields.classList.add('visible');
            }
            
            // Fonction pour mettre à jour le dropdown des groupes en fonction de l'année
            function updateGroupsDropdown(yearId) {
                const groupMenu = document.getElementById('group-menu');
                groupMenu.innerHTML = '';
                
                // S'il y a des groupes pour cette année
                if (groupsByYear[yearId] && groupsByYear[yearId].length > 0) {
                    groupsByYear[yearId].forEach(group => {
                        const item = document.createElement('div');
                        item.className = 'dropdown-item';
                        item.setAttribute('data-value', group.id);
                        item.textContent = group.name;
                        
                        item.addEventListener('click', function(e) {
                            e.stopPropagation(); // Prevent event bubbling
                            document.getElementById('selected-group').textContent = this.textContent;
                            document.getElementById('group_id').value = this.getAttribute('data-value');
                            // Close the dropdown after selection
                            const button = document.getElementById('group-dropdown');
                            const menu = document.getElementById('group-menu');
                            button.classList.remove("active");
                            menu.classList.remove("open");
                            menu.classList.add("closing");
                            
                            setTimeout(() => {
                                menu.classList.remove("closing");
                                menu.style.display = "none";
                            }, 300);
                        });
                        
                        groupMenu.appendChild(item);
                    });
                } else {
                    // Pas de groupes trouvés
                    const noItem = document.createElement('div');
                    noItem.className = 'dropdown-item';
                    noItem.style.color = '#888';
                    noItem.textContent = 'Aucun groupe disponible pour cette année';
                    groupMenu.appendChild(noItem);
                }
            }
            
            // Fonction pour afficher un toast
            function showToast(type, message) {
                const toast = document.getElementById('toast-notification');
                if (!toast) return;
                
                toast.className = 'toast';
                
                if (type === 'success') {
                    toast.classList.add('toast-success');
                    toast.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                } else {
                    toast.classList.add('toast-error');
                    toast.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                }
                
                document.getElementById('toast-message').textContent = message;
                toast.style.display = 'block';
                
                // Force repaint
                void toast.offsetWidth;
                
                // Show animation
                toast.classList.add('show');
                
                // Auto-hide after 4 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 300);
                }, 4000);
            }
        });
    </script>
</body>
</html> 