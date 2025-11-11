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
    
    // Récupérer le nombre de cours annulés ou reportés
    $notificationStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM timetables t
        JOIN users u ON t.professor_id = u.id
        JOIN years y ON t.year_id = y.id
        JOIN `groups` g ON t.group_id = g.id
        JOIN subjects s ON t.subject_id = s.id
        WHERE (t.is_canceled = 1 OR t.is_reschedule = 1)
    ");
    $notificationStmt->execute();
    $notificationCount = $notificationStmt->fetchColumn();
    
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
    <link rel="stylesheet" href="../assets/css/pages/admin_index.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Tableau de Bord Admin</h1>
                <a href="../views/logout.php" class="logout-button">Déconnexion</a>
            </div>
        </div>
    </header>
    
    <main>
        <div class="welcome-card">
            <h2 class="welcome-title">Bienvenue, <?php echo htmlspecialchars($admin['email']); ?> !</h2>
            <p class="welcome-text">Utilisez les outils ci-dessous pour gérer le système d'emploi du temps.</p>
        </div>
        
        <div class="section-header">
            <h2 class="section-title">Gestion des Emplois du Temps</h2>
            <div class="button-group">
                <button type="button" id="publish-all-button" class="btn btn-publish">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Publier Tous les Emplois du Temps</span>
                </button>
                <button type="button" id="clear-button" class="btn btn-clear">
                    <svg id="clear-icon-default" class="mobile-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <svg id="clear-icon-loading" class="mobile-icon-small animate-spin hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span id="clear-text">Effacer Tous les Emplois du Temps</span>
                </button>
            </div>
        </div>
        
        <!-- Modal de confirmation pour Effacer Tous les Emplois du Temps -->
        <div id="clear-modal" class="modal hidden">
            <div class="modal-content">
                <h3 class="modal-title red">Confirmer l'Action</h3>
                <p class="modal-text">Êtes-vous sûr de vouloir effacer TOUTES les données d'emploi du temps ? Cette action ne peut pas être annulée.</p>
                <div class="modal-actions">
                    <button id="clear-cancel" class="modal-btn modal-btn-cancel">
                        Annuler
                    </button>
                    <button id="clear-confirm" class="modal-btn modal-btn-confirm red">
                        Oui, Effacer Toutes les Données
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal de confirmation pour Publier Tous les Emplois du Temps -->
        <div id="publish-all-modal" class="modal hidden">
            <div class="modal-content">
                <h3 class="modal-title purple">Confirmer la Publication</h3>
                <p class="modal-text">Êtes-vous sûr de vouloir publier TOUS les emplois du temps pour TOUTES les années et groupes ? Les emplois du temps publiés seront visibles par tous les étudiants et professeurs.</p>
                <div class="modal-actions">
                    <button id="publish-all-cancel" class="modal-btn modal-btn-cancel">
                        Annuler
                    </button>
                    <button id="publish-all-confirm" class="modal-btn modal-btn-confirm purple">
                        Oui, Tout Publier
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Modal animation functions
        function showModalWithAnimation(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            modal.classList.remove('hidden');
            
            // Force reflow
            void modal.offsetWidth;
            
            modal.classList.add('fade-in');
            modal.classList.remove('fade-out');
        }
        
        function closeModalWithAnimation(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            modal.classList.remove('fade-in');
            modal.classList.add('fade-out');
            
            // Hide after animation completes
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('fade-out');
            }, 300);
        }
        
        // Toast notification handling
        function createToastElement() {
            if (document.getElementById("toast-notification")) return;

            const toast = document.createElement("div");
            toast.id = "toast-notification";
            toast.className = "toast";
            document.body.appendChild(toast);
        }
        
        function showToast(type, message) {
            // Create toast element if it doesn't exist
            createToastElement();
            
            const toast = document.getElementById("toast-notification");
            toast.textContent = message;
            toast.className = "toast";

            if (type === "success") {
                toast.classList.add("toast-success");
            } else if (type === "error") {
                toast.classList.add("toast-error");
            } else {
                toast.classList.add("bg-blue-500", "text-white");
            }

            toast.classList.add("show");

            setTimeout(() => {
                toast.classList.remove("show");
            }, 3000);
        }
        
        // Show the modal when clicking the clear button
        document.getElementById('clear-button').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showModalWithAnimation('clear-modal');
        });
        
        // Hide the modal when clicking cancel
        document.getElementById('clear-cancel').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModalWithAnimation('clear-modal');
        });
        
        // Submit the form when confirming
        document.getElementById('clear-confirm').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
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
                
                // Use fetch instead of form submission
                fetch('../api/clear_timetables.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    // Re-enable the button and restore its appearance
                    button.disabled = false;
                    button.classList.remove('opacity-75');
                    defaultIcon.classList.remove('hidden');
                    loadingIcon.classList.add('hidden');
                    clearText.textContent = 'Effacer Tous les Emplois du Temps';
                    
                    if (data.success) {
                        showToast("success", data.message || "Tous les emplois du temps ont été effacés avec succès !");
                        // Optionally refresh the page to show emptied timetable lists
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showToast("error", data.message || "Échec de l'effacement des emplois du temps.");
                    }
                })
                .catch(error => {
                    // Re-enable the button and restore its appearance
                    button.disabled = false;
                    button.classList.remove('opacity-75');
                    defaultIcon.classList.remove('hidden');
                    loadingIcon.classList.add('hidden');
                    clearText.textContent = 'Effacer Tous les Emplois du Temps';
                    
                    console.error('Error clearing timetables:', error);
                    showToast("error", "Erreur lors de l'effacement des emplois du temps.");
                });
            }, 300);
        });
        
        // Close the modal if clicking outside of it
        document.getElementById('clear-modal').addEventListener('click', function(e) {
            if (e.target === e.currentTarget) {
                e.stopPropagation();
                closeModalWithAnimation('clear-modal');
            }
        });
        
        // Prevent clicks on modal content from closing the modal
        document.querySelector('#clear-modal .modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Show the publish all modal when clicking the publish all button
        document.getElementById('publish-all-button').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showModalWithAnimation('publish-all-modal');
        });
        
        // Hide the publish all modal when clicking cancel
        document.getElementById('publish-all-cancel').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModalWithAnimation('publish-all-modal');
        });
        
        // Handle the publish all confirmation
        document.getElementById('publish-all-confirm').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Hide the modal with animation
            closeModalWithAnimation('publish-all-modal');
            
            // Send request to publish all timetables
            fetch('../api/publish_all_timetables.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("success", data.message || "Tous les emplois du temps ont été publiés avec succès !");
                    // Reload the page to refresh notifications
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast("error", data.message || "Échec de la publication de tous les emplois du temps.");
                }
            })
            .catch(error => {
                console.error('Error publishing all timetables:', error);
                showToast("error", "Erreur lors de la publication de tous les emplois du temps.");
            });
        });
        
        // Close the publish all modal if clicking outside of it
        document.getElementById('publish-all-modal').addEventListener('click', function(e) {
            if (e.target === e.currentTarget) {
                e.stopPropagation();
                closeModalWithAnimation('publish-all-modal');
            }
        });
        
        // Prevent clicks on modal content from closing the modal
        document.querySelector('#publish-all-modal .modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Create toast element on page load
        createToastElement();
        
        // Ensure modals are properly hidden on page load
        const clearModal = document.getElementById('clear-modal');
        const publishModal = document.getElementById('publish-all-modal');
        if (clearModal) {
            clearModal.classList.add('hidden');
            clearModal.classList.remove('fade-in', 'fade-out');
        }
        if (publishModal) {
            publishModal.classList.add('hidden');
            publishModal.classList.remove('fade-in', 'fade-out');
        }
        
        // Prevent card clicks from triggering modals
        document.querySelectorAll('.card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                // Allow normal navigation, don't interfere
                e.stopPropagation();
            });
        });
        }); // End DOMContentLoaded
        </script>
        
        <div class="card-grid">
            <a href="../views/admin_timetable.php" class="card">
                <div class="card-content">
                    <div class="card-icon indigo">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">Gérer les Emplois du Temps</h3>
                        <p class="card-description">Créer et modifier les emplois du temps pour toutes les années et groupes</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/notifications.php" class="card">
                <?php if ($notificationCount > 0): ?>
                <div class="notification-badge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></div>
                <?php endif; ?>
                <div class="card-content">
                    <div class="card-icon red">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">Notifications</h3>
                        <p class="card-description">Voir les cours annulés ou reportés par les professeurs</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/professor.php" class="card">
                <div class="card-content">
                    <div class="card-icon purple">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">Vue Professeur</h3>
                        <p class="card-description">Voir les emplois du temps comme chaque professeur les voit</p>
                    </div>
                </div>
            </a>
        </div>
        
        <h2 class="section-title">Gestion du Système</h2>
        
        <div class="card-grid">
            <a href="../views/fix_passwords.php" class="card">
                <div class="card-content">
                    <div class="card-icon yellow">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">Corriger les Mots de Passe</h3>
                        <p class="card-description">Réinitialiser ou mettre à jour les mots de passe utilisateurs</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/check_users.php" class="card">
                <div class="card-content">
                    <div class="card-icon red">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">Gestion des Utilisateurs</h3>
                        <p class="card-description">Gérer les comptes et les permissions des utilisateurs ou ajouter un nouveau utilisateur</p>
                    </div>
                </div>
            </a>
            
        </div>
    </main>
</body>
</html>