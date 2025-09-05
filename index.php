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
    
    // Popular services for bar chart
    $stmt = $conn->prepare("SELECT service_name, COUNT(*) as usage_count FROM receipt_services GROUP BY service_name ORDER BY usage_count DESC LIMIT 10");
    $stmt->execute();
    $popular_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

    <!-- Popular Services Chart Section -->
    <div class="dashboard-content">
        <div class="dashboard-row">
            <!-- Popular Services Bar Chart -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Popular Services
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="servicesChart" style="max-height: 400px;"></canvas>
                </div>
                
                <?php if (empty($popular_services)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-chart-bar" style="font-size: 48px; opacity: 0.3; margin-bottom: 20px;"></i>
                        <p>No service data available</p>
                        <p style="font-size: 14px; color: #999;">Start using services to see analytics</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Popular Services Bar Chart
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($popular_services)): ?>
            const ctx = document.getElementById('servicesChart').getContext('2d');
            
            const servicesData = {
                labels: [<?php 
                    $labels = array_map(function($service) {
                        return '"' . htmlspecialchars($service['service_name']) . '"';
                    }, $popular_services);
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    label: 'Number of Services',
                    data: [<?php 
                        $counts = array_map(function($service) {
                            return $service['usage_count'];
                        }, $popular_services);
                        echo implode(', ', $counts);
                    ?>],
                    backgroundColor: [
                        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                        '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#6b7280'
                    ],
                    borderColor: [
                        '#1d4ed8', '#059669', '#d97706', '#dc2626', '#7c3aed',
                        '#0891b2', '#ea580c', '#65a30d', '#db2777', '#4b5563'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            };

            const config = {
                type: 'bar',
                data: servicesData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Service Usage Analytics',
                            font: {
                                size: 18,
                                weight: 'bold'
                            },
                            color: '#374151'
                        },
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' times used';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Services Used',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                color: '#6b7280'
                            },
                            ticks: {
                                stepSize: 1,
                                color: '#6b7280'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Service Types',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                color: '#6b7280'
                            },
                            ticks: {
                                color: '#6b7280',
                                maxRotation: 45,
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            };

            new Chart(ctx, config);
            <?php endif; ?>
        });
    </script>

<?php include 'includes/footer.php'; ?>