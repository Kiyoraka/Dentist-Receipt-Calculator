<?php
$current_page = basename($_SERVER['PHP_SELF']);
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
                <a href="index.php" class="nav-link">
                    <i class="fas fa-chart-dashboard"></i>
                    <span>Main Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'financial.php') ? 'active' : ''; ?>">
                <a href="modules/financial.php" class="nav-link">
                    <i class="fas fa-calculator"></i>
                    <span>Financial Management</span>
                </a>
            </li>
            
            <li class="nav-item <?php echo ($current_page == 'patients.php') ? 'active' : ''; ?>">
                <a href="modules/patients.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Patient Management</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="system-info">
            <p><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i'); ?></p>
            <p><i class="fas fa-database"></i> Connected</p>
        </div>
    </div>
</aside>

<!-- Main Content Area -->
<main class="main-content">
    <!-- Mobile menu toggle -->
    <div class="mobile-header">
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="mobile-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
    </div>