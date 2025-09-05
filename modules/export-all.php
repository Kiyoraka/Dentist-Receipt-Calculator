<?php
/**
 * Export All Receipts - HTML/PDF Generation
 * Professional dental practice comprehensive report with pie chart and receipt formats
 * 
 * Features:
 * - Pie chart showing Clinic Fee vs Doctor Fee totals
 * - Individual receipts in new format (Date|Invoice, Name, Clinic Fee, Doctor Fee, Services bullets, Total)
 * - HTML display with PDF save functionality
 */

require_once '../config/database.php';

// Check if this is a PDF export request
$export_pdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';

// Database connection
$db = new Database();
$conn = $db->getConnection();

try {
    // Get all receipts with patient information
    $stmt = $conn->prepare("
        SELECT r.*, p.name as patient_name 
        FROM receipts r 
        LEFT JOIN patients p ON r.patient_id = p.id 
        ORDER BY r.invoice_date DESC, r.invoice_number DESC
    ");
    $stmt->execute();
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals for pie chart
    $total_clinic_fee = 0;
    $total_doctor_fee = 0;
    $total_other_charges = 0;
    $total_amount = 0;
    
    foreach ($receipts as $receipt) {
        $total_clinic_fee += $receipt['clinic_fee'];
        $total_doctor_fee += $receipt['doctor_fee'];
        $total_other_charges += $receipt['other_charges'];
        $total_amount += $receipt['total_amount'];
    }
    
    // Get services for each receipt
    foreach ($receipts as &$receipt) {
        $stmt = $conn->prepare("SELECT service_name FROM receipt_services WHERE receipt_id = ?");
        $stmt->execute([$receipt['id']]);
        $receipt['services'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get other charges for each receipt
        $stmt = $conn->prepare("SELECT description, amount FROM receipt_charges WHERE receipt_id = ?");
        $stmt->execute([$receipt['id']]);
        $receipt['charges'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Set headers for PDF if requested
if ($export_pdf) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="dental_receipts_' . date('Y-m-d_H-i-s') . '.pdf"');
    // Note: For actual PDF generation, you'd need a library like TCPDF or DOMPDF
    // For now, we'll output HTML that can be printed to PDF
    header('Content-Type: text/html');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>All Receipts Export - Dental Practice</title>
    <meta charset="UTF-8">
    <style>
        /* Professional Print/PDF Styles */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }
        
        /* Print-specific styles */
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        
        /* Header Styles */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }
        
        .report-header h1 {
            color: #2563eb;
            font-size: 28px;
            margin: 0;
        }
        
        .report-header p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        /* Summary Section */
        .summary-section {
            margin-bottom: 40px;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: #f8f9ff;
            border: 2px solid #2563eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            min-width: 150px;
            margin: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Pie Chart Container */
        .chart-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .chart-canvas {
            max-width: 400px;
            height: 400px;
            margin: 0 auto;
        }
        
        /* Receipt Styles - Matching financial.js exactly */
        .receipt {
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 30px auto; 
            padding: 20px; 
            line-height: 1.4;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .receipt .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .receipt .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        
        .receipt .header-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .receipt .customer-name {
            font-size: 18px;
            margin: 20px 0;
            font-weight: bold;
        }
        
        .receipt .fee-section {
            margin: 15px 0;
        }
        
        .receipt .fee-amount {
            font-size: 16px;
            font-weight: bold;
        }
        
        .receipt .services-section {
            margin: 10px 0 15px 0;
        }
        
        .receipt .total-section {
            margin: 20px 0;
            padding: 10px;
            border: 2px solid #2563eb;
            background-color: #f8f9ff;
        }
        
        .receipt .total-amount {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
        }
        
        .receipt .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        /* Action Buttons */
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="btn">🖨️ Print/PDF</button>
        <button onclick="generatePDF()" class="btn">💾 Save PDF</button>
        <a href="financial.php" class="btn btn-secondary">← Back to Financial</a>
    </div>

    <!-- Report Header -->
    <div class="report-header">
        <h1>🦷 DENTAL PRACTICE</h1>
        <h2>Comprehensive Receipts Report</h2>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>Total Receipts: <?php echo count($receipts); ?></p>
    </div>

    <!-- Summary Section with Stats and Pie Chart -->
    <div class="summary-section">
        <h3 style="text-align: center; color: #2563eb; margin-bottom: 20px;">Financial Summary</h3>
        
        <div class="summary-stats">
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_clinic_fee, 2); ?></div>
                <div class="stat-label">Total Clinic Fees</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_doctor_fee, 2); ?></div>
                <div class="stat-label">Total Doctor Fees</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_other_charges, 2); ?></div>
                <div class="stat-label">Other Charges</div>
            </div>
            
            <div class="stat-box">
                <div class="stat-value">RM <?php echo number_format($total_amount, 2); ?></div>
                <div class="stat-label">Grand Total</div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="chart-container">
            <h4 style="color: #2563eb;">Fee Distribution</h4>
            <canvas id="feeChart" class="chart-canvas"></canvas>
        </div>
    </div>

    <?php if (empty($receipts)): ?>
        <div style="text-align: center; padding: 50px; color: #666;">
            <h3>No receipts found</h3>
            <p>No receipt data available for export.</p>
        </div>
    <?php else: ?>
        <!-- Individual Receipts -->
        <div class="page-break"></div>
        <h3 style="text-align: center; color: #2563eb; margin: 30px 0;">Individual Receipts</h3>
        
        <?php foreach ($receipts as $index => $receipt): ?>
            <?php if ($index > 0): ?>
                <div class="page-break"></div>
            <?php endif; ?>
            
            <!-- Individual Receipt - Matching financial.js format exactly -->
            <div class="receipt">
                <div class="header">
                    <h1>🦷 DENTAL PRACTICE</h1>
                </div>
                
                <div class="header-row">
                    <div><?php echo date('M j, Y', strtotime($receipt['invoice_date'])); ?></div>
                    <div><?php echo htmlspecialchars($receipt['invoice_number']); ?></div>
                </div>
                
                <div class="customer-name">
                    <?php echo htmlspecialchars($receipt['patient_name'] ?: 'Walk-in Patient'); ?>
                </div>
                
                <div class="fee-section">
                    <div class="fee-amount">Clinic Fee: RM <?php echo number_format($receipt['clinic_fee'], 2); ?></div>
                </div>
                
                <div class="fee-section">
                    <div class="fee-amount">Doctor Fee: RM <?php echo number_format($receipt['doctor_fee'], 2); ?></div>
                    
                    <?php if (!empty($receipt['services'])): ?>
                        <div class="services-section">
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <?php foreach ($receipt['services'] as $service): ?>
                                    <li><?php echo htmlspecialchars($service); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($receipt['charges'])): ?>
                    <div style="margin: 15px 0;">
                        <strong>Other Charges: RM <?php echo number_format($receipt['other_charges'], 2); ?></strong>
                    </div>
                <?php endif; ?>
                
                <div class="total-section">
                    <div class="total-amount">Total Amount: RM <?php echo number_format($receipt['total_amount'], 2); ?></div>
                </div>
                
                <div class="footer">
                    <p>Payment Method: <?php echo htmlspecialchars($receipt['payment_method']); ?></p>
                    <p>Thank you for choosing our dental practice!</p>
                    <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Pie Chart Generation
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('feeChart').getContext('2d');
            
            const clinicFee = <?php echo $total_clinic_fee; ?>;
            const doctorFee = <?php echo $total_doctor_fee; ?>;
            const otherCharges = <?php echo $total_other_charges; ?>;
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Clinic Fees', 'Doctor Fees', 'Other Charges'],
                    datasets: [{
                        data: [clinicFee, doctorFee, otherCharges],
                        backgroundColor: [
                            '#2563eb',  // Blue for clinic fees
                            '#10b981',  // Green for doctor fees  
                            '#f59e0b'   // Orange for other charges
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = clinicFee + doctorFee + otherCharges;
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: RM ${value.toFixed(2)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
        
        // PDF Generation Function
        function generatePDF() {
            // For actual PDF generation, you'd integrate with a library like jsPDF or use server-side PDF generation
            // For now, we'll open the print dialog
            window.print();
        }
        
        // Print optimization
        window.addEventListener('beforeprint', function() {
            // Hide action buttons when printing
            document.querySelector('.action-buttons').style.display = 'none';
        });
        
        window.addEventListener('afterprint', function() {
            // Show action buttons after printing
            document.querySelector('.action-buttons').style.display = 'block';
        });
    </script>
</body>
</html>