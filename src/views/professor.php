<?php
session_start();

// Redirection vers la vue d'emploi du temps pour les professeurs
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'professor') {
    header("Location: timetable_view.php?role=professor");
    exit;
} else {
    // Non connecté ou pas un professeur, redirection vers la page de connexion
    header("Location: login.php?error=invalid_access");
    exit;
}
?> 