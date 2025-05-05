<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    if (isset($_SESSION['user_id'])) {
        error_log("Unauthorized access attempt to admin.php by user ID: " . $_SESSION['user_id']);
    } else {
        error_log("Unauthorized access attempt to admin.php (no session)");
    }
    
    // Redirect to login page
    header("Location: ../views/login.php");
    exit;
}

// Get admin info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // This should not happen if session checks are working, but just in case
        error_log("Admin user not found in database despite valid session. User ID: " . $_SESSION['user_id']);
        session_destroy();
        header("Location: ../views/login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in admin.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Include Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-gradient-to-r from-indigo-700 to-blue-500 text-white shadow-md">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                <a href="../views/logout.php" class="bg-white/20 hover:bg-white/30 text-white font-medium py-2 px-4 rounded transition duration-300 text-sm">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-8">
        <!-- display success message if operation was done -->
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'cleared'): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-8" role="alert">
                <p class="font-bold">Success!</p>
                <p>All timetable data has been cleared successfully.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Welcome, <?php echo htmlspecialchars($admin['email']); ?>!</h2>
            <p class="text-gray-600">Use the tools below to manage the timetable system.</p>
        </div>
        
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Timetable Management</h2>
            <div class="flex space-x-3">
                <form action="../utils/clear_timetables.php" method="POST" id="clear-form" class="m-0">
                    <button type="button" id="clear-button" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md transition duration-300 text-sm flex items-center">
                        <svg id="clear-icon-default" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <svg id="clear-icon-loading" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 animate-spin hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span id="clear-text">Clear All Timetables</span>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Confirmation Modal for Clear All Timetables -->
        <div id="clear-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4 transform transition-all">
                <h3 class="text-lg font-bold text-red-600 mb-2">Confirm Action</h3>
                <p class="text-gray-700 mb-4">Are you sure you want to clear ALL timetable data? This action cannot be undone.</p>
                <div class="flex justify-end space-x-3">
                    <button id="clear-cancel" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button id="clear-confirm" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Yes, Clear All Data
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        // Show the modal when clicking the clear button
        document.getElementById('clear-button').addEventListener('click', function() {
            document.getElementById('clear-modal').classList.remove('hidden');
        });
        
        // Hide the modal when clicking cancel
        document.getElementById('clear-cancel').addEventListener('click', function() {
            document.getElementById('clear-modal').classList.add('hidden');
        });
        
        // Submit the form when confirming
        document.getElementById('clear-confirm').addEventListener('click', function() {
            const form = document.getElementById('clear-form');
            const button = document.getElementById('clear-button');
            const defaultIcon = document.getElementById('clear-icon-default');
            const loadingIcon = document.getElementById('clear-icon-loading');
            const clearText = document.getElementById('clear-text');
            
            // Hide the modal
            document.getElementById('clear-modal').classList.add('hidden');
            
            // Disable the button and show loading state
            button.disabled = true;
            button.classList.add('opacity-75');
            defaultIcon.classList.add('hidden');
            loadingIcon.classList.remove('hidden');
            clearText.textContent = 'Clearing...';
            
            // Submit the form
            form.submit();
        });
        
        // Close the modal if clicking outside of it
        document.getElementById('clear-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
        </script>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <a href="../views/admin_timetable.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-indigo-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Manage Timetables</h3>
                        <p class="mt-1 text-sm text-gray-500">Create and edit timetables for all years and groups</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/timetable_view.php?role=professor&preview=true" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Professor View</h3>
                        <p class="mt-1 text-sm text-gray-500">Preview the timetable as professors see it</p>
                    </div>
                </div>
            </a>
            
            <a href="../views/timetable_view.php?role=student&preview=true" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Student View</h3>
                        <p class="mt-1 text-sm text-gray-500">Preview the timetable as students see it</p>
                    </div>
                </div>
            </a>
        </div>
        
        <h2 class="text-xl font-semibold mb-4">System Management</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="../utils/fix_passwords.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Fix User Passwords</h3>
                        <p class="mt-1 text-sm text-gray-500">Reset or update user passwords</p>
                    </div>
                </div>
            </a>
            
            <a href="../utils/check_users.php" class="card bg-white rounded-lg shadow-md p-6 hover:bg-gray-50">
                <div class="flex items-start">
                    <div class="bg-red-100 p-3 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Check Users</h3>
                        <p class="mt-1 text-sm text-gray-500">Manage user accounts and permissions</p>
                    </div>
                </div>
            </a>
        </div>
    </main>
</body>
</html>