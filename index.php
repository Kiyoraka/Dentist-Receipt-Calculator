<?php
$page_title = 'Main Dashboard - Dental Practice Management';
require_once 'config/database.php';
require_once 'includes/header.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get dashboard statistics
try {
    // Today's statistics
    $today = date('Y-m-d');
    
    // Total receipts today
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM receipts WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // This month's statistics
    $this_month = date('Y-m-01');
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM receipts WHERE created_at >= ?");
    $stmt->execute([$this_month]);
    $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total patients
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients");
    $stmt->execute();
    $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent receipts
    $stmt = $conn->prepare("SELECT r.*, p.name as patient_name FROM receipts r LEFT JOIN patients p ON r.patient_id = p.id ORDER BY r.created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top services
    $stmt = $conn->prepare("SELECT service_name, COUNT(*) as usage_count, SUM(amount) as total_amount FROM receipt_services GROUP BY service_name ORDER BY usage_count DESC LIMIT 5");
    $stmt->execute();
    $top_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>

<?php include 'includes/sidebar.php'; ?>

    <div class="content-header">
        <h1 class="content-title">
            <i class="fas fa-chart-dashboard"></i>
            Main Dashboard
        </h1>
        <p class="content-subtitle">Practice overview and key metrics</p>
    </div>

    <!-- Dashboard Statistics Cards -->
    <div class="dashboard-cards">
        <!-- Today's Revenue -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon primary">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <h3 class="card-title">Today's Revenue</h3>
                    <div class="card-value">RM <?php echo number_format($today_stats['revenue'], 2); ?></div>
                    <p class="card-subtitle"><?php echo $today_stats['count']; ?> receipts</p>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon success">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h3 class="card-title">This Month</h3>
                    <div class="card-value">RM <?php echo number_format($month_stats['revenue'], 2); ?></div>
                    <p class="card-subtitle"><?php echo $month_stats['count']; ?> total receipts</p>
                </div>
            </div>
        </div>

        <!-- Total Patients -->
        <div class="dashboard-card">
            <div class="card-header">
                <div class="card-icon info">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="card-title">Total Patients</h3>
                    <div class="card-value"><?php echo $patient_count; ?></div>
                    <p class="card-subtitle">Registered patients</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Recent Activity Section -->
    <div class="dashboard-content">
        <div class="dashboard-row">
            <!-- Recent Receipts -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-receipt"></i>
                        Recent Receipts
                    </h2>
                    <a href="modules/financial.php" class="btn btn-outline">View All</a>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_receipts)): ?>
                                <?php foreach ($recent_receipts as $receipt): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($receipt['invoice_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($receipt['patient_name'] ?? 'Walk-in'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($receipt['invoice_date'])); ?></td>
                                    <td><strong>RM <?php echo number_format($receipt['total_amount'], 2); ?></strong></td>
                                    <td><span class="payment-badge"><?php echo htmlspecialchars($receipt['payment_method']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No receipts found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="dashboard-row">
            <!-- Top Services -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tooth"></i>
                        Popular Services
                    </h2>
                </div>
                
                <div class="services-stats">
                    <?php if (!empty($top_services)): ?>
                        <?php foreach ($top_services as $service): ?>
                        <div class="service-stat-item">
                            <div class="service-info">
                                <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                <p><?php echo $service['usage_count']; ?> times used</p>
                            </div>
                            <div class="service-amount">
                                <strong>RM <?php echo number_format($service['total_amount'], 2); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No service data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>