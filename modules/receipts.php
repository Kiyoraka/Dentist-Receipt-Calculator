<?php
// Authentication protection
require_once '../includes/auth.php';
requireAuth();

require_once '../config/database.php';
require_once '../config/config.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

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
    $total_pages = ceil($total_receipts / $limit);
    
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
        ORDER BY r.created_at DESC 
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique payment methods for filter
    $stmt = $conn->prepare("SELECT DISTINCT payment_method FROM receipts ORDER BY payment_method");
    $stmt->execute();
    $payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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
            <div>
                <button type="submit" 
                        style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-right: 10px;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?" 
                   style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-block;">
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
                                <button onclick="editReceipt(<?php echo $receipt['id']; ?>)" 
                                        style="background: #f59e0b; color: white; border: none; padding: 6px 12px; border-radius: 4px; margin-right: 5px; cursor: pointer; font-size: 12px;"
                                        title="Edit Receipt">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteReceipt(<?php echo $receipt['id']; ?>, '<?php echo htmlspecialchars($receipt['invoice_number']); ?>')" 
                                        style="background: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;"
                                        title="Delete Receipt">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="padding: 20px; border-top: 1px solid #e5e7eb; background: #f8fafc;">
            <div style="display: flex; justify-content: center; align-items: center; gap: 10px;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       style="padding: 8px 16px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <span style="margin: 0 15px; color: #6b7280; font-weight: 500;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       style="padding: 8px 16px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <div class="modal-header" style="margin-bottom: 20px; text-align: center;">
            <h3 style="color: #dc2626; margin: 0; font-size: 24px;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>
                Confirm Deletion
            </h3>
        </div>
        <div class="modal-body" style="margin-bottom: 30px; text-align: center;">
            <p style="margin: 0; color: #64748b; font-size: 16px; line-height: 1.6;">
                Are you sure you want to delete receipt <strong id="deleteInvoiceNumber"></strong>?
            </p>
            <p style="margin: 10px 0 0 0; color: #dc2626; font-size: 14px; font-weight: 500;">
                This action cannot be undone and will also delete all related services and charges.
            </p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: center; gap: 15px;">
            <button type="button" 
                    onclick="hideDeleteModal()" 
                    style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" 
                    id="confirmDeleteBtn"
                    style="background: #dc2626; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<script>
let currentReceiptId = null;

function editReceipt(receiptId) {
    // Redirect to financial management with edit parameter
    window.location.href = '../modules/financial.php?edit_receipt=' + receiptId;
}

function deleteReceipt(receiptId, invoiceNumber) {
    currentReceiptId = receiptId;
    document.getElementById('deleteInvoiceNumber').textContent = invoiceNumber;
    document.getElementById('deleteModal').style.display = 'flex';
    
    // Set up confirm button click
    document.getElementById('confirmDeleteBtn').onclick = function() {
        confirmDelete(receiptId);
    };
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    currentReceiptId = null;
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

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDeleteModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('deleteModal').style.display === 'flex') {
        hideDeleteModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>