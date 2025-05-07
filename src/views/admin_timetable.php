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
$role = $_SESSION['role'];
if ($role === 'admin') {
    // Admin stays on this page - full edit capabilities
} elseif ($role === 'professor') {
    // Redirect professors to view-only version
    header("Location: timetable_view.php?role=professor");
    exit;
} elseif ($role === 'student') {
    // Redirect students to view-only version with their group pre-selected
    $group_id = $_SESSION['group_id'] ?? 'G1';
    $year_id = $_SESSION['year_id'] ?? 'Première Année';
    header("Location: timetable_view.php?role=student&year=$year_id&group=$group_id");
    exit;
} else {
    // Unknown role
    header("Location: login.php?error=invalid_role");
    exit;
}

// Initialize variables and define constants
try {
    // Get all years
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
    
    // Get all unique group names (for dropdown display)
    $allGroups = [];
    foreach ($groupsByYear as $yearGroups) {
        $allGroups = array_merge($allGroups, $yearGroups);
    }
    $groups = array_unique($allGroups);
    
    // Fetch real subjects from database
    $stmt = $pdo->query("SELECT id, name, code FROM subjects ORDER BY name");
    $subjectsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add a default color for each subject since the database doesn't have colors
    foreach ($subjectsData as &$subject) {
        // Generate a random color or use a default
        $subject['color'] = '#3b82f6'; // Default blue color
    }
    
    $subjects = array_column($subjectsData, 'name');
} catch (PDOException $e) {
    // Fallback to defaults if database query fails
    error_log("Failed to load database data: " . $e->getMessage());
    $years = ["Première Année", "Deuxième Année", "Troisième Année"];
    $groups = ["G1", "G2", "G3", "G4", "G5", "G6"];
    
    // Default groupsByYear structure based on the database results
    $groupsByYear = [
        "Première Année" => ["G1", "G2", "G3", "G4", "G5", "G6"],
        "Deuxième Année" => ["G1", "G2", "G3", "G4"],
        "Troisième Année" => ["G1", "G2"]
    ];
    
    // Fallback to empty array if query fails
    $subjectsData = [];
    $subjects = [];
}

// Fetch real professors from database
try {
    // Fetch ID and NAME instead of email
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'professor' ORDER BY name");
    $professorsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // We don't need the separate $professors array anymore
    // $professors = array_column($professorsData, 'name'); 
} catch (PDOException $e) {
    error_log("Failed to load professors from database: " . $e->getMessage());
    // Fallback to empty array if query fails
    $professorsData = [];
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
$currentYear = isset($_GET['year']) ? $_GET['year'] : $years[0] ?? 'Première Année';
$currentGroup = isset($_GET['group']) ? $_GET['group'] : 
    (isset($groupsByYear[$currentYear]) && !empty($groupsByYear[$currentYear]) ? 
    $groupsByYear[$currentYear][0] : 'G1');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>University Timetable Management</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
      * {
        transition: all 0.2s ease;
        box-sizing: border-box;
      }

      body {
        background-color: #f5f7fa;
        font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI",
          Roboto, sans-serif;
        margin: 0;
        padding: 10px;

      }

      .card {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
          0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border-radius: 0.75rem;
        overflow: hidden;
        background-color: white;
        max-width: 1200px;
        margin: 10px auto;
        transition: all 0.3s ease;
      }

      .header-admin {
        background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
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
        padding: 4px;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: top;
        height: 80px;
      }

      .time-cell {
        font-weight: 500;
        text-align: center;
        width: 90px;
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
        transition: all 0.3s ease, border-color 0.2s ease, box-shadow 0.2s ease;
      }
      
      .dropdown-button:hover {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1);
      }
      
      .dropdown-button[disabled] {
        cursor: not-allowed;
        background-color: #f1f5f9;
        color: #94a3b8;
        border-color: #e2e8f0;
      }
      
      .dropdown-button[disabled]:hover {
        border-color: #e2e8f0;
        box-shadow: none;
      }

      .dropdown-button svg {
        transition: transform 0.3s ease;
      }

      .dropdown-button.active svg {
        transform: rotate(180deg);
      }

      .dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        width: 100%;
        background-color: #fff;
        border-radius: 0.375rem;
        box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.12);
        z-index: 100;
        max-height: 200px;
        overflow-y: auto;
        opacity: 0;
        transform: translateY(-10px) rotateX(-5deg);
        transform-origin: top center;
        transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                    transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
        visibility: hidden;
        display: none; /* Start with display none to prevent initial animation */
      }

      .dropdown-menu.open {
        display: block;
        visibility: visible;
        opacity: 1;
        transform: translateY(0) rotateX(0);
        pointer-events: auto;
        animation: menuAppear 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
      }
      
      .dropdown-menu.closing {
        display: block;
        animation: menuClose 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
      }
      
      @keyframes menuAppear {
        0% {
          opacity: 0;
          transform: translateY(-10px) rotateX(-5deg);
          visibility: visible;
        }
        100% {
          opacity: 1;
          transform: translateY(0) rotateX(0);
          visibility: visible;
        }
      }
      
      @keyframes menuClose {
        0% {
          opacity: 1;
          transform: translateY(0) rotateX(0);
          visibility: visible;
        }
        100% {
          opacity: 0;
          transform: translateY(-10px) rotateX(-5deg);
          visibility: hidden;
        }
      }

      .dropdown-item {
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s ease, transform 0.1s ease;
      }

      .dropdown-item:hover {
        background-color: #f1f5f9;
        transform: translateX(2px);
      }

      .class-block {
        background-color: #f8fafc;
        border-radius: 0.375rem;
        border-left: 4px solid #3b82f6;
        padding: 6px;
        margin-bottom: 2px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 0.9rem;
      }

      .class-block:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      }

      .empty-cell {
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
      }

      .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        backdrop-filter: blur(5px);
        opacity: 1;
      }
      
      .modal.fade-in {
        animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }
      
      .modal.fade-out {
        animation: fadeOut 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .modal-content {
        background-color: white;
        max-width: 500px;
        margin: 100px auto;
        padding: 20px;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      }
      
      .modal.fade-in .modal-content {
        animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      
      .modal.fade-out .modal-content {
        animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
      }

      .close {
        cursor: pointer;
        font-size: 24px;
      }

      .btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      }

      /* Compact styling for smaller screens */
      @media (max-width: 768px) {
        .timetable td {
          height: 70px;
          padding: 3px;
          font-size: 0.8rem;
        }

        .time-cell {
          width: 70px;
        }
      }

      /* Animation keyframes */
      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }
      
      @keyframes fadeOut {
        from {
          opacity: 1;
        }
        to {
          opacity: 0;
        }
      }

      @keyframes slideDown {
        from {
          transform: translateY(-30px) scale(0.95);
          opacity: 0;
        }
        to {
          transform: translateY(0) scale(1);
          opacity: 1;
        }
      }
      
      @keyframes slideUp {
        from {
          transform: translateY(0) scale(1);
          opacity: 1;
        }
        to {
          transform: translateY(10px) scale(0.95);
          opacity: 0;
        }
      }

      /* Set maximum height for the timetable container */
      .timetable-container {
        max-height: 60vh;
        overflow-y: auto;
        border-radius: 0.5rem;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;     /* Firefox */
      }
      
      /* Hide scrollbar for all browsers */
      .timetable-container::-webkit-scrollbar,
      *::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
      }
      
      /* Hide all scrollbars for any element */
      * {
        -ms-overflow-style: none;
        scrollbar-width: none;
      }

      /* Custom select styling */
      .custom-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        background-color: white;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
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

      .p-6 {
        padding: 1rem !important;
      }
    </style>
</head>
<body>
    <div class="card">
        <div class="p-6 flex justify-between items-center header-admin">
            <h1 class="text-2xl font-bold">Gestion des Emplois du Temps - Admin</h1>
            <a href="../admin/index.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour au Tableau de Bord
            </a>
        </div>

        <div class="p-6">
            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Année</label>
                    <div class="dropdown-container">
                        <button class="dropdown-button" id="year-dropdown">
                            <span id="selected-year"><?php echo $currentYear; ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Groupe</label>
                    <div class="dropdown-container">
                        <button class="dropdown-button" id="group-dropdown">
                            <span id="selected-group"><?php echo $currentGroup; ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        <?php foreach ($timeSlots as $time): ?>
                            <tr>
                                <td class="time-cell"><?php echo $time; ?></td>
                                <?php foreach ($days as $day): ?>
                                    <td class="subject-cell">
                                        <div class="empty-cell h-full">
                                            <button class="text-gray-400 hover:text-blue-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
            <div class="mt-6 flex justify-end space-x-3">
                <button id="save-btn" class="btn px-4 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button id="publish-btn" class="btn px-4 py-2 bg-green-600 text-white font-medium rounded-md hover:bg-green-700">
                    Publier
                </button>
                <button id="publish-all-btn" class="btn px-4 py-2 bg-purple-600 text-white font-medium rounded-md hover:bg-purple-700">
                    Tout Publier
                </button>
            </div>

            <!-- Message de statut -->
            <div id="status-message" class="mt-4 hidden p-4 rounded-md"></div>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Cours -->
    <div id="class-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-bold" id="modal-title">Ajouter un Cours</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="class-form">
                    <input type="hidden" id="edit-day" />
                    <input type="hidden" id="edit-time" />
                    <input type="hidden" id="edit-id" />
                    <input type="hidden" id="edit-color" value="#3b82f6" />

                    <div class="mb-4">
                        <label for="professor-select" class="block text-sm font-medium text-gray-700 mb-1">Professeur</label>
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="professor-dropdown">
                                <span id="selected-professor">Sélectionner un professeur</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="dropdown-menu" id="professor-menu">
                                <?php foreach ($professorsData as $professor): ?>
                                <div class="dropdown-item" data-value="<?php echo htmlspecialchars($professor['name']); ?>" data-id="<?php echo $professor['id']; ?>">
                                    <?php echo htmlspecialchars($professor['name']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="subject-select" class="block text-sm font-medium text-gray-700 mb-1">Matière</label>
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="subject-dropdown" disabled>
                                <span id="selected-subject">Sélectionner un professeur d'abord</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                    <div class="mb-4">
                        <label for="room-select" class="block text-sm font-medium text-gray-700 mb-1">Salle</label>
                        <div class="dropdown-container">
                            <button type="button" class="dropdown-button" id="room-dropdown">
                                <span id="selected-room">Sélectionner une salle</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancel-btn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" id="save-class-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                            Enregistrer
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
                <h2 class="text-xl font-bold text-red-600">Modifications Non Enregistrées</h2>
                <span class="close" id="unsaved-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="mb-2">Vous avez des modifications non enregistrées dans votre emploi du temps.</p>
                    <p>Souhaitez-vous enregistrer vos modifications avant de continuer ?</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" id="discard-btn" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Ignorer les Modifications
                    </button>
                    <button type="button" id="save-continue-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                        Enregistrer et Continuer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour Publier Tous les Emplois du Temps -->
    <div id="publish-all-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-purple-600">Publier Tous les Emplois du Temps</h2>
                <span class="close" id="publish-all-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="mb-2">Ceci va publier TOUS les emplois du temps pour TOUTES les années et groupes.</p>
                    <p>Les emplois du temps publiés seront visibles par tous les étudiants et professeurs. Êtes-vous sûr ?</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" id="publish-all-cancel" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="button" id="publish-all-confirm" class="px-4 py-2 bg-purple-600 text-white rounded-md text-sm font-medium hover:bg-purple-700">
                        Oui, Tout Publier
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmation de Suppression -->
    <div id="delete-class-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-xl font-bold text-red-600">Supprimer le Cours</h2>
                <span class="close" id="delete-class-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <p class="mb-2">Êtes-vous sûr de vouloir supprimer ce cours ?</p>
                    <p id="delete-class-name" class="font-medium"></p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" id="delete-class-cancel" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="button" id="delete-class-confirm" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Additional global functions for modal animation
            window.showModalWithAnimation = function(modalId) {
                const modal = document.getElementById(modalId);
                modal.classList.add('fade-in');
                modal.classList.remove('fade-out');
                modal.style.display = 'block';
            };
            
            window.closeModalWithAnimation = function(modalId) {
                const modal = document.getElementById(modalId);
                modal.classList.remove('fade-in');
                modal.classList.add('fade-out');
                
                setTimeout(function() {
                    modal.style.display = 'none';
                    modal.classList.remove('fade-out');
                }, 300);
            };
            
            // Apply animations to existing modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                const closeBtn = modal.querySelector('.close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        closeModalWithAnimation(modal.id);
                    });
                }
                
                // Close on click outside
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModalWithAnimation(modal.id);
                    }
                });
            });
            
            // Replace existing event listeners for modal specific buttons
            document.getElementById("cancel-btn").addEventListener("click", function() {
                closeModalWithAnimation("class-modal");
            });
            
            // Publish all modal buttons
            document.getElementById("publish-all-btn").addEventListener("click", function() {
                showModalWithAnimation("publish-all-modal");
            });
            
            document.getElementById("publish-all-close").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
            });
            
            document.getElementById("publish-all-cancel").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
            });
            
            document.getElementById("publish-all-confirm").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
                setTimeout(performPublishAllTimetables, 300);
            });
            
            // Delete class modal buttons
            document.getElementById("delete-class-close").addEventListener("click", function() {
                closeModalWithAnimation("delete-class-modal");
            });
            
            document.getElementById("delete-class-cancel").addEventListener("click", function() {
                closeModalWithAnimation("delete-class-modal");
            });
            
            // Variables for storing class to be deleted
            let deleteClassDay = null;
            let deleteClassTime = null;
            
            // Timetable data
            let timetableData = {};
            let currentYear = "<?php echo $currentYear; ?>";
            let currentGroup = "<?php echo $currentGroup; ?>";
            let groupsByYear = <?php echo json_encode($groupsByYear); ?>;
            // Track changes
            let hasUnsavedChanges = false;
            let isCurrentlyPublished = false; // True if the current version is published
            let hasDraftChanges = false; // True if there are saved changes that haven't been published yet
            
            // Track destination for when changing sections
            let pendingDestination = null;

            const timeSlots = <?php echo json_encode($timeSlots); ?>;
            const days = <?php echo json_encode($days); ?>;

            // Function to toggle dropdown state and arrow rotation
            function toggleDropdown(dropdownButton, dropdownMenu) {
                if (dropdownMenu.classList.contains("open")) {
                    // Closing the dropdown
                    dropdownButton.classList.remove("active");
                    dropdownMenu.classList.remove("open");
                    dropdownMenu.classList.add("closing");
                    
                    // After animation completes, hide the dropdown completely
                    setTimeout(() => {
                        dropdownMenu.classList.remove("closing");
                        dropdownMenu.style.display = "none";
                    }, 300); // Match the animation duration
                    
                    return false;
                } else {
                    // Opening the dropdown
                    closeAllDropdowns(); // Close any other open dropdowns
                    dropdownButton.classList.add("active");
                    dropdownMenu.style.display = "block"; // Make it visible first
                    
                    // Trigger reflow/repaint to ensure the animation runs
                    void dropdownMenu.offsetWidth;
                    
                    dropdownMenu.classList.add("open");
                    return true;
                }
            }
            
            // Function to close all dropdowns
            function closeAllDropdowns() {
                document.querySelectorAll(".dropdown-menu.open").forEach(menu => {
                    const button = menu.parentElement.querySelector(".dropdown-button");
                    button.classList.remove("active");
                    menu.classList.remove("open");
                    menu.classList.add("closing");
                    
                    setTimeout(() => {
                        menu.classList.remove("closing");
                        menu.style.display = "none";
                    }, 300);
                });
            }

            // Initialize empty timetable data
            function initTimetableData() {
                timetableData = {};
                days.forEach(day => {
                    timetableData[day] = {};
                    timeSlots.forEach(time => {
                        timetableData[day][time] = null;
                    });
                });
                hasUnsavedChanges = false;
                isCurrentlyPublished = false;
                hasDraftChanges = false;
            }
            
            // Display the publish status on the page
            function updatePublishStatus() {
                const statusDiv = document.getElementById("status-message");
                statusDiv.classList.remove("hidden", "bg-green-100", "bg-yellow-100", "bg-blue-100", "text-green-800", "text-yellow-800", "text-blue-800");
                
                // Check if timetable is completely empty
                let isEmptyTimetable = true;
                for (const day in timetableData) {
                    for (const time in timetableData[day]) {
                        if (timetableData[day][time] !== null) {
                            isEmptyTimetable = false;
                            break;
                        }
                    }
                    if (!isEmptyTimetable) break;
                }
                
                // Hide status message for completely empty timetables that haven't been saved yet
                if (isEmptyTimetable && !hasUnsavedChanges && !hasDraftChanges && !isCurrentlyPublished) {
                    statusDiv.classList.add("hidden");
                    return;
                }
                
                if (hasUnsavedChanges) {
                    // Always show unsaved changes first regardless of publish state
                    statusDiv.classList.add("bg-yellow-100", "text-yellow-800");
                    statusDiv.textContent = "Vous avez des modifications non enregistrées. N'oubliez pas d'enregistrer avant de quitter !";
                    statusDiv.classList.remove("hidden");
                } else if (hasDraftChanges && isCurrentlyPublished) {
                    // Show when we have saved changes that differ from the published version
                    statusDiv.classList.add("bg-blue-100", "text-blue-800");
                    statusDiv.textContent = "Vous avez des modifications enregistrées qui ne sont pas encore publiées. Les étudiants et professeurs voient toujours la version précédemment publiée.";
                    statusDiv.classList.remove("hidden");
                } else if (isCurrentlyPublished) {
                    // If saved and published with no draft changes
                    statusDiv.classList.add("bg-green-100", "text-green-800");
                    statusDiv.textContent = "Cet emploi du temps est publié et visible par les étudiants et professeurs.";
                    statusDiv.classList.remove("hidden");
                } else if (!isEmptyTimetable) {
                    // If saved but not published
                    statusDiv.classList.add("bg-yellow-100", "text-yellow-800");
                    statusDiv.textContent = "Cet emploi du temps est enregistré mais pas encore publié. Visible uniquement par les admins jusqu'à la publication.";
                    statusDiv.classList.remove("hidden");
                } else {
                    // Empty timetable, hide status
                    statusDiv.classList.add("hidden");
                }
            }

            // Function to show unsaved changes warning
            function showUnsavedChangesWarning(callback) {
                const modal = document.getElementById("unsaved-changes-modal");
                showModalWithAnimation("unsaved-changes-modal");
                
                const closeBtn = document.getElementById("unsaved-close");
                const discardBtn = document.getElementById("discard-btn");
                const saveBtn = document.getElementById("save-continue-btn");
                
                // Clear previous event listeners
                const newCloseBtn = closeBtn.cloneNode(true);
                closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                
                const newDiscardBtn = discardBtn.cloneNode(true);
                discardBtn.parentNode.replaceChild(newDiscardBtn, discardBtn);
                
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                // Add event listeners
                newCloseBtn.addEventListener("click", function() {
                    closeModalWithAnimation("unsaved-changes-modal");
                });
                
                newDiscardBtn.addEventListener("click", function() {
                    closeModalWithAnimation("unsaved-changes-modal");
                    hasUnsavedChanges = false;
                    hasDraftChanges = false;
                    if (callback) callback(false); // Continue without saving
                });
                
                newSaveBtn.addEventListener("click", function() {
                    closeModalWithAnimation("unsaved-changes-modal");
                    saveCurrentTimetable(function() {
                        if (callback) callback(true); // Continue after saving
                    });
                });
            }

            // Generate empty timetable
            function generateEmptyTimetable() {
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

                            const classBlock = document.createElement("div");
                            classBlock.className = "class-block";
                            // Use the color from data if available, otherwise use default blue
                            classBlock.style.borderLeftColor = data.color || "#3b82f6";

                            const subjectDiv = document.createElement("div");
                            subjectDiv.className = "font-medium";
                            subjectDiv.textContent = data.subject;

                            const professorDiv = document.createElement("div");
                            professorDiv.className = "text-sm text-gray-600";
                            professorDiv.textContent = data.professor;

                            const roomDiv = document.createElement("div");
                            roomDiv.className = "text-xs text-gray-500 mt-1";
                            roomDiv.textContent = `Salle: ${data.room}`;

                            const actionDiv = document.createElement("div");
                            actionDiv.className = "mt-2 flex justify-end space-x-2";

                            const editBtn = document.createElement("button");
                            editBtn.className = "text-xs text-blue-600 hover:text-blue-800";
                            editBtn.textContent = "Modifier";
                            editBtn.addEventListener("click", function() {
                                openEditModal(day, time);
                            });

                            const deleteBtn = document.createElement("button");
                            deleteBtn.className = "text-xs text-red-600 hover:text-red-800";
                            deleteBtn.textContent = "Supprimer";
                            deleteBtn.addEventListener("click", function() {
                                // Store the day and time for the class to delete
                                deleteClassDay = day;
                                deleteClassTime = time;
                                
                                // Set the class name in the modal
                                document.getElementById("delete-class-name").textContent = data.subject;
                                
                                // Show the delete confirmation modal
                                showModalWithAnimation("delete-class-modal");
                            });

                            actionDiv.appendChild(editBtn);
                            actionDiv.appendChild(deleteBtn);

                            classBlock.appendChild(subjectDiv);
                            classBlock.appendChild(professorDiv);
                            classBlock.appendChild(roomDiv);
                            classBlock.appendChild(actionDiv);

                            cell.appendChild(classBlock);
                        } else {
                            // Empty cell with add button
                            const emptyCell = document.createElement("div");
                            emptyCell.className = "empty-cell h-full";
                            emptyCell.innerHTML = `
                                <button class="text-gray-400 hover:text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                </button>
                            `;
                            emptyCell.addEventListener("click", function() {
                                openAddModal(day, time);
                            });

                            cell.appendChild(emptyCell);
                        }

                        row.appendChild(cell);
                    });

                    tbody.appendChild(row);
                });
            }

            // Initialize
            initTimetableData();
            generateEmptyTimetable();

            // Dropdown handling
            const yearDropdown = document.getElementById("year-dropdown");
            const yearMenu = document.getElementById("year-menu");
            const groupDropdown = document.getElementById("group-dropdown");
            const groupMenu = document.getElementById("group-menu");

            yearDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(yearDropdown, yearMenu);
            });

            groupDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(groupDropdown, groupMenu);
            });

            // Close dropdowns when clicking outside
            document.addEventListener("click", function(event) {
                closeAllDropdowns();
            });
            
            // Function to update group dropdown based on selected year
            function updateGroupDropdown(year) {
                const groupMenu = document.getElementById("group-menu");
                groupMenu.innerHTML = '';
                
                if (groupsByYear[year]) {
                    groupsByYear[year].forEach(group => {
                        const item = document.createElement("div");
                        item.className = "dropdown-item";
                        item.setAttribute("data-value", group);
                        item.textContent = group;
                        item.addEventListener("click", function() {
                            const selectedGroup = this.getAttribute("data-value");
                            
                            // Skip if selecting the same group
                            if (selectedGroup === currentGroup) {
                                groupMenu.classList.remove("open");
                                groupDropdown.classList.remove("active"); // Retirer la classe active
                                return;
                            }
                            
                            // Store the destination
                            pendingDestination = {
                                type: 'group',
                                year: currentYear,
                                group: selectedGroup
                            };
                            
                            // If there are unsaved changes, show warning
                            if (hasUnsavedChanges) {
                                showUnsavedChangesWarning(function(saved) {
                                    // After user's decision, switch to the group
                                    document.getElementById("selected-group").textContent = selectedGroup;
                                    currentGroup = selectedGroup;
                                    groupMenu.classList.remove("open");
                                    groupDropdown.classList.remove("active"); // Retirer la classe active
                                    
                                    // Load timetable for this year/group
                                    loadSavedData();
                                    showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                                });
                            } else {
                                // No changes, just update and load
                                document.getElementById("selected-group").textContent = selectedGroup;
                                currentGroup = selectedGroup;
                                groupMenu.classList.remove("open");
                                groupDropdown.classList.remove("active"); // Retirer la classe active
                                
                                // Load timetable for this year/group
                                loadSavedData();
                                showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                            }
                        });
                        groupMenu.appendChild(item);
                    });
                }
            }

            // Year selection
            document.querySelectorAll("#year-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const year = this.getAttribute("data-value");
                    
                    // Skip if selecting the same year
                    if (year === currentYear) {
                        yearMenu.classList.remove("open");
                        yearDropdown.classList.remove("active"); // Retirer la classe active
                        return;
                    }
                    
                    // Store the destination
                    pendingDestination = {
                        type: 'year',
                        year: year
                    };
                    
                    // If there are unsaved changes, show warning
                    if (hasUnsavedChanges) {
                        showUnsavedChangesWarning(function(saved) {
                            // After user's decision, switch to the year
                            document.getElementById("selected-year").textContent = year;
                            currentYear = year;
                            yearMenu.classList.remove("open");
                            yearDropdown.classList.remove("active"); // Retirer la classe active
                            
                            // Update the group dropdown with year-specific groups
                            updateGroupDropdown(year);
                            
                            // Reset to first group in the list
                            if (groupsByYear[year] && groupsByYear[year].length > 0) {
                                currentGroup = groupsByYear[year][0];
                                document.getElementById("selected-group").textContent = currentGroup;
                            }

                            // Load timetable for this year/group
                            loadSavedData();
                            showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                        });
                    } else {
                        // No changes, just update and load
                        document.getElementById("selected-year").textContent = year;
                        currentYear = year;
                        yearMenu.classList.remove("open");
                        yearDropdown.classList.remove("active"); // Retirer la classe active
                        
                        // Update the group dropdown with year-specific groups
                        updateGroupDropdown(year);
                        
                        // Reset to first group in the list
                        if (groupsByYear[year] && groupsByYear[year].length > 0) {
                            currentGroup = groupsByYear[year][0];
                            document.getElementById("selected-group").textContent = currentGroup;
                        }

                        // Load timetable for this year/group
                        loadSavedData();
                        showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                    }
                });
            });
            
            // Initialize group dropdown with current year's groups on page load
            updateGroupDropdown(currentYear);
            
            // Function to save current timetable
            function saveCurrentTimetable(callback) {
                // Create a payload for server - excluding any publish flags
                const payload = {
                    year: currentYear,
                    group: currentGroup,
                    data: timetableData,
                    action: "save_only" // Explicit action
                };

                console.log('Saving timetable with payload:', JSON.stringify(payload).substring(0, 100) + '...');

                // Send data to PHP backend
                fetch('../api/save_timetable.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => {
                    console.log('Save response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Save response data:', data);
                    if (data.success) {
                        showToast("success", `Emploi du temps enregistré pour ${currentYear}-${currentGroup}`);
                        hasUnsavedChanges = false;
                        
                        // Check if the server tells us this is already published elsewhere
                        if (data.is_published) {
                            isCurrentlyPublished = true;
                            // If we saved after publishing, we have draft changes
                            hasDraftChanges = true;
                        } else {
                            isCurrentlyPublished = false;
                            hasDraftChanges = false;
                        }
                        
                        updatePublishStatus();
                        if (callback) callback();
                    } else {
                        showToast("error", "Échec de l'enregistrement de l'emploi du temps");
                    }
                })
                .catch(error => {
                    console.error('Error saving timetable:', error);
                    showToast("error", "Erreur lors de l'enregistrement de l'emploi du temps");
                    if (callback) callback();
                });
            }
            
            // Update file paths for AJAX requests
            document.getElementById("save-btn").addEventListener("click", function() {
                console.log('Save button clicked');
                saveCurrentTimetable();
            });

            document.getElementById("publish-btn").addEventListener("click", function() {
                console.log('Publish button clicked');
                // Publish is a separate action
                publishCurrentTimetable();
            });
            
            document.getElementById("publish-all-btn").addEventListener("click", function() {
                console.log('Publish All button clicked');
                // Show the modal instead of confirm dialog
                showModalWithAnimation("publish-all-modal");
            });
            
            // Close publish all modal when clicking X
            document.getElementById("publish-all-close").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
            });
            
            // Cancel publish all action
            document.getElementById("publish-all-cancel").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
            });
            
            // Confirm publish all action
            document.getElementById("publish-all-confirm").addEventListener("click", function() {
                closeModalWithAnimation("publish-all-modal");
                setTimeout(performPublishAllTimetables, 300);
            });
            
            // Function to publish current timetable - separated from save functionality
            function publishCurrentTimetable() {
                // Create a payload specifically for publishing
                const payload = {
                    year: currentYear,
                    group: currentGroup,
                    data: timetableData,
                    action: "publish" // Explicit action
                };

                console.log('Publishing timetable with payload:', JSON.stringify(payload).substring(0, 100) + '...');

                // Send data to PHP backend - different endpoint
                fetch('../api/publish_timetable.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => {
                    console.log('Publish response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Publish response data:', data);
                    if (data.success) {
                        showToast("success", `Emploi du temps publié pour ${currentYear}-${currentGroup}`);
                        hasUnsavedChanges = false;
                        isCurrentlyPublished = true;
                        hasDraftChanges = false; // Reset draft changes flag since we just published
                        updatePublishStatus();
                    } else {
                        showToast("error", "Échec de la publication de l'emploi du temps");
                    }
                })
                .catch(error => {
                    console.error('Error publishing timetable:', error);
                    showToast("error", "Erreur lors de la publication de l'emploi du temps");
                });
            }

            // Function to publish all timetables
            function publishAllTimetables() {
                // Show modal instead of confirm dialog
                document.getElementById("publish-all-modal").style.display = "block";
            }
            
            // Actual implementation of publish all timetables
            function performPublishAllTimetables() {
                // Send request to publish all timetables
                fetch('../api/publish_all_timetables.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => {
                    console.log('Publish all response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Publish all response data:', data);
                    if (data.success) {
                        showToast("success", data.message);
                        // If current timetable was in the published list, update its status
                        if (data.published.includes(currentYear + '-' + currentGroup)) {
                            isCurrentlyPublished = true;
                            hasDraftChanges = false;
                            updatePublishStatus();
                        }
                    } else {
                        showToast("error", data.message || "Échec de la publication de tous les emplois du temps");
                    }
                })
                .catch(error => {
                    console.error('Error publishing all timetables:', error);
                    showToast("error", "Erreur lors de la publication de tous les emplois du temps");
                });
            }

            // Modal handling
            const modal = document.getElementById("class-modal");
            const closeBtn = document.querySelector(".close");
            const cancelBtn = document.getElementById("cancel-btn");
            const classForm = document.getElementById("class-form");

            // Professor dropdown handling
            const professorDropdown = document.getElementById("professor-dropdown");
            const professorMenu = document.getElementById("professor-menu");
            const subjectDropdown = document.getElementById("subject-dropdown");
            const subjectMenu = document.getElementById("subject-menu");

            professorDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(professorDropdown, professorMenu);
            });

            document.querySelectorAll("#professor-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const professorName = this.getAttribute("data-value"); // Get name from data-value
                    const professorId = this.getAttribute("data-id");
                    
                    console.log("Selected professor:", professorName, "ID:", professorId); // Log name and ID
                    
                    document.getElementById("selected-professor").textContent = professorName; // Display name
                    document.getElementById("selected-professor").setAttribute("data-id", professorId);
                    professorMenu.classList.remove("open");
                    professorDropdown.classList.remove("active"); // Retirer la classe active
                    
                    // Enable subject dropdown now that a professor is selected
                    subjectDropdown.removeAttribute("disabled");
                    document.getElementById("selected-subject").textContent = "Chargement des matières...";
                    
                    // Filter subjects based on selected professor
                    filterSubjectsByProfessor(professorId);
                });
            });

            function filterSubjectsByProfessor(professorId) {
                // Fetch subjects assigned to this professor from the database
                if (!professorId) {
                    document.getElementById("selected-subject").textContent = "Sélectionner un professeur d'abord";
                    subjectDropdown.setAttribute("disabled", "disabled");
                    return;
                }
                
                console.log('Fetching subjects for professor ID:', professorId);
                
                // Clear existing subject menu items
                const subjectMenu = document.getElementById("subject-menu");
                subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Chargement...</div>';
                
                // Make an AJAX call to get the subjects for this professor
                // Use a full URL path to test
                const baseUrl = window.location.protocol + '//' + window.location.hostname;
                const apiUrl = baseUrl + '/PI-php/src/api/get_professor_subjects.php?professor_id=' + professorId;
                console.log('Requesting API URL:', apiUrl);
                
                fetch(apiUrl)
                .then(response => {
                    console.log('API response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API response data:', data);
                    
                    if (data.success && data.subjects && data.subjects.length > 0) {
                        // Clear current menu
                        subjectMenu.innerHTML = '';
                        
                        // Add each subject to the dropdown
                        data.subjects.forEach(subject => {
                            const item = document.createElement("div");
                            item.className = "dropdown-item";
                            item.setAttribute("data-value", subject.name);
                            item.setAttribute("data-id", subject.id);
                            item.setAttribute("data-color", subject.color || "#3b82f6");
                            
                            // Show subject name and code if available
                            const displayText = subject.code ? 
                                `${subject.name} (${subject.code})` : 
                                subject.name;
                            
                            item.textContent = displayText;
                            
                            item.addEventListener("click", function() {
                                const subject = this.getAttribute("data-value");
                                const subjectId = this.getAttribute("data-id");
                                const color = this.getAttribute("data-color");
                                
                                document.getElementById("selected-subject").textContent = subject;
                                document.getElementById("selected-subject").setAttribute("data-id", subjectId);
                                document.getElementById("edit-color").value = color;
                                subjectMenu.classList.remove("open");
                                subjectDropdown.classList.remove("active"); // Retirer la classe active
                            });
                            
                            subjectMenu.appendChild(item);
                        });
                        
                        // Enable the dropdown
                        document.getElementById("subject-dropdown").removeAttribute("disabled");
                        document.getElementById("selected-subject").textContent = "Sélectionner une matière";
                    } else {
                        // No subjects found for this professor
                        subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Aucune matière assignée à ce professeur</div>';
                        document.getElementById("selected-subject").textContent = "Aucune matière disponible";
                        document.getElementById("subject-dropdown").setAttribute("disabled", "disabled");
                    }
                })
                .catch(error => {
                    console.error('Error fetching professor subjects:', error);
                    document.getElementById("selected-subject").textContent = "Erreur lors du chargement des matières";
                    document.getElementById("subject-dropdown").setAttribute("disabled", "disabled");
                    subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Erreur lors du chargement des matières</div>';
                });
            }

            // Subject dropdown handling
            subjectDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Only allow opening if not disabled
                if (!this.hasAttribute("disabled")) {
                    toggleDropdown(subjectDropdown, subjectMenu);
                }
            });

            document.querySelectorAll("#subject-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const subject = this.getAttribute("data-value");
                    const subjectId = this.getAttribute("data-id") || null;
                    const color = this.getAttribute("data-color") || "#3b82f6";
                    
                    document.getElementById("selected-subject").textContent = subject;
                    document.getElementById("selected-subject").setAttribute("data-id", subjectId);
                    document.getElementById("edit-color").value = color;
                    subjectMenu.classList.remove("open");
                    subjectDropdown.classList.remove("active"); // Retirer la classe active
                });
            });

            // Room dropdown handling
            const roomDropdown = document.getElementById("room-dropdown");
            const roomMenu = document.getElementById("room-menu");

            roomDropdown.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(roomDropdown, roomMenu);
            });

            document.querySelectorAll("#room-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    document.getElementById("selected-room").textContent = this.getAttribute("data-value");
                    roomMenu.classList.remove("open");
                    roomDropdown.classList.remove("active"); // Retirer la classe active
                });
            });

            function openAddModal(day, time) {
                document.getElementById("modal-title").textContent = "Ajouter un Cours";
                document.getElementById("edit-day").value = day;
                document.getElementById("edit-time").value = time;
                document.getElementById("edit-id").value = ""; // New class

                // Reset dropdowns
                document.getElementById("selected-professor").textContent = "Sélectionner un professeur";
                document.getElementById("selected-professor").removeAttribute("data-id");
                document.getElementById("selected-subject").textContent = "Sélectionner un professeur d'abord";
                document.getElementById("selected-room").textContent = "Sélectionner une salle";
                
                // Disable subject dropdown until professor is selected
                document.getElementById("subject-dropdown").setAttribute("disabled", "disabled");

                showModalWithAnimation("class-modal");
            }

            function openEditModal(day, time) {
                const data = timetableData[day][time];
                if (!data) return;

                document.getElementById("modal-title").textContent = "Modifier un Cours";
                document.getElementById("edit-day").value = day;
                document.getElementById("edit-time").value = time;
                document.getElementById("edit-id").value = data.id || "";
                document.getElementById("edit-color").value = data.color || "#3b82f6";

                // Fill form with existing data - use professor name
                document.getElementById("selected-professor").textContent = data.professor || "Sélectionner un professeur";
                if (data.professor_id) {
                    document.getElementById("selected-professor").setAttribute("data-id", data.professor_id);
                }
                
                // Enable subject dropdown since we have a professor
                document.getElementById("subject-dropdown").removeAttribute("disabled");
                document.getElementById("selected-subject").textContent = data.subject || "Sélectionner une matière";
                if (data.subject_id) {
                    document.getElementById("selected-subject").setAttribute("data-id", data.subject_id);
                }
                
                document.getElementById("selected-room").textContent = data.room || "Sélectionner une salle";

                showModalWithAnimation("class-modal");
            }

            closeBtn.addEventListener("click", function() {
                closeModalWithAnimation("class-modal");
            });

            cancelBtn.addEventListener("click", function() {
                closeModalWithAnimation("class-modal");
            });

            // Close modal when clicking outside
            window.addEventListener("click", function(event) {
                if (event.target.classList.contains('modal')) {
                    closeModalWithAnimation(event.target.id);
                }
            });

            // Form submission
            classForm.addEventListener("submit", function(e) {
                e.preventDefault();

                const day = document.getElementById("edit-day").value;
                const time = document.getElementById("edit-time").value;
                const id = document.getElementById("edit-id").value || new Date().getTime().toString();
                const color = document.getElementById("edit-color").value || "#3b82f6";

                const professorElement = document.getElementById("selected-professor");
                const subjectElement = document.getElementById("selected-subject");
                const roomElement = document.getElementById("selected-room");
                
                const professor = professorElement.textContent;
                const professorId = professorElement.getAttribute("data-id");
                const subject = subjectElement.textContent;
                const subjectId = subjectElement.getAttribute("data-id");
                const room = roomElement.textContent;

                // Validate professor first
                if (professor === "Sélectionner un professeur") {
                    showToast("error", "Veuillez sélectionner un professeur");
                    return;
                }
                
                // Then validate subject
                if (subject === "Select a subject" || 
                    subject === "Sélectionner un professeur d'abord" ||
                    subject === "Aucune matière disponible" ||
                    subject === "Erreur lors du chargement des matières" ||
                    subject === "Chargement des matières...") {
                    showToast("error", "Veuillez sélectionner une matière");
                    return;
                }
                
                // Finally validate room
                if (room === "Sélectionner une salle") {
                    showToast("error", "Veuillez sélectionner une salle");
                    return;
                }

                // Save data with IDs for database storage
                timetableData[day][time] = {
                    id: id,
                    subject: subject,
                    subject_id: subjectId,
                    professor: professor,
                    professor_id: professorId,
                    room: room,
                    color: color,
                    year: currentYear,
                    group: currentGroup
                };

                // Mark that we have unsaved changes
                hasUnsavedChanges = true;
                updatePublishStatus();
                
                // Regenerate table
                generateEmptyTimetable();

                // Close modal
                closeModalWithAnimation("class-modal");

                showToast("success", "Cours enregistré ! N'oubliez pas d'utiliser le bouton Enregistrer pour sauvegarder les modifications");
            });

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

            // Load saved timetable data
            function loadSavedData() {
                // First initialize with empty timetable
                initTimetableData();
                
                // Try to load from server
                fetch(`../api/get_timetable.php?year=${currentYear}&group=${currentGroup}&admin=true`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.success !== false && data.data) {
                        // We found saved data, load it
                        timetableData = data.data;
                        generateEmptyTimetable(); // This will actually display the loaded data
                        showToast("success", `Emploi du temps chargé pour ${currentYear}-${currentGroup}`);
                        
                        // Set published flag based on the server response
                        isCurrentlyPublished = data.is_published || false;
                        hasDraftChanges = data.has_draft_changes || false;
                        hasUnsavedChanges = false;
                        updatePublishStatus();
                        console.log('Loaded timetable with published status:', isCurrentlyPublished, 'draft changes:', hasDraftChanges);
                    } else {
                        // No saved data found, keep the empty timetable
                        generateEmptyTimetable();
                        showToast("info", `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`);
                        isCurrentlyPublished = false;
                        hasDraftChanges = false;
                        hasUnsavedChanges = false;
                        updatePublishStatus();
                    }
                })
                .catch(error => {
                    console.error('Error loading timetable data:', error);
                    // Show error toast
                    showToast("error", "Erreur lors du chargement des données");
                    // Just use empty timetable
                    generateEmptyTimetable();
                    isCurrentlyPublished = false;
                    hasDraftChanges = false;
                    hasUnsavedChanges = false;
                    updatePublishStatus();
                });
            }
            
            // Warn user when leaving with unsaved changes using custom modal
            window.addEventListener('beforeunload', function(e) {
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
            
            // Add navigation handling for back button
            document.querySelector('a[href="../admin/index.php"]').addEventListener('click', function(e) {
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    showUnsavedChangesWarning(function() {
                        window.location.href = href;
                    });
                }
            });
            
            // Load any saved data on initial load
            loadSavedData(); // Actually load data instead of just initializing empty
            
            // Set up the delete confirmation button
            document.getElementById("delete-class-confirm").addEventListener("click", function() {
                if (deleteClassDay && deleteClassTime) {
                    // Delete the class
                    timetableData[deleteClassDay][deleteClassTime] = null;
                    
                    // Mark as unsaved changes
                    hasUnsavedChanges = true;
                    updatePublishStatus();
                    
                    // Regenerate timetable
                    generateEmptyTimetable();
                    
                    // Show success message
                    showToast("success", "Cours supprimé! N'oubliez pas de sauvegarder vos modifications.");
                    
                    // Close modal
                    closeModalWithAnimation("delete-class-modal");
                    
                    // Reset variables
                    deleteClassDay = null;
                    deleteClassTime = null;
                }
            });
        });
    </script>
</body>
</html>
