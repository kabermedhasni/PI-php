<?php
session_start();
require_once '../includes/db.php';

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Journaliser la tentative d'accès non autorisé
    if (isset($_SESSION['user_id'])) {
        error_log("Tentative d'accès non autorisé à admin.php par l'utilisateur ID: " . $_SESSION['user_id']);
    } else {
        error_log("Tentative d'accès non autorisé à admin.php (pas de session)");
    }
    
    // Redirection vers la page de connexion
    header("Location: ../views/login.php");
    exit;
}

// Obtenir les informations de l'administrateur
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // Cela ne devrait pas se produire si les vérifications de session fonctionnent, mais juste au cas où
        error_log("Utilisateur admin non trouvé dans la base de données malgré une session valide. ID utilisateur: " . $_SESSION['user_id']);
        session_destroy();
        header("Location: ../views/login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur de base de données dans admin.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Modal animations */
        .modal {
            backdrop-filter: blur(0px);
            transition: backdrop-filter 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
        }
        
        .modal.fade-in {
            animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            backdrop-filter: blur(5px);
        }
        
        .modal.fade-out {
            animation: fadeOut 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            backdrop-filter: blur(0px);
        }
        
        .modal-content {
            transform: translateY(-30px) scale(0.95);
            opacity: 0;
        }
        
        .modal.fade-in .modal-content {
            animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        
        .modal.fade-out .modal-content {
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-30px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            to {
                transform: translateY(10px) scale(0.95);
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-700 to-blue-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Tableau de Bord Admin</h1>
                <a href="../views/logout.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm">Déconnexion</a>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <!-- Afficher un message de succès si l'opération a été effectuée -->
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'cleared'): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-8" role="alert">
                <p class="font-bold">Succès !</p>
                <p>Toutes les données d'emploi du temps ont été effacées avec succès.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Bienvenue, <?php echo htmlspecialchars($admin['email']); ?> !</h2>
            <p class="text-gray-600">Utilisez les outils ci-dessous pour gérer le système d'emploi du temps.</p>
        </div>
        
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Gestion des Emplois du Temps</h2>
            <div class="flex space-x-3">
                <form action="../utils/clear_timetables.php" method="POST" id="clear-form" class="m-0">
                    <button type="button" id="clear-button" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-300 text-sm flex items-center">
                        <svg id="clear-icon-default" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <svg id="clear-icon-loading" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 animate-spin hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span id="clear-text">Effacer Tous les Emplois du Temps</span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Modal de confirmation pour Effacer Tous les Emplois du Temps -->
        <div id="clear-modal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
            <div class="modal-content bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-bold text-red-600 mb-2">Confirmer l'Action</h3>
                <p class="text-gray-700 mb-4">Êtes-vous sûr de vouloir effacer TOUTES les données d'emploi du temps ? Cette action ne peut pas être annulée.</p>
                <div class="flex justify-end space-x-3">
                    <button id="clear-cancel" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button id="clear-confirm" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Oui, Effacer Toutes les Données
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        // Modal animation functions
        function showModalWithAnimation(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            
            // Force reflow
            void modal.offsetWidth;
            
            modal.classList.add('fade-in');
            modal.classList.remove('fade-out');
        }
        
        function closeModalWithAnimation(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('fade-in');
            modal.classList.add('fade-out');
            
            // Hide after animation completes
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('fade-out');
            }, 300);
        }
        
        // Show the modal when clicking the clear button
        document.getElementById('clear-button').addEventListener('click', function() {
            showModalWithAnimation('clear-modal');
        });
        
        // Hide the modal when clicking cancel
        document.getElementById('clear-cancel').addEventListener('click', function() {
            closeModalWithAnimation('clear-modal');
        });
        
        // Submit the form when confirming
        document.getElementById('clear-confirm').addEventListener('click', function() {
            const form = document.getElementById('clear-form');
            const button = document.getElementById('clear-button');
            const defaultIcon = document.getElementById('clear-icon-default');
            const loadingIcon = document.getElementById('clear-icon-loading');
            const clearText = document.getElementById('clear-text');
            
            // Hide the modal with animation
            closeModalWithAnimation('clear-modal');
            
            // Add delay to match animation time
            setTimeout(() => {
                // Disable the button and show loading state
                button.disabled = true;
                button.classList.add('opacity-75');
                defaultIcon.classList.add('hidden');
                loadingIcon.classList.remove('hidden');
                clearText.textContent = 'Suppression en cours...';
                
                // Submit the form
                form.submit();
            }, 300);
        });
        
        // Close the modal if clicking outside of it
        document.getElementById('clear-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModalWithAnimation('clear-modal');
            }
        });
        </script>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <a href="../views/admin_timetable.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-indigo-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Gérer les Emplois du Temps</h3>
                        <p class="mt-1 text-sm text-gray-500">Créer et modifier les emplois du temps pour toutes les années et groupes</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/timetable_view.php?role=professor&preview=true" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Vue Professeur</h3>
                        <p class="mt-1 text-sm text-gray-500">Prévisualiser l'emploi du temps comme les professeurs le voient</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/timetable_view.php?role=student&preview=true" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Vue Étudiant</h3>
                        <p class="mt-1 text-sm text-gray-500">Prévisualiser l'emploi du temps comme les étudiants le voient</p>
                    </div>
                </div>
            </a>
        </div>
        
        <h2 class="text-xl font-semibold mb-4">Gestion du Système</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="../utils/fix_passwords.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Corriger les Mots de Passe</h3>
                        <p class="mt-1 text-sm text-gray-500">Réinitialiser ou mettre à jour les mots de passe utilisateurs</p>
                    </div>
                </div>
            </a>
            
            <a href="../utils/check_users.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-red-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Vérifier les Utilisateurs</h3>
                        <p class="mt-1 text-sm text-gray-500">Gérer les comptes utilisateurs et les permissions</p>
                    </div>
                </div>
            </a>
        </div>
    </main>
</body>
</html>