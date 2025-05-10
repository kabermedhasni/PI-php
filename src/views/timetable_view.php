<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit;
}

// Check if user is student or professor
$role = $_SESSION['role'];
$isPreview = isset($_GET['preview']) && $_GET['preview'] === 'true';
$isProfessorDebug = isset($_GET['professor_id']) && ($role === 'admin' || $role === 'professor');

// If it's a preview request
if ($isPreview && $role === 'admin') {
    // Admin can preview student or professor view
    $role = isset($_GET['role']) ? $_GET['role'] : 'student';
    if ($role !== 'student' && $role !== 'professor') {
        $role = 'student'; // Default to student view if invalid
    }
} else if ($role !== 'student' && $role !== 'professor' && $role !== 'admin') {
    // For invalid roles, default to student view instead of redirecting
    $role = 'student';
    error_log("Invalid role detected: " . $role . ". Defaulting to student view.");
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
    // Fallback to defaults if database query fails
    error_log("Failed to load years and groups from database: " . $e->getMessage());
    $years = ["Première Année", "Deuxième Année", "Troisième Année"];
    $groups = ["G1", "G2", "G3", "G4", "G5", "G6"];
    
    // Default groupsByYear structure based on the database results
    $groupsByYear = [
        "Première Année" => ["G1", "G2", "G3", "G4", "G5", "G6"],
        "Deuxième Année" => ["G1", "G2", "G3", "G4"],
        "Troisième Année" => ["G1", "G2"]
    ];
}

$timeSlots = [
    "08:00 - 09:30",
    "09:30 - 11:00",
    "11:30 - 13:00",
    "13:00 - 14:30",
    "15:00 - 16:30",
    "16:30 - 18:00"
];
$days = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi"];

// If we're in admin preview with professor_id set, get professor info
$professorId = null;
$professorName = null;
if ($isProfessorDebug && isset($_GET['professor_id'])) {
    $professorId = $_GET['professor_id'];
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
    $professorName = $_SESSION['name'] ?? 'Professeur';
}

// Default selections
if ($role === 'student') {
    // For students, use their assigned year and group
    $currentYear = $_SESSION['year_id'] ?? 'Première Année';
    $currentGroup = $_SESSION['group_id'] ?? 'G1';
    
    // Override with URL parameters if they exist
    if (isset($_GET['year'])) $currentYear = $_GET['year'];
    if (isset($_GET['group'])) $currentGroup = $_GET['group'];
} else {
    // For professors, we don't have specific group/year - we'll display all their courses
    // The API call will filter by professor ID
    $currentYear = isset($_GET['year']) ? $_GET['year'] : 'Première Année';
    $currentGroup = isset($_GET['group']) ? $_GET['group'] : 'G1';
}

// Page title based on role
$pageTitle = ($role === 'student') ? 'Emploi du Temps Étudiant' : 'Emploi du Temps Professeur';
$headerClass = ($role === 'student') ? 'header-student' : 'header-professor';
$headerBg = ($role === 'student') ? 'bg-blue-600' : 'bg-purple-700';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $pageTitle; ?></title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Outfit Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <style>
      * {
        transition: all 0.2s ease;
        box-sizing: border-box;
      }

      body {
        background-color: #f5f7fa;
        font-family: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI",
          Roboto, sans-serif;
        margin: 0;
        padding: 20px;
      }

      .card {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
          0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border-radius: 0.75rem;
        overflow: hidden;
        background-color: white;
        max-width: 1200px;
        margin: 20px auto;
      }

      .header-student {
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        color: white;
      }
      
      .header-professor {
        background: linear-gradient(135deg, #7e22ce 0%, #9333ea 100%);
        color: white;
      }

      .timetable {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #e2e8f0;
        table-layout: fixed;
        background: white;
      }

      .timetable th {
        background-color: #f8fafc;
        padding: 12px;
        text-align: center;
        font-weight: 600;
        font-size: 0.875rem;
        color: #4b5563;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
      }

      .timetable td {
        padding: 8px;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: top;
        height: 100px;
      }

      .time-cell {
        font-weight: 500;
        text-align: center;
        width: 100px;
        background-color: #f8fafc;
      }

      .dropdown-container {
        position: relative;
        width: 100%;
        margin-bottom: 8px;
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
        height: 38px;
      }

      .dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        background-color: #fff;
        border-radius: 0.375rem;
        box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.12);
        display: none;
        z-index: 100;
        max-height: 200px;
        overflow-y: auto;
      }

      .dropdown-menu.open {
        display: block;
      }

      .dropdown-item {
        padding: 8px 12px;
        cursor: pointer;
      }

      .dropdown-item:hover {
        background-color: #f1f5f9;
      }

      .class-block {
        background-color: #f8fafc;
        border-radius: 0.375rem;
        border-left: 4px solid #6b7280;
        padding: 8px;
        margin-bottom: 4px;
      }

      /* Set maximum height for the timetable container */
      .timetable-container {
        max-height: 100ch;
        overflow-y: auto;
        border-radius: 0.5rem;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
      }

      /* Custom scrollbar */
      .timetable-container::-webkit-scrollbar {
        width: 8px;
      }

      .timetable-container::-webkit-scrollbar-track {
        background: #f1f5f9;
      }

      .timetable-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 8px;
      }

      /* Toast notification */
      .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 0.375rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        z-index: 2000;
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
    </style>
</head>
<body>
    <div class="card">
        <div class="p-6 flex justify-between items-center <?php echo $headerClass; ?>">
            <h1 class="text-2xl font-bold">
                <?php echo $pageTitle; ?>
                <?php if ($isPreview): ?>
                <span class="text-sm bg-white/20 px-2 py-1 rounded ml-2">Mode Aperçu</span>
                <?php endif; ?>
                <?php if ($isProfessorDebug && $professorName): ?>
                <span class="text-sm bg-white/20 px-2 py-1 rounded ml-2">Debug: <?php echo htmlspecialchars($professorName); ?></span>
                <?php endif; ?>
            </h1>
            <div class="flex space-x-3">
                <?php if ($isPreview): ?>
                <a href="../admin/index.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour au Tableau de Bord
                </a>
                <?php endif; ?>
                <a href="logout.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Déconnexion
                </a>
            </div>
        </div>

        <div class="p-6">
            <!-- Filters - only for admin debugging or students, professors don't get filtering -->
            <?php if ($isProfessorDebug): ?>
            <!-- Admin Debug Controls -->
            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 mb-6">
                <h3 class="text-lg font-medium text-amber-800">Mode Debug Professeur</h3>
                <p class="text-amber-700 mb-3">Sélectionnez un professeur pour visualiser son emploi du temps</p>
                
                <div class="flex flex-wrap gap-3 mt-2">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
                        $professors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($professors as $prof) {
                            $isActive = ($prof['id'] == $professorId);
                            $btnClass = $isActive ? "bg-amber-600 text-white" : "bg-white text-amber-800 border border-amber-300";
                            echo '<a href="?professor_id=' . $prof['id'] . '" class="px-3 py-1.5 rounded-full text-sm font-medium ' . $btnClass . ' hover:bg-amber-500 hover:text-white transition-colors">' . htmlspecialchars($prof['name']) . '</a>';
                        }
                    } catch (PDOException $e) {
                        echo '<p class="text-red-600">Erreur: Impossible de charger la liste des professeurs</p>';
                    }
                    ?>
                </div>
            </div>
            <?php elseif ($role === 'student'): ?>
            <!-- For students, show their fixed year and group -->
            <div class="flex mb-6">
                <div class="flex items-center mr-6">
                    <span class="text-sm font-medium text-gray-700 mr-2">Année:</span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium"><?php echo $currentYear; ?></span>
                </div>
                <div class="flex items-center">
                    <span class="text-sm font-medium text-gray-700 mr-2">Groupe:</span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium"><?php echo $currentGroup; ?></span>
                </div>
            </div>
            <?php else: ?>
            <!-- For professors, show a message that they see their own timetable -->
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6">
                <p class="text-purple-800">
                    Bonjour <strong><?php echo htmlspecialchars($_SESSION['name'] ?? 'Professeur'); ?></strong>, voici votre emploi du temps personnel.
                    Vous trouverez ci-dessous tous vos cours planifiés pour la semaine.
                </p>
            </div>
            <?php endif; ?>

            <!-- Timetable -->
            <div class="timetable-container overflow-x-auto rounded-lg shadow-sm border border-gray-200">
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

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize variables
            let currentYear = "<?php echo $currentYear; ?>";
            let currentGroup = "<?php echo $currentGroup; ?>";
            const professorId = "<?php echo $professorId; ?>";
            const userRole = "<?php echo $role; ?>";
            const groupsByYear = <?php echo json_encode($groupsByYear); ?>;
            const timeSlots = <?php echo json_encode($timeSlots); ?>;
            const days = <?php echo json_encode($days); ?>;
            const isPreview = <?php echo $isPreview ? 'true' : 'false'; ?>;
            const isProfessorDebug = <?php echo $isProfessorDebug ? 'true' : 'false'; ?>;

            // Timetable data
            let timetableData = {};

            // Initialize empty timetable data
            function initTimetableData() {
                timetableData = {};
                days.forEach(day => {
                    timetableData[day] = {};
                    timeSlots.forEach(time => {
                        timetableData[day][time] = null;
                    });
                });
            }

            // Generate view-only timetable
            function generateViewTimetable() {
                const tbody = document.getElementById("timetable-body");
                tbody.innerHTML = "";

                timeSlots.forEach(time => {
                    const row = document.createElement("tr");

                    // Time cell
                    const timeCell = document.createElement("td");
                    timeCell.className = "time-cell";
                    timeCell.textContent = time;
                    row.appendChild(timeCell);

                    // Day cells
                    days.forEach(day => {
                        const cell = document.createElement("td");
                        cell.className = "subject-cell";

                        // Check if we have data for this cell
                        if (timetableData[day] && timetableData[day][time]) {
                            const data = timetableData[day][time];

                            // If we're in professor view, we might have multiple classes at same time
                            const classes = Array.isArray(data) ? data : [data];
                            
                            classes.forEach(classData => {
                                if (!classData) return;
                                
                                const classBlock = document.createElement("div");
                                classBlock.className = "class-block";
                                // Use the color from data if available, otherwise use default color
                                classBlock.style.borderLeftColor = classData.color || "#3b82f6";

                                const subjectDiv = document.createElement("div");
                                subjectDiv.className = "font-medium";
                                subjectDiv.textContent = classData.subject;

                                // For professors, show group and year
                                let detailsText = '';
                                if (userRole === 'professor' || isProfessorDebug) {
                                    detailsText = `${classData.year} - ${classData.group}`;
                                } else {
                                    detailsText = classData.professor;
                                }
                                
                                const detailsDiv = document.createElement("div");
                                detailsDiv.className = "text-sm text-gray-600";
                                detailsDiv.textContent = detailsText;

                                const roomDiv = document.createElement("div");
                                roomDiv.className = "text-xs text-gray-500 mt-1";
                                roomDiv.textContent = `Salle: ${classData.room}`;

                                classBlock.appendChild(subjectDiv);
                                classBlock.appendChild(detailsDiv);
                                classBlock.appendChild(roomDiv);

                                cell.appendChild(classBlock);
                            });
                        } else {
                            // Empty cell
                            cell.innerHTML = `<div class="h-full flex items-center justify-center text-gray-300 text-sm">Pas de cours</div>`;
                        }

                        row.appendChild(cell);
                    });

                    tbody.appendChild(row);
                });
            }

            // Initialize
            initTimetableData();
            generateViewTimetable();

            // Create toast notification element
            function createToastElement() {
                if (document.getElementById("toast-notification")) return;

                const toast = document.createElement("div");
                toast.id = "toast-notification";
                toast.className = "toast";
                document.body.appendChild(toast);
            }
            createToastElement();

            // Show toast message
            function showToast(type, message) {
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

            // Load timetable data
            function loadTimetableData() {
                let apiUrl = '';
                
                if (userRole === 'professor' || isProfessorDebug) {
                    // For professor view, we get courses filtered by professor ID
                    apiUrl = `../api/get_timetable.php?professor_id=${professorId}`;
                } else {
                    // For students and admin preview of student view, filter by year and group
                    apiUrl = `../api/get_timetable.php?year=${currentYear}&group=${currentGroup}`;
                }
                
                // Try to load from server - prefer published version first
                fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data && data.data) {
                        timetableData = data.data;
                        generateViewTimetable();
                        showToast("success", `Emploi du temps chargé`);
                    } else {
                        // If no data from server, show empty timetable
                        initTimetableData();
                        generateViewTimetable();
                        if (userRole === 'professor' || isProfessorDebug) {
                            showToast("info", "Aucun cours n'a été trouvé dans votre emploi du temps");
                        } else {
                            showToast("info", `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`);
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des données:', error);
                    // Show error toast
                    showToast("error", "Erreur lors du chargement des données");
                    // Initialize empty
                    initTimetableData();
                    generateViewTimetable();
                });
            }

            // Load timetable data on page load
            loadTimetableData();
        });
    </script>
</body>
</html> 