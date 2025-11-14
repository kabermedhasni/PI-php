<?php
session_start();
require_once 'core/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in, redirect to login page
    header("Location: auth.php");
    exit;
}

// Check if user is student, professor, or admin
$role = $_SESSION['role'];
$isProfessorDebug = isset($_GET['professor_id']) && $role === 'admin';

// Handle invalid roles
if ($role !== 'student' && $role !== 'professor' && $role !== 'admin') {
    // For invalid roles, default to student view instead of redirecting
    $role = 'student';
    error_log("Invalid role detected. Defaulting to student view.");
}

// If an admin hits the root timetable page without debug mode,
// send them to the admin dashboard instead of trying to load a student timetable
if ($role === 'admin' && !$isProfessorDebug) {
    header("Location: admin/index.php");
    exit;
}

// Initialize variables and define constants
try {
    // Get all years from database
    $yearsStmt = $pdo->query("SELECT id, name FROM `years` ORDER BY name");
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
    
    // Get all unique group names (for backward compatibility)
    $allGroups = [];
    foreach ($groupsByYear as $yearGroups) {
        $allGroups = array_merge($allGroups, $yearGroups);
    }
    $groups = array_unique($allGroups);
    
} catch (PDOException $e) {
    // Log the error but don't provide fallbacks
    error_log("Failed to load years and groups from database: " . $e->getMessage());
    die("Database error occurred. Please contact administrator.");
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

// If we're in admin preview with professor_id set, get professor info
$professorId = null;
$professorName = null;
if ($isProfessorDebug && isset($_GET['professor_id'])) {
    $professorId = $_GET['professor_id'];
    $currentYear = null;
    $currentGroup = null;
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'professor'");
        $stmt->execute([$professorId]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($professor) {
            $professorName = $professor['name'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch professor data: " . $e->getMessage());
    }
} elseif ($role === 'professor') {
    // For regular professor view, use their own ID
    $professorId = $_SESSION['user_id'];
    $currentYear = null;
    $currentGroup = null;
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$professorId]);
        $professor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($professor) {
            $professorName = $professor['name'];
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch professor data: " . $e->getMessage());
    }
}

// Default selections
else {
    // For students, use their assigned year and group
    $currentYear = $_SESSION['year_id'];
    $currentGroup = $_SESSION['group_id'];
}

// Page title based on role
$pageTitle = ($role === 'student') ? 'Emploi du Temps Étudiant' : 'Emploi du Temps Professeur';
$headerClass = ($role === 'student') ? 'header-student' : 'header-professor';
$headerBg = ($role === 'student') ? 'blue' : 'purple';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" href="assets/images/logo-supnum2.png" />
    <link rel="stylesheet" href="assets/css/pages/index.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
  <body>
    <div id="timetable-root" class="card" data-config='<?= htmlspecialchars(json_encode([
      "currentYear" => $currentYear,
      "currentGroup" => $currentGroup,
      "professorId" => $professorId,
      "role" => $role,
      "groupsByYear" => $groupsByYear,
      "timeSlots" => $timeSlots,
      "days" => $days,
      "isProfessorDebug" => $isProfessorDebug,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>'>
        <div class="header-bar <?php echo $headerClass; ?>">
            <h1 class="page-title"><?php echo $pageTitle; ?></h1>
            <div class="actions">
                <?php if ($isProfessorDebug): ?>
                <a href="admin/professor.php" class="btn">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour à la Sélection
                </a>
                <?php endif; ?>
                <?php if (!$isProfessorDebug): ?>
                <a href="core/logout.php" class="btn">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Déconnexion
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <!-- Filters - only for admin debugging or students, professors don't get filtering -->
            <?php if ($isProfessorDebug): ?>
            <!-- Admin Debug Controls -->
            <div class="info-box info-debug">
                <h3 class="info-title">Mode Debug Professeur</h3>
                <p class="info-subtitle">Sélectionnez un professeur pour visualiser son emploi du temps</p>
                
                <div class="prof-list">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
                        $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($professors as $prof) {
                            $isActive = ($prof['id'] == $professorId);
                            $btnClass = $isActive ? "prof-btn active" : "prof-btn";
                            echo '<a href="?professor_id=' . $prof['id'] . '" class="' . $btnClass . '">' . htmlspecialchars($prof['name']) . '</a>';
                        }
                    } catch (PDOException $e) {
                        echo '<p class="error-text">Erreur: Impossible de charger la liste des professeurs</p>';
                    }
                    ?>
                </div>
            </div>
            <?php elseif ($role === 'student'): ?>
            <!-- For students, show their fixed year and group -->
            <div class="filters">
                <div class="filter-item">
                    <span class="label">Année:</span>
                    <span class="badge badge-blue"><?php echo $currentYear; ?></span>
                </div>
                <div class="filter-item">
                    <span class="label">Groupe:</span>
                    <span class="badge badge-blue"><?php echo $currentGroup; ?></span>
                </div>
            </div>
            <?php else: ?>
            <!-- For professors, show a message that they see their own timetable -->
            <div class="info-box info-professor">
                <p>
                    Bonjour <strong><?php echo htmlspecialchars($professorName); ?></strong>, voici votre emploi du temps personnel.
                    Vous trouverez ci-dessous tous vos cours planifiés pour la semaine.
                </p>
            </div>
            <?php endif; ?>

            <!-- Timetable -->
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
                        <!-- Will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="assets/js/timetable_view.js"></script>
</body>
</html>