<?php
// Authentication protection
require_once '../includes/auth.php';
requireAuth();

$page_title = 'Profile Management - Dental Practice Management';
require_once '../config/database.php';
require_once '../includes/header.php';

// Handle change password form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters long';
    } else {
        // Get current user
        $user = getCurrentUser();
        
        // For now, use simple validation since we're using hardcoded credentials
        if ($user['username'] === 'admin' && $current_password === 'dental2025') {
            // In a real system, you would update the database here
            $success_message = 'Password change simulated successfully. In production, this would update the database.';
        } else {
            $error_message = 'Current password is incorrect';
        }
    }
}

// Get current user info
$user = getCurrentUser();
?>

<?php include '../includes/sidebar.php'; ?>

    <div class="content-header">
        <h1 class="content-title">
            <i class="fas fa-user-cog"></i>
            Profile Management
        </h1>
        <p class="content-subtitle">Manage your account settings and security</p>
    </div>

    <div class="profile-content">
        <!-- User Information Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-user"></i>
                    User Information
                </h2>
            </div>
            
            <div class="profile-info-card">
                <div class="profile-avatar-section">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p class="profile-role">Administrator</p>
                        <p class="profile-login">Last login: <?php echo date('Y-m-d H:i', $user['login_time']); ?></p>
                    </div>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <i class="fas fa-shield-alt"></i>
                        <span class="stat-label">Account Type</span>
                        <span class="stat-value">Administrator</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        <span class="stat-label">Session Duration</span>
                        <span class="stat-value"><?php echo gmdate("H:i:s", time() - $user['login_time']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    Change Password
                </h2>
                <p class="section-description">Update your account password for security</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="password-form-card">
                <form method="POST" action="" class="password-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-lock"></i>
                                Current Password
                            </label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required 
                                   placeholder="Enter your current password"
                                   class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-key"></i>
                                New Password
                            </label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required 
                                   minlength="6"
                                   placeholder="Enter new password (min 6 characters)"
                                   class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check"></i>
                                Confirm New Password
                            </label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   placeholder="Confirm your new password"
                                   class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Change Password
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-undo"></i>
                            Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Information -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Security Information
                </h2>
            </div>
            
            <div class="security-info-card">
                <div class="security-tips">
                    <h4><i class="fas fa-lightbulb"></i> Password Security Tips</h4>
                    <ul>
                        <li>Use at least 6 characters (recommended 8 or more)</li>
                        <li>Include a mix of letters, numbers, and special characters</li>
                        <li>Avoid using personal information</li>
                        <li>Change your password regularly</li>
                        <li>Don't share your password with others</li>
                    </ul>
                </div>
                
                <div class="system-note">
                    <h4><i class="fas fa-cog"></i> System Note</h4>
                    <p>This system currently uses hardcoded authentication for simplicity. In production, implement the users table from the provided SQL file for secure password management with proper hashing.</p>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>