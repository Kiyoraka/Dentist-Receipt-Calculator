<?php
// Authentication protection
require_once '../includes/auth.php';
requireAuth();

require_once '../config/database.php';
require_once '../config/config.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Handle AJAX request for receipt update
if (isset($_POST['action']) && $_POST['action'] === 'update_receipt') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['receipt_id'])) {
        echo json_encode(['success' => false, 'message' => 'Receipt ID required']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Get or create patient
        $patient_id = null;
        if (!empty($_POST['customer_name'])) {
            // Check if patient exists
            $stmt = $conn->prepare("SELECT id FROM patients WHERE name = ? LIMIT 1");
            $stmt->execute([$_POST['customer_name']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient) {
                $patient_id = $patient['id'];
            } else {
                // Create new patient
                $stmt = $conn->prepare("INSERT INTO patients (name) VALUES (?)");
                $stmt->execute([$_POST['customer_name']]);
                $patient_id = $conn->lastInsertId();
            }
        }
        
        // Update receipt
        $stmt = $conn->prepare("UPDATE receipts SET patient_id = ?, invoice_number = ?, terminal_invoice_number = ?, invoice_date = ?, clinic_fee = ?, doctor_fee = ?, other_charges = ?, payment_method = ?, subtotal = ?, total_amount = ? WHERE id = ?");
        
        $stmt->execute([
            $patient_id,
            $_POST['invoice_number'],
            $_POST['terminal_invoice_number'] ?? '',
            $_POST['invoice_date'],
            $_POST['clinic_fee'],
            $_POST['doctor_fee'],
            $_POST['other_charges'] ?? 0,
            $_POST['payment_method'],
            $_POST['subtotal'],
            $_POST['total_amount'],
            $_POST['receipt_id']
        ]);
        
        if ($stmt->rowCount() > 0 || true) { // Allow service updates even if receipt data unchanged
            // Update services - delete existing and insert new ones
            $stmt = $conn->prepare("DELETE FROM receipt_services WHERE receipt_id = ?");
            $stmt->execute([$_POST['receipt_id']]);
            
            // Insert new services
            if (!empty($_POST['selected_services'])) {
                $services = json_decode($_POST['selected_services'], true);
                if ($services && is_array($services)) {
                    $stmt = $conn->prepare("INSERT INTO receipt_services (receipt_id, service_name) VALUES (?, ?)");
                    
                    foreach ($services as $service) {
                        $stmt->execute([
                            $_POST['receipt_id'],
                            $service['name']
                        ]);
                    }
                }
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Receipt updated successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Receipt not found or no changes made']);
        }
    } catch (PDOException $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request for receipt deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_receipt') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['receipt_id'])) {
        echo json_encode(['success' => false, 'message' => 'Receipt ID required']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Delete related services first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM receipt_services WHERE receipt_id = ?");
        $stmt->execute([$_POST['receipt_id']]);
        
        // Delete related charges
        $stmt = $conn->prepare("DELETE FROM receipt_charges WHERE receipt_id = ?");
        $stmt->execute([$_POST['receipt_id']]);
        
        // Delete the receipt
        $stmt = $conn->prepare("DELETE FROM receipts WHERE id = ?");
        $stmt->execute([$_POST['receipt_id']]);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Receipt deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Receipt not found']);
        }
    } catch (PDOException $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request for getting receipt details for editing
if (isset($_GET['action']) && $_GET['action'] === 'get_receipt') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['receipt_id'])) {
        echo json_encode(['error' => 'Receipt ID required']);
        exit;
    }
    
    try {
        // Get receipt details
        $stmt = $conn->prepare("
            SELECT r.*, p.name as patient_name 
            FROM receipts r 
            LEFT JOIN patients p ON r.patient_id = p.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$_GET['receipt_id']]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($receipt) {
            // Get services for this receipt
            $stmt = $conn->prepare("SELECT service_name FROM receipt_services WHERE receipt_id = ?");
            $stmt->execute([$_GET['receipt_id']]);
            $services = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get charges for this receipt
            $stmt = $conn->prepare("SELECT description, amount FROM receipt_charges WHERE receipt_id = ?");
            $stmt->execute([$_GET['receipt_id']]);
            $charges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $receipt['services'] = $services;
            $receipt['charges'] = $charges;
            
            echo json_encode($receipt);
        } else {
            echo json_encode(['error' => 'Receipt not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Regular page rendering starts here
$page_title = 'Receipt Management - Dental Practice Management';

// Add CSS for styling
$additional_css = ['../assets/css/charge-calculator.css'];

require_once '../includes/header.php';

// Pagination and filtering
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Build search query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(r.invoice_number LIKE ? OR r.terminal_invoice_number LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "r.invoice_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "r.invoice_date <= ?";
    $params[] = $date_to;
}

if (!empty($payment_method)) {
    $where_conditions[] = "r.payment_method = ?";
    $params[] = $payment_method;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM receipts r LEFT JOIN patients p ON r.patient_id = p.id WHERE $where_clause";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_receipts = $stmt->fetchColumn();
    $total_pages = ceil($total_receipts / $records_per_page);
    
    // Get receipts with patient info and services
    $sql = "
        SELECT 
            r.*,
            p.name as patient_name,
            GROUP_CONCAT(DISTINCT rs.service_name ORDER BY rs.service_name SEPARATOR ', ') as services
        FROM receipts r 
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN receipt_services rs ON r.id = rs.receipt_id
        WHERE $where_clause
        GROUP BY r.id 
        ORDER BY r.invoice_number DESC 
        LIMIT $records_per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique payment methods for filter
    $stmt = $conn->prepare("SELECT DISTINCT payment_method FROM receipts ORDER BY payment_method");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get dental services for edit modal
    $stmt = $conn->prepare("SELECT * FROM dental_services ORDER BY service_name");
    $stmt->execute();
    $dental_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>

<?php include '../includes/sidebar.php'; ?>

<div class="content-header">
    <h1 class="content-title">
        <i class="fas fa-receipt"></i>
        Receipt Management
    </h1>
    <p class="content-subtitle">Edit, delete, and manage individual receipts</p>
</div>

<!-- Search and Filter Section -->
<div class="filter-section" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <form method="GET" class="filter-form">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
            <!-- Search Input -->
            <div>
                <label for="search" style="display: block; margin-bottom: 5px; font-weight: 600; color: #2563eb;">
                    <i class="fas fa-search"></i> Search
                </label>
                <input type="text" 
                       id="search" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Invoice number, patient name..."
                       style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
            </div>
            
            <!-- Date From -->
            <div>
                <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: 600; color: #2563eb;">
                    <i class="fas fa-calendar"></i> From Date
                </label>
                <input type="date" 
                       id="date_from" 
                       name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>"
                       style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
            </div>
            
            <!-- Date To -->
            <div>
                <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: 600; color: #2563eb;">
                    <i class="fas fa-calendar"></i> To Date
                </label>
                <input type="date" 
                       id="date_to" 
                       name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>"
                       style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
            </div>
            
            <!-- Payment Method Filter -->
            <div>
                <label for="payment_method" style="display: block; margin-bottom: 5px; font-weight: 600; color: #2563eb;">
                    <i class="fas fa-credit-card"></i> Payment Method
                </label>
                <select id="payment_method" 
                        name="payment_method"
                        style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                    <option value="">All Methods</option>
                    <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo htmlspecialchars($method); ?>" 
                                <?php echo ($payment_method === $method) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($method); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div style="display: flex; gap: 10px;">
                <button type="submit" 
                        style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; min-width: 100px;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?" 
                   style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block; text-align: center; min-width: 100px; box-sizing: border-box;">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </div>
    </form>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Receipts Table -->
<div class="table-container" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div style="padding: 20px; border-bottom: 2px solid #e5e7eb; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);">
        <h2 style="color: white; margin: 0; font-size: 18px; font-weight: 600;">
            <i class="fas fa-list"></i>
            Receipt List (<?php echo number_format($total_receipts); ?> total)
        </h2>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="patients-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e5e7eb;">
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Invoice #</th>
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Terminal Invoice</th>
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Patient</th>
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Date</th>
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Services</th>
                    <th style="padding: 15px; text-align: right; font-weight: 600; color: #374151;">Total</th>
                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151;">Payment</th>
                    <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receipts)): ?>
                    <tr>
                        <td colspan="8" style="padding: 30px; text-align: center; color: #6b7280; font-style: italic;">
                            <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.3;"></i>
                            No receipts found matching your criteria
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($receipts as $receipt): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 15px;">
                                <span style="font-weight: 600; color: #2563eb;">
                                    <?php echo htmlspecialchars($receipt['invoice_number']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <?php if (!empty($receipt['terminal_invoice_number'])): ?>
                                    <span style="color: #7c3aed; font-weight: 500;">
                                        <?php echo htmlspecialchars($receipt['terminal_invoice_number']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px;">
                                <span style="font-weight: 500;">
                                    <?php echo htmlspecialchars($receipt['patient_name'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <?php echo date('M d, Y', strtotime($receipt['invoice_date'])); ?>
                            </td>
                            <td style="padding: 15px; max-width: 200px;">
                                <span style="font-size: 13px; color: #4b5563;">
                                    <?php echo htmlspecialchars($receipt['services'] ?? 'No services'); ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <span style="font-weight: 600; color: #059669; font-size: 16px;">
                                    RM <?php echo number_format($receipt['total_amount'], 2); ?>
                                </span>
                            </td>
                            <td style="padding: 15px;">
                                <span class="payment-badge" style="background: #dbeafe; color: #2563eb; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">
                                    <?php echo htmlspecialchars($receipt['payment_method']); ?>
                                </span>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <div class="action-buttons-row">
                                    <button type="button" class="btn-action btn-view" onclick="viewReceipt(<?php echo $receipt['id']; ?>)" title="View Receipt">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-print" onclick="downloadReceiptPDF(<?php echo $receipt['id']; ?>)" title="Download PDF">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-edit" onclick="editReceipt(<?php echo $receipt['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="deleteReceipt(<?php echo $receipt['id']; ?>, '<?php echo htmlspecialchars($receipt['invoice_number']); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f9fafb; border-top: 2px solid #e5e7eb; margin-top: -1px;">
        <div style="color: #6b7280; font-size: 14px;">
            <?php if($total_receipts > 0): ?>
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_receipts); ?></strong> of <strong><?php echo $total_receipts; ?></strong> receipts
            <?php else: ?>
                No receipts to display
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php 
            // Build query parameters for pagination links
            $query_params = [];
            if (!empty($search)) $query_params[] = 'search=' . urlencode($search);
            if (!empty($date_from)) $query_params[] = 'date_from=' . urlencode($date_from);
            if (!empty($date_to)) $query_params[] = 'date_to=' . urlencode($date_to);
            if (!empty($payment_method)) $query_params[] = 'payment_method=' . urlencode($payment_method);
            $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
            ?>
            
            <?php if ($page > 1): ?>
                <a href="?page=1<?php echo $query_string; ?>" 
                   style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>" 
                   style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                    <i class="fas fa-angle-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php
            // Calculate page range to display
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_string; ?>" 
                   style="padding: 8px 12px; border: 1px solid <?php echo $i == $page ? '#2563eb' : '#e5e7eb'; ?>; 
                          border-radius: 6px; color: <?php echo $i == $page ? 'white' : '#374151'; ?>; 
                          background: <?php echo $i == $page ? '#2563eb' : 'white'; ?>; text-decoration: none; font-weight: 500;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>" 
                   style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                    Next <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>" 
                   style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Receipt Modal -->
<div id="editReceiptModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto;">
    <div class="modal-content" style="background: white; padding: 0; border-radius: 10px; max-width: 800px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3); margin: 20px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 20px;">
                <i class="fas fa-edit" style="margin-right: 10px;"></i>
                Edit Receipt
            </h3>
            <button type="button" onclick="hideEditModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; line-height: 1;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editReceiptForm" style="padding: 20px;">
            <input type="hidden" id="editReceiptId" name="receipt_id">
            
            <!-- Invoice Details Section -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #2563eb; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                    <i class="fas fa-file-invoice"></i> Invoice Details
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Invoice Date:</label>
                        <input type="date" id="editInvoiceDate" name="invoice_date" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Invoice Number:</label>
                        <input type="text" id="editInvoiceNumber" name="invoice_number" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Terminal Invoice:</label>
                        <input type="text" id="editTerminalInvoice" name="terminal_invoice_number" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Patient Name:</label>
                    <input type="text" id="editPatientName" name="patient_name" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <!-- Financial Details Section -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #2563eb; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                    <i class="fas fa-calculator"></i> Financial Details
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Doctor Fee:</label>
                        <input type="number" id="editDoctorFee" name="doctor_fee" step="0.01" min="0" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Clinic Fee:</label>
                        <input type="number" id="editClinicFee" name="clinic_fee" step="0.01" min="0" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Other Charges:</label>
                        <input type="number" id="editOtherCharges" name="other_charges" step="0.01" min="0" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
            </div>
            
            <!-- Payment Details Section -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #2563eb; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                    <i class="fas fa-credit-card"></i> Payment Details
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Payment Method:</label>
                        <select id="editPaymentMethod" name="payment_method" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Mastercard">Mastercard</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Subtotal:</label>
                        <input type="number" id="editSubtotal" name="subtotal" step="0.01" min="0" readonly style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; background: #f9fafb;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Total Amount:</label>
                        <input type="number" id="editTotalAmount" name="total_amount" step="0.01" min="0" required style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; font-weight: 600; color: #059669;">
                    </div>
                </div>
            </div>
            
            <!-- Services Section -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #2563eb; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                    <i class="fas fa-tooth"></i> Services & Charges Management
                </h4>
                
                <!-- Add Service Section -->
                <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: end;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Add Service:</label>
                        <select id="editServiceSelect" style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                            <option value="">Select a service to add</option>
                            <?php foreach ($dental_services as $service): ?>
                                <option value="<?php echo $service['percentage']; ?>" 
                                        data-service="<?php echo htmlspecialchars($service['service_name']); ?>">
                                    <?php 
                                    if (floatval($service['percentage']) == 0) {
                                        echo htmlspecialchars($service['service_name']) . ' (0% Doctor Fee)';
                                    } else {
                                        echo htmlspecialchars($service['service_name']) . ' (' . $service['percentage'] . '% Doctor Fee)';
                                    }
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Charge Amount:</label>
                        <input type="number" id="editServiceAmount" min="0" step="0.01" placeholder="0.00" 
                               style="width: 120px; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                    </div>
                    <button type="button" onclick="addServiceToEdit()" 
                            style="background: #2563eb; color: white; border: none; padding: 10px 16px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                
                <!-- Selected Services Display -->
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #374151;">Selected Services:</label>
                    <div id="editSelectedServices" style="background: #f8fafc; padding: 15px; border-radius: 6px; border: 2px solid #e5e7eb; min-height: 60px;">
                        <em style="color: #6b7280;">No services selected</em>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-footer" style="padding: 20px; border-top: 1px solid #e5e7eb; background: #f8fafc; border-radius: 0 0 10px 10px; display: flex; justify-content: end; gap: 15px;">
            <button type="button" onclick="hideEditModal()" style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" onclick="saveEditedReceipt()" style="background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal hidden">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
            <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
            <button type="button" class="modal-close" onclick="hideDeleteModal()" style="color: white;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px; text-align: center;">
            <i class="fas fa-receipt" style="font-size: 3em; color: #ef4444; margin-bottom: 20px;"></i>
            <p style="font-size: 16px; margin-bottom: 10px;">Are you sure you want to delete receipt:</p>
            <h3 id="deleteInvoiceNumber" style="color: #1e293b; margin: 10px 0;"></h3>
            <div class="delete-receipt-info" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin: 16px 0; border: 1px solid #e2e8f0;">
                <p id="deleteReceiptDetails" style="color: #64748b; margin: 0;">Receipt information will be loaded here</p>
            </div>
            <p style="color: #ef4444; font-weight: 500; margin-top: 15px;">
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
            </p>
            <p style="color: #64748b; font-size: 14px; margin-top: 10px;">
                All associated services and charges will be permanently removed.
            </p>
        </div>
        <div class="modal-actions" style="justify-content: center; gap: 10px; padding: 20px; background: #f9fafb;">
            <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                <i class="fas fa-trash"></i> Delete Receipt
            </button>
        </div>
    </div>
</div>

<script>
let currentReceiptId = null;
let editModalServices = [];

// Dental services data for percentage lookup
const dentalServicesData = <?php echo json_encode(array_column($dental_services, 'percentage', 'service_name')); ?>;

function getServicePercentage(serviceName) {
    return dentalServicesData[serviceName] || 0;
}

// Service management functions for edit modal
function addServiceToEdit() {
    const serviceSelect = document.getElementById('editServiceSelect');
    const amountInput = document.getElementById('editServiceAmount');
    
    if (!serviceSelect.value || !amountInput.value) {
        showToast('Please select a service and enter an amount', 'error');
        return;
    }
    
    const serviceName = serviceSelect.options[serviceSelect.selectedIndex].dataset.service;
    const percentage = parseFloat(serviceSelect.value);
    const amount = parseFloat(amountInput.value);
    
    // Check if service already exists
    if (editModalServices.some(s => s.name === serviceName)) {
        showToast('Service already added', 'error');
        return;
    }
    
    // Calculate doctor and clinic fees
    const doctorFee = (amount * percentage) / 100;
    const clinicFee = amount - doctorFee;
    
    // Add to services array
    editModalServices.push({
        name: serviceName,
        percentage: percentage,
        amount: amount,
        doctorFee: doctorFee,
        clinicFee: clinicFee
    });
    
    // Reset form
    serviceSelect.value = '';
    amountInput.value = '';
    
    // Update display
    updateEditServicesDisplay();
    calculateEditTotals();
}

function removeServiceFromEdit(serviceName) {
    editModalServices = editModalServices.filter(s => s.name !== serviceName);
    updateEditServicesDisplay();
    calculateEditTotals();
}

function updateEditServicesDisplay() {
    const container = document.getElementById('editSelectedServices');
    
    if (editModalServices.length === 0) {
        container.innerHTML = '<em style="color: #6b7280;">No services selected</em>';
        return;
    }
    
    let html = '<div style="display: grid; gap: 8px;">';
    
    editModalServices.forEach(service => {
        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">${service.name}</div>
                    <div style="font-size: 12px; color: #6b7280;">
                        Doctor: RM ${service.doctorFee.toFixed(2)} | Clinic: RM ${service.clinicFee.toFixed(2)} | Total: RM ${service.amount.toFixed(2)}
                    </div>
                </div>
                <button type="button" onclick="removeServiceFromEdit('${service.name.replace(/'/g, "\\'")}')" 
                        style="background: #dc2626; color: white; border: none; padding: 6px 8px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function calculateEditTotals() {
    const totalDoctorFee = editModalServices.reduce((sum, s) => sum + s.doctorFee, 0);
    const totalClinicFee = editModalServices.reduce((sum, s) => sum + s.clinicFee, 0);
    const totalAmount = totalDoctorFee + totalClinicFee;
    
    // Update form fields
    document.getElementById('editDoctorFee').value = totalDoctorFee.toFixed(2);
    document.getElementById('editClinicFee').value = totalClinicFee.toFixed(2);
    document.getElementById('editSubtotal').value = totalAmount.toFixed(2);
    document.getElementById('editTotalAmount').value = totalAmount.toFixed(2);
}

function editReceipt(receiptId) {
    // Fetch receipt data and show modal
    fetch('receipts.php?action=get_receipt&receipt_id=' + receiptId)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        // Populate modal form with receipt data
        document.getElementById('editReceiptId').value = receiptId;
        document.getElementById('editInvoiceDate').value = data.invoice_date;
        document.getElementById('editInvoiceNumber').value = data.invoice_number;
        document.getElementById('editTerminalInvoice').value = data.terminal_invoice_number || '';
        document.getElementById('editPatientName').value = data.patient_name || '';
        document.getElementById('editDoctorFee').value = data.doctor_fee;
        document.getElementById('editClinicFee').value = data.clinic_fee;
        document.getElementById('editOtherCharges').value = data.other_charges || '0';
        document.getElementById('editPaymentMethod').value = data.payment_method;
        document.getElementById('editSubtotal').value = data.subtotal;
        document.getElementById('editTotalAmount').value = data.total_amount;
        
        // Reset and populate services
        editModalServices = [];
        
        if (data.services && data.services.length > 0) {
            // Calculate individual service amounts based on total fees and service percentages
            const totalDoctorFee = parseFloat(data.doctor_fee);
            const totalClinicFee = parseFloat(data.clinic_fee);
            const totalAmount = totalDoctorFee + totalClinicFee;
            
            // For simplicity, divide total amount equally among services (or you can store individual amounts in database)
            const amountPerService = totalAmount / data.services.length;
            
            data.services.forEach(serviceName => {
                // Find service percentage from dental services data
                const servicePercentage = getServicePercentage(serviceName);
                const doctorFee = (amountPerService * servicePercentage) / 100;
                const clinicFee = amountPerService - doctorFee;
                
                editModalServices.push({
                    name: serviceName,
                    percentage: servicePercentage,
                    amount: amountPerService,
                    doctorFee: doctorFee,
                    clinicFee: clinicFee
                });
            });
            
            updateEditServicesDisplay();
        } else {
            updateEditServicesDisplay();
        }
        
        // Show modal
        document.getElementById('editReceiptModal').style.display = 'flex';
    })
    .catch(error => {
        showToast('Error loading receipt data', 'error');
        console.error('Error:', error);
    });
}

function deleteReceipt(receiptId, invoiceNumber) {
    currentReceiptId = receiptId;
    
    // Fetch receipt details for the modal
    fetch('receipts.php?action=get_receipt&receipt_id=' + receiptId)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        // Populate modal with receipt information
        document.getElementById('deleteInvoiceNumber').textContent = data.invoice_number;
        
        // Create receipt details display
        const receiptInfo = `
            <div style="text-align: left;">
                <p style="margin: 8px 0;"><strong>Patient:</strong> ${data.patient_name || 'Unknown'}</p>
                <p style="margin: 8px 0;"><strong>Date:</strong> ${new Date(data.invoice_date).toLocaleDateString()}</p>
                <p style="margin: 8px 0;"><strong>Total Amount:</strong> RM ${parseFloat(data.total_amount).toFixed(2)}</p>
                <p style="margin: 8px 0;"><strong>Payment Method:</strong> ${data.payment_method}</p>
                ${data.services && data.services.length > 0 ? 
                    `<p style="margin: 8px 0;"><strong>Services:</strong> ${data.services.join(', ')}</p>` : 
                    ''
                }
            </div>
        `;
        
        document.getElementById('deleteReceiptDetails').innerHTML = receiptInfo;
        
        // Show modal using CSS class
        const modal = document.getElementById('deleteModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        
        // Set up confirm button click
        document.getElementById('confirmDeleteBtn').onclick = function() {
            confirmDelete(receiptId);
        };
    })
    .catch(error => {
        showToast('Error loading receipt details', 'error');
        console.error('Error:', error);
    });
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
    currentReceiptId = null;
}

function hideEditModal() {
    document.getElementById('editReceiptModal').style.display = 'none';
    // Clear the services array
    editModalServices = [];
    // Reset form
    document.getElementById('editReceiptForm').reset();
    updateEditServicesDisplay();
}

function saveEditedReceipt() {
    const form = document.getElementById('editReceiptForm');
    const formData = new FormData();
    
    // Add action and receipt ID
    formData.append('action', 'update_receipt');
    formData.append('receipt_id', document.getElementById('editReceiptId').value);
    
    // Add all form data
    formData.append('invoice_date', document.getElementById('editInvoiceDate').value);
    formData.append('invoice_number', document.getElementById('editInvoiceNumber').value);
    formData.append('terminal_invoice_number', document.getElementById('editTerminalInvoice').value);
    formData.append('customer_name', document.getElementById('editPatientName').value);
    formData.append('doctor_fee', document.getElementById('editDoctorFee').value);
    formData.append('clinic_fee', document.getElementById('editClinicFee').value);
    formData.append('other_charges', document.getElementById('editOtherCharges').value || '0');
    formData.append('payment_method', document.getElementById('editPaymentMethod').value);
    formData.append('subtotal', document.getElementById('editSubtotal').value);
    formData.append('total_amount', document.getElementById('editTotalAmount').value);
    
    // Add services data
    formData.append('selected_services', JSON.stringify(editModalServices));
    
    fetch('receipts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Receipt updated successfully', 'success');
            hideEditModal();
            // Reload page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Error updating receipt', 'error');
        }
    })
    .catch(error => {
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
    });
}

function confirmDelete(receiptId) {
    const formData = new FormData();
    formData.append('action', 'delete_receipt');
    formData.append('receipt_id', receiptId);
    
    fetch('receipts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideDeleteModal();
        
        if (data.success) {
            showToast(data.message, 'success');
            // Reload page to update the table
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Error deleting receipt', 'error');
        }
    })
    .catch(error => {
        hideDeleteModal();
        showToast('Network error occurred', 'error');
        console.error('Error:', error);
    });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    switch(type) {
        case 'success':
            toast.style.background = '#059669';
            toast.innerHTML = '<i class="fas fa-check-circle" style="margin-right: 8px;"></i>' + message;
            break;
        case 'error':
            toast.style.background = '#dc2626';
            toast.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>' + message;
            break;
        default:
            toast.style.background = '#2563eb';
            toast.innerHTML = '<i class="fas fa-info-circle" style="margin-right: 8px;"></i>' + message;
    }
    
    document.body.appendChild(toast);
    
    // Slide in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Close modals when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDeleteModal();
    }
});

document.getElementById('editReceiptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideEditModal();
    }
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const deleteModal = document.getElementById('deleteModal');
        const editModal = document.getElementById('editReceiptModal');
        
        if (!deleteModal.classList.contains('hidden')) {
            hideDeleteModal();
        }
        if (editModal.style.display === 'flex') {
            hideEditModal();
        }
    }
});

// View Receipt Function (Display Only)
function viewReceipt(receiptId) {
    // Fetch receipt data for viewing
    fetch('receipts.php?action=get_receipt&receipt_id=' + receiptId)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        // Generate and display receipt HTML
        displayReceiptData(data);
    })
    .catch(error => {
        showToast('Error loading receipt data', 'error');
        console.error('Error:', error);
    });
}

// Download Receipt as PDF Function
function downloadReceiptPDF(receiptId) {
    // Fetch receipt data for PDF generation
    fetch('receipts.php?action=get_receipt&receipt_id=' + receiptId)
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        // Generate PDF and download
        generateReceiptPDF(data);
    })
    .catch(error => {
        showToast('Error loading receipt data', 'error');
        console.error('Error:', error);
    });
}

function displayReceiptData(receiptData) {
    // Generate receipt HTML similar to financial.js print functionality
    const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Receipt - ${receiptData.invoice_number}</title>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f8fafc;
                }
                .receipt-container {
                    max-width: 400px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .receipt-header {
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    color: white;
                    padding: 25px;
                    text-align: center;
                }
                .clinic-name {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 8px;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                }
                .clinic-subtitle {
                    font-size: 14px;
                    opacity: 0.9;
                    margin-bottom: 15px;
                }
                .receipt-info {
                    background: rgba(255,255,255,0.15);
                    padding: 12px;
                    border-radius: 8px;
                    font-size: 13px;
                }
                .receipt-body {
                    padding: 25px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 12px;
                    padding: 8px 0;
                    border-bottom: 1px solid #f1f5f9;
                }
                .info-label {
                    font-weight: bold;
                    color: #374151;
                }
                .info-value {
                    color: #6b7280;
                }
                .services-section {
                    margin: 20px 0;
                    padding: 20px;
                    background: #f8fafc;
                    border-radius: 8px;
                    border-left: 4px solid #2563eb;
                }
                .services-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #2563eb;
                    margin-bottom: 12px;
                }
                .service-item {
                    padding: 6px 0;
                    color: #374151;
                    font-size: 14px;
                }
                .totals-section {
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 2px solid #e5e7eb;
                }
                .total-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                .total-row.final {
                    font-weight: bold;
                    font-size: 20px;
                    color: #059669;
                    padding: 15px;
                    background: #f0fdf4;
                    border-radius: 8px;
                    margin-top: 15px;
                }
                .payment-method {
                    text-align: center;
                    margin-top: 20px;
                    padding: 12px;
                    background: #dbeafe;
                    color: #2563eb;
                    border-radius: 6px;
                    font-weight: 500;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 30px;
                    padding: 20px;
                    border-top: 1px solid #e5e7eb;
                    color: #6b7280;
                    font-size: 12px;
                }
                @media print {
                    body { background: white; padding: 0; }
                    .receipt-container { box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="clinic-name">🦷 Dental Practice</div>
                    <div class="clinic-subtitle">Professional Dental Care Services</div>
                    <div class="receipt-info">
                        <div><strong>Invoice:</strong> ${receiptData.invoice_number}</div>
                        <div><strong>Date:</strong> ${new Date(receiptData.invoice_date).toLocaleDateString()}</div>
                    </div>
                </div>
                
                <div class="receipt-body">
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span class="info-value">${receiptData.patient_name || 'Walk-in Patient'}</span>
                    </div>
                    ${receiptData.terminal_invoice_number ? 
                        `<div class="info-row">
                            <span class="info-label">Terminal Invoice:</span>
                            <span class="info-value">${receiptData.terminal_invoice_number}</span>
                        </div>` : ''
                    }
                    
                    <div class="services-section">
                        <div class="services-title">📋 Services Provided</div>
                        ${receiptData.services && receiptData.services.length > 0 ? 
                            receiptData.services.map(service => 
                                `<div class="service-item">• ${service}</div>`
                            ).join('') : 
                            '<div class="service-item">• General Consultation</div>'
                        }
                    </div>
                    
                    <div class="totals-section">
                        <div class="total-row">
                            <span>Clinic Fee:</span>
                            <span>RM ${parseFloat(receiptData.clinic_fee).toFixed(2)}</span>
                        </div>
                        <div class="total-row">
                            <span>Doctor Fee:</span>
                            <span>RM ${parseFloat(receiptData.doctor_fee).toFixed(2)}</span>
                        </div>
                        ${receiptData.other_charges && parseFloat(receiptData.other_charges) > 0 ?
                            `<div class="total-row">
                                <span>Other Charges:</span>
                                <span>RM ${parseFloat(receiptData.other_charges).toFixed(2)}</span>
                            </div>` : ''
                        }
                        <div class="total-row final">
                            <span>Total Amount:</span>
                            <span>RM ${parseFloat(receiptData.total_amount).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="payment-method">
                        💳 Payment Method: ${receiptData.payment_method}
                    </div>
                </div>
                
                <div class="receipt-footer">
                    Thank you for choosing our dental practice!<br>
                    Generated on ${new Date().toLocaleString()}
                </div>
            </div>
        </body>
        </html>
    `;
    
    // Open print window
    const printWindow = window.open('', '_blank', 'width=500,height=700');
    printWindow.document.write(receiptHTML);
    printWindow.document.close();
    
    // Just display the receipt - no automatic print
    printWindow.focus();
}

function generateReceiptPDF(receiptData) {
    // Use jsPDF library to generate PDF
    // For now, we'll use the browser's print-to-PDF functionality
    const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Receipt - ${receiptData.invoice_number}</title>
            <style>
                @page { margin: 0.5in; }
                body {
                    font-family: 'Arial', sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: white;
                    color: black;
                }
                .receipt-container {
                    max-width: 500px;
                    margin: 0 auto;
                    background: white;
                    border: 2px solid #2563eb;
                    border-radius: 10px;
                    overflow: hidden;
                }
                .receipt-header {
                    background: #2563eb;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .clinic-name {
                    font-size: 22px;
                    font-weight: bold;
                    margin-bottom: 8px;
                }
                .clinic-subtitle {
                    font-size: 13px;
                    margin-bottom: 12px;
                }
                .receipt-info {
                    background: rgba(255,255,255,0.2);
                    padding: 10px;
                    border-radius: 6px;
                    font-size: 12px;
                }
                .receipt-body {
                    padding: 20px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 4px 0;
                    border-bottom: 1px solid #eee;
                    font-size: 14px;
                }
                .info-label {
                    font-weight: bold;
                }
                .services-section {
                    margin: 15px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    border-left: 3px solid #2563eb;
                }
                .services-title {
                    font-size: 14px;
                    font-weight: bold;
                    color: #2563eb;
                    margin-bottom: 8px;
                }
                .service-item {
                    padding: 3px 0;
                    font-size: 12px;
                }
                .totals-section {
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 2px solid #ddd;
                }
                .total-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 14px;
                }
                .total-row.final {
                    font-weight: bold;
                    font-size: 16px;
                    padding: 10px;
                    background: #f0f9ff;
                    border-radius: 6px;
                    margin-top: 10px;
                    border: 1px solid #2563eb;
                }
                .payment-method {
                    text-align: center;
                    margin-top: 15px;
                    padding: 8px;
                    background: #e0f2fe;
                    color: #0369a1;
                    border-radius: 4px;
                    font-weight: 500;
                    font-size: 12px;
                }
                .receipt-footer {
                    text-align: center;
                    margin-top: 20px;
                    padding: 15px;
                    border-top: 1px solid #ddd;
                    color: #666;
                    font-size: 10px;
                }
                @media print {
                    body { background: white !important; }
                    .receipt-container { border: 2px solid #2563eb !important; }
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="clinic-name">🦷 Dental Practice</div>
                    <div class="clinic-subtitle">Professional Dental Care Services</div>
                    <div class="receipt-info">
                        <div><strong>Invoice:</strong> ${receiptData.invoice_number}</div>
                        <div><strong>Date:</strong> ${new Date(receiptData.invoice_date).toLocaleDateString()}</div>
                    </div>
                </div>
                
                <div class="receipt-body">
                    <div class="info-row">
                        <span class="info-label">Patient Name:</span>
                        <span>${receiptData.patient_name || 'Walk-in Patient'}</span>
                    </div>
                    ${receiptData.terminal_invoice_number ? 
                        `<div class="info-row">
                            <span class="info-label">Terminal Invoice:</span>
                            <span>${receiptData.terminal_invoice_number}</span>
                        </div>` : ''
                    }
                    
                    <div class="services-section">
                        <div class="services-title">Services Provided</div>
                        ${receiptData.services && receiptData.services.length > 0 ? 
                            receiptData.services.map(service => 
                                `<div class="service-item">• ${service}</div>`
                            ).join('') : 
                            '<div class="service-item">• General Consultation</div>'
                        }
                    </div>
                    
                    <div class="totals-section">
                        <div class="total-row">
                            <span>Clinic Fee:</span>
                            <span>RM ${parseFloat(receiptData.clinic_fee).toFixed(2)}</span>
                        </div>
                        <div class="total-row">
                            <span>Doctor Fee:</span>
                            <span>RM ${parseFloat(receiptData.doctor_fee).toFixed(2)}</span>
                        </div>
                        ${receiptData.other_charges && parseFloat(receiptData.other_charges) > 0 ?
                            `<div class="total-row">
                                <span>Other Charges:</span>
                                <span>RM ${parseFloat(receiptData.other_charges).toFixed(2)}</span>
                            </div>` : ''
                        }
                        <div class="total-row final">
                            <span>Total Amount:</span>
                            <span>RM ${parseFloat(receiptData.total_amount).toFixed(2)}</span>
                        </div>
                    </div>
                    
                    <div class="payment-method">
                        Payment Method: ${receiptData.payment_method}
                    </div>
                </div>
                
                <div class="receipt-footer">
                    Thank you for choosing our dental practice!<br>
                    Generated on ${new Date().toLocaleString()}
                </div>
            </div>
            
            <script>
                // Auto-trigger print dialog for PDF save
                window.onload = function() {
                    window.print();
                    // Close window after print dialog
                    setTimeout(() => {
                        window.close();
                    }, 100);
                };
            </script>
        </body>
        </html>
    `;
    
    // Open print window optimized for PDF generation
    const printWindow = window.open('', '_blank', 'width=600,height=800');
    printWindow.document.write(receiptHTML);
    printWindow.document.close();
}
</script>

<?php require_once '../includes/footer.php'; ?>