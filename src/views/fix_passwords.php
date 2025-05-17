<?php
require_once '../includes/db.php';
session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle password change submission
$message = '';
$messageType = '';
$selectedUser = null;
$searchResults = [];

// Search for a user
if (isset($_POST['search_user']) && isset($_POST['email_search'])) {
    $email_search = trim($_POST['email_search']);
    
    try {
        $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
        $stmt->execute([$email_search]);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($searchResults)) {
            $message = "Aucun utilisateur trouvé avec l'email: " . htmlspecialchars($email_search);
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
        $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
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
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <style>
        * {
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Remove transitions for inputs to eliminate animations */
        input {
            transition: all 0.6s ease !important;
        }
        
        /* Target both inputs specifically */
        input[name="email_search"], 
        input[name="new_password"] {
            border: 1px solid #d1d5db !important;
            border-radius: 0.375rem !important;
        }
        
        /* Simple yellow focus style without animations */
        input:focus,
        input[name="email_search"]:focus, 
        input[name="new_password"]:focus {
            outline: none !important;
            border-color: #eab308 !important;
            box-shadow: 0 0 10px rgba(234, 179, 8, 0.3) !important;
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
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .user-card {
            transition: all 0.3s ease;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        }
        
        .toast-success {
            background-color: #10b981;
            color: white;
        }
        
        .toast-error {
            background-color: #ef4444;
            color: white;
        }
        
        /* Search styles */
        .highlight {
            background-color: #fef3c7;
            font-weight: 500;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 640px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .user-card {
                margin-bottom: 1rem;
            }
            
            .search-box {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-yellow-600 to-yellow-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Modifier les Mots de Passe</h1>
                <a href="../admin/index.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm">
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <?php if ($message): ?>
            <div id="toast" class="toast <?php echo $messageType === 'success' ? 'toast-success' : 'toast-error'; ?>">
                <div class="flex items-center">
                    <?php if ($messageType === 'success'): ?>
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    <?php endif; ?>
                    <?php echo $message; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-6">
                <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold">Modification des Mots de Passe</h2>
                    <p class="text-gray-600 mt-1">Recherchez un utilisateur par email puis modifiez son mot de passe</p>
                </div>
            </div>
            
            <?php if (!$selectedUser): ?>
                <!-- Step 1: Search for a user -->
                <form method="post" action="" class="mb-6">
                    <div class="mb-4">
                        <label for="email_search" class="block text-sm font-medium text-gray-700 mb-1">Email utilisateur:</label>
                        <div class="mt-1 relative rounded-md">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                            <input type="text" name="email_search" id="email_search" class="pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Entrez l'email à rechercher" required>
                        </div>
                    </div>
                    <button type="submit" name="search_user" class="w-full md:w-auto bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-md transition duration-300 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Rechercher
                    </button>
                </form>
                
                <!-- Show search results if any -->
                <?php if (isset($searchResults) && !empty($searchResults)): ?>
                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Résultats de recherche</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
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
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="role-badge <?php echo $role_class; ?>">
                                                        <?php echo htmlspecialchars($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="?user_id=<?php echo $user['id']; ?>" class="text-yellow-600 hover:text-yellow-900">Sélectionner</a>
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
                <div class="mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 flex flex-col">
                                <h3 class="text-sm font-medium text-blue-800">Modification du mot de passe pour:</h3>
                                <div class="mt-2 flex items-center">
                                    <span class="font-medium text-blue-800 mr-2"><?php echo htmlspecialchars($selectedUser['email']); ?></span>
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
                    
                    <form method="post" action="" class="mt-4 password-form">
                        <input type="hidden" name="user_id" value="<?php echo $selectedUser['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe:</label>
                            <div class="relative">
                                <input type="password" name="new_password" id="new_password" autocomplete="new-password" required class="block w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Entrez le nouveau mot de passe">
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <img src="../assets/images/eye-show-svgrepo-com.svg" alt="Toggle Password" class="h-5 w-5">
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button type="submit" name="change_password" class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-md transition duration-300 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                Modifier le mot de passe
                            </button>
                            <a href="fix_passwords.php" class="text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-md transition duration-300 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show toast notification and auto-hide after 3 seconds
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 3000);
            }
            
            // Toggle password visibility
            const toggleButtons = document.querySelectorAll('.toggle-password');
            if (toggleButtons) {
                toggleButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const input = this.closest('.relative').querySelector('input');
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        // Toggle eye icon
                        const img = this.querySelector('img');
                        if (type === 'text') {
                            // Show the "hide" icon when password is visible
                            img.src = "../assets/images/eye-off-svgrepo-com.svg";
                        } else {
                            // Show the "show" icon when password is hidden
                            img.src = "../assets/images/eye-show-svgrepo-com.svg";
                        }
                    });
                });
            }
        });
    </script>
</body>
</html> 