<?php
/**
 * Export to Excel - Patient Visit Records
 * Generates Excel file with same column structure as export-all.php
 */

require_once '../config/database.php';
require_once '../config/config.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Dental_Practice_Report_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

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
    
    // Calculate totals
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
}
?>
<!DOCTYPE html>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            padding: 10px;
        }
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .bold {
            font-weight: bold;
        }
        .header {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .subheader {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        .summary {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .total-row {
            background-color: #e0e7ff;
            font-weight: bold;
        }
    </style>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Patient Report</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
</head>
<body>
    <!-- Report Header -->
    <div align="center">
        <h1 class="header">CANINEHUB SDN BHD REPORT</h1>
        <h2 class="subheader">
            <?php if ($filter_month && $filter_year): ?>
                Individual Visit Records - <?php echo date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)); ?>
            <?php elseif ($filter_month): ?>
                Individual Visit Records - <?php echo date('F', mktime(0, 0, 0, $filter_month, 1)); ?> (All Years)
            <?php elseif ($filter_year): ?>
                Individual Visit Records - <?php echo $filter_year; ?>
            <?php else: ?>
                Individual Visit Records - All Time
            <?php endif; ?>
        </h2>
        <p>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>Total Records: <?php echo $total_visits; ?></p>
    </div>
    
    <br>
    
    <!-- Summary Statistics -->
    <table style="width: 50%; margin: 0 auto 20px auto;">
        <tr class="summary">
            <td><strong>Total Clinic Fees:</strong></td>
            <td>RM <?php echo number_format($total_clinic_fees, 2); ?></td>
        </tr>
        <tr class="summary">
            <td><strong>Total Doctor Fees:</strong></td>
            <td>RM <?php echo number_format($total_doctor_fees, 2); ?></td>
        </tr>
        <tr class="summary">
            <td><strong>Total Visits:</strong></td>
            <td><?php echo $total_visits; ?></td>
        </tr>
        <tr class="summary">
            <td><strong>Grand Total Revenue:</strong></td>
            <td>RM <?php echo number_format($total_amount, 2); ?></td>
        </tr>
    </table>
    
    <br>
    
    <!-- Patient Data Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient Name</th>
                <th>Invoice Date</th>
                <th>Invoice Number</th>
                <th>Terminal Invoice</th>
                <th>Clinic Fee</th>
                <th>Doctor Fee</th>
                <th>Total Spent</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($patients)): ?>
                <?php foreach ($patients as $index => $patient): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td class="text-left bold"><?php echo htmlspecialchars($patient['name']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($patient['invoice_date'])); ?></td>
                        <td><?php echo htmlspecialchars($patient['invoice_number']); ?></td>
                        <td><?php echo htmlspecialchars($patient['terminal_invoice_number'] ?? 'N/A'); ?></td>
                        <td>RM <?php echo number_format($patient['total_clinic_fee'], 2); ?></td>
                        <td>RM <?php echo number_format($patient['total_doctor_fee'], 2); ?></td>
                        <td class="bold">RM <?php echo number_format($patient['total_spent'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Total Row -->
                <tr class="total-row">
                    <td colspan="5" class="text-left"><strong>TOTAL</strong></td>
                    <td><strong>RM <?php echo number_format($total_clinic_fees, 2); ?></strong></td>
                    <td><strong>RM <?php echo number_format($total_doctor_fees, 2); ?></strong></td>
                    <td><strong>RM <?php echo number_format($total_amount, 2); ?></strong></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="8">No patient data found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <br>
    
    <!-- Footer Summary -->
    <div align="center" style="margin-top: 20px;">
        <p><strong>Summary:</strong> <?php echo count($patients); ?> total patients | <?php echo $total_visits; ?> total visits | RM <?php echo number_format($total_amount, 2); ?> total revenue</p>
    </div>
</body>
</html>