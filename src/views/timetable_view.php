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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
      * {
        transition: all 0.2s ease;
        box-sizing: border-box;
      }

      body {
        background-color: #f5f7fa;
        padding: 10px;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
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

    <script></script>
</body>
</html> 