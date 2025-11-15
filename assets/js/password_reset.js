
        document.addEventListener('DOMContentLoaded', function() {
            // Show toast notification and auto-hide after 3 seconds
            const toast = document.getElementById('toast');
            if (toast) {
                // Add the show class after a small delay to trigger the animation
                setTimeout(() => {
                    toast.classList.add('show');
                }, 100);
                
                // Hide the toast after 3 seconds
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.remove();
                    }, 500); // Wait for the animation to complete
                }, 3000);
            }
            
            // Toggle password visibility
            const toggleButtons = document.querySelectorAll('.toggle-password');
            if (toggleButtons) {
                toggleButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const input = this.closest('.relative').querySelector('input');
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        // Toggle eye icon
                        const img = this.querySelector('img');
                        if (type === 'text') {
                            // Show the "hide" icon when password is visible
                            img.src = "../assets/images/eye-off.svg";
                        } else {
                            // Show the "show" icon when password is hidden
                            img.src = "../assets/images/eye-show.svg";
                        }
                    });
                });
            }
        });
    