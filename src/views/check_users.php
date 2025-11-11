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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <link rel="icon" href="../assets/images/logo-supnum2.png" />
    <link rel="stylesheet" href="../assets/css/pages/check_users.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-content">
                <h1 class="header-title">Vérifier les Utilisateurs</h1>
                <div class="header-actions">
                    <a href="../views/manage_users.php" class="header-btn">
                        Créer un Utilisateur
                    </a>
                    <a href="../admin/index.php" class="header-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
        <div style="display: flex; align-items: center;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
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
            <div class="modal-header">
                <h3 class="modal-title">Confirmation de suppression</h3>
                <button type="button" id="close-modal" class="modal-close">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p id="delete-message" class="modal-text">Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete-user-email"></strong> ?</p>
                <div id="delete-multiple-info" class="modal-info hidden">
                    <p>Vous avez sélectionné <strong id="selected-count">0</strong> utilisateurs.</p>
                    <ul id="selected-users-list" class="selected-users-list">
                        <!-- Selected users will be listed here -->
                    </ul>
                </div>
                <p class="modal-warning">Cette action est irréversible.</p>
            </div>
            <div class="modal-actions">
                <button type="button" id="cancel-delete" class="modal-btn modal-btn-cancel">
                    Annuler
                </button>
                <button type="button" id="confirm-delete" class="modal-btn modal-btn-confirm">
                    Supprimer
                </button>
            </div>
        </div>
    </div>
    
    <main>
        <div class="content-card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="card-title">Utilisateurs dans la Base de Données</h2>
            </div>
            
            <!-- Search Box -->
            <div class="search-container">
                <div class="search-wrapper">
                    <div class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input id="email-search" type="text" class="search-box" placeholder="Rechercher par email...">
                </div>
                <div class="search-info">
                    <p id="search-results" class="search-results">Affichage de <span id="count-display"><?php echo count($users); ?></span> utilisateurs</p>
                    
                    <button id="bulk-delete-btn" class="bulk-delete-btn" disabled>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer les sélectionnés (<span id="selected-counter">0</span>)
                    </button>
                </div>
            </div>
            
            <?php if (count($users) === 0): ?>
                <div class="alert alert-warning">
                    <div style="display: flex;">
                        <div class="alert-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="alert-content">
                            <p class="alert-text">
                                Aucun utilisateur trouvé dans la base de données.
                            </p>
                            <p class="alert-text" style="margin-top: 0.5rem;">
                                Vous devrez peut-être ajouter des utilisateurs d'abord.
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
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
                
                <div class="alert alert-info" style="margin-top: 1.5rem;">
                    <p class="alert-text">
                        Pour les problèmes de sécurité des mots de passe, utilisez l'utilitaire 
                        <a href="fix_passwords.php" class="alert-link">Changer les mots de passe</a>.
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