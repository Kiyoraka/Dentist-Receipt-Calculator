<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Check if config.php exists, if not, include it
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
?>

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-tooth"></i>
            <h2>Dental System</h2>
        </div>
        <p class="subtitle">Practice Management</p>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Main Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'financial.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/modules/financial.php" class="nav-link">
                    <i class="fas fa-calculator"></i>
                    <span>Financial Management</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'receipts.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/modules/receipts.php" class="nav-link">
                    <i class="fas fa-receipt"></i>
                    <span>Receipt Management</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'patients.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/modules/patients.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Patient Management</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>/modules/profile.php" class="nav-link">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile Management</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <!-- Logout Button -->
        <?php 
        require_once __DIR__ . '/auth.php';
        $user = getCurrentUser();
        if ($user): 
        ?>
            <div class="sidebar-logout">
                <a href="#" class="logout-btn" onclick="showLogoutModal(); return false;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</aside>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-sign-out-alt"></i>
                Confirm Logout
            </h3>
            <button type="button" class="modal-close" onclick="hideLogoutModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="margin: 0; color: #64748b; font-size: 1rem; line-height: 1.6;">
                Are you sure you want to logout from the dental practice management system?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="hideLogoutModal()">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button type="button" class="btn-primary" onclick="confirmLogout()">
                <i class="fas fa-sign-out-alt"></i>
                Yes, Logout
            </button>
        </div>
    </div>
</div>

<script>
function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function hideLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
    // Check if we're on production
    const isProduction = window.location.hostname === 'caninehubdentist.com';
    
    if (isProduction) {
        window.location.href = 'https://caninehubdentist.com?logout=1';
    } else {
        window.location.href = '?logout=1';
    }
}

// Close modal when clicking outside
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLogoutModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('logoutModal').style.display === 'flex') {
        hideLogoutModal();
    }
});
</script>

<!-- Main Content Area -->
<main class="main-content">
    <!-- Mobile menu toggle -->
    <div class="mobile-header">
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="mobile-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
    </div>