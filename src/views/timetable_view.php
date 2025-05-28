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
$isProfessorDebug = isset($_GET['professor_id']) && $role === 'admin';

// Handle invalid roles
if ($role !== 'student' && $role !== 'professor' && $role !== 'admin') {
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
$headerBg = ($role === 'student') ? 'bg-blue-600' : 'bg-purple-700';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
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
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: auto;
        border-radius: 0.75rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
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

      /* Mobile responsive adjustments */
      @media (max-width: 768px) {
        body {
          padding: 8px;
          margin: 0;
        }
        
        .card {
          margin: 5px;
          border-radius: 0.5rem;
        }
        
        .p-6 {
          padding: 0.75rem !important;
        }
        
        h1.text-2xl {
          font-size: 1.25rem;
          line-height: 1.75rem;
        }
        
        /* Hide scrollbar on mobile */
        .timetable-container {
          max-height: 70vh;
          border-radius: 0.25rem;
          -ms-overflow-style: none;
          scrollbar-width: none;
          overflow-x: auto;
        }
        
        .timetable-container::-webkit-scrollbar {
          display: none;
          width: 0;
        }
        
        /* Compact timetable for mobile */
        .timetable th, .timetable td {
          padding: 4px;
          font-size: 0.7rem;
        }
        
        .time-cell {
          width: 40px;
          font-size: 0.65rem;
          padding: 2px !important;
        }
        
        .timetable td {
          height: 70px;
          vertical-align: top;
        }
        
        .class-block {
          padding: 4px;
          margin-bottom: 2px;
          font-size: 0.7rem;
        }
        
        /* Make class block details more compact */
        .class-block div {
          margin-bottom: 2px;
        }
        
        /* Adjust the filter section */
        .flex.mb-6 {
          flex-direction: column;
          gap: 0.5rem;
        }
        
        /* Improve info boxes */
        .bg-purple-50, .bg-amber-50 {
          padding: 0.5rem !important;
          margin-bottom: 0.5rem !important;
        }
        
        .bg-purple-50 p, .bg-amber-50 p {
          font-size: 0.8rem;
          margin: 0;
        }
        
        /* Make toast notifications more visible on mobile */
        .toast {
          width: 90%;
          left: 5%;
          right: 5%;
          font-size: 0.8rem;
          padding: 10px;
          text-align: center;
        }
        
        /* Adjust buttons */
        a.flex.items-center {
          padding: 0.4rem 0.75rem !important;
          font-size: 0.75rem !important;
        }
        
        a.flex.items-center svg {
          width: 0.75rem;
          height: 0.75rem;
        }

        #arrow {
          width: 22px;
          height: auto;
        }
      }
      
      /* Additional optimizations for extra small screens */
      @media (max-width: 480px) {
        body {
          padding: 4px;
        }
        
        .card {
          margin: 3px;
        }
        
        .p-6 {
          padding: 0.5rem !important;
        }
        
        /* Make timetable even more compact */
        .timetable-container {
          max-height: 75vh;
        }
        
        .timetable td {
          height: 50px !important;
          padding: 2px !important;
        }
        
        .time-cell {
          width: 30px !important;
          font-size: 0.6rem !important;
        }
        
        .timetable th {
          padding: 3px !important;
          font-size: 0.65rem !important;
        }
        
        /* Ultra compact class blocks */
        .class-block {
          padding: 2px !important;
          margin-bottom: 0 !important;
          font-size: 0.65rem !important;
        }
        
        /* Optimize class block content */
        .class-block div {
          margin-bottom: 1px !important;
          line-height: 1.1 !important;
        }
        
        .class-block div.font-medium {
          font-size: 0.65rem !important;
        }
        
        .class-block div.text-sm {
          font-size: 0.6rem !important;
        }
        
        .class-block div.text-xs {
          font-size: 0.55rem !important;
        }
        
        /* Empty cell message */
        .h-full.flex.items-center.justify-center.text-gray-300.text-sm {
          font-size: 0.6rem !important;
        }
        
        /* Adjust year and group badges */
        .px-3.py-1.bg-blue-100.text-blue-800.rounded-full.text-sm {
          padding: 2px 6px !important;
          font-size: 0.65rem !important;
        }
        
        /* Adjust info boxes */
        .bg-purple-50 p, .bg-amber-50 p {
          font-size: 0.7rem !important;
        }
      }
    </style>
</head>
<body>
    <div class="card">
        <div class="p-6 flex justify-between items-center <?php echo $headerClass; ?>">
            <h1 class="text-2xl font-bold"><?php echo $pageTitle; ?></h1>
            <div class="flex space-x-3">
                <?php if ($isProfessorDebug): ?>
                <a href="../views/professor.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Retour à la Sélection
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
                    Bonjour <strong><?php echo htmlspecialchars($professorName); ?></strong>, voici votre emploi du temps personnel.
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
                                
                                // Determine color based on class_type if available
                                let color;
                                if (classData.class_type) {
                                    switch(classData.class_type) {
                                        case "CM": color = "#6b7280"; break;
                                        case "TD": color = "#10b981"; break;
                                        case "TP": color = "#3b82f6"; break;
                                        case "DE": color = "#f59e0b"; break;
                                        case "CO": color = "#ef4444"; break;
                                        default: color = classData.color || "#6b7280"; // Fallback to data.color or default grey
                                    }
                                } else {
                                    // Use the color from data if available, otherwise use default color
                                    color = classData.color || "#6b7280";
                                }
                                
                                classBlock.style.borderLeftColor = color;
                                
                                // Apply visual styling for professor view and admin debug mode if class is canceled or rescheduled
                                if ((userRole === 'professor' || userRole === 'admin') && (classData.is_canceled == 1 || classData.is_reschedule == 1)) {
                                    if (classData.is_canceled == 1) {
                                        classBlock.style.backgroundColor = "#FEF2F2"; // Very light red background for professor
                                    } else if (classData.is_reschedule == 1) {
                                        classBlock.style.backgroundColor = "#EFF6FF"; // Very light blue background for professor
                                    }
                                }

                                const subjectDiv = document.createElement("div");
                                subjectDiv.className = "font-medium";
                                subjectDiv.textContent = classData.subject;
                                
                                // Color the subject name based on class type
                                if (classData.class_type) {
                                    subjectDiv.style.color = color;
                                    
                                    // Add class type indicator
                                    const typeSpan = document.createElement("span");
                                    typeSpan.className = "ml-2 text-xs font-normal";
                                    typeSpan.textContent = `(${classData.class_type})`;
                                    subjectDiv.appendChild(typeSpan);
                                }

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
                                
                                // Add class type to room info if available
                                if (classData.class_type) {
                                    roomDiv.textContent = `Salle: ${classData.room}`;
                                }

                                classBlock.appendChild(subjectDiv);
                                classBlock.appendChild(detailsDiv);
                                classBlock.appendChild(roomDiv);
                                
                                // Add action buttons for professors (but not for admins in debug mode)
                                if (userRole === 'professor') {
                                    const actionsDiv = document.createElement("div");
                                    actionsDiv.className = "flex justify-end space-x-2 mt-2";
                                    
                                    if (classData.is_reschedule == 1) {
                                        // Show undo reschedule button
                                        const undoRescheduleBtn = document.createElement("button");
                                        undoRescheduleBtn.className = "text-xs bg-blue-100 text-blue-800 hover:bg-blue-200 px-2 py-1 rounded relative right-[5px]";
                                        undoRescheduleBtn.textContent = "Annuler le report";
                                        undoRescheduleBtn.onclick = function(e) {
                                            e.stopPropagation();
                                            updateClassStatus(classData.id, 'reset', classData.professor_id);
                                        };
                                        actionsDiv.appendChild(undoRescheduleBtn);
                                    } else if (classData.is_canceled == 1) {
                                        // Show undo cancel button
                                        const undoCancelBtn = document.createElement("button");
                                        undoCancelBtn.className = "text-xs bg-red-100 text-red-800 hover:bg-red-200 px-2 py-1 rounded ";
                                        undoCancelBtn.textContent = "Annuler l'annulation";
                                        undoCancelBtn.onclick = function(e) {
                                            e.stopPropagation();
                                            updateClassStatus(classData.id, 'reset', classData.professor_id);
                                        };
                                        actionsDiv.appendChild(undoCancelBtn);
                                    } else {
                                        // Show regular buttons
                                        // Reschedule button
                                        const rescheduleBtn = document.createElement("button");
                                        rescheduleBtn.className = "text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded relative left-[10px]";
                                        rescheduleBtn.textContent = "Reporter";
                                        rescheduleBtn.onclick = function(e) {
                                            e.stopPropagation();
                                            updateClassStatus(classData.id, 'reschedule', classData.professor_id);
                                        };
                                        
                                        // Cancel button
                                        const cancelBtn = document.createElement("button");
                                        cancelBtn.className = "text-xs text-red-600 hover:text-red-800 px-2 py-1 rounded";
                                        cancelBtn.textContent = "Annuler";
                                        cancelBtn.onclick = function(e) {
                                            e.stopPropagation();
                                            updateClassStatus(classData.id, 'cancel', classData.professor_id);
                                        };
                                        
                                        actionsDiv.appendChild(rescheduleBtn);
                                        actionsDiv.appendChild(cancelBtn);
                                    }
                                    
                                    classBlock.appendChild(actionsDiv);
                                }
                                
                                // Apply visual indicators for admin if class is canceled or rescheduled
                                if (userRole === 'admin' && (classData.is_canceled == 1 || classData.is_reschedule == 1)) {
                                    if (classData.is_canceled == 1) {
                                        classBlock.style.backgroundColor = "#FEE2E2"; // Light red background
                                        const statusDiv = document.createElement("div");
                                        statusDiv.className = "text-xs font-medium text-red-700 mt-1";
                                        statusDiv.textContent = "ANNULÉ PAR LE PROFESSEUR";
                                        classBlock.appendChild(statusDiv);
                                    } else if (classData.is_reschedule == 1) {
                                        classBlock.style.backgroundColor = "#DBEAFE"; // Light blue background
                                        const statusDiv = document.createElement("div");
                                        statusDiv.className = "text-xs font-medium text-blue-700 mt-1";
                                        statusDiv.textContent = "DEMANDE DE REPORT";
                                        classBlock.appendChild(statusDiv);
                                    }
                                }

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
                    apiUrl = `../api/get_timetable.php?year=${encodeURIComponent(currentYear)}&group=${encodeURIComponent(currentGroup)}`;
                }
                
                // Try to load from server - prefer published version first
                fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data && data.data) {
                        timetableData = data.data;
                        generateViewTimetable();
                        showToast("success", `Emploi du temps chargé`);
                        
                        // Update URL with current year and group
                        updateUrlWithYearAndGroup(currentYear, currentGroup);
                    } else {
                        // If no data from server, show empty timetable
                        initTimetableData();
                        generateViewTimetable();
                        if (userRole === 'professor' || isProfessorDebug) {
                            showToast("info", "Aucun cours n'a été trouvé dans votre emploi du temps");
                        } else {
                            showToast("info", `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`);
                            
                            // Update URL with current year and group even if no data found
                            updateUrlWithYearAndGroup(currentYear, currentGroup);
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
            
            // Function to update URL with current year and group without reloading the page
            function updateUrlWithYearAndGroup(year, group) {
                if (userRole !== 'professor' && !isProfessorDebug) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('year', year);
                    url.searchParams.set('group', group);
                    window.history.replaceState({}, '', url);
                }
            }

            // Function to update class status (cancel or reschedule)
            function updateClassStatus(classId, status, professorId) {
                if (!classId || !status || !professorId) {
                    showToast("error", "Données manquantes pour mettre à jour le statut du cours");
                    return;
                }
                
                const data = {
                    id: classId,
                    status: status,
                    professor_id: professorId
                };
                
                fetch('../api/update_class_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showToast("success", result.message);
                        // Reload timetable data to reflect the changes
                        loadTimetableData();
                    } else {
                        showToast("error", result.message || "Erreur lors de la mise à jour du statut");
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast("error", "Erreur lors de la mise à jour du statut");
                });
            }
            
            // Load timetable data on page load
            loadTimetableData();
        });
    </script>
</body>
</html> 