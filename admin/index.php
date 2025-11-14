<?php
session_start();
require_once '../core/db.php';

// Vérification si l'utilisateur est connecté et a le rôle d'administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Journaliser la tentative d'accès non autorisé
    if (isset($_SESSION['user_id'])) {
        error_log("Tentative d'accès non autorisé à admin.php par l'utilisateur ID: " . $_SESSION['user_id']);
    } else {
        error_log("Tentative d'accès non autorisé à admin.php (pas de session)");
    }
    
    // Redirection vers la page de connexion
    header("Location: ../auth.php");
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
        header("Location: ../auth.php");
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
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <link rel="stylesheet" href="../assets/css/pages/admin_index.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Tableau de Bord Admin</h1>
                <a href="../core/logout.php" class="logout-button">Déconnexion</a>
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
        
        <script src="../assets/js/admin_index.js"></script>
        
        <div class="card-grid">
            <a href="timetable_management.php" class="card">
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
            
            <a href="notifications.php" class="card">
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
            
            <a href="professor.php" class="card">
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
            <a href="password_reset.php" class="card">
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
            
            <a href="user_lookup.php" class="card">
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