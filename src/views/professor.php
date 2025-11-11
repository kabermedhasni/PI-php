<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit;
}

// Redirect based on role
if ($_SESSION['role'] === 'professor') {
    // Professors go directly to their timetable
    header("Location: timetable_view.php?role=professor");
    exit;
} elseif ($_SESSION['role'] === 'admin') {
    // Show professor selection for admins
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sélection Professeur</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/pages/professor.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1 class="header-title">Sélection Professeur - Mode Debug</h1>
                <a href="../admin/index.php" class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
            </div>
        </div>
        <div class="content">
        
        <p class="description">
            Sélectionnez un professeur pour visualiser son emploi du temps.
        </p>
        
        <div class="professor-grid">
            <?php
            try {
                $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
                $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($professors as $prof) {
                    echo '<a href="timetable_view.php?professor_id=' . $prof['id'] . '" ';
                    echo 'class="professor-card">';
                    echo '<div class="professor-name">' . htmlspecialchars($prof['name']) . '</div>';
                    echo '</a>';
                }
                
                if (empty($professors)) {
                    echo '<p class="no-results">Aucun professeur trouvé</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="error-message">Erreur: ' . $e->getMessage() . '</p>';
            }
            ?>
        </div>
        </div>
    </div>
</body>
</html>
<?php
} else {
    // Students or other roles
    header("Location: timetable_view.php");
    exit;
}
?> 