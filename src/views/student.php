<?php
session_start();

// Redirection vers la vue d'emploi du temps pour les étudiants
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    // Si group_id existe et est au format numérique (ex: 12 pour Année 1 Groupe 2)
    if (isset($_SESSION['group_id']) && is_numeric($_SESSION['group_id']) && strlen($_SESSION['group_id']) >= 2) {
        $groupNumeric = $_SESSION['group_id'];
        $year = substr($groupNumeric, 0, 1);
        $group = substr($groupNumeric, 1, 1);
        
        // Conversion au format attendu par timetable_view.php et les fichiers de données
        switch($year) {
            case '1':
                $yearName = "Première Année";
                break;
            case '2':
                $yearName = "Deuxième Année";
                break;
            case '3':
                $yearName = "Troisième Année";
                break;
            default:
                $yearName = "Première Année";
        }
        
        $groupName = "G" . $group;
        
        error_log("Redirection étudiant: Utilisation de groupID $groupNumeric comme année=$yearName, groupe=$groupName");
        header("Location: timetable_view.php?role=student&year=$yearName&group=$groupName");
        exit;
    } else {
        // Alternative pour format hérité ou group_id manquant
        // Par défaut "Première Année" au lieu du format "Y1"
        $year_id = $_SESSION['year_id'] ?? 'Première Année';
        $group_id = $_SESSION['group_id'] ?? 'G1';
        
        error_log("Redirection étudiant (format hérité): année=$year_id, groupe=$group_id");
        header("Location: timetable_view.php?role=student&year=$year_id&group=$group_id");
        exit;
    }
} else {
    // Non connecté ou pas un étudiant, redirection vers la page de connexion
    header("Location: login.php?error=invalid_access");
    exit;
}
?>
