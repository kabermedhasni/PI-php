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
<script src="https://cdn.tailwindcss.com"></script>
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

    /* Dropdown search styling */
    #professor-search {
        transition: all 0.2s ease;
        border-radius: 0.375rem;
    }
    
    #professor-search:focus {
        box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1);
        border-color: #3b82f6;
        outline: none;
    }
    
    .dropdown-menu .sticky {
        outline: none;
        border: none;
        background-color: white;
        z-index: 10;
    }

    #professor-list {
        max-height: 200px;
        overflow-y: auto;
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
    padding: 6px 10px;
    background-color: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    cursor: pointer;
    font-weight: 500;
    height: 34px;
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
    max-height: 190px;
    overflow-y: auto;
    opacity: 0;
    transform-origin: top center;
    transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), 
                transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    visibility: hidden;
    display: none; /* Start with display none to prevent initial animation */
    border: 1px solid #e2e8f0;
    }

    .dropdown-menu.open {
    display: block;
    visibility: visible;
    opacity: 1;
    transform: translateY(0) rotateX(0);
    pointer-events: auto;
    animation: menuAppear 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .dropdown-menu.closing {
    display: block;
    animation: menuClose 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    
    /* Update animations to support both top and bottom positioning */
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
    padding: 6px 10px;
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.1s ease;
    }

    .dropdown-item:hover {
    background-color: #f1f5f9;
    }

    .class-block {
    background-color: #f8fafc;
    border-radius: 0.375rem;
    border-left: 4px solid #6b7280; /* Changed from #3b82f6 to match CM default */
    padding: 6px;
    margin-bottom: 2px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.9rem;
    }

    /* .class-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    } */

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
    opacity: 0;
    transition: opacity 0.3s ease;
    }
    
    .modal.show {
    opacity: 1;
    }

    .modal-content {
    background-color: white;
    max-width: 500px;
    margin: 20px auto;
    padding: 15px;
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-20px);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
    max-height: 95vh;
    /* overflow-y: auto; */
    }
    
    .modal.show .modal-content {
    transform: translateY(0);
    opacity: 1;
    }

    .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
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

    /* Set maximum height for the timetable container */
    .timetable-container {
    max-height: 60vh;
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 0.5rem;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;     /* Firefox */
    -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
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
    padding: 6px 10px;
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

    /* Mobile responsive styling */
    @media (max-width: 768px) {
    body {
        padding: 5px;
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
    
    .timetable-container {
        max-height: 70vh;
        border-radius: 0.25rem;
    }
    
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
        
        .class-block div {
            margin-bottom: 2px;
        }
        
        .grid.grid-cols-1.md\:grid-cols-2.gap-6.mb-6 {
            gap: 0.5rem;
        }
        
        .toast {
            width: 90%;
            left: 5%;
            right: 5%;
            font-size: 0.8rem;
            padding: 10px;
            text-align: center;
        }
        
        a.flex.items-center, .btn {
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
        
        .dropdown-button {
            height: 32px;
            padding: 6px 8px;
            font-size: 0.8rem;
    }
    
    /* Make dropdowns in modals more touch-friendly */
    .dropdown-item {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
        
        /* Professor search mobile optimization */
        #professor-search {
            padding: 8px;
            font-size: 0.9rem;
        }
        
        #professor-list {
            max-height: 180px;
        }
    
    /* Improve empty cell appearance */
    .empty-cell button svg {
        width: 18px;
        height: 18px;
    }
    
        /* Mobile optimizations for small screens */
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
    
    .class-block {
        padding: 2px !important;
        margin-bottom: 0 !important;
        font-size: 0.65rem !important;
    }
    
    .class-block .text-xs {
        font-size: 0.6rem !important;
    }
    
    .class-block .mt-2 {
        margin-top: 0.25rem !important;
    }
    
    .class-block .space-x-2 > * + * {
        margin-left: 0.25rem !important;
    }
    
    .class-block .text-sm {
        font-size: 0.65rem !important;
        margin-bottom: 0 !important;
        line-height: 1.1 !important;
    }
    
    .class-block .mt-1 {
        margin-top: 0.1rem !important;
    }
    
    .class-block .text-xs.text-blue-600,
    .class-block .text-xs.text-red-600 {
        padding: 2px 4px !important;
        border-radius: 3px !important;
        background-color: rgba(255, 255, 255, 0.8) !important;
    }
    
    .class-block .text-xs.text-blue-600 {
        border: 1px solid #3b82f6 !important;
    }
    
    .class-block .text-xs.text-red-600 {
        border: 1px solid #ef4444 !important;
    }
    
    .modal-content {
        width: 95%;
        margin: 50px auto;
        padding: 12px;
    }
    
    .modal-header h2 {
        font-size: 1.1rem;
    }
    
    .btn {
        padding: 0.4rem 0.75rem !important;
        font-size: 0.8rem !important;
    }
    
    #arrow {
        width: 20px;
        height: auto;
    }
    }
    }

    /* Specific fix for second subgroup room dropdown */
    #room-menu-2, #professor-menu-2, #subject-menu-2 {
        z-index: 110; /* Higher z-index to appear above other dropdowns */
    }
    
    #second-subgroup-options {
        padding-bottom: 30px; /* Add more padding at the bottom */
        margin-bottom: 20px; /* Add margin at the bottom */
    }
    
    /* Add spacing between form sections */
    .modal-body .mb-4:last-child {
        margin-bottom: 30px;
    }
    
    /* Radio button animation */
    input[type="radio"] {
        appearance: none;
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        border: 2px solid #cbd5e0;
        border-radius: 50%;
        outline: none;
        position: relative;
        margin-right: 8px;
        cursor: pointer;
        transition: border-color 0.2s ease;
        vertical-align: -4px;
    }
    
    input[type="radio"]:checked {
        border-color: #4f46e5;
        animation: radio-pulse 0.3s ease;
    }
    
    @keyframes radio-pulse {
        0% { transform: scale(0.9); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    input[type="radio"]:after {
        content: '';
        position: absolute;
        width: 10px;
        height: 10px;
        background-color: #4f46e5;
        border-radius: 50%;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0);
        transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    input[type="radio"]:checked:after {
        transform: translate(-50%, -50%) scale(1);
        animation: radio-dot 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    @keyframes radio-dot {
        0% {
            transform: translate(-50%, -50%) scale(0);
        }
        50% {
            transform: translate(-50%, -50%) scale(1.2);
        }
        100% {
            transform: translate(-50%, -50%) scale(1);
        }
    }
</style>
</head>
<body>
<div class="card">
    <div class="p-6 flex justify-between items-center header-admin">
        <h1 class="text-2xl font-bold">Gestion des Emplois du Temps - Admin</h1>
        <div class="flex space-x-3">
            <?php if ($role === 'admin' && isset($_GET['professor_id'])): ?>
            <a href="../views/professor.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour à la Sélection
            </a>
            <?php endif; ?>
            <a href="../admin/index.php" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-white/20 backdrop-blur-sm border border-white/30 rounded-md hover:bg-white/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Retour au Tableau de Bord
            </a>
        </div>
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
            <button id="delete-timetable-btn" class="btn px-4 py-2 bg-red-600 text-white font-medium rounded-md hover:bg-red-700">
                Supprimer
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
                <input type="hidden" id="edit-color" />

                <div class="mb-3">
                    <label for="professor-select" class="block text-sm font-medium text-gray-700 mb-0.5">Professeur</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="professor-dropdown">
                            <span id="selected-professor">Sélectionner un professeur</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="professor-menu">
                            <div class="p-1.5 sticky top-0 bg-white z-10">
                                <input type="text" id="professor-search" placeholder="Rechercher un professeur..." class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
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

                <div class="mb-3">
                    <label for="subject-select" class="block text-sm font-medium text-gray-700 mb-0.5">Matière</label>
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

                <div class="mb-3">
                    <label for="room-select" class="block text-sm font-medium text-gray-700 mb-0.5">Salle</label>
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

                <div class="mb-3">
                    <label for="course-type-select" class="block text-sm font-medium text-gray-700 mb-0.5">Type de Cours</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="course-type-dropdown">
                            <span id="selected-course-type">CM</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <div id="subgroup-options" class="mb-3 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-0.5">Options de sous-groupe</label>
                    <div class="flex items-center mb-1">
                        <input type="radio" id="subgroup-single" name="subgroup-option" value="single" checked class="h-3.5 w-3.5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <label for="subgroup-single" class="ml-1.5 block text-sm text-gray-700 cursor-pointer">Classe entière</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="subgroup-split" name="subgroup-option" value="split" class="h-3.5 w-3.5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <label for="subgroup-split" class="ml-1.5 block text-sm text-gray-700 cursor-pointer">Diviser en sous-groupes</label>
                    </div>
                </div>

                <!-- Subgroup split options - initially hidden -->
                <div id="subgroup-split-options" class="mb-3 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-0.5">Options de division</label>
                    <div class="flex items-center mb-1">
                        <input type="radio" id="subgroup-same-time" name="subgroup-split-option" value="same-time" checked class="h-3.5 w-3.5 text-indigo-600 focus:ring-indigo-500 border-gray-300 ">
                        <label for="subgroup-same-time" class="ml-1.5 block text-sm text-gray-700 cursor-pointer">Même créneau horaire</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="subgroup-single-group" name="subgroup-split-option" value="single-group" class="h-3.5 w-3.5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <label for="subgroup-single-group" class="ml-1.5 block text-sm text-gray-700 cursor-pointer">Un seul sous-groupe</label>
                    </div>
                </div>

                <!-- Second professor and room for same-time subgroups - initially hidden -->
                <div id="second-subgroup-options" class="mb-3 hidden">
                    <div class="border-t border-gray-300 my-2 pt-2">
                        <h3 class="text-sm font-medium text-gray-700 mb-1.5">Informations pour le deuxième sous-groupe</h3>
                        
                        <div class="mb-2">
                            <label for="professor-select-2" class="block text-sm font-medium text-gray-700 mb-0.5">Professeur (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="professor-dropdown-2">
                                    <span id="selected-professor-2">Sélectionner un professeur</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div class="dropdown-menu" id="professor-menu-2">
                                    <div class="p-1.5 sticky top-0 bg-white z-10">
                                        <input type="text" id="professor-search-2" placeholder="Rechercher un professeur..." class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
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

                        <div class="mb-2">
                            <label for="subject-select-2" class="block text-sm font-medium text-gray-700 mb-0.5">Matière (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="subject-dropdown-2" disabled>
                                    <span id="selected-subject-2">Sélectionner un professeur d'abord</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                        <div class="mb-2">
                            <label for="room-select-2" class="block text-sm font-medium text-gray-700 mb-0.5">Salle (2ème sous-groupe)</label>
                            <div class="dropdown-container">
                                <button type="button" class="dropdown-button" id="room-dropdown-2">
                                    <span id="selected-room-2">Sélectionner une salle</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                <div id="single-subgroup-selector" class="mb-3 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-0.5">Sélectionner le sous-groupe</label>
                    <div class="dropdown-container">
                        <button type="button" class="dropdown-button" id="subgroup-dropdown">
                            <span id="selected-subgroup">Sous-groupe 1</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="dropdown-menu" id="subgroup-menu">
                            <div class="dropdown-item" data-value="1">Sous-groupe 1</div>
                            <div class="dropdown-item" data-value="2">Sous-groupe 2</div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-btn" class="px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" id="save-class-btn" class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
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
            <h2 class="text-xl font-bold text-red-600">Modifications Non Enregistrées</h2>
            <span class="close" id="unsaved-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <p class="mb-2 leading-[1.4]">Vous avez des modifications non enregistrées dans votre emploi du temps.</p>
                <p class="leading-[0.6]">Enregistrer les modifications avant de continuer ?</p>
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

<!-- Modal pour Supprimer l'Emploi du Temps -->
<div id="delete-timetable-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="text-xl font-bold text-red-600">Supprimer l'Emploi du Temps</h2>
            <span class="close" id="delete-timetable-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <p class="mb-2">Voulez-vous vraiment supprimer l'emploi du temps pour <span id="delete-year-group" class="font-semibold"></span> ?</p>
                <p>Cette action supprimera définitivement toutes les données d'emploi du temps pour cette année et ce groupe.</p>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" id="delete-timetable-cancel" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>
                <button type="button" id="delete-timetable-confirm" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
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
            <h2 class="text-xl font-bold text-red-600">Conflit d'Horaire Professeur</h2>
            <span class="close" id="professor-conflict-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <p class="mb-2 font-medium text-red-600">Ce professeur ne peut pas être assigné à ce créneau horaire car il est déjà occupé :</p>
                <div id="conflict-details" class="mt-3 p-3 bg-transparent border border-red-600 rounded-md">
                    <!-- Conflict details will be inserted here -->
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="button" id="conflict-cancel" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
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
            <h2 class="text-xl font-bold text-red-600">Conflit de Salle</h2>
            <span class="close" id="room-conflict-close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <p class="mb-2 font-medium text-red-600">Cette salle ne peut pas être réservée à ce créneau horaire car elle est déjà occupée :</p>
                <div id="room-conflict-details" class="mt-3 p-3 bg-transparent border border-red-600 rounded-md">
                    <!-- Room conflict details will be inserted here -->
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="button" id="room-conflict-cancel" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
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
        // Setup radio button animations - reset animation on each click
        document.addEventListener('click', function(e) {
            if (e.target.type === 'radio') {
                e.target.style.animation = 'none';
                e.target.offsetHeight; // Force reflow
                e.target.style.animation = '';
            }
        });
        
        // Global variables and utility functions
        let timetableData = {};
        let currentYear = "<?php echo $currentYear; ?>";
        let currentGroup = "<?php echo $currentGroup; ?>";
        let groupsByYear = <?php echo json_encode($groupsByYear); ?>;
        let hasUnsavedChanges = false;
        let isCurrentlyPublished = false;
        let hasDraftChanges = false; 
        let pendingDestination = null;
        let deleteClassDay = null;
        let deleteClassTime = null;
        
        const timeSlots = <?php echo json_encode($timeSlots); ?>;
        const days = <?php echo json_encode($days); ?>;

        // Consolidated modal animation functions
        window.showModalWithAnimation = function(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            void modal.offsetWidth; // Force reflow
            modal.classList.add('show');
        };
        
        window.closeModalWithAnimation = function(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(function() {
                modal.style.display = 'none';
            }, 300);
        };
        
        // Apply animations to all modals
        document.querySelectorAll('.modal').forEach(modal => {
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
        
        // Replace modal button handlers with consolidated approach
        document.getElementById("cancel-btn").addEventListener("click", function() {
            closeModalWithAnimation("class-modal");
        });
        
        // Consolidated dropdown handling function
        function toggleDropdown(dropdownButton, dropdownMenu) {
            if (dropdownButton.hasAttribute("disabled")) return false;
            
            if (dropdownMenu.classList.contains("open")) {
                // Closing the dropdown
                dropdownButton.classList.remove("active");
                dropdownMenu.classList.remove("open");
                dropdownMenu.classList.add("closing");
                
                setTimeout(() => {
                    dropdownMenu.classList.remove("closing");
                    dropdownMenu.style.display = "none";
                }, 300);
                
                return false;
            } else {
                // Opening the dropdown
                closeAllDropdowns(); // Close any other open dropdowns
                dropdownButton.classList.add("active");
                dropdownMenu.style.display = "block";
                
                // Always position dropdown below the button
                dropdownMenu.style.top = 'calc(100% + 4px)';
                dropdownMenu.style.bottom = 'auto';
                
                void dropdownMenu.offsetWidth; // Force reflow
                dropdownMenu.classList.add("open");
                
                // Ensure dropdown is visible within modal
                setTimeout(() => {
                    ensureDropdownVisible(dropdownButton, dropdownMenu);
                }, 50);
                
                return true;
            }
        }
        
        // Function to ensure dropdown is visible within modal by scrolling if necessary
        function ensureDropdownVisible(button, dropdown) {
            const modalContent = document.querySelector('.modal-content');
            if (!modalContent) return;
            
            const modalRect = modalContent.getBoundingClientRect();
            const buttonRect = button.getBoundingClientRect();
            const dropdownHeight = dropdown.offsetHeight;
            
            // Calculate if dropdown would extend beyond modal bottom
            const dropdownBottom = buttonRect.bottom + dropdownHeight - modalRect.top;
            const modalHeight = modalContent.offsetHeight;
            
            if (dropdownBottom > modalHeight) {
                // Dropdown extends beyond modal bottom, scroll to make it visible
                const scrollAmount = dropdownBottom - modalHeight + 20; // Add 20px padding
                modalContent.scrollTop += scrollAmount;
            }
        }
        
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

        // Core data management functions
        function initTimetableData() {
            // Create a fresh object
            timetableData = {};
            
            // Initialize each day and time slot
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
        
        // Status management functions
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
                statusDiv.classList.add("bg-yellow-100", "text-yellow-800");
                statusDiv.textContent = "Vous avez des modifications non enregistrées. N'oubliez pas d'enregistrer avant de quitter !";
                statusDiv.classList.remove("hidden");
            } else if (hasDraftChanges && isCurrentlyPublished) {
                statusDiv.classList.add("bg-blue-100", "text-blue-800");
                statusDiv.textContent = "Vous avez des modifications enregistrées qui ne sont pas encore publiées. Les étudiants et professeurs voient toujours la version précédemment publiée.";
                statusDiv.classList.remove("hidden");
            } else if (isCurrentlyPublished) {
                statusDiv.classList.add("bg-green-100", "text-green-800");
                statusDiv.textContent = "Cet emploi du temps est publié et visible par les étudiants et professeurs.";
                statusDiv.classList.remove("hidden");
            } else if (!isEmptyTimetable) {
                statusDiv.classList.add("bg-yellow-100", "text-yellow-800");
                statusDiv.textContent = "Cet emploi du temps est enregistré mais pas encore publié. Visible uniquement par les admins jusqu'à la publication.";
                statusDiv.classList.remove("hidden");
            } else {
                statusDiv.classList.add("hidden");
            }
        }

        // Show warning for unsaved changes
        function showUnsavedChangesWarning(callback) {
            const modal = document.getElementById("unsaved-changes-modal");
            showModalWithAnimation("unsaved-changes-modal");
            
            const closeBtn = document.getElementById("unsaved-close");
            const discardBtn = document.getElementById("discard-btn");
            const saveBtn = document.getElementById("save-continue-btn");
            
            // Clone and replace buttons to remove old event listeners
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

        // Initialize and display the timetable
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
                        
                        // Determine color based on class_type if available
                        let color;
                        if (data.class_type) {
                            switch(data.class_type) {
                                case "CM": color = "#6b7280"; break;
                                case "TD": color = "#10b981"; break;
                                case "TP": color = "#3b82f6"; break;
                                case "DE": color = "#f59e0b"; break;
                                case "CO": color = "#ef4444"; break;
                                default: color = data.color || "#6b7280"; // Fallback to data.color or default grey
                            }
                        } else {
                            // Use the color from data if available, otherwise use default grey
                            color = data.color || "#6b7280";
                        }
                        
                        classBlock.style.borderLeftColor = color;
                        
                        // Apply visual indicators if class is canceled or rescheduled
                        if (data.is_canceled == 1) {
                            classBlock.style.backgroundColor = "#FEE2E2"; // Light red background
                        } else if (data.is_reschedule == 1) {
                            classBlock.style.backgroundColor = "#DBEAFE"; // Light blue background
                        }

                        const subjectDiv = document.createElement("div");
                        subjectDiv.className = "text-sm font-semibold";
                        subjectDiv.textContent = data.subject;
                        subjectDiv.style.color = color; // Make subject name same color as course type
                        
                        // Add a small indicator for the class type if available
                        if (data.class_type) {
                            const typeSpan = document.createElement("span");
                            typeSpan.className = "ml-2 text-xs font-normal";
                            typeSpan.textContent = `(${data.class_type})`;
                            typeSpan.style.color = color;
                            subjectDiv.appendChild(typeSpan);
                        }

                        const professorDiv = document.createElement("div");
                        professorDiv.className = "text-xs text-black mt-1 font-semibold";
                        professorDiv.textContent = data.professor;

                        const roomDiv = document.createElement("div");
                        roomDiv.className = "text-xs text-black mt-1 font-semibold";
                        roomDiv.textContent = `Salle: ${data.room}`;

                        const actionDiv = document.createElement("div");
                        actionDiv.className = "mt-2 flex justify-end space-x-2";

                        const editBtn = document.createElement("button");
                        editBtn.className = "text-xs text-blue-600 hover:text-blue-800 font-semibold";
                        editBtn.textContent = "Modifier";
                        editBtn.addEventListener("click", function() {
                            openEditModal(day, time);
                        });

                        const deleteBtn = document.createElement("button");
                        deleteBtn.className = "text-xs text-red-600 hover:text-red-800 font-semibold";
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

                        // Handle subgroup display if applicable
                        if ((data.class_type === "TD" || data.class_type === "TP") && data.is_split) {
                            // Add a split class indicator
                            classBlock.style.borderTop = "2px dashed " + color;
                            
                            if (data.split_type === "same_time") {
                                // Display both subgroups in same time slot
                                const subgroupDiv = document.createElement("div");
                                subgroupDiv.className = "text-xs text-black mt-1 font-semibold";
                                subgroupDiv.textContent = `${data.subgroup1}/${data.subgroup2}`;
                                
                                // Update subject display to show both subjects if they're different
                                if (data.subject2 && data.subject !== data.subject2) {
                                    subjectDiv.textContent = `${data.subject}/${data.subject2}`;
                                    
                                    // Add a tooltip to show full subject names
                                    subjectDiv.title = `${data.subject} / ${data.subject2}`;
                                }
                                
                                // Update professor display to show both professors
                                professorDiv.textContent = `${data.professor}/${data.professor2}`;
                                professorDiv.title = `${data.professor} / ${data.professor2}`;
                                
                                // Update room display to show both rooms
                                roomDiv.textContent = `Salle: ${data.room}/${data.room2}`;
                                roomDiv.title = `${data.room} / ${data.room2}`;
                                
                                // Add subgroup div after subject
                                classBlock.appendChild(subjectDiv);
                                classBlock.appendChild(subgroupDiv);
                                classBlock.appendChild(professorDiv);
                                classBlock.appendChild(roomDiv);
                            } else if (data.split_type === "single_group") {
                                // Display single subgroup
                                const subgroupDiv = document.createElement("div");
                                subgroupDiv.className = "text-xs text-black mt-1 font-semibold";
                                
                                // Make sure we're using the correct subgroup property and provide a fallback
                                if (data.subgroup) {
                                    subgroupDiv.textContent = data.subgroup;
                                } else {
                                    // Recreate subgroup name from group if missing
                                    const groupNumber = currentGroup.replace("G", "");
                                    const subgroupNumber = (parseInt(groupNumber) * 2) - 1; // Default to first subgroup
                                    subgroupDiv.textContent = data.class_type + subgroupNumber;
                                    
                                    console.log("Recreated missing subgroup name:", subgroupDiv.textContent);
                                }
                                
                                // Add subgroup div after subject
                                classBlock.appendChild(subjectDiv);
                                classBlock.appendChild(subgroupDiv);
                                classBlock.appendChild(professorDiv);
                                classBlock.appendChild(roomDiv);
                            }
                        } else {
                            // Standard display without subgroups
                            classBlock.appendChild(subjectDiv);
                            classBlock.appendChild(professorDiv);
                            classBlock.appendChild(roomDiv);
                        }
                        
                        // Add status indicators if class is canceled or rescheduled
                        if (data.is_canceled == 1 || data.is_reschedule == 1) {
                            const statusDiv = document.createElement("div");
                            if (data.is_canceled == 1) {
                                statusDiv.className = "text-xs font-medium text-red-700 mt-1";
                                statusDiv.textContent = "ANNULÉ PAR LE PROFESSEUR";
                            } else if (data.is_reschedule == 1) {
                                statusDiv.className = "text-xs font-medium text-blue-700 mt-1";
                                statusDiv.textContent = "DEMANDE DE REPORT";
                            }
                            classBlock.appendChild(statusDiv);
                        }
                        
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

        // Set up initial state
        initTimetableData();
        generateEmptyTimetable();

        // Toast notification handling
        function createToastElement() {
            if (document.getElementById("toast-notification")) return;

            const toast = document.createElement("div");
            toast.id = "toast-notification";
            toast.className = "toast";
            document.body.appendChild(toast);
        }
        createToastElement();

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
        
        // Setup event listeners for all dropdowns
        const dropdowns = [
            {button: "year-dropdown", menu: "year-menu"},
            {button: "group-dropdown", menu: "group-menu"},
            {button: "professor-dropdown", menu: "professor-menu"},
            {button: "subject-dropdown", menu: "subject-menu"},
            {button: "room-dropdown", menu: "room-menu"},
            {button: "course-type-dropdown", menu: "course-type-menu"},
            {button: "professor-dropdown-2", menu: "professor-menu-2"},
            {button: "subject-dropdown-2", menu: "subject-menu-2"},
            {button: "room-dropdown-2", menu: "room-menu-2"},
            {button: "subgroup-dropdown", menu: "subgroup-menu"}
        ];
        
        dropdowns.forEach(dropdown => {
            const button = document.getElementById(dropdown.button);
            const menu = document.getElementById(dropdown.menu);
            
            if (button && menu) {
                button.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
                    toggleDropdown(this, menu);
                });
            }
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener("click", function(event) {
            closeAllDropdowns();
        });
        
        // Handle professor search
        const professorSearch = document.getElementById("professor-search");
        if (professorSearch) {
            professorSearch.addEventListener("input", function(e) {
                e.stopPropagation();
                const searchTerm = this.value.toLowerCase().trim();
                const professorItems = document.querySelectorAll("#professor-list .dropdown-item");
                
                professorItems.forEach(item => {
                    const professorName = item.getAttribute("data-value").toLowerCase();
                    if (searchTerm === "" || professorName.includes(searchTerm)) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
            });
            
            // Prevent dropdown from closing when clicking in the search input
            professorSearch.addEventListener("click", function(e) {
                e.stopPropagation();
            });
        }

        // Setup handlers for dropdown items
        function setupDropdownItemHandlers() {
            // Year selection
            document.querySelectorAll("#year-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const year = this.getAttribute("data-value");
                    
                    // Skip if selecting the same year
                    if (year === currentYear) {
                        document.getElementById("year-menu").classList.remove("open");
                        document.getElementById("year-dropdown").classList.remove("active");
                        return;
                    }
                    
                    // Store the destination
                    pendingDestination = { type: 'year', year: year };
                    
                    // If there are unsaved changes, show warning
                    if (hasUnsavedChanges) {
                        showUnsavedChangesWarning(function(saved) {
                            // After user's decision, switch to the year
                            document.getElementById("selected-year").textContent = year;
                            currentYear = year;
                            document.getElementById("year-menu").classList.remove("open");
                            document.getElementById("year-dropdown").classList.remove("active");
                            
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
                        document.getElementById("year-menu").classList.remove("open");
                        document.getElementById("year-dropdown").classList.remove("active");
                        
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
            
            // Setup professor dropdown items
            document.querySelectorAll("#professor-list .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const professorName = this.getAttribute("data-value");
                    const professorId = this.getAttribute("data-id");
                    
                    document.getElementById("selected-professor").textContent = professorName;
                    document.getElementById("selected-professor").setAttribute("data-id", professorId);
                    document.getElementById("professor-menu").classList.remove("open");
                    document.getElementById("professor-dropdown").classList.remove("active");
                    
                    // Enable subject dropdown now that a professor is selected
                    document.getElementById("subject-dropdown").removeAttribute("disabled");
                    document.getElementById("subject-dropdown").style.backgroundColor = "#ffffff";
                    document.getElementById("subject-dropdown").style.cursor = "pointer";
                    document.getElementById("selected-subject").textContent = "Chargement des matières...";
                    
                    // Filter subjects based on selected professor
                    filterSubjectsByProfessor(professorId);
                });
            });
            
            // Setup subject dropdown items
            document.querySelectorAll("#subject-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const subject = this.getAttribute("data-value");
                    const subjectId = this.getAttribute("data-id") || null;
                    const color = this.getAttribute("data-color");
                    
                    document.getElementById("selected-subject").textContent = subject;
                    document.getElementById("selected-subject").setAttribute("data-id", subjectId);
                    document.getElementById("edit-color").value = color;
                    document.getElementById("subject-menu").classList.remove("open");
                    document.getElementById("subject-dropdown").classList.remove("active");
                });
            });
            
            // Setup room dropdown items
            document.querySelectorAll("#room-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    // Get the current course type and its corresponding color
                    const courseType = document.getElementById("selected-course-type").textContent;
                    let color;
                    
                    // Map course types to their colors
                    switch(courseType) {
                        case "CM": color = "#6b7280"; break;
                        case "TD": color = "#10b981"; break;
                        case "TP": color = "#3b82f6"; break;
                        case "DE": color = "#f59e0b"; break;
                        case "CO": color = "#ef4444"; break;
                        default: color = "#6b7280"; // Default to CM color
                    }
                    
                    document.getElementById("selected-room").textContent = this.getAttribute("data-value");
                    document.getElementById("room-menu").classList.remove("open");
                    document.getElementById("room-dropdown").classList.remove("active");
                    
                    // Set the color based on the current course type
                    document.getElementById("edit-color").value = color;
                });
            });
            
            // Setup course type dropdown items
            document.querySelectorAll("#course-type-menu .dropdown-item").forEach(item => {
                item.addEventListener("click", function() {
                    const courseType = this.getAttribute("data-value");
                    const color = this.getAttribute("data-color");
                    
                    document.getElementById("selected-course-type").textContent = courseType;
                    document.getElementById("edit-color").value = color;
                    
                    document.getElementById("course-type-menu").classList.remove("open");
                    document.getElementById("course-type-dropdown").classList.remove("active");
                    
                    // Show/hide subgroup options based on course type
                    const subgroupOptions = document.getElementById("subgroup-options");
                    if (courseType === "TD" || courseType === "TP") {
                        subgroupOptions.classList.remove("hidden");
                    } else {
                        subgroupOptions.classList.add("hidden");
                        document.getElementById("subgroup-split-options").classList.add("hidden");
                        document.getElementById("second-subgroup-options").classList.add("hidden");
                        document.getElementById("single-subgroup-selector").classList.add("hidden");
                    }
                });
            });
        }
        
        setupDropdownItemHandlers();
        
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
                            document.getElementById("group-dropdown").classList.remove("active");
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
                                document.getElementById("group-dropdown").classList.remove("active");
                                
                                // Load timetable for this year/group
                                loadSavedData();
                                showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                            });
                        } else {
                            // No changes, just update and load
                            document.getElementById("selected-group").textContent = selectedGroup;
                            currentGroup = selectedGroup;
                            groupMenu.classList.remove("open");
                            document.getElementById("group-dropdown").classList.remove("active");
                            
                            // Load timetable for this year/group
                            loadSavedData();
                            showToast("info", `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`);
                        }
                    });
                    groupMenu.appendChild(item);
                });
            }
        }

        // Initialize group dropdown with current year's groups on page load
        updateGroupDropdown(currentYear);
        
        // Setup handlers for all action buttons
        document.getElementById("save-btn").addEventListener("click", function() {
            saveCurrentTimetable();
        });

        document.getElementById("publish-btn").addEventListener("click", function() {
            publishCurrentTimetable();
        });
        
        document.getElementById("delete-timetable-btn").addEventListener("click", function() {
            // Show confirmation modal with current year and group
            document.getElementById("delete-year-group").textContent = currentYear + " - " + currentGroup;
            showModalWithAnimation("delete-timetable-modal");
        });

        // Delete timetable modal handlers
        document.getElementById("delete-timetable-close").addEventListener("click", function() {
            closeModalWithAnimation("delete-timetable-modal");
        });
        
        document.getElementById("delete-timetable-cancel").addEventListener("click", function() {
            closeModalWithAnimation("delete-timetable-modal");
        });
        
        document.getElementById("delete-timetable-confirm").addEventListener("click", function() {
            // Create payload for delete operation
            const payload = {
                year: currentYear,
                group: currentGroup
            };

            // Send delete request to server
            fetch('../api/delete_timetable.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                closeModalWithAnimation("delete-timetable-modal");
                if (data.success) {
                    // Reset timetable
                    initTimetableData();
                    generateEmptyTimetable();
                    hasUnsavedChanges = false;
                    isCurrentlyPublished = false;
                    hasDraftChanges = false;
                    updatePublishStatus();
                    showToast("success", `Emploi du temps supprimé pour ${currentYear}-${currentGroup}`);
                } else {
                    showToast("error", data.message || "Erreur lors de la suppression de l'emploi du temps");
                }
            })
            .catch(error => {
                closeModalWithAnimation("delete-timetable-modal");
                console.error('Error deleting timetable:', error);
                showToast("error", "Erreur lors de la suppression de l'emploi du temps");
            });
        });
        
        // ...Remove the publish-all-btn event listener and function...

        // Function to save current timetable
        function saveCurrentTimetable(callback) {
            // Create a deep copy of the timetable data to avoid reference issues
            const timetableDataCopy = JSON.parse(JSON.stringify(timetableData || {}));
            
            // Create a payload for server - excluding any publish flags
            const payload = {
                year: currentYear,
                group: currentGroup,
                data: timetableDataCopy,
                action: "save_only" // Explicit action
            };
            
            console.log("Saving timetable with payload:", payload);
            
            // Construct the correct API URL
            const baseUrl = window.location.origin;
            const projectPath = '/PI-php'; // Update this to match your actual project folder name
            const apiUrl = `${baseUrl}${projectPath}/src/api/save_timetable.php`;
            
            console.log("Using API URL:", apiUrl);

            // Send data to PHP backend
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Server response:", data);
                if (data.success) {
                    // Update URL with current year and group to prevent redirection issues
                    updateUrlWithYearAndGroup(currentYear, currentGroup);
                    
                    showToast("success", `Emploi du temps enregistré pour ${currentYear}-${currentGroup}`);
                    hasUnsavedChanges = false;
                    
                    // Check if the server tells us this is already published elsewhere
                    if (data.is_published) {
                        isCurrentlyPublished = true;
                        hasDraftChanges = true;
                    } else {
                        isCurrentlyPublished = false;
                        hasDraftChanges = false;
                    }
                    
                    updatePublishStatus();
                    if (callback) callback();
                } else {
                    console.error("Save failed:", data);
                    showToast("error", data.message || "Échec de l'enregistrement de l'emploi du temps");
                    if (callback) callback();
                }
            })
            .catch(error => {
                console.error('Error saving timetable:', error);
                showToast("error", "Erreur lors de l'enregistrement de l'emploi du temps");
                if (callback) callback();
            });
        }
        
        // Function to publish current timetable
        function publishCurrentTimetable() {
            // Create a payload specifically for publishing
            const timetableDataCopy = JSON.parse(JSON.stringify(timetableData || {}));
            
            const payload = {
                year: currentYear,
                group: currentGroup,
                data: timetableDataCopy,
                action: "publish" // Explicit action
            };
            
            console.log("Publishing timetable with payload:", payload);
            
            // Construct the correct API URL
            const baseUrl = window.location.origin;
            const projectPath = '/PI-php'; // Update this to match your actual project folder name
            const apiUrl = `${baseUrl}${projectPath}/src/api/publish_timetable.php`;
            
            console.log("Using API URL:", apiUrl);

            // Send data to PHP backend
            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Server response:", data);
                if (data.success) {
                    // Update URL with current year and group
                    updateUrlWithYearAndGroup(currentYear, currentGroup);
                    
                    showToast("success", `Emploi du temps publié pour ${currentYear}-${currentGroup}`);
                    hasUnsavedChanges = false;
                    isCurrentlyPublished = true;
                    hasDraftChanges = false; // Reset draft changes flag since we just published
                    updatePublishStatus();
                } else {
                    console.error("Publish failed:", data);
                    showToast("error", data.message || "Échec de la publication de l'emploi du temps");
                }
            })
            .catch(error => {
                console.error('Error publishing timetable:', error);
                showToast("error", "Erreur lors de la publication de l'emploi du temps");
            });
        }

        // Function to publish all timetables
        function performPublishAllTimetables() {
            // Send request to publish all timetables
            fetch('../api/publish_all_timetables.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
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

        // Modal handling for class add/edit
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
            document.getElementById("selected-course-type").textContent = "CM";
            document.getElementById("edit-color").value = "#6b7280"; // Default grey color for CM
            
            // Reset subgroup options
            document.getElementById("subgroup-options").classList.add("hidden");
            document.getElementById("subgroup-split-options").classList.add("hidden");
            document.getElementById("second-subgroup-options").classList.add("hidden");
            document.getElementById("single-subgroup-selector").classList.add("hidden");
            document.getElementById("subgroup-single").checked = true;
            
            // Reset second professor dropdown
            document.getElementById("selected-professor-2").textContent = "Sélectionner un professeur";
            document.getElementById("selected-professor-2").removeAttribute("data-id");
            
            // Reset second subject dropdown
            document.getElementById("selected-subject-2").textContent = "Sélectionner un professeur d'abord";
            document.getElementById("subject-dropdown-2").setAttribute("disabled", "disabled");
            document.getElementById("subject-dropdown-2").style.backgroundColor = "#f1f5f9";
            document.getElementById("subject-dropdown-2").style.cursor = "not-allowed";
            document.getElementById("selected-subject-2").removeAttribute("data-id");
            
            // Reset second room dropdown
            document.getElementById("selected-room-2").textContent = "Sélectionner une salle";
            
            // Reset subgroup selector
            document.getElementById("selected-subgroup").textContent = "Sous-groupe 1";
            
            // Disable subject dropdown until professor is selected
            const subjectDropdown = document.getElementById("subject-dropdown");
            subjectDropdown.setAttribute("disabled", "disabled");
            subjectDropdown.style.backgroundColor = "#f1f5f9";
            subjectDropdown.style.cursor = "not-allowed";

            showModalWithAnimation("class-modal");
        }

        function openEditModal(day, time) {
            const data = timetableData[day][time];
            if (!data) return;

            document.getElementById("modal-title").textContent = "Modifier un Cours";
            document.getElementById("edit-day").value = day;
            document.getElementById("edit-time").value = time;
            document.getElementById("edit-id").value = data.id || "";
            document.getElementById("edit-color").value = data.color;

            // Fill form with existing data
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
            
            // Set course type if available
            if (data.class_type) {
                document.getElementById("selected-course-type").textContent = data.class_type;
                
                // Handle subgroup options for TD and TP
                if ((data.class_type === "TD" || data.class_type === "TP") && data.is_split) {
                    // Show subgroup options
                    document.getElementById("subgroup-options").classList.remove("hidden");
                    document.getElementById("subgroup-split").checked = true;
                    
                    // Check split type and show appropriate options
                    if (data.split_type === "same_time") {
                        document.getElementById("subgroup-split-options").classList.remove("hidden");
                        document.getElementById("subgroup-same-time").checked = true;
                        document.getElementById("second-subgroup-options").classList.remove("hidden");
                        document.getElementById("single-subgroup-selector").classList.add("hidden");
                        
                        // Set second professor and room
                        if (data.professor2) {
                            document.getElementById("selected-professor-2").textContent = data.professor2;
                            if (data.professor2_id) {
                                document.getElementById("selected-professor-2").setAttribute("data-id", data.professor2_id);
                            }
                        }
                        
                        // Set second subject if available
                        if (data.subject2) {
                            document.getElementById("subject-dropdown-2").removeAttribute("disabled");
                            document.getElementById("subject-dropdown-2").style.backgroundColor = "#ffffff";
                            document.getElementById("subject-dropdown-2").style.cursor = "pointer";
                            document.getElementById("selected-subject-2").textContent = data.subject2;
                            if (data.subject2_id) {
                                document.getElementById("selected-subject-2").setAttribute("data-id", data.subject2_id);
                            }
                        }
                        
                        if (data.room2) {
                            document.getElementById("selected-room-2").textContent = data.room2;
                        }
                        
                    } else if (data.split_type === "single_group") {
                        document.getElementById("subgroup-split-options").classList.remove("hidden");
                        document.getElementById("subgroup-single-group").checked = true;
                        document.getElementById("second-subgroup-options").classList.add("hidden");
                        document.getElementById("single-subgroup-selector").classList.remove("hidden");
                        
                        // Set selected subgroup
                        if (data.subgroup) {
                            const subgroupNum = data.subgroup.slice(-1);
                            document.getElementById("selected-subgroup").textContent = "Sous-groupe " + subgroupNum;
                        }
                    }
                } else {
                    // Hide subgroup options for non-TD/TP or non-split classes
                    document.getElementById("subgroup-options").classList.add("hidden");
                    document.getElementById("subgroup-split-options").classList.add("hidden");
                    document.getElementById("second-subgroup-options").classList.add("hidden");
                    document.getElementById("single-subgroup-selector").classList.add("hidden");
                    document.getElementById("subgroup-single").checked = true;
                }
            }

            showModalWithAnimation("class-modal");
        }

        // Load saved timetable data
        function loadSavedData() {
            // First initialize with empty timetable
            initTimetableData();
            
            // Construct the correct API URL using window.location
            const baseUrl = window.location.origin;
            const projectPath = '/PI-php'; // Update this to match your actual project folder name
            const apiUrl = `${baseUrl}${projectPath}/src/api/get_timetable.php`;
            
            // Add query parameters
            const params = new URLSearchParams({
                year: currentYear,
                group: currentGroup,
                admin: 'true'
            });
            
            console.log("Loading timetable data from:", `${apiUrl}?${params.toString()}`);
            
            // Try to load from server
            fetch(`${apiUrl}?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Server response for timetable data:", data);
                
                if (data && data.success !== false && data.data) {
                    // We found saved data, load it
                    console.log("Received timetable data:", data.data);
                    
                    // Make sure we have a properly structured timetableData object
                    timetableData = {};
                    
                    // Initialize the structure for all days and time slots
                    days.forEach(day => {
                        timetableData[day] = {};
                        timeSlots.forEach(time => {
                            timetableData[day][time] = null;
                        });
                    });
                    
                    // Now populate with the received data
                    for (const day in data.data) {
                        if (!timetableData[day]) {
                            timetableData[day] = {};
                        }
                        
                        for (const time in data.data[day]) {
                            timetableData[day][time] = data.data[day][time];
                            
                            // Debug logging for split classes
                            const entry = data.data[day][time];
                            if (entry && entry.is_split) {
                                console.log(`Split class found at ${day} ${time}:`, {
                                    split_type: entry.split_type,
                                    subgroup: entry.subgroup,
                                    subgroup1: entry.subgroup1,
                                    subgroup2: entry.subgroup2,
                                    subject2: entry.subject2,
                                    professor2: entry.professor2
                                });
                            }
                        }
                    }
                    
                    console.log("Processed timetable data:", timetableData);
                    
                    generateEmptyTimetable(); // This will actually display the loaded data
                    showToast("success", `Emploi du temps chargé pour ${currentYear}-${currentGroup}`);
                    
                    // Update URL with current year and group
                    updateUrlWithYearAndGroup(currentYear, currentGroup);
                    
                    // Set published flag based on the server response
                    isCurrentlyPublished = data.is_published || false;
                    hasDraftChanges = data.has_draft_changes || false;
                    hasUnsavedChanges = false;
                    updatePublishStatus();
                } else {
                    // No saved data found, keep the empty timetable
                    console.log("No timetable data found or invalid response");
                    generateEmptyTimetable();
                    showToast("info", `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`);
                    
                    // Update URL with current year and group
                    updateUrlWithYearAndGroup(currentYear, currentGroup);
                    
                    isCurrentlyPublished = false;
                    hasDraftChanges = false;
                    hasUnsavedChanges = false;
                    updatePublishStatus();
                }
            })
            .catch(error => {
                console.error('Error loading timetable data:', error);
                showToast("error", "Erreur lors du chargement des données. Vérifiez votre connexion et le chemin du projet.");
                generateEmptyTimetable();
                isCurrentlyPublished = false;
                hasDraftChanges = false;
                hasUnsavedChanges = false;
                updatePublishStatus();
            });
        }

        // Function to update URL with current year and group without reloading the page
        function updateUrlWithYearAndGroup(year, group) {
            const url = new URL(window.location.href);
            url.searchParams.set('year', year);
            url.searchParams.set('group', group);
            window.history.replaceState({}, '', url);
        }

        // Filter subjects based on professor ID
        function filterSubjectsByProfessor(professorId) {
            // Fetch subjects assigned to this professor from the database
            if (!professorId) {
                document.getElementById("selected-subject").textContent = "Sélectionner un professeur d'abord";
                document.getElementById("subject-dropdown").setAttribute("disabled", "disabled");
                return;
            }
            
            // Clear existing subject menu items
            const subjectMenu = document.getElementById("subject-menu");
            subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Chargement...</div>';
            
            // Construct the correct API URL
            const baseUrl = window.location.origin;
            const projectPath = '/PI-php'; // Update this to match your actual project folder name
            const apiUrl = `${baseUrl}${projectPath}/src/api/get_professor_subjects.php?professor_id=${professorId}`;
            
            console.log("Fetching professor subjects from:", apiUrl);
            
            fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Professor subjects response:", data);
                if (data.success && data.subjects && data.subjects.length > 0) {
                    // Clear current menu
                    subjectMenu.innerHTML = '';
                    
                    // Add each subject to the dropdown
                    data.subjects.forEach(subject => {
                        const item = document.createElement("div");
                        item.className = "dropdown-item";
                        item.setAttribute("data-value", subject.name);
                        item.setAttribute("data-id", subject.id);
                        item.setAttribute("data-color", subject.color);
                        
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
                            document.getElementById("subject-dropdown").classList.remove("active");
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
        
        // Display professor conflict in modal
        function showProfessorConflict(conflicts, classData) {
            // Generate conflict details HTML
            const conflictDetailsElement = document.getElementById('conflict-details');
            let conflictHtml = '';
            
            conflicts.forEach(conflict => {
                // Determine which professor has the conflict
                const isProfessor2Conflict = conflict.is_professor2_conflict === true;
                const professorTitle = isProfessor2Conflict ? 
                    "Conflit avec le deuxième professeur:" : 
                    "Conflit avec le professeur:";
                
                conflictHtml += `
                    <div class="mb-4 p-2 border-b border-gray-200 pb-4">
                        <p class="font-medium text-red-600">${professorTitle}</p>
                        <p><span class="font-semibold">Professeur:</span> ${conflict.professor || classData.professor}</p>
                        <p><span class="font-semibold">Jour:</span> ${conflict.day}</p>
                        <p><span class="font-semibold">Heure:</span> ${conflict.time}</p>
                        <p><span class="font-semibold">Année:</span> ${conflict.year}</p>
                        <p><span class="font-semibold">Groupe:</span> ${conflict.group}</p>
                        <p><span class="font-semibold">Matière:</span> ${conflict.subject}</p>
                        <p><span class="font-semibold">Salle:</span> ${conflict.room}</p>`;
                
                // Add subgroup information if available
                if (conflict.is_split) {
                    if (conflict.split_type === 'same_time') {
                        conflictHtml += `
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <p class="font-medium text-blue-600">Cours avec sous-groupes :</p>
                            <p><span class="font-semibold">Sous-groupe 1:</span> ${conflict.subgroup1 || ''}</p>
                            <p><span class="font-semibold">Sous-groupe 2:</span> ${conflict.subgroup2 || ''}</p>
                            <p><span class="font-semibold">Professeur 2:</span> ${conflict.professor2 || ''}</p>
                            <p><span class="font-semibold">Salle 2:</span> ${conflict.room2 || ''}</p>
                        </div>`;
                    } else if (conflict.split_type === 'single_group') {
                        conflictHtml += `
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <p class="font-medium text-blue-600">Cours avec sous-groupe unique :</p>
                            <p><span class="font-semibold">Sous-groupe:</span> ${conflict.subgroup || ''}</p>
                        </div>`;
                    }
                }
                
                conflictHtml += `</div>`;
            });
            
            conflictDetailsElement.innerHTML = conflictHtml;
            showModalWithAnimation("professor-conflict-modal");
        }
        
        // Display room conflict in modal
        function showRoomConflict(conflicts, classData) {
            // Generate conflict details HTML
            const conflictDetailsElement = document.getElementById('room-conflict-details');
            let conflictHtml = '';
            
            conflicts.forEach(conflict => {
                conflictHtml += `
                    <div class="mb-4 p-2 border-b border-gray-200 pb-4">
                        <p><span class="font-semibold">Salle:</span> ${conflict.room || classData.room}</p>
                        <p><span class="font-semibold">Jour:</span> ${conflict.day}</p>
                        <p><span class="font-semibold">Heure:</span> ${conflict.time}</p>
                        <p><span class="font-semibold">Année:</span> ${conflict.year}</p>
                        <p><span class="font-semibold">Groupe:</span> ${conflict.group}</p>
                        <p><span class="font-semibold">Matière:</span> ${conflict.subject}</p>
                        <p><span class="font-semibold">Professeur:</span> ${conflict.professor}</p>`;
                
                // Add subgroup information if available
                if (conflict.is_split) {
                    if (conflict.split_type === 'same_time') {
                        conflictHtml += `
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <p class="font-medium text-blue-600">Cours avec sous-groupes :</p>
                            <p><span class="font-semibold">Sous-groupe 1:</span> ${conflict.subgroup1 || ''}</p>
                            <p><span class="font-semibold">Sous-groupe 2:</span> ${conflict.subgroup2 || ''}</p>
                            <p><span class="font-semibold">Matière 2:</span> ${conflict.subject2 || ''}</p>
                            <p><span class="font-semibold">Professeur 2:</span> ${conflict.professor2 || ''}</p>
                            <p><span class="font-semibold">Salle 2:</span> ${conflict.room2 || ''}</p>
                        </div>`;
                    } else if (conflict.split_type === 'single_group') {
                        conflictHtml += `
                        <div class="mt-2 pt-2 border-t border-gray-200">
                            <p class="font-medium text-blue-600">Cours avec sous-groupe unique :</p>
                            <p><span class="font-semibold">Sous-groupe:</span> ${conflict.subgroup || ''}</p>
                        </div>`;
                    }
                }
                
                conflictHtml += `</div>`;
            });
            
            conflictDetailsElement.innerHTML = conflictHtml;
            showModalWithAnimation("room-conflict-modal");
        }
        
        // Function to save class data after validations
        function saveClassData(classData, day, time) {
            console.log("Saving class data:", { classData, day, time });
            
            // Ensure the day object exists
            if (!timetableData[day]) {
                timetableData[day] = {};
            }
            
            // Now we can safely set the time slot
            timetableData[day][time] = classData;
            
            hasUnsavedChanges = true;
            updatePublishStatus();
            generateEmptyTimetable();
            closeModalWithAnimation("class-modal");
            showToast("success", "Cours enregistré ! N'oubliez pas d'utiliser le bouton Enregistrer pour sauvegarder les modifications");
        }
        
        // Form submission handler
        document.getElementById("class-form").addEventListener("submit", function(e) {
            e.preventDefault();

            const day = document.getElementById("edit-day").value;
            const time = document.getElementById("edit-time").value;
            const id = document.getElementById("edit-id").value || new Date().getTime().toString();
            const color = document.getElementById("edit-color").value;

            const professorElement = document.getElementById("selected-professor");
            const subjectElement = document.getElementById("selected-subject");
            const roomElement = document.getElementById("selected-room");
            
            const professor = professorElement.textContent;
            const professorId = professorElement.getAttribute("data-id");
            const subject = subjectElement.textContent;
            const subjectId = subjectElement.getAttribute("data-id");
            const room = roomElement.textContent;
            const roomId = room; // For now, use room name as ID
            
            console.log("Form submission values:", {
                day, time, id, color, professor, professorId, subject, subjectId, room, roomId
            });

            // Validate inputs
            if (professor === "Sélectionner un professeur") {
                showToast("error", "Veuillez sélectionner un professeur");
                return;
            }
            
            if (subject === "Sélectionner une matière" || 
                subject === "Aucune matière disponible" ||
                subject === "Erreur lors du chargement des matières" ||
                subject === "Chargement des matières..." ||
                subject === "Sélectionner un professeur d'abord") {
                showToast("error", "Veuillez sélectionner une matière");
                return;
            }
            
            if (room === "Sélectionner une salle") {
                showToast("error", "Veuillez sélectionner une salle");
                return;
            }
            
            // Get the current course type
            const courseTypeElement = document.getElementById("selected-course-type");
            const courseType = courseTypeElement.textContent;
            
            // Create class data object
            const classData = {
                id: id,
                subject: subject,
                subject_id: subjectId,
                professor: professor,
                professor_id: professorId,
                room: room,
                room_id: roomId, // Add room_id to data
                color: color,
                class_type: courseType, // Add class_type to data
                year: currentYear,
                group: currentGroup
            };
            
            // Subgroup information for availability checks
            let is_split = false;
            let split_type = null;
            let subgroup = null;
            let room2 = null;
            let professor2_id = null;
            
            // Handle subgroup information if applicable
            if (courseType === "TD" || courseType === "TP") {
                // Check if split option is selected
                if (document.getElementById("subgroup-split").checked) {
                    classData.is_split = 1;
                    is_split = true;
                    
                    // Check which split option is selected
                    if (document.getElementById("subgroup-same-time").checked) {
                        classData.split_type = "same_time";
                        split_type = "same_time";
                        
                        // Get second professor and room information
                        const professor2Element = document.getElementById("selected-professor-2");
                        const subject2Element = document.getElementById("selected-subject-2");
                        const room2Element = document.getElementById("selected-room-2");
                        
                        const professor2 = professor2Element.textContent;
                        const professor2Id = professor2Element.getAttribute("data-id");
                        const subject2 = subject2Element.textContent;
                        const subject2Id = subject2Element.getAttribute("data-id");
                        const room2Value = room2Element.textContent;
                        
                        // Validate second professor, subject and room
                        if (professor2 === "Sélectionner un professeur") {
                            showToast("error", "Veuillez sélectionner un professeur pour le deuxième sous-groupe");
                            return;
                        }
                        
                        if (subject2 === "Sélectionner une matière" || 
                            subject2 === "Aucune matière disponible" ||
                            subject2 === "Erreur lors du chargement des matières" ||
                            subject2 === "Chargement des matières..." ||
                            subject2 === "Sélectionner un professeur d'abord") {
                            showToast("error", "Veuillez sélectionner une matière pour le deuxième sous-groupe");
                            return;
                        }
                        
                        if (room2Value === "Sélectionner une salle") {
                            showToast("error", "Veuillez sélectionner une salle pour le deuxième sous-groupe");
                            return;
                        }
                        
                        // Add second professor, subject and room to class data
                        classData.professor2 = professor2;
                        classData.professor2_id = professor2Id;
                        classData.subject2 = subject2;
                        classData.subject2_id = subject2Id;
                        classData.room2 = room2Value;
                        
                        // Save for conflict checks
                        room2 = room2Value;
                        professor2_id = professor2Id;
                        
                        // Check if same professor is selected for both subgroups
                        if (professor2Id === professorId) {
                            showToast("error", "Le même professeur ne peut pas enseigner à deux sous-groupes en même temps");
                            return;
                        }
                        
                        // Check if same room is selected for both subgroups
                        if (room2Value === room) {
                            showToast("error", "La même salle ne peut pas être utilisée par deux sous-groupes en même temps");
                            return;
                        }
                        
                        // Generate subgroup names based on group
                        // Format: TD1/TD2 for G1, TD3/TD4 for G2, etc.
                        const groupNumber = currentGroup.replace("G", "");
                        const subgroupNumber1 = (parseInt(groupNumber) * 2) - 1;
                        const subgroupNumber2 = parseInt(groupNumber) * 2;
                        
                        classData.subgroup1 = courseType + subgroupNumber1;
                        classData.subgroup2 = courseType + subgroupNumber2;
                        
                        // Debug logging
                        console.log("Split subgroups same time:", {
                            subgroup1: classData.subgroup1,
                            subgroup2: classData.subgroup2,
                            professor2: professor2,
                            room2: room2Value
                        });
                    } else if (document.getElementById("subgroup-single-group").checked) {
                        classData.split_type = "single_group";
                        split_type = "single_group";
                        
                        // Get selected subgroup
                        const subgroupElement = document.getElementById("selected-subgroup");
                        const subgroupNum = subgroupElement.textContent.includes("1") ? 1 : 2;
                        
                        // Generate subgroup name based on group
                        const groupNumber = currentGroup.replace("G", "");
                        const subgroupNumber = subgroupNum === 1 ? 
                                              (parseInt(groupNumber) * 2) - 1 : 
                                              parseInt(groupNumber) * 2;
                        
                        classData.subgroup = courseType + subgroupNumber;
                        subgroup = classData.subgroup;
                        
                        // Debug logging
                        console.log("Single subgroup selected:", {
                            subgroupNum,
                            groupNumber,
                            subgroupNumber,
                            subgroup: classData.subgroup
                        });
                    }
                } else {
                    classData.is_split = 0; // Explicitly set to integer 0 instead of boolean false
                }
            } else {
                // For non-TD/TP courses, always set is_split to 0
                classData.is_split = 0;
            }
            
            console.log("Class data prepared:", classData);
            
            // Make sure timetableData is properly initialized
            if (!timetableData) {
                console.log("timetableData was null, initializing");
                initTimetableData();
            }
            
            // Ensure the day object exists
            if (!timetableData[day]) {
                console.log(`Day ${day} not found in timetableData, creating it`);
                timetableData[day] = {};
            }
            
            // Prepare data for professor availability check
            const professorCheckData = {
                professor_id: professorId,
                day: day,
                time_slot: time,
                year: currentYear,
                group: currentGroup,
                is_split: is_split,
                split_type: split_type,
                subgroup: subgroup,
                professor2_id: professor2_id
            };
            
            // Prepare data for room availability check
            const roomCheckData = {
                room: room,
                day: day,
                time_slot: time,
                year: currentYear,
                group: currentGroup,
                is_split: is_split,
                split_type: split_type,
                subgroup: subgroup,
                room2: room2
            };
            
            console.log("Checking professor availability with:", professorCheckData);
            console.log("Checking room availability with:", roomCheckData);
            
            // Check professor availability first
            fetch('../api/check_professor_availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(professorCheckData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.available) {
                    // No professor conflicts, now check room availability
                    fetch('../api/check_room_availability.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(roomCheckData)
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(roomData => {
                        if (roomData.available) {
                            // No conflicts, save the class
                            console.log("No conflicts found, saving class data");
                            saveClassData(classData, day, time);
                        } else {
                            // Room conflict found
                            console.log("Room conflict found:", roomData.conflicts);
                            // Remove any data from this time slot to ensure conflicts aren't saved
                            if (timetableData[day] && timetableData[day][time]) {
                                timetableData[day][time] = null;
                            }
                            
                            // Show room conflict modal
                            showRoomConflict(roomData.conflicts, classData);
                            closeModalWithAnimation("class-modal");
                        }
                    })
                    .catch(error => {
                        console.error("Error checking room availability:", error);
                        showToast("error", "Erreur lors de la vérification de la disponibilité de la salle");
                    });
                } else {
                    // Professor conflict found
                    console.log("Professor conflict found:", data.conflicts);
                    // Remove any data from this time slot to ensure conflicts aren't saved
                    if (timetableData[day] && timetableData[day][time]) {
                        timetableData[day][time] = null;
                    }
                    
                    // Show conflict modal
                    showProfessorConflict(data.conflicts, classData);
                    closeModalWithAnimation("class-modal");
                }
            })
            .catch(error => {
                console.error("Error checking professor availability:", error);
                showToast("error", "Erreur lors de la vérification de la disponibilité du professeur");
            });
        });
        
        // Warn user when leaving page with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });
        
        // Handle back button navigation
        document.querySelector('a[href="../admin/index.php"]').addEventListener('click', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                const href = this.getAttribute('href');
                showUnsavedChangesWarning(function() {
                    window.location.href = href;
                });
            }
        });
        
        // Load saved data on initial page load
        loadSavedData();
        
        // Add animation effect to radio buttons - simplified
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    // Force animation to replay
                    this.style.animation = 'none';
                    void this.offsetHeight; // Trigger reflow
                    this.style.animation = '';
                }
            });
        });

        // Delete class confirmation handler
        document.getElementById("delete-class-confirm").addEventListener("click", function() {
            if (deleteClassDay && deleteClassTime) {
                // Get the class data before deleting it
                const classToDelete = timetableData[deleteClassDay][deleteClassTime];
                
                console.log("Deleting class:", { day: deleteClassDay, time: deleteClassTime, class: classToDelete });
                
                // Delete from in-memory timetable
                timetableData[deleteClassDay][deleteClassTime] = null;
                
                // Since we're deleting directly from the database, don't mark as unsaved changes
                // hasUnsavedChanges = true; - REMOVED THIS LINE
                updatePublishStatus();
                
                // Also delete from database to make it permanent
                const baseUrl = window.location.origin;
                const projectPath = '/PI-php';
                const apiUrl = `${baseUrl}${projectPath}/src/api/delete_class.php`;
                
                const deleteData = {
                    year: currentYear,
                    group: currentGroup,
                    day: deleteClassDay,
                    time_slot: deleteClassTime
                };
                
                console.log("Sending delete request with data:", deleteData);
                
                fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(deleteData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Delete response:", data);
                    if (data.success) {
                        // Show success message
                        showToast("success", "Cours supprimé avec succès");
                    } else {
                        showToast("error", data.message || "Erreur lors de la suppression du cours");
                        console.error("Delete error:", data);
                    }
                })
                .catch(error => {
                    console.error("Error deleting class:", error);
                    showToast("error", "Erreur lors de la suppression du cours");
                });
                
                // Regenerate timetable
                generateEmptyTimetable();
                
                // Close modal
                closeModalWithAnimation("delete-class-modal");
                
                // Reset variables
                deleteClassDay = null;
                deleteClassTime = null;
            }
        });

        // Professor conflict modal handlers
        document.getElementById("conflict-cancel").addEventListener("click", function() {
            closeModalWithAnimation("professor-conflict-modal");
        });
        
        // Room conflict modal handlers
        document.getElementById("room-conflict-cancel").addEventListener("click", function() {
            closeModalWithAnimation("room-conflict-modal");
        });
        
        document.getElementById("room-conflict-close").addEventListener("click", function() {
            closeModalWithAnimation("room-conflict-modal");
        });
        
        // No longer needed - removed resetClassStatus function

        // Handle subgroup option changes
        document.getElementById("subgroup-single").addEventListener("change", function() {
            document.getElementById("subgroup-split-options").classList.add("hidden");
            document.getElementById("second-subgroup-options").classList.add("hidden");
            document.getElementById("single-subgroup-selector").classList.add("hidden");
        });
        
        document.getElementById("subgroup-split").addEventListener("change", function() {
            document.getElementById("subgroup-split-options").classList.remove("hidden");
            
            // Check which split option is selected and show/hide accordingly
            if (document.getElementById("subgroup-same-time").checked) {
                document.getElementById("second-subgroup-options").classList.remove("hidden");
                document.getElementById("single-subgroup-selector").classList.add("hidden");
                
                // Scroll to make second subgroup options visible
                setTimeout(() => {
                    const secondSubgroupOptions = document.getElementById("second-subgroup-options");
                    if (secondSubgroupOptions) {
                        secondSubgroupOptions.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }, 100);
            } else {
                document.getElementById("second-subgroup-options").classList.add("hidden");
                document.getElementById("single-subgroup-selector").classList.remove("hidden");
            }
        });
        
        // Handle subgroup split option changes
        document.getElementById("subgroup-same-time").addEventListener("change", function() {
            document.getElementById("second-subgroup-options").classList.remove("hidden");
            document.getElementById("single-subgroup-selector").classList.add("hidden");
            
            // Scroll to make second subgroup options visible
            setTimeout(() => {
                const secondSubgroupOptions = document.getElementById("second-subgroup-options");
                if (secondSubgroupOptions) {
                    secondSubgroupOptions.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 100);
        });
        
        document.getElementById("subgroup-single-group").addEventListener("change", function() {
            document.getElementById("second-subgroup-options").classList.add("hidden");
            document.getElementById("single-subgroup-selector").classList.remove("hidden");
        });
        
        // Setup subgroup dropdown items
        document.querySelectorAll("#subgroup-menu .dropdown-item").forEach(item => {
            item.addEventListener("click", function() {
                const subgroupNum = this.getAttribute("data-value");
                document.getElementById("selected-subgroup").textContent = "Sous-groupe " + subgroupNum;
                document.getElementById("subgroup-menu").classList.remove("open");
                document.getElementById("subgroup-dropdown").classList.remove("active");
            });
        });
        
        // Handle professor search for the second professor dropdown
        const professorSearch2 = document.getElementById("professor-search-2");
        if (professorSearch2) {
            professorSearch2.addEventListener("input", function(e) {
                e.stopPropagation();
                const searchTerm = this.value.toLowerCase().trim();
                const professorItems = document.querySelectorAll("#professor-list-2 .dropdown-item");
                
                professorItems.forEach(item => {
                    const professorName = item.getAttribute("data-value").toLowerCase();
                    if (searchTerm === "" || professorName.includes(searchTerm)) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
            });
            
            // Prevent dropdown from closing when clicking in the search input
            professorSearch2.addEventListener("click", function(e) {
                e.stopPropagation();
            });
        }
        
        // Setup handlers for second professor dropdown items
        document.querySelectorAll("#professor-list-2 .dropdown-item").forEach(item => {
            item.addEventListener("click", function() {
                const professorName = this.getAttribute("data-value");
                const professorId = this.getAttribute("data-id");
                
                document.getElementById("selected-professor-2").textContent = professorName;
                document.getElementById("selected-professor-2").setAttribute("data-id", professorId);
                
                document.getElementById("professor-menu-2").classList.remove("open");
                document.getElementById("professor-dropdown-2").classList.remove("active");
                
                // Enable subject dropdown for second professor
                document.getElementById("subject-dropdown-2").removeAttribute("disabled");
                document.getElementById("subject-dropdown-2").style.backgroundColor = "#ffffff";
                document.getElementById("subject-dropdown-2").style.cursor = "pointer";
                document.getElementById("selected-subject-2").textContent = "Chargement des matières...";
                
                // Filter subjects based on selected professor
                filterSubjectsByProfessor2(professorId);
            });
        });
        
        // Filter subjects based on second professor ID
        function filterSubjectsByProfessor2(professorId) {
            // Fetch subjects assigned to this professor from the database
            if (!professorId) {
                document.getElementById("selected-subject-2").textContent = "Sélectionner un professeur d'abord";
                document.getElementById("subject-dropdown-2").setAttribute("disabled", "disabled");
                return;
            }
            
            // Clear existing subject menu items
            const subjectMenu = document.getElementById("subject-menu-2");
            subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Chargement...</div>';
            
            // Construct the correct API URL
            const baseUrl = window.location.origin;
            const projectPath = '/PI-php'; // Update this to match your actual project folder name
            const apiUrl = `${baseUrl}${projectPath}/src/api/get_professor_subjects.php?professor_id=${professorId}`;
            
            console.log("Fetching professor subjects from:", apiUrl);
            
            fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log("Professor subjects response:", data);
                if (data.success && data.subjects && data.subjects.length > 0) {
                    // Clear current menu
                    subjectMenu.innerHTML = '';
                    
                    // Add each subject to the dropdown
                    data.subjects.forEach(subject => {
                        const item = document.createElement("div");
                        item.className = "dropdown-item";
                        item.setAttribute("data-value", subject.name);
                        item.setAttribute("data-id", subject.id);
                        item.setAttribute("data-color", subject.color);
                        
                        // Show subject name and code if available
                        const displayText = subject.code ? 
                            `${subject.name} (${subject.code})` : 
                            subject.name;
                        
                        item.textContent = displayText;
                        
                        item.addEventListener("click", function() {
                            const subject = this.getAttribute("data-value");
                            const subjectId = this.getAttribute("data-id");
                            
                            document.getElementById("selected-subject-2").textContent = subject;
                            document.getElementById("selected-subject-2").setAttribute("data-id", subjectId);
                            subjectMenu.classList.remove("open");
                            document.getElementById("subject-dropdown-2").classList.remove("active");
                        });
                        
                        subjectMenu.appendChild(item);
                    });
                    
                    // Enable the dropdown
                    document.getElementById("subject-dropdown-2").removeAttribute("disabled");
                    document.getElementById("selected-subject-2").textContent = "Sélectionner une matière";
                } else {
                    // No subjects found for this professor
                    subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Aucune matière assignée à ce professeur</div>';
                    document.getElementById("selected-subject-2").textContent = "Aucune matière disponible";
                    document.getElementById("subject-dropdown-2").setAttribute("disabled", "disabled");
                }
            })
            .catch(error => {
                console.error('Error fetching professor subjects:', error);
                document.getElementById("selected-subject-2").textContent = "Erreur lors du chargement des matières";
                document.getElementById("subject-dropdown-2").setAttribute("disabled", "disabled");
                subjectMenu.innerHTML = '<div class="dropdown-item" style="color: #888;">Erreur lors du chargement des matières</div>';
            });
        }
        
        // Setup handlers for second subject dropdown items
        document.querySelectorAll("#subject-menu-2 .dropdown-item").forEach(item => {
            item.addEventListener("click", function() {
                const subject = this.getAttribute("data-value");
                const subjectId = this.getAttribute("data-id");
                
                document.getElementById("selected-subject-2").textContent = subject;
                document.getElementById("selected-subject-2").setAttribute("data-id", subjectId);
                document.getElementById("subject-menu-2").classList.remove("open");
                document.getElementById("subject-dropdown-2").classList.remove("active");
            });
        });
        
        // Setup handlers for second room dropdown items
        document.querySelectorAll("#room-menu-2 .dropdown-item").forEach(item => {
            item.addEventListener("click", function() {
                const roomName = this.getAttribute("data-value");
                
                document.getElementById("selected-room-2").textContent = roomName;
                document.getElementById("room-menu-2").classList.remove("open");
                document.getElementById("room-dropdown-2").classList.remove("active");
            });
        });

        // ... existing code ...
        // Add animation effect to radio buttons
    });
</script>
</body>
</html>
