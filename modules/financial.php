<?php
// Authentication protection
require_once '../includes/auth.php';
requireAuth();

$page_title = 'Financial Management - CASSIA DENTAL CARE Management';
require_once '../config/database.php';
require_once '../includes/header.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Check if we're in edit mode
$edit_mode = false;
$edit_receipt = null;
$edit_receipt_services = [];
$edit_receipt_charges = [];

if (isset($_GET['edit_receipt']) && !empty($_GET['edit_receipt'])) {
    $edit_mode = true;
    $edit_receipt_id = $_GET['edit_receipt'];
    
    try {
        // Get receipt details
        $stmt = $conn->prepare("
            SELECT r.*, p.name as patient_name 
            FROM receipts r 
            LEFT JOIN patients p ON r.patient_id = p.id 
            WHERE r.id = ?
        ");
        $stmt->execute([$edit_receipt_id]);
        $edit_receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_receipt) {
            // Get services for this receipt
            $stmt = $conn->prepare("SELECT service_name FROM receipt_services WHERE receipt_id = ?");
            $stmt->execute([$edit_receipt_id]);
            $edit_receipt_services = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get charges for this receipt
            $stmt = $conn->prepare("SELECT description, amount FROM receipt_charges WHERE receipt_id = ?");
            $stmt->execute([$edit_receipt_id]);
            $edit_receipt_charges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Receipt not found for editing.";
            $edit_mode = false;
        }
    } catch (PDOException $e) {
        $error_message = "Error loading receipt for editing: " . $e->getMessage();
        $edit_mode = false;
    }
}

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'clear_all_receipts') {
            // Begin transaction for safe deletion
            $conn->beginTransaction();
            
            // Delete all receipt services first (foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM receipt_services");
            $stmt->execute();
            
            // Delete all receipt charges
            $stmt = $conn->prepare("DELETE FROM receipt_charges");
            $stmt->execute();
            
            // Delete all receipts
            $stmt = $conn->prepare("DELETE FROM receipts");
            $stmt->execute();
            
            $conn->commit();
            $success_message = "All receipts cleared successfully!";
            
        } elseif ($_POST['action'] === 'save_receipt') {
            // Begin transaction
            $conn->beginTransaction();
            
            $is_update = isset($_POST['edit_receipt_id']) && !empty($_POST['edit_receipt_id']);
            
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
            
            if ($is_update) {
                // Update existing receipt
                $receipt_id = $_POST['edit_receipt_id'];
                
                $stmt = $conn->prepare("UPDATE receipts SET patient_id = ?, invoice_number = ?, terminal_invoice_number = ?, invoice_date = ?, clinic_fee = ?, doctor_fee = ?, other_charges = ?, payment_method = ?, payment_fee_percentage = ?, payment_fee_amount = ?, terminal_charge_percentage = ?, terminal_charge_amount = ?, subtotal = ?, total_amount = ? WHERE id = ?");
                
                $stmt->execute([
                    $patient_id,
                    $_POST['invoice_number'],
                    $_POST['terminal_invoice_number'] ?? '',
                    $_POST['invoice_date'],
                    $_POST['clinic_fee'],
                    $_POST['doctor_fee'],
                    $_POST['other_charges'],
                    $_POST['payment_method'],
                    $_POST['payment_fee_percentage'],
                    $_POST['payment_fee_amount'],
                    $_POST['terminal_charge_percentage'] ?? 0,
                    $_POST['terminal_charge_amount'] ?? 0,
                    $_POST['subtotal'],
                    $_POST['total_amount'],
                    $receipt_id
                ]);
                
                // Delete existing services and charges for this receipt
                $stmt = $conn->prepare("DELETE FROM receipt_services WHERE receipt_id = ?");
                $stmt->execute([$receipt_id]);
                
                $stmt = $conn->prepare("DELETE FROM receipt_charges WHERE receipt_id = ?");
                $stmt->execute([$receipt_id]);
                
            } else {
                // Insert new receipt
                $stmt = $conn->prepare("INSERT INTO receipts (patient_id, invoice_number, terminal_invoice_number, invoice_date, clinic_fee, doctor_fee, other_charges, payment_method, payment_fee_percentage, payment_fee_amount, terminal_charge_percentage, terminal_charge_amount, subtotal, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $patient_id,
                    $_POST['invoice_number'],
                    $_POST['terminal_invoice_number'] ?? '',
                    $_POST['invoice_date'],
                    $_POST['clinic_fee'],
                    $_POST['doctor_fee'],
                    $_POST['other_charges'],
                    $_POST['payment_method'],
                    $_POST['payment_fee_percentage'],
                    $_POST['payment_fee_amount'],
                    $_POST['terminal_charge_percentage'] ?? 0,
                    $_POST['terminal_charge_amount'] ?? 0,
                    $_POST['subtotal'],
                    $_POST['total_amount']
                ]);
                
                $receipt_id = $conn->lastInsertId();
            }
            
            // Insert services
            if (!empty($_POST['selected_services'])) {
                $services = json_decode($_POST['selected_services'], true);
                $stmt = $conn->prepare("INSERT INTO receipt_services (receipt_id, service_name) VALUES (?, ?)");
                
                foreach ($services as $service) {
                    $stmt->execute([
                        $receipt_id,
                        $service['name']
                    ]);
                }
            }
            
            // Insert other charges
            if (!empty($_POST['other_charges_list'])) {
                $charges = json_decode($_POST['other_charges_list'], true);
                $stmt = $conn->prepare("INSERT INTO receipt_charges (receipt_id, description, amount) VALUES (?, ?, ?)");
                
                foreach ($charges as $charge) {
                    $stmt->execute([
                        $receipt_id,
                        $charge['description'],
                        $charge['amount']
                    ]);
                }
            }
            
            $conn->commit();
            if ($is_update) {
                $success_message = "Receipt updated successfully! Invoice #: " . $_POST['invoice_number'];
            } else {
                $success_message = "Receipt saved successfully! Invoice #: " . $_POST['invoice_number'];
            }
            
            // Preserve form data for printing after successful save
            $saved_receipt_data = $_POST;
            
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error saving receipt: " . $e->getMessage();
    }
}

// Get recent receipts for history
try {
    $stmt = $conn->prepare("SELECT r.*, p.name as patient_name FROM receipts r LEFT JOIN patients p ON r.patient_id = p.id ORDER BY r.created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_receipts = [];
}

// Get dental services
try {
    $stmt = $conn->prepare("SELECT * FROM dental_services ORDER BY service_name");
    $stmt->execute();
    $dental_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dental_services = [];
}
?>

<?php include '../includes/sidebar.php'; ?>

    <div class="content-header">
        <h1 class="content-title">
            <i class="fas fa-calculator"></i>
            <?php if ($edit_mode): ?>
                Edit Receipt - Financial Management
            <?php else: ?>
                Financial Management
            <?php endif; ?>
        </h1>
        <p class="content-subtitle">
            <?php if ($edit_mode): ?>
                Editing receipt: <?php echo htmlspecialchars($edit_receipt['invoice_number']); ?>
                <a href="../modules/financial.php" style="margin-left: 15px; color: #2563eb; text-decoration: none;">
                    <i class="fas fa-plus"></i> Create New Receipt
                </a>
            <?php else: ?>
                Receipt calculator and financial tracking
            <?php endif; ?>
        </p>
    </div>


    <div class="financial-layout">
        <!-- Calculator Section -->
        <div class="calculator-section">
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-receipt"></i>
                        Receipt Calculator
                    </h2>
                </div>

                <form id="receipt-form" method="POST" class="receipt-form">
                    <input type="hidden" name="action" value="save_receipt">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="edit_receipt_id" value="<?php echo $edit_receipt['id']; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="charges_list" id="charges-list-data">
                    <input type="hidden" name="clinic_fee" id="clinic-fee-input">
                    <input type="hidden" name="doctor_fee" id="doctor-fee-input"> 
                    <input type="hidden" name="selected_services" id="selected-services-data">
                    <input type="hidden" name="other_charges_list" id="other-charges-data">
                    <input type="hidden" name="other_charges" id="other-charges-input">
                    <input type="hidden" name="payment_fee_percentage" id="payment-fee-percentage-input">
                    <input type="hidden" name="payment_fee_amount" id="payment-fee-amount-input">
                    <input type="hidden" name="subtotal" id="subtotal-input">
                    <input type="hidden" name="total_amount" id="total-amount-input">

                    <!-- Invoice Details -->
                    <div class="form-section">
                        <h3><i class="fas fa-file-invoice"></i> Invoice Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="invoice-date">Invoice Date:</label>
                                <input type="date" 
                                       id="invoice-date" 
                                       name="invoice_date" 
                                       required 
                                       value="<?php echo $edit_mode ? $edit_receipt['invoice_date'] : date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="invoice-number">Invoice Number:</label>
                                <input type="text" 
                                       id="invoice-number" 
                                       name="invoice_number" 
                                       placeholder="INV-<?php echo date('Ymd'); ?>-001" 
                                       required
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_receipt['invoice_number']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="terminal-invoice-number">Terminal Invoice Number:</label>
                                <input type="text" 
                                       id="terminal-invoice-number" 
                                       name="terminal_invoice_number" 
                                       placeholder="T-<?php echo date('Ymd'); ?>-001"
                                       value="<?php echo $edit_mode ? htmlspecialchars($edit_receipt['terminal_invoice_number'] ?? '') : ''; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customer-name">Customer Name:</label>
                            <input type="text" 
                                   id="customer-name" 
                                   name="customer_name" 
                                   placeholder="Patient Name" 
                                   required
                                   value="<?php echo $edit_mode ? htmlspecialchars($edit_receipt['patient_name'] ?? '') : ''; ?>">
                        </div>
                    </div>

                    <!-- Charge Calculator -->
                    <div class="form-section">
                        <h3><i class="fas fa-calculator"></i> Charge Calculator</h3>
                        <div class="calculator-input">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="charge-amount">Charge Amount (RM):</label>
                                    <input type="number" id="charge-amount" min="0" step="0.01" placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label for="service-select">Dental Service:</label>
                                    <select id="service-select">
                                        <option value="">Select Service</option>
                                        <?php foreach ($dental_services as $service): ?>
                                        <option value="<?php echo $service['percentage']; ?>" 
                                                data-service="<?php echo htmlspecialchars($service['service_name']); ?>">
                                            <?php 
                                            if (floatval($service['percentage']) == 0) {
                                                echo htmlspecialchars($service['service_name']);
                                            } else {
                                                echo htmlspecialchars($service['service_name']) . ' (' . $service['percentage'] . '% Doctor)';
                                            }
                                            ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="button" id="add-charge-btn" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Charge
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Charges List -->
                        <div id="charges-list" class="charges-container" style="display: none;">
                            <h4>Added Charges:</h4>
                            <div class="charges-table-wrapper">
                                <table class="charges-table" id="charges-table">
                                    <thead class="table-header">
                                        <tr>
                                            <th class="service-col">Service</th>
                                            <th class="charge-col">Charge</th>
                                            <th class="doctor-col">Doctor Fee</th>
                                            <th class="clinic-col">Clinic Fee</th>
                                            <th class="action-col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="charges-rows">
                                        <!-- Table rows will be added dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- Other Charges -->
                    <div class="form-section">
                        <h3><i class="fas fa-plus-circle"></i> Other Charges</h3>
                        <div id="other-charges-container">
                            <div class="charge-item">
                                <input type="text" placeholder="Description" class="charge-description">
                                <input type="number" placeholder="0.00" min="0" step="0.01" class="charge-amount">
                                <button type="button" class="btn-remove" onclick="removeOtherCharge(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" id="add-charge" class="btn btn-secondary btn-sm">
                            <i class="fas fa-plus"></i> Add Charge
                        </button>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <div class="payment-method-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                        </div>
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Cash" data-fee="0" checked>
                                <span><i class="fas fa-money-bill"></i> Cash (0%)</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Online" data-fee="0">
                                <span><i class="fas fa-globe"></i> Online (0%)</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Debit Card" data-fee="0.5">
                                <span><i class="fas fa-credit-card"></i> Debit Card (0.5%)</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Credit Card" data-fee="1.2">
                                <span><i class="fas fa-credit-card"></i> Credit Card (1.2%)</span>
                            </label>
                            <label class="payment-option" style="position: relative;">
                                <!-- Settings Button positioned above this option -->
                                <div style="position: absolute; top: -45px; right: 0; z-index: 10;">
                                    <button type="button" id="payment-settings-btn" class="btn-settings" title="Configure Payment Method Processing Fees" 
                                            style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important; 
                                                   color: white !important; 
                                                   border: none !important; 
                                                   padding: 6px 12px !important; 
                                                   border-radius: 5px !important; 
                                                   font-size: 11px !important; 
                                                   font-weight: 600 !important; 
                                                   cursor: pointer !important; 
                                                   display: inline-flex !important; 
                                                   align-items: center !important; 
                                                   gap: 4px !important; 
                                                   box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25) !important;
                                                   transition: all 0.3s ease !important;
                                                   position: relative !important;
                                                   overflow: hidden !important;
                                                   white-space: nowrap !important;">
                                        <i class="fas fa-cog" style="color: white !important; font-size: 10px !important;"></i> 
                                        <span style="color: white !important;">⚙️</span>
                                    </button>
                                </div>
                                <input type="radio" name="payment_method" value="Mastercard" data-fee="2.5">
                                <span><i class="fas fa-credit-card"></i> Mastercard (2.5%)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Final Summary -->
                    <div class="form-section calculation-summary">
                        <h3><i class="fas fa-calculator"></i> Final Summary</h3>
                        <div class="summary-row">
                            <span><strong>Total Clinic Fee:</strong></span>
                            <span id="final-clinic-fee"><strong>RM 0.00</strong></span>
                        </div>
                        <div class="summary-row">
                            <span><strong>Total Doctor Fee:</strong></span>
                            <span id="final-doctor-fee"><strong>RM 0.00</strong></span>
                        </div>
                        <div class="summary-row">
                            <span>Additional Charges:</span>
                            <span id="other-charges-total">RM 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Payment Fee:</span>
                            <span id="payment-fee">RM 0.00</span>
                        </div>
                        <div class="summary-row subtotal">
                            <span>Subtotal:</span>
                            <span id="subtotal-amount">RM 0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span><strong>GRAND TOTAL:</strong></span>
                            <span id="total-amount"><strong>RM 0.00</strong></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <button type="button" id="calculate-btn" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> Calculate
                        </button>
                        <button type="submit" id="save-btn" class="btn btn-success" disabled>
                            <i class="fas fa-save"></i> Save Receipt
                        </button>
                        <button type="button" id="print-btn" class="btn btn-info" disabled>
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Receipts Section -->
        <div class="history-section">
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Receipts
                    </h2>
                    <button type="button" class="btn btn-primary" onclick="clearAllReceipts()">
                        <i class="fas fa-eye-slash"></i> Hide All
                    </button>
                </div>

                <div class="receipts-list">
                    <?php if (!empty($recent_receipts)): ?>
                        <?php foreach ($recent_receipts as $receipt): ?>
                        <div class="receipt-item">
                            <div class="receipt-header">
                                <strong><?php echo htmlspecialchars($receipt['invoice_number']); ?></strong>
                                <span class="receipt-date"><?php echo date('M j, Y', strtotime($receipt['invoice_date'])); ?></span>
                            </div>
                            <div class="receipt-details">
                                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($receipt['patient_name'] ?? 'Walk-in'); ?></p>
                                <p><i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($receipt['payment_method']); ?></p>
                                <p class="receipt-amount"><strong>RM <?php echo number_format($receipt['total_amount'], 2); ?></strong></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No receipts found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php if ($edit_mode && $edit_receipt): ?>
<script>
// Pre-populate form data for editing
document.addEventListener('DOMContentLoaded', function() {
    // Restore calculation data from edit receipt
    calculationData.totalClinicFee = <?php echo $edit_receipt['clinic_fee']; ?>;
    calculationData.totalDoctorFee = <?php echo $edit_receipt['doctor_fee']; ?>;
    calculationData.paymentMethod = '<?php echo $edit_receipt['payment_method']; ?>';
    calculationData.paymentFeePercentage = <?php echo $edit_receipt['payment_fee_percentage']; ?>;
    calculationData.paymentFeeAmount = <?php echo $edit_receipt['payment_fee_amount']; ?>;
    calculationData.subtotal = <?php echo $edit_receipt['subtotal']; ?>;
    calculationData.totalAmount = <?php echo $edit_receipt['total_amount']; ?>;
    
    // Restore services and calculate charges for editing
    <?php if (!empty($edit_receipt_services)): ?>
    const editServices = <?php echo json_encode($edit_receipt_services); ?>;
    const serviceFees = {};
    
    // Get service percentages from dental services
    <?php foreach ($dental_services as $service): ?>
    serviceFees['<?php echo addslashes($service['service_name']); ?>'] = <?php echo $service['percentage']; ?>;
    <?php endforeach; ?>
    
    // Calculate charges based on services and fees
    calculationData.charges = [];
    const clinicFee = <?php echo $edit_receipt['clinic_fee']; ?>;
    const doctorFee = <?php echo $edit_receipt['doctor_fee']; ?>;
    const totalServiceFee = clinicFee + doctorFee;
    
    editServices.forEach(function(serviceName) {
        const servicePercentage = serviceFees[serviceName] || 0;
        const calculatedDoctorFee = (totalServiceFee * servicePercentage) / 100;
        const calculatedClinicFee = totalServiceFee - calculatedDoctorFee;
        
        calculationData.charges.push({
            name: serviceName,
            amount: totalServiceFee,
            doctorFee: calculatedDoctorFee,
            clinicFee: calculatedClinicFee
        });
    });
    <?php endif; ?>
    
    // Restore other charges
    <?php if (!empty($edit_receipt_charges)): ?>
    calculationData.otherCharges = <?php echo json_encode($edit_receipt_charges); ?>;
    <?php endif; ?>
    
    // Update displays
    updateChargesDisplay();
    updateFinalCalculation();
    
    // Enable save and print buttons
    document.getElementById('save-btn').disabled = false;
    document.getElementById('print-btn').disabled = false;
    
    // Select correct payment method
    const paymentRadio = document.querySelector(`input[name="payment_method"][value="<?php echo $edit_receipt['payment_method']; ?>"]`);
    if (paymentRadio) paymentRadio.checked = true;
});
</script>
<?php endif; ?>

<?php if (isset($saved_receipt_data)): ?>
<script>
// Restore form data after successful save for printing
document.addEventListener('DOMContentLoaded', function() {
    // Restore form fields
    document.getElementById('invoice-date').value = '<?php echo $saved_receipt_data['invoice_date']; ?>';
    document.getElementById('invoice-number').value = '<?php echo htmlspecialchars($saved_receipt_data['invoice_number']); ?>';
    document.getElementById('customer-name').value = '<?php echo htmlspecialchars($saved_receipt_data['customer_name']); ?>';
    
    // Restore calculation data
    calculationData.totalClinicFee = <?php echo $saved_receipt_data['clinic_fee']; ?>;
    calculationData.totalDoctorFee = <?php echo $saved_receipt_data['doctor_fee']; ?>;
    calculationData.paymentMethod = '<?php echo $saved_receipt_data['payment_method']; ?>';
    calculationData.paymentFeePercentage = <?php echo $saved_receipt_data['payment_fee_percentage']; ?>;
    calculationData.paymentFeeAmount = <?php echo $saved_receipt_data['payment_fee_amount']; ?>;
    calculationData.subtotal = <?php echo $saved_receipt_data['subtotal']; ?>;
    calculationData.totalAmount = <?php echo $saved_receipt_data['total_amount']; ?>;
    
    // Restore charges from JSON data
    <?php if (!empty($saved_receipt_data['selected_services'])): ?>
    calculationData.charges = <?php echo $saved_receipt_data['charges_list']; ?> || [];
    <?php endif; ?>
    
    // Restore other charges
    <?php if (!empty($saved_receipt_data['other_charges_list'])): ?>
    calculationData.otherCharges = <?php echo $saved_receipt_data['other_charges_list']; ?> || [];
    <?php endif; ?>
    
    // Update displays
    updateChargesDisplay();
    updateFinalCalculation();
    
    // Enable save and print buttons
    document.getElementById('save-btn').disabled = false;
    document.getElementById('print-btn').disabled = false;
    
    // Select correct payment method
    const paymentRadio = document.querySelector(`input[name="payment_method"][value="<?php echo $saved_receipt_data['payment_method']; ?>"]`);
    if (paymentRadio) paymentRadio.checked = true;
});
</script>
<?php endif; ?>

<!-- Payment Settings Modal -->
<div id="payment-settings-modal" class="modal" style="display: none;">
    <div class="modal-backdrop"></div>
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-cog"></i> Payment Method Fee Settings</h3>
            <button type="button" class="close-modal" onclick="closePaymentSettingsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <p style="color: #6b7280; margin-bottom: 25px; font-size: 14px;">
                Configure the processing fees for each payment method. Changes will be applied to all future calculations.
            </p>
            
            <div class="form-group">
                <label for="debit-card-fee">
                    <i class="fas fa-credit-card"></i> Debit Card Fee (%)
                </label>
                <input type="number" id="debit-card-fee" step="0.1" min="0" max="10" placeholder="e.g., 0.5">
            </div>
            
            <div class="form-group">
                <label for="credit-card-fee">
                    <i class="fas fa-credit-card"></i> Credit Card Fee (%)
                </label>
                <input type="number" id="credit-card-fee" step="0.1" min="0" max="10" placeholder="e.g., 1.2">
            </div>
            
            <div class="form-group">
                <label for="mastercard-fee">
                    <i class="fas fa-credit-card"></i> Mastercard Fee (%)
                </label>
                <input type="number" id="mastercard-fee" step="0.1" min="0" max="10" placeholder="e.g., 2.5">
            </div>
            
            <div style="background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; margin-top: 20px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                    <strong style="color: #1e40af;">Preview</strong>
                </div>
                <div id="fee-preview" style="color: #374151; font-size: 14px;">
                    Current settings will be shown here...
                </div>
            </div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closePaymentSettingsModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="savePaymentSettings()">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </div>
</div>

<script>
// Payment Settings Modal Functions
function openPaymentSettingsModal() {
    // Load current settings
    loadCurrentPaymentSettings();
    
    // Show modal
    document.getElementById('payment-settings-modal').style.display = 'flex';
    updateFeePreview();
}

function closePaymentSettingsModal() {
    document.getElementById('payment-settings-modal').style.display = 'none';
}

function loadCurrentPaymentSettings() {
    // Get current fees from the payment options
    const debitFee = document.querySelector('input[value="Debit Card"]').getAttribute('data-fee');
    const creditFee = document.querySelector('input[value="Credit Card"]').getAttribute('data-fee');
    const mastercardFee = document.querySelector('input[value="Mastercard"]').getAttribute('data-fee');
    
    // Populate modal inputs
    document.getElementById('debit-card-fee').value = debitFee;
    document.getElementById('credit-card-fee').value = creditFee;
    document.getElementById('mastercard-fee').value = mastercardFee;
}

function updateFeePreview() {
    const debitFee = document.getElementById('debit-card-fee').value || '0';
    const creditFee = document.getElementById('credit-card-fee').value || '0';
    const mastercardFee = document.getElementById('mastercard-fee').value || '0';
    
    const preview = document.getElementById('fee-preview');
    preview.innerHTML = `
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>🏧 Debit Card:</span> <span>${debitFee}%</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span>💳 Credit Card:</span> <span>${creditFee}%</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>🔴 Mastercard:</span> <span>${mastercardFee}%</span>
        </div>
    `;
}

function savePaymentSettings() {
    const debitFee = parseFloat(document.getElementById('debit-card-fee').value) || 0;
    const creditFee = parseFloat(document.getElementById('credit-card-fee').value) || 0;
    const mastercardFee = parseFloat(document.getElementById('mastercard-fee').value) || 0;
    
    // Validate inputs
    if (debitFee < 0 || debitFee > 10 || creditFee < 0 || creditFee > 10 || mastercardFee < 0 || mastercardFee > 10) {
        alert('Please enter valid fee percentages between 0 and 10.');
        return;
    }
    
    // Update the payment options in the form
    document.querySelector('input[value="Debit Card"]').setAttribute('data-fee', debitFee);
    document.querySelector('input[value="Credit Card"]').setAttribute('data-fee', creditFee);
    document.querySelector('input[value="Mastercard"]').setAttribute('data-fee', mastercardFee);
    
    // Update the display labels
    document.querySelector('input[value="Debit Card"]').nextElementSibling.innerHTML = 
        `<i class="fas fa-credit-card"></i> Debit Card (${debitFee}%)`;
    document.querySelector('input[value="Credit Card"]').nextElementSibling.innerHTML = 
        `<i class="fas fa-credit-card"></i> Credit Card (${creditFee}%)`;
    document.querySelector('input[value="Mastercard"]').nextElementSibling.innerHTML = 
        `<i class="fas fa-credit-card"></i> Mastercard (${mastercardFee}%)`;
    
    // Save to localStorage for persistence
    localStorage.setItem('paymentFees', JSON.stringify({
        debit: debitFee,
        credit: creditFee,
        mastercard: mastercardFee
    }));
    
    // Recalculate if there's an active calculation
    if (typeof recalculateTotal === 'function') {
        recalculateTotal();
    }
    
    // Close modal
    closePaymentSettingsModal();
    
    // Show success message
    if (typeof showToast === 'function') {
        showToast('Payment method fees updated successfully!', 'success');
    } else {
        alert('Payment method fees updated successfully!');
    }
}

// Add event listeners for live preview
document.addEventListener('DOMContentLoaded', function() {
    // Load saved settings on page load
    const savedFees = localStorage.getItem('paymentFees');
    if (savedFees) {
        const fees = JSON.parse(savedFees);
        
        // Update payment options
        document.querySelector('input[value="Debit Card"]').setAttribute('data-fee', fees.debit);
        document.querySelector('input[value="Credit Card"]').setAttribute('data-fee', fees.credit);
        document.querySelector('input[value="Mastercard"]').setAttribute('data-fee', fees.mastercard);
        
        // Update display labels
        document.querySelector('input[value="Debit Card"]').nextElementSibling.innerHTML = 
            `<i class="fas fa-credit-card"></i> Debit Card (${fees.debit}%)`;
        document.querySelector('input[value="Credit Card"]').nextElementSibling.innerHTML = 
            `<i class="fas fa-credit-card"></i> Credit Card (${fees.credit}%)`;
        document.querySelector('input[value="Mastercard"]').nextElementSibling.innerHTML = 
            `<i class="fas fa-credit-card"></i> Mastercard (${fees.mastercard}%)`;
    }
    
    // Settings button click event and styling enforcement
    const settingsBtn = document.getElementById('payment-settings-btn');
    settingsBtn.addEventListener('click', openPaymentSettingsModal);
    
    // Force hover effects via JavaScript since CSS might be overridden
    settingsBtn.addEventListener('mouseenter', function() {
        this.style.setProperty('background', 'linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%)', 'important');
        this.style.setProperty('transform', 'translateY(-2px)', 'important');
        this.style.setProperty('box-shadow', '0 6px 20px rgba(37, 99, 235, 0.4)', 'important');
        const icon = this.querySelector('i');
        if (icon) {
            icon.style.setProperty('transform', 'rotate(90deg)', 'important');
        }
    });
    
    settingsBtn.addEventListener('mouseleave', function() {
        this.style.setProperty('background', 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)', 'important');
        this.style.setProperty('transform', 'translateY(0)', 'important');
        this.style.setProperty('box-shadow', '0 3px 10px rgba(37, 99, 235, 0.3)', 'important');
        const icon = this.querySelector('i');
        if (icon) {
            icon.style.setProperty('transform', 'rotate(0deg)', 'important');
        }
    });
    
    // Live preview updates
    ['debit-card-fee', 'credit-card-fee', 'mastercard-fee'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', updateFeePreview);
        }
    });
    
    // Close modal when clicking outside
    document.getElementById('payment-settings-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePaymentSettingsModal();
        }
    });
});
</script>

<?php 
$additional_css = ['../assets/css/charge-calculator.css'];
$additional_js = ['../assets/js/financial-helpers.js', '../assets/js/financial.js'];
include '../includes/footer.php'; 
?>