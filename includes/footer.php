        </main>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Change Password</h3>
                <button class="modal-close" onclick="closeChangePasswordModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm">
                    <div class="form-group">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="current_password" required 
                               placeholder="Enter your current password">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="new_password" required 
                               placeholder="Enter new password (min 6 characters)" minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required 
                               placeholder="Confirm your new password">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="changePassword()">
                    <i class="fas fa-save"></i> Change Password
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript with Dynamic Paths -->
    <script src="<?php echo JS_URL; ?>/dashboard.js"></script>
    <script src="<?php echo JS_URL; ?>/calculator.js"></script>
    
    <!-- Additional page-specific JavaScript -->
    <?php if(isset($additional_js)): ?>
        <?php foreach($additional_js as $js_file): ?>
            <script src="<?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Global JavaScript functions - Fallback if not defined elsewhere
        if (typeof showLoading === 'undefined') {
            window.showLoading = function() {
                // Create loading overlay if it doesn't exist
                if (!document.getElementById('loading-overlay')) {
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.id = 'loading-overlay';
                    loadingOverlay.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.5);
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        z-index: 9999;
                    `;
                    loadingOverlay.innerHTML = `
                        <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #2563eb;"></i>
                            <p style="margin-top: 10px; color: #333;">Processing...</p>
                        </div>
                    `;
                    document.body.appendChild(loadingOverlay);
                } else {
                    document.getElementById('loading-overlay').style.display = 'flex';
                }
            };
        }
        
        if (typeof hideLoading === 'undefined') {
            window.hideLoading = function() {
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            };
        }
        
        if (typeof showNotification === 'undefined') {
            window.showNotification = function(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                    color: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    animation: slideIn 0.3s ease-out;
                    max-width: 400px;
                `;
                
                // Add icon
                const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
                notification.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
                
                // Add to body
                document.body.appendChild(notification);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease-in';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            };
        }
        
        // Add animation styles if not already present
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(400px); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Change Password Modal Functions
        function openChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.getElementById('currentPassword').focus();
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            document.getElementById('changePasswordForm').reset();
        }

        function changePassword() {
            const form = document.getElementById('changePasswordForm');
            const formData = new FormData(form);
            
            const currentPassword = formData.get('current_password');
            const newPassword = formData.get('new_password');
            const confirmPassword = formData.get('confirm_password');

            // Client-side validation
            if (!currentPassword || !newPassword || !confirmPassword) {
                showNotification('All fields are required', 'error');
                return;
            }

            if (newPassword !== confirmPassword) {
                showNotification('New passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 6) {
                showNotification('New password must be at least 6 characters long', 'error');
                return;
            }

            // Show loading
            showLoading();

            // Send request to backend (handle different path contexts)
            const basePath = window.location.pathname.includes('/modules/') ? '../modules/' : 'modules/';
            fetch(basePath + 'change-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    showNotification('Password changed successfully!', 'success');
                    closeChangePasswordModal();
                } else {
                    showNotification(data.message || 'Failed to change password', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('An error occurred while changing password', 'error');
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('changePasswordModal');
            if (e.target === modal) {
                closeChangePasswordModal();
            }
        });

        // Handle Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeChangePasswordModal();
            }
        });

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-active');
                });
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 768 && 
                        !sidebar.contains(e.target) && 
                        !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('mobile-active');
                    }
                });
            }
        });
    </script>
</body>
</html>