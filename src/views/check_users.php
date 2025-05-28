<?php
require_once '../includes/db.php';
session_start();

// This script should only be accessible from specific IPs
/*$client_ip = $_SERVER['REMOTE_ADDR'];
$allowed_ips = ['41.188.115.42'];

if (!in_array($client_ip, $allowed_ips)) {
    echo "This script can only be accessed from authorized devices.";
    exit;
}*/

// Auto-reset the auto-increment value to prevent gaps
function resetAutoIncrement($pdo) {
    try {
        // Get the maximum ID
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM users");
        $maxId = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
        
        // Get current auto-increment value
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = 'users'");
        $tableStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentAutoIncrement = $tableStatus['Auto_increment'];
        
        // Only reset if there's a gap
        if ((int)$currentAutoIncrement > (int)$maxId + 1) {
            $pdo->exec("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Failed to reset auto-increment: " . $e->getMessage());
        return false;
    }
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Automatically reset auto-increment on page load
resetAutoIncrement($pdo);

// Initialize message variables
$success_message = '';
$error_message = '';

// Process messages from the delete operation
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success_message = "L'utilisateur a été supprimé avec succès.";
} elseif (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, email, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifier les Utilisateurs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <style>
        * {
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: "Outfit", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 0.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #f3f4f6;
            font-weight: 600;
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .badge-admin {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        
        .badge-professor {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .badge-student {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Search highlight */
        .highlight {
            background-color: #fef3c7;
            font-weight: 500;
        }
        
        /* Fade in animation for search results */
        @keyframes fadeIn {
            from { opacity: 0.2; }
            to { opacity: 1; }
        }
        
        tr.filtered-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        /* Input focus animation */
        input {
            transition: all 0.6s ease !important;
        }
        
        .search-box:focus {
            outline: none !important;
            border: 1px solid #ef4444 !important;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.3) !important;
        }
        
        /* Delete button and confirmation modal */
        .delete-btn {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .delete-btn:hover {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        
        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: scale(0.95);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .modal-backdrop.active .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            z-index: 2000;
            max-width: 350px;
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
        
        /* Mobile responsive styles */
        @media (max-width: 640px) {
            /* Convert table to cards on mobile */
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .table-container {
                border: none;
                box-shadow: none;
                overflow: visible;
            }
            
            table {
                border: none;
                box-shadow: none;
            }
            
            tbody tr {
                margin-bottom: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
                background: white;
                position: relative;
                border: 1px solid #e5e7eb;
            }
            
            td {
                border: none;
                border-bottom: 1px solid #f3f4f6;
                position: relative;
                padding-left: 40%;
                min-height: 45px;
                display: flex;
                align-items: center;
            }
            
            td:last-child {
                border-bottom: none;
            }
            
            td:before {
                position: absolute;
                left: 1rem;
                width: 35%;
                font-weight: 600;
                color: #4b5563;
                content: attr(data-label);
            }
            
            /* Fix role badges on mobile */
            td .role-badge {
                margin-left: auto;
                margin-right: auto;
            }
            
            /* Improve search box on mobile */
            .search-box {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
        
        /* Clean up the custom checkbox styles */
        .custom-checkbox {
            display: flex;
            align-items: center;
            padding: 0.375rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .custom-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .custom-checkbox .checkbox-icon {
            position: relative;
            display: inline-block;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #d1d5db;
            border-radius: 0.25rem;
            margin-right: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon {
            background-color: #ef4444;
            border-color: #ef4444;
        }
        
        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon:after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 4px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        th .custom-checkbox .checkbox-icon {
            border-color: #ef4444;
        }
        
        th .custom-checkbox:hover .checkbox-icon {
            border-color: #dc2626;
        }
        
        /* Simplify by combining the row and delete button styles */
        .user-row, .checkbox-column, .delete-btn {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .user-row:hover {
            background-color: #f9fafb;
        }
        
        .user-row.selected {
            background-color: #f1f5f9;
        }
        
        .delete-btn {
            position: relative;
            z-index: 10;
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
        }
        
        .delete-btn:hover {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        /* Combine bulk action button with delete button styles */
        .bulk-delete-btn {
            background-color: #fee2e2;
            color: #b91c1c;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .bulk-delete-btn:hover {
            background-color: #fecaca;
            color: #991b1b;
        }
        
        .bulk-delete-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-red-700 to-red-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Vérifier les Utilisateurs</h1>
                <div class="flex space-x-4">
                    <a href="../views/manage_users.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm">
                        Créer un Utilisateur
                    </a>
                    <a href="../admin/index.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Retour au Tableau de Bord
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Toast Notification -->
    <div id="toast-notification" class="toast <?php echo !empty($success_message) ? 'toast-success' : (!empty($error_message) ? 'toast-error' : ''); ?>" style="display: none;">
        <div class="flex items-center">
            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <?php if (!empty($success_message)): ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                <?php else: ?>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                <?php endif; ?>
            </svg>
            <span id="toast-message"><?php echo !empty($success_message) ? htmlspecialchars($success_message) : htmlspecialchars($error_message); ?></span>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="delete-modal" class="modal-backdrop">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Confirmation de suppression</h3>
                <button type="button" id="close-modal" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <p id="delete-message">Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete-user-email"></strong> ?</p>
                <div id="delete-multiple-info" class="hidden">
                    <p class="mt-2">Vous avez sélectionné <strong id="selected-count">0</strong> utilisateurs.</p>
                    <ul id="selected-users-list" class="mt-2 max-h-32 overflow-y-auto text-sm text-gray-600 border border-gray-200 rounded-md p-2 bg-gray-50">
                        <!-- Selected users will be listed here -->
                    </ul>
                </div>
                <p class="text-sm text-red-600 mt-2">Cette action est irréversible.</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-delete" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded">
                    Annuler
                </button>
                <button type="button" id="confirm-delete" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded">
                    Supprimer
                </button>
            </div>
        </div>
    </div>
    
    <main class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center mb-4">
                <div class="bg-red-100 p-3 rounded-lg mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold">Utilisateurs dans la Base de Données</h2>
            </div>
            
            <!-- Search Box -->
            <div class="mb-6">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input id="email-search" type="text" class="search-box block w-full pl-10 pr-3 py-2 rounded-md border border-gray-300 shadow-sm focus:outline-none focus:border-red-500 focus:ring-0 transition duration-150 ease-in-out" placeholder="Rechercher par email...">
                </div>
                <div class="flex justify-between items-center mt-2">
                    <p id="search-results" class="text-sm text-gray-600">Affichage de <span id="count-display"><?php echo count($users); ?></span> utilisateurs</p>
                    
                    <button id="bulk-delete-btn" class="bulk-delete-btn py-2 px-4 rounded text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer les sélectionnés (<span id="selected-counter">0</span>)
                    </button>
                </div>
            </div>
            
            <?php if (count($users) === 0): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Aucun utilisateur trouvé dans la base de données.
                            </p>
                            <p class="mt-2 text-sm text-yellow-700">
                                Vous devrez peut-être ajouter des utilisateurs d'abord.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <div class="custom-checkbox">
                                        <input type="checkbox" id="select-all-checkbox">
                                        <span class="checkbox-icon"></span>
                                    </div>
                                </th>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php foreach ($users as $user): 
                                // Set role badge class
                                $role_class = 'badge-student';
                                if ($user['role'] === 'admin') {
                                    $role_class = 'badge-admin';
                                } elseif ($user['role'] === 'professor') {
                                    $role_class = 'badge-professor';
                                }
                                
                                // Don't allow deletion of self
                                $is_current_user = $user['id'] == $_SESSION['user_id'];
                            ?>
                                <tr class="user-row" 
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>" 
                                    data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                    data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                    <?php if ($is_current_user): ?>data-current-user="true"<?php endif; ?>>
                                    <td data-label="Sélection" class="checkbox-column">
                                        <?php if (!$is_current_user): ?>
                                        <div class="custom-checkbox">
                                        <input type="checkbox" 
                                                class="user-checkbox" 
                                                id="user-<?php echo htmlspecialchars($user['id']); ?>"
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>">
                                            <span class="checkbox-icon"></span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td class="font-medium email-cell" data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td data-label="Rôle">
                                        <span class="role-badge <?php echo $role_class; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions">
                                        <?php if (!$is_current_user): ?>
                                            <button 
                                                class="delete-btn" 
                                                data-id="<?php echo htmlspecialchars($user['id']); ?>" 
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            >
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Supprimer
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-xs italic">Compte actuel</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 bg-red-50 border-l-4 border-red-500 p-4">
                    <p class="text-red-700">
                        Pour les problèmes de sécurité des mots de passe, utilisez l'utilitaire 
                        <a href="fix_passwords.php" class="text-red-600 hover:text-red-800 font-medium underline">Changer les mots de passe</a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const searchInput = document.getElementById('email-search');
            const countDisplay = document.getElementById('count-display');
            const userRows = document.querySelectorAll('.user-row');
            const totalUsers = userRows.length;
            const deleteModal = document.getElementById('delete-modal');
            const closeModalBtn = document.getElementById('close-modal');
            const cancelDeleteBtn = document.getElementById('cancel-delete');
            const confirmDeleteBtn = document.getElementById('confirm-delete');
            const deleteUserEmail = document.getElementById('delete-user-email');
            const deleteMessage = document.getElementById('delete-message');
            const deleteMultipleInfo = document.getElementById('delete-multiple-info');
            const selectedCountElem = document.getElementById('selected-count');
            const selectedUsersList = document.getElementById('selected-users-list');
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
            const selectedCounter = document.getElementById('selected-counter');
            
            let selectedUsers = [];
            let isMultipleDelete = false;
            
            // Initialize
            initUI();
            
            function initUI() {
                // Show toast notification if needed
                if ('<?php echo $success_message; ?>' || '<?php echo $error_message; ?>') {
                    showToast('<?php echo $success_message ? "success" : "error"; ?>', 
                        '<?php echo addslashes($success_message ?: $error_message); ?>');
                }
                
                // Initialize all interactive components
                initCheckboxes();
                initClickableRows();
                initDeleteButtons();
                initSearchFunctionality();
                initModalHandlers();
            }
            
            // Initialize search functionality
            function initSearchFunctionality() {
                searchInput.addEventListener('input', handleSearch);
                
                // Clear search when ESC key is pressed
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        this.value = '';
                        this.dispatchEvent(new Event('input'));
                    }
                });
            }
            
            // Handle search input changes
            function handleSearch() {
                const searchValue = this.value.toLowerCase().trim();
                let visibleCount = 0;
                
                userRows.forEach(row => {
                    const email = row.getAttribute('data-email').toLowerCase();
                    const emailCell = row.querySelector('.email-cell');
                    
                    if (searchValue === '') {
                        // Reset to default state
                        row.style.display = '';
                        emailCell.innerHTML = email;
                        visibleCount++;
                    } else if (email.includes(searchValue)) {
                        // Show the row and highlight the match
                        row.style.display = '';
                        row.classList.add('filtered-in');
                        
                        // Highlight the matching part
                        const highlightedEmail = email.replace(
                            new RegExp(searchValue, 'gi'),
                            match => `<span class="highlight">${match}</span>`
                        );
                        emailCell.innerHTML = highlightedEmail;
                        visibleCount++;
                    } else {
                        // Hide the row
                        row.style.display = 'none';
                    }
                });
                
                // Update count display
                countDisplay.textContent = visibleCount;
                
                // Show "no results" message if needed
                const searchResults = document.getElementById('search-results');
                if (visibleCount === 0) {
                    searchResults.innerHTML = `<span class="text-amber-600">Aucun résultat trouvé pour "${searchValue}"</span>`;
                } else if (visibleCount < totalUsers) {
                    searchResults.innerHTML = `Affichage de <span class="font-medium">${visibleCount}</span> utilisateurs sur ${totalUsers}`;
                } else {
                    searchResults.innerHTML = `Affichage de <span id="count-display">${totalUsers}</span> utilisateurs`;
                }
                
                // Update select all checkbox state based on visible rows
                updateSelectAllCheckbox();
            }
            
            // Initialize clickable rows
            function initClickableRows() {
                userRows.forEach(row => {
                    // Skip rows that represent the current user (not selectable)
                    if (row.hasAttribute('data-current-user')) {
                        return;
                    }
                    
                    row.addEventListener('click', function(e) {
                        // Don't trigger if clicking on a button or checkbox
                        if (e.target.closest('.delete-btn') || 
                            e.target.closest('.custom-checkbox') || 
                            e.target.tagName === 'INPUT' ||
                            e.target.tagName === 'BUTTON') {
                            return;
                        }
                        
                        // Find the checkbox within this row
                        const checkbox = row.querySelector('.user-checkbox');
                        if (checkbox) {
                            // Toggle checkbox
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change'));
                            
                            // Update row styling
                            updateRowSelection(row, checkbox.checked);
                        }
                    });
                });
            }
            
            // Update row styling based on selection
            function updateRowSelection(row, isSelected) {
                if (isSelected) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            }
            
            // Initialize checkboxes
            function initCheckboxes() {
                // Select All checkbox functionality
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    
                    // Get all visible non-current-user rows
                    const visibleRows = Array.from(userRows).filter(row => {
                        return row.style.display !== 'none' && !row.hasAttribute('data-current-user');
                    });
                    
                    // Select/deselect checkboxes for visible rows
                    visibleRows.forEach(row => {
                        const checkbox = row.querySelector('.user-checkbox');
                        if (checkbox) {
                            checkbox.checked = isChecked;
                            updateRowSelection(row, isChecked);
                        }
                    });
                    
                    updateSelectedUsers();
                });
                
                // Initialize select all checkbox container click handler
                const selectAllContainer = selectAllCheckbox.closest('.custom-checkbox');
                if (selectAllContainer) {
                    selectAllContainer.addEventListener('click', function(e) {
                        // Only toggle if the click wasn't directly on the input
                        if (e.target !== selectAllCheckbox) {
                            selectAllCheckbox.checked = !selectAllCheckbox.checked;
                            selectAllCheckbox.dispatchEvent(new Event('change'));
                            e.stopPropagation();
                        }
                    });
                }
                
                // Individual checkboxes 
                userCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        // Update row styling when checkbox changes
                        const row = this.closest('tr');
                        updateRowSelection(row, this.checked);
                        
                        updateSelectedUsers();
                        
                        // Check if all visible checkboxes are checked
                        updateSelectAllCheckbox();
                    });
                    
                    // Make the custom checkbox container also toggle the checkbox
                    const container = checkbox.closest('.custom-checkbox');
                    if (container) {
                        container.addEventListener('click', function(e) {
                            // Only toggle if the click wasn't directly on the input
                            if (e.target !== checkbox) {
                                checkbox.checked = !checkbox.checked;
                                checkbox.dispatchEvent(new Event('change'));
                                e.stopPropagation(); // Prevent row click from triggering again
                            }
                        });
                    }
                });
                
                // Bulk delete button
                bulkDeleteBtn.addEventListener('click', function() {
                    if (selectedUsers.length > 0) {
                        showMultiDeleteModal();
                    }
                });
            }
            
            // Update selected users array and UI elements
            function updateSelectedUsers() {
                selectedUsers = Array.from(userCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => ({
                        id: checkbox.getAttribute('data-id'),
                        email: checkbox.getAttribute('data-email')
                    }));
                
                // Update counter
                selectedCounter.textContent = selectedUsers.length;
                
                // Enable/disable bulk delete button
                bulkDeleteBtn.disabled = selectedUsers.length === 0;
            }
            
            // Update select all checkbox state
            function updateSelectAllCheckbox() {
                // Get only the visible rows (not hidden by search)
                const visibleRows = Array.from(userRows).filter(row => {
                    return row.style.display !== 'none' && !row.hasAttribute('data-current-user');
                });
                
                // Get the checkboxes from the visible rows
                const visibleCheckboxes = visibleRows
                    .map(row => row.querySelector('.user-checkbox'))
                    .filter(checkbox => checkbox !== null);
                
                // Check if all visible checkboxes are checked
                const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(checkbox => checkbox.checked);
                const someChecked = visibleCheckboxes.some(checkbox => checkbox.checked);
                
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            // Initialize delete buttons
            function initDeleteButtons() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.getAttribute('data-id');
                        const userEmail = this.getAttribute('data-email');
                        
                        showSingleDeleteModal(userId, userEmail);
                    });
                });
                
                // Bulk delete button
                bulkDeleteBtn.addEventListener('click', function() {
                    if (selectedUsers.length > 0) {
                        showMultiDeleteModal();
                    }
                });
            }
            
            // Initialize modal handlers
            function initModalHandlers() {
                // Close modal when clicking the close button
                closeModalBtn.addEventListener('click', () => hideModal(deleteModal));
                
                // Close modal when clicking the cancel button
                cancelDeleteBtn.addEventListener('click', () => hideModal(deleteModal));
                
                // Close modal when clicking outside the modal content
                deleteModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideModal(deleteModal);
                    }
                });
                
                // Handle deletion confirmation
                confirmDeleteBtn.addEventListener('click', function() {
                    if (selectedUsers.length > 0) {
                        deleteUsers(selectedUsers);
                    }
                });
            }
            
            // Show modal for deleting a single user
            function showSingleDeleteModal(userId, userEmail) {
                isMultipleDelete = false;
                deleteUserEmail.textContent = userEmail;
                deleteMessage.textContent = `Êtes-vous sûr de vouloir supprimer l'utilisateur ${userEmail} ?`;
                deleteMultipleInfo.classList.add('hidden');
                
                // Set up for single user deletion
                selectedUsers = [{id: userId, email: userEmail}];
                
                showModal(deleteModal);
            }
            
            // Show modal for deleting multiple users
            function showMultiDeleteModal() {
                isMultipleDelete = true;
                deleteMessage.textContent = 'Êtes-vous sûr de vouloir supprimer les utilisateurs sélectionnés ?';
                deleteMultipleInfo.classList.remove('hidden');
                
                // Update count and list
                selectedCountElem.textContent = selectedUsers.length;
                selectedUsersList.innerHTML = '';
                
                // Add each user to the list
                selectedUsers.forEach(user => {
                    const listItem = document.createElement('li');
                    listItem.className = 'py-1';
                    listItem.textContent = user.email;
                    selectedUsersList.appendChild(listItem);
                });
                
                showModal(deleteModal);
            }
            
            // Function to delete users (single or multiple)
            function deleteUsers(users) {
                // Show processing state
                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Suppression...
                `;
                
                // Process each user deletion sequentially
                const deletePromises = users.map(user => {
                    const formData = new FormData();
                    formData.append('user_id', user.id);
                    
                    return fetch('../api/delete_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        return { userId: user.id, success: data.success, message: data.message };
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        return { userId: user.id, success: false, message: 'Erreur réseau' };
                    });
                });
                
                Promise.all(deletePromises)
                    .then(results => {
                        // Count successes and failures
                        const successCount = results.filter(r => r.success).length;
                        const failureCount = results.length - successCount;
                        
                        // Remove successful deletions from UI
                        results.forEach(result => {
                            if (result.success) {
                                const userRow = document.querySelector(`.user-row[data-id="${result.userId}"]`);
                                if (userRow) {
                                    userRow.remove();
                                }
                            }
                        });
                        
                        // Update the user count
                        countDisplay.textContent = document.querySelectorAll('.user-row').length;
                        
                        // Reset selected users
                        selectedUsers = [];
                        updateSelectedUsers();
                        updateSelectAllCheckbox();
                        
                        // Hide the modal
                        hideModal(deleteModal);
                        
                        // Show result message
                        if (successCount > 0 && failureCount === 0) {
                            // All deletions succeeded
                            const message = successCount === 1 
                                ? 'L\'utilisateur a été supprimé avec succès' 
                                : `${successCount} utilisateurs ont été supprimés avec succès`;
                            showToast('success', message);
                        } else if (successCount > 0 && failureCount > 0) {
                            // Some succeeded, some failed
                            showToast('error', `${successCount} supprimés, ${failureCount} échecs`);
                        } else {
                            // All failed
                            showToast('error', 'Échec de la suppression');
                        }
                        
                        // Reset button state
                        confirmDeleteBtn.disabled = false;
                        confirmDeleteBtn.innerHTML = 'Supprimer';
                    });
            }
            
            // Function to show modal
            function showModal(modal) {
                modal.classList.add('active');
            }
            
            // Reset confirm button state
            function resetConfirmButton() {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = 'Supprimer';
            }

            // Function to hide modal
            function hideModal(modal) {
                modal.classList.remove('active');
                resetConfirmButton();
            }
            
            // Function to show toast notification
            function showToast(type, message) {
                const toast = document.getElementById('toast-notification');
                if (!toast) return;
                
                toast.className = 'toast';
                toast.classList.add(type === 'success' ? 'toast-success' : 'toast-error');
                
                toast.querySelector('svg').innerHTML = type === 'success'
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                
                document.getElementById('toast-message').textContent = message;
                toast.style.display = 'block';
                
                // Force repaint
                void toast.offsetWidth;
                
                // Show animation
                toast.classList.add('show');
                
                // Auto-hide after 4 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 300);
                }, 4000);
            }
        });
    </script>
</body>
</html>
<?php
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 