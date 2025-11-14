<?php
session_start();
require_once '../core/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
// Not logged in, redirect to login page
header("Location: ../auth.php");
exit;
}

// Redirect based on role
$role = $_SESSION['role'];
if ($role === 'admin') {
// Admin stays on this page - full edit capabilities
} elseif ($role === 'professor') {
// Redirect professors to view-only version (main timetable)
header("Location: ../index.php");
exit;
} elseif ($role === 'student') {
// Redirect students to view-only version (main timetable)
header("Location: ../index.php");
exit;
} else {
// Unknown role
header("Location: ../auth.php?error=invalid_role");
exit;
}

// Initialize variables and define constants
try {
// Get all years
$yearsStmt = $pdo->query("SELECT id, name FROM `years` ORDER BY id");
$yearsData = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);
$years = array_column($yearsData, 'name');

// Create an array to store groups by year
$groupsByYear = [];

// Get groups for each year
foreach ($yearsData as $year) {
    $groupsStmt = $pdo->prepare("SELECT name FROM `groups` WHERE year_id = ? ORDER BY name");
    $groupsStmt->execute([$year['id']]);
    $groupsByYear[$year['name']] = $groupsStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get all unique group names (for dropdown display)
$allGroups = [];
foreach ($groupsByYear as $yearGroups) {
    $allGroups = array_merge($allGroups, $yearGroups);
}
$groups = array_unique($allGroups);

// Fetch real subjects from database
$stmt = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
$subjectsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add a default color for each subject since the database doesn't have colors

$subjects = array_column($subjectsData, 'name');
} catch (PDOException $e) {
    // Log the error but don't provide fallbacks
    error_log("Failed to load years and groups from database: " . $e->getMessage());
    die("Database error occurred. Please contact administrator.");
}

// Fetch real professors from database
try {
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
$professorsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
error_log("Failed to load professors from database: " . $e->getMessage());
// Fallback to empty array if query fails
$professorsData = [];
}

$timeSlots = [
"08:00 - 09:30",
"09:45 - 11:15",
"11:30 - 13:00",
"13:15 - 14:45",
"15:00 - 16:30",
"16:45 - 18:15"
];
$days = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"];
$rooms = [
"Salle 101",
"Salle 102",
"Salle 201",
"Salle 202",
"Labo 301",
"Labo 302",
"Auditorium"
];

// Default selections
$currentYear = isset($_GET['year']) ? $_GET['year'] : 'Première Année';
$currentGroup = isset($_GET['group']) ? $_GET['group'] :'G1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title>University Timetable Management</title>
<link rel="icon" href="../assets/images/logo-supnum2.png" />
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/pages/admin_timetable.css">
</head>
<body>
<div id="timetable-root" class="card" data-config='<?= htmlspecialchars(json_encode([
    "currentYear" => $currentYear,
    "currentGroup" => $currentGroup,
    "groupsByYear" => $groupsByYear,
    "timeSlots" => $timeSlots,
    "days" => $days,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'>
    <div class="header-admin toolbar">
        <h1 class="page-title">Gestion des Emplois du Temps - Admin</h1>
        <div class="actions">
            <?php if ($role === 'admin' && isset($_GET['professor_id'])): ?>
            <a href="professor.php" class="btn btn-light">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm icon-left" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour à la Sélection
            </a>
            <?php endif; ?>
            <a href="../admin/index.php" class="btn btn-light">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm icon-left" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour au Tableau de Bord
            </a>
        </div>
    </div>

    <div class="content">
        <!-- Filters -->
        <div class="filters">
            <div>
                <label class="form-label">Année</label>
                <div class="dropdown-container">
                    <button class="dropdown-button" id="year-dropdown">
                        <span id="selected-year"><?php echo $currentYear; ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="year-menu">
                        <?php foreach ($years as $year): ?>
                            <div class="dropdown-item" data-value="<?php echo $year; ?>"><?php echo $year; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <label class="form-label">Groupe</label>
                <div class="dropdown-container">
                    <button class="dropdown-button" id="group-dropdown">
                        <span id="selected-group"><?php echo $currentGroup; ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="dropdown-menu" id="group-menu">
                        <?php foreach ($groups as $group): ?>
                            <div class="dropdown-item" data-value="<?php echo $group; ?>"><?php echo $group; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emploi du temps -->
        <div class="timetable-container">
            <table class="timetable" id="timetable">
                <thead>
                    <tr>
                        <th class="time-cell">Heure</th>
                        <?php foreach ($days as $day): ?>
                            <th><?php echo $day; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="timetable-body">
                    <?php foreach ($timeSlots as $time): ?>
                        <tr>
                            <td class="time-cell"><?php echo $time; ?></td>
                            <?php foreach ($days as $day): ?>
                                <td class="subject-cell">
                                    <div class="empty-cell">
                                        <button class="btn-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Contrôles admin -->
        <div class="controls">
            <button id="save-btn" class="btn btn-primary">
                Enregistrer
            </button>
            <button id="publish-btn" class="btn btn-success">
                Publier
            </button>
            <button id="delete-timetable-btn" class="btn btn-danger">
                Supprimer
            </button>
        </div>

        <!-- Message de statut -->
        <div id="status-message" class="status hidden"></div>
    </div>
</div>

<!-- Modal Ajouter/Modifier Cours -->
<div id="class-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-title">Ajouter un Cours</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="class-form">
                <input type="hidden" id="edit-day" />
                <input type="hidden" id="edit-time" />
                <input type="hidden" id="edit-id" />
                <input type="hidden" id="edit-color" />

                <div class="form-section">
                    <label for="professor-select" class="form-label">Professeur</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="professor-dropdown">
                            <span id="selected-professor">Sélectionner un professeur</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="professor-menu">
                            <div class="dropdown-menu-header sticky">
                                <input type="text" id="professor-search" placeholder="Rechercher un professeur..." />
                            </div>
                            <div id="professor-list">
                                <?php foreach ($professorsData as $professor): ?>
                                <div class="dropdown-item" data-value="<?php echo htmlspecialchars($professor['name']); ?>" data-id="<?php echo $professor['id']; ?>">
                                    <?php echo htmlspecialchars($professor['name']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <label for="subject-select" class="form-label">Matière</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="subject-dropdown" disabled>
                            <span id="selected-subject">Sélectionner un professeur d'abord</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="subject-menu">
                            <?php foreach ($subjectsData as $subject): ?>
                            <div class="dropdown-item" data-value="<?php echo htmlspecialchars($subject['name']); ?>" data-id="<?php echo $subject['id']; ?>" data-color="<?php echo $subject['color']; ?>">
                                <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <label for="room-select" class="form-label">Salle</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="room-dropdown">
                            <span id="selected-room">Sélectionner une salle</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="room-menu">
                            <?php foreach ($rooms as $room): ?>
                            <div class="dropdown-item" data-value="<?php echo htmlspecialchars($room); ?>">
                                <?php echo htmlspecialchars($room); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <label for="course-type-select" class="form-label">Type de Cours</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="course-type-dropdown">
                            <span id="selected-course-type">CM</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="course-type-menu">
                            <div class="dropdown-item" data-value="CM" data-color="#6b7280">CM</div>
                            <div class="dropdown-item" data-value="TD" data-color="#10b981">TD</div>
                            <div class="dropdown-item" data-value="TP" data-color="#3b82f6">TP</div>
                            <div class="dropdown-item" data-value="DE" data-color="#f59e0b">DE</div>
                            <div class="dropdown-item" data-value="CO" data-color="#ef4444">CO</div>
                        </div>
                    </div>
                </div>

                <!-- Subgroup options - initially hidden -->
                <div id="subgroup-options" class="form-section hidden">
                    <label class="form-label">Options de sous-groupe</label>
                    <div class="radio-line mb-1">
                        <input type="radio" id="subgroup-single" name="subgroup-option" value="single" checked>
                        <label for="subgroup-single" class="radio-label">Classe entière</label>
                    </div>
                    <div class="radio-line">
                        <input type="radio" id="subgroup-split" name="subgroup-option" value="split">
                        <label for="subgroup-split" class="radio-label">Diviser en sous-groupes</label>
                    </div>
                </div>

                <!-- Subgroup split options - initially hidden -->
                <div id="subgroup-split-options" class="form-section hidden">
                    <label class="form-label">Options de division</label>
                    <div class="radio-line mb-1">
                        <input type="radio" id="subgroup-same-time" name="subgroup-split-option" value="same-time" checked>
                        <label for="subgroup-same-time" class="radio-label">Même créneau horaire</label>
                    </div>
                    <div class="radio-line">
                        <input type="radio" id="subgroup-single-group" name="subgroup-split-option" value="single-group">
                        <label for="subgroup-single-group" class="radio-label">Un seul sous-groupe</label>
                    </div>
                </div>

                <!-- Second professor and room for same-time subgroups - initially hidden -->
                <div id="second-subgroup-options" class="form-section hidden">
                    <div class="section-split">
                        <h3 class="subheading">Informations pour le deuxième sous-groupe</h3>
                        
                        <div class="form-section-sm">
                            <label for="professor-select-2" class="form-label">Professeur (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="professor-dropdown-2">
                                    <span id="selected-professor-2">Sélectionner un professeur</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div class="dropdown-menu" id="professor-menu-2">
                                    <div class="dropdown-menu-header sticky">
                                        <input type="text" id="professor-search-2" placeholder="Rechercher un professeur..." />
                                    </div>
                                    <div id="professor-list-2">
                                        <?php foreach ($professorsData as $professor): ?>
                                        <div class="dropdown-item" data-value="<?php echo htmlspecialchars($professor['name']); ?>" data-id="<?php echo $professor['id']; ?>">
                                            <?php echo htmlspecialchars($professor['name']); ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-sm">
                            <label for="subject-select-2" class="form-label">Matière (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="subject-dropdown-2" disabled>
                                    <span id="selected-subject-2">Sélectionner un professeur d'abord</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div class="dropdown-menu" id="subject-menu-2">
                                    <?php foreach ($subjectsData as $subject): ?>
                                    <div class="dropdown-item" data-value="<?php echo htmlspecialchars($subject['name']); ?>" data-id="<?php echo $subject['id']; ?>" data-color="<?php echo $subject['color']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section-sm">
                            <label for="room-select-2" class="form-label">Salle (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="room-dropdown-2">
                                    <span id="selected-room-2">Sélectionner une salle</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div class="dropdown-menu" id="room-menu-2">
                                    <?php foreach ($rooms as $room): ?>
                                    <div class="dropdown-item" data-value="<?php echo htmlspecialchars($room); ?>">
                                        <?php echo htmlspecialchars($room); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Single subgroup selection - initially hidden -->
                <div id="single-subgroup-selector" class="form-section hidden">
                    <label class="form-label">Sélectionner le sous-groupe</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="subgroup-dropdown">
                            <span id="selected-subgroup">Sous-groupe 1</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="subgroup-menu">
                            <div class="dropdown-item" data-value="1">Sous-groupe 1</div>
                            <div class="dropdown-item" data-value="2">Sous-groupe 2</div>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" id="cancel-btn" class="btn btn-outline">
                        Annuler
                    </button>
                    <button type="submit" id="save-class-btn" class="btn btn-primary">
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'avertissement pour modifications non enregistrées -->
<div id="unsaved-changes-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title modal-title-danger">Modifications Non Enregistrées</h2>
            <span class="close" id="unsaved-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p class="para lead-tight">Vous avez des modifications non enregistrées dans votre emploi du temps.</p>
                <p class="para lead-compact">Enregistrer les modifications avant de continuer ?</p>
            </div>

            <div class="modal-actions">
                <button type="button" id="discard-btn" class="btn btn-outline">
                    Ignorer les Modifications
                </button>
                <button type="button" id="save-continue-btn" class="btn btn-primary">
                    Enregistrer et Continuer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour Supprimer l'Emploi du Temps -->
<div id="delete-timetable-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title modal-title-danger">Supprimer l'Emploi du Temps</h2>
            <span class="close" id="delete-timetable-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p>Voulez-vous vraiment supprimer l'emploi du temps pour <span id="delete-year-group" class="strong-text"></span> ?</p>
                <p>Cette action supprimera définitivement toutes les données d'emploi du temps pour cette année et ce groupe.</p>
            </div>

            <div class="modal-actions">
                <button type="button" id="delete-timetable-cancel" class="btn btn-outline">
                    Annuler
                </button>
                <button type="button" id="delete-timetable-confirm" class="btn btn-danger">
                    Oui, Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Professor Availability Conflicts -->
<div id="professor-conflict-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title modal-title-danger">Conflit d'Horaire Professeur</h2>
            <span class="close" id="professor-conflict-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p class="mb-2 text-danger">Ce professeur ne peut pas être assigné à ce créneau horaire car il est déjà occupé :</p>
                <div id="conflict-details" class="conflict-box">
                    <!-- Conflict details will be inserted here -->
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" id="conflict-cancel" class="btn btn-primary">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Room Availability Conflicts -->
<div id="room-conflict-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title modal-title-danger">Conflit de Salle</h2>
            <span class="close" id="room-conflict-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p class="mb-2 text-danger">Cette salle ne peut pas être réservée à ce créneau horaire car elle est déjà occupée :</p>
                <div id="room-conflict-details" class="conflict-box">
                    <!-- Room conflict details will be inserted here -->
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" id="room-conflict-cancel" class="btn btn-primary">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmation de Suppression -->
<div id="delete-class-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title modal-title-danger">Supprimer le Cours</h2>
            <span class="close" id="delete-class-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p>Êtes-vous sûr de vouloir supprimer ce cours ?</p>
                <p id="delete-class-name" class="strong-text"></p>
            </div>

            <div class="modal-actions">
                <button type="button" id="delete-class-cancel" class="btn btn-outline">
                    Annuler
                </button>
                <button type="button" id="delete-class-confirm" class="btn btn-danger">
                    Supprimer
                </button>
            </div>
        </div>
    </div>
</div>
<div id="move-class-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Déplacer le Cours</h2>
            <span class="close" id="move-class-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-section">
                <p id="move-class-message"></p>
            </div>
            <div class="modal-actions">
                <button type="button" id="move-class-cancel" class="btn btn-outline">
                    Annuler
                </button>
                <button type="button" id="move-class-confirm" class="btn btn-primary">
                    Confirmer
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin_timetable.js"></script>
</body>
</html>
