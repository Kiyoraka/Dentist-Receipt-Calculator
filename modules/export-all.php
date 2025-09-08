<?php
/**
 * Export All - Two Page Report  
 * Page 1: Pie Chart with Financial Summary
 * Page 2: Individual Visit Records Table (Landscape)
 */

require_once '../config/database.php';
require_once '../config/config.php';

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$filter_month = $_GET['filter_month'] ?? '';
$filter_year = $_GET['filter_year'] ?? '';

// Get all individual visit records with financial data
try {
    $sql = "
        SELECT 
            p.*,
            r.id as receipt_id,
            r.invoice_number,
            r.terminal_invoice_number,
            r.total_amount,
            r.doctor_fee,
            r.clinic_fee,
            r.invoice_date,
            r.created_at as visit_date,
            1 as receipt_count,
            COALESCE(r.total_amount, 0) as total_spent,
            COALESCE(r.doctor_fee, 0) as total_doctor_fee,
            COALESCE(r.clinic_fee, 0) as total_clinic_fee,
            r.created_at as last_visit
        FROM patients p 
        INNER JOIN receipts r ON p.id = r.patient_id 
    ";
    
    $params = [];
    $where_conditions = [];
    
    // Add date filtering if specified
    if ($filter_month && $filter_year) {
        $where_conditions[] = "DATE_FORMAT(r.invoice_date, '%m') = ? AND DATE_FORMAT(r.invoice_date, '%Y') = ?";
        $params = array_merge($params, [$filter_month, $filter_year]);
    } elseif ($filter_month) {
        $where_conditions[] = "DATE_FORMAT(r.invoice_date, '%m') = ?";
        $params[] = $filter_month;
    } elseif ($filter_year) {
        $where_conditions[] = "DATE_FORMAT(r.invoice_date, '%Y') = ?";
        $params[] = $filter_year;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY r.created_at DESC, p.name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals for pie chart
    $total_doctor_fees = array_sum(array_column($patients, 'total_doctor_fee'));
    $total_clinic_fees = array_sum(array_column($patients, 'total_clinic_fee'));
    $total_amount = array_sum(array_column($patients, 'total_spent'));
    $total_visits = count($patients);
    
} catch (Exception $e) {
    $patients = [];
    $total_doctor_fees = 0;
    $total_clinic_fees = 0;
    $total_amount = 0;
    $total_visits = 0;
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dental Practice Report - <?php echo date('Y-m-d'); ?></title>
    <meta charset="UTF-8">
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }
        
        /* Print/PDF Styles - Landscape */
        @media print {
            body { 
                padding: 0; 
                margin: 0;
            }
            .no-print { 
                display: none !important; 
            }
            .page-break { 
                page-break-before: always; 
            }
            @page {
                size: landscape;
                margin: 20mm;
            }
            
            /* Force table header colors in print */
            .patients-table th {
                background: #2563eb !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Ensure alternating row colors in print */
            .patients-table tr:nth-child(even) {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* Page 1 - Portrait for Pie Chart */
        .page-1 {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        /* Report Header */
        .report-header {
            margin-bottom: 40px;
        }
        
        .report-header h1 {
            color: #2563eb;
            font-size: 36px;
            margin: 0;
        }
        
        .report-header h2 {
            color: #666;
            font-size: 24px;
            margin: 10px 0;
        }
        
        .report-header p {
            color: #888;
            font-size: 16px;
            margin: 5px 0;
        }
        
        /* Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 40px 0;
            max-width: 800px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #f8f9ff 0%, #e0e7ff 100%);
            border: 2px solid #2563eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 120px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            margin-top: auto;
        }
        
        /* Pie Chart */
        .chart-container {
            margin: 40px 0;
        }
        
        .chart-canvas {
            width: 500px;
            height: 500px;
            margin: 0 auto;
        }
        
        /* Page 2 - Landscape for Table */
        .page-2 {
            min-height: 100vh;
        }
        
        .table-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .table-header h2 {
            color: #2563eb;
            font-size: 28px;
            margin: 0;
        }
        
        /* Patient Table - Landscape Optimized */
        .patients-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 20px 0;
        }
        
        .patients-table th {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            font-weight: bold;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid #1d4ed8;
        }
        
        .patients-table td {
            padding: 10px 8px;
            text-align: center;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        
        .patients-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .patients-table tr:hover {
            background-color: #f0f9ff;
        }
        
        /* Column Widths for Landscape */
        .col-id { width: 6%; }
        .col-name { width: 22%; text-align: left !important; font-weight: bold; color: #2563eb; }
        .col-visitdate { width: 13%; }
        .col-receipt { width: 12%; }
        .col-terminal { width: 12%; }
        .col-clinic { width: 12%; font-weight: bold; color: #dc2626; }
        .col-doctor { width: 12%; font-weight: bold; color: #059669; }
        .col-total { width: 13%; font-weight: bold; color: #2563eb; }
        
        /* Action Buttons */
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 4px 8px rgba(107, 114, 128, 0.3);
        }
        
        /* Summary Footer */
        .summary-footer {
            margin-top: 30px;
            text-align: center;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 8px;
            border: 1px solid #e0e7ff;
        }
        
        .summary-footer h3 {
            color: #2563eb;
            margin: 0 0 10px 0;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body>
    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn">
            <i class="fas fa-print"></i> 🖨️ Print/Save PDF
        </button>
        <a href="patients.php" class="btn btn-secondary">
            ← Back to Patients
        </a>
    </div>

    <!-- PAGE 1: PIE CHART -->
    <div class="page-1">
        <div class="report-header">
            <h1>🦷 CANINEHUB SDN BHD</h1>
            <h2>
                <?php if ($filter_month && $filter_year): ?>
                    Individual Visit Records Report - <?php echo date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)); ?>
                <?php elseif ($filter_month): ?>
                    Individual Visit Records Report - <?php echo date('F', mktime(0, 0, 0, $filter_month, 1)); ?> (All Years)
                <?php elseif ($filter_year): ?>
                    Individual Visit Records Report - <?php echo $filter_year; ?>
                <?php else: ?>
                    Individual Visit Records Report - All Time
                <?php endif; ?>
            </h2>
            <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Total Visit Records: <?php echo $total_visits; ?></p>
        </div>

        <div class="summary-stats">
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_clinic_fees, 2); ?></div>
                <div class="stat-label">Total Clinic Fees</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_doctor_fees, 2); ?></div>
                <div class="stat-label">Total Doctor Fees</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value"><?php echo $total_visits; ?></div>
                <div class="stat-label">Total Visits</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_amount, 2); ?></div>
                <div class="stat-label">Grand Total Revenue</div>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="feeChart" class="chart-canvas"></canvas>
        </div>
    </div>

    <!-- PAGE 2: PATIENT TABLE (LANDSCAPE) -->
    <div class="page-break page-2">
        <div class="table-header">
            <h2>🦷 CANINEHUB SDN BHD - Patient Management Report</h2>
            <p>Complete patient data with financial breakdown - Generated <?php echo date('F j, Y'); ?></p>
        </div>

        <?php if (!empty($patients)): ?>
            <table class="patients-table">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-name">Patient Name</th>
                        <th class="col-visitdate">Invoice Date</th>
                        <th class="col-receipt">Invoice Number</th>
                        <th class="col-terminal">Terminal Invoice</th>
                        <th class="col-clinic">Clinic Fee</th>
                        <th class="col-doctor">Doctor Fee</th>
                        <th class="col-total">Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $index => $patient): ?>
                        <tr>
                            <td class="col-id"><?php echo $index + 1; ?></td>
                            <td class="col-name">
                                <i class="fas fa-user-circle" style="margin-right: 5px; color: #2563eb;"></i>
                                <?php echo htmlspecialchars($patient['name']); ?>
                            </td>
                            <td class="col-visitdate"><?php echo date('M j, Y', strtotime($patient['invoice_date'])); ?></td>
                            <td class="col-receipt"><?php echo htmlspecialchars($patient['invoice_number']); ?></td>
                            <td class="col-terminal"><?php echo htmlspecialchars($patient['terminal_invoice_number'] ?? 'N/A'); ?></td>
                            <td class="col-clinic">RM <?php echo number_format($patient['total_clinic_fee'], 2); ?></td>
                            <td class="col-doctor">RM <?php echo number_format($patient['total_doctor_fee'], 2); ?></td>
                            <td class="col-total">RM <?php echo number_format($patient['total_spent'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary-footer">
                <h3>Summary Statistics</h3>
                <p>
                    <strong><?php echo count($patients); ?></strong> total patients | 
                    <strong><?php echo $total_visits; ?></strong> total visits | 
                    <strong>RM <?php echo number_format($total_amount, 2); ?></strong> total revenue
                </p>
            </div>

        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <h3>No patient data found</h3>
                <p>No patient records available for export.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Pie Chart Generation
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('feeChart').getContext('2d');
            
            const clinicFees = <?php echo $total_clinic_fees; ?>;
            const doctorFees = <?php echo $total_doctor_fees; ?>;
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Clinic Fees', 'Doctor Fees'],
                    datasets: [{
                        data: [clinicFees, doctorFees],
                        backgroundColor: [
                            '#dc2626',  // Red for clinic fees
                            '#059669'   // Green for doctor fees
                        ],
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 30,
                                font: {
                                    size: 18
                                },
                                color: '#333',
                                usePointStyle: false,
                                boxWidth: 20,
                                boxHeight: 20
                            }
                        },
                        datalabels: {
                            color: 'white',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            formatter: function(value, context) {
                                const total = clinicFees + doctorFees;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return percentage + '%';
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = clinicFees + doctorFees;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    return `${label}: RM ${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });

        // Print optimization
        window.addEventListener('beforeprint', function() {
            document.querySelector('.action-buttons').style.display = 'none';
        });
        
        window.addEventListener('afterprint', function() {
            document.querySelector('.action-buttons').style.display = 'block';
        });
    </script>
</body>
</html>