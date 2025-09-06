<?php
$page_title = 'Financial Management - Dental Practice Management';
require_once '../config/database.php';
require_once '../includes/header.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

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
            
            // Insert receipt
            $stmt = $conn->prepare("INSERT INTO receipts (patient_id, invoice_number, invoice_date, clinic_fee, doctor_fee, other_charges, payment_method, payment_fee_percentage, payment_fee_amount, terminal_charge_percentage, terminal_charge_amount, subtotal, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $_POST['invoice_number'],
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
            $success_message = "Receipt saved successfully! Invoice #: " . $_POST['invoice_number'];
            
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
            Financial Management
        </h1>
        <p class="content-subtitle">Receipt calculator and financial tracking</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

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
                                <input type="date" id="invoice-date" name="invoice_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="invoice-number">Invoice Number:</label>
                                <input type="text" id="invoice-number" name="invoice_number" placeholder="INV-<?php echo date('Ymd'); ?>-001" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customer-name">Customer Name:</label>
                            <input type="text" id="customer-name" name="customer_name" placeholder="Patient Name" required>
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
                                <button type="button" class="btn-remove" onclick="removeCharge(this)">
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
                        <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
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
                            <label class="payment-option">
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

<?php 
$additional_css = ['../assets/css/charge-calculator.css'];
$additional_js = ['../assets/js/financial-helpers.js', '../assets/js/financial.js'];
include '../includes/footer.php'; 
?>