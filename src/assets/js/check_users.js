
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