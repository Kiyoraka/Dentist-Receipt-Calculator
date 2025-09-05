// Enhanced Financial Calculator with Database Integration
// Professional dental practice management system

let calculationData = {
    baseCost: 0,
    selectedServices: [],
    otherCharges: [],
    paymentMethod: 'Cash',
    paymentFeePercentage: 0,
    terminalChargeEnabled: true,
    terminalChargePercentage: 8
};

document.addEventListener('DOMContentLoaded', function() {
    initializeFinancialCalculator();
});

function initializeFinancialCalculator() {
    // Event listeners
    setupEventListeners();
    
    // Generate initial invoice number
    generateInvoiceNumber();
    
    // Initialize auto-save
    initializeAutoSave('receipt-form', 60000); // Save every minute
    
    console.log('Financial calculator initialized');
}

function setupEventListeners() {
    // Base cost input
    const baseCostInput = document.getElementById('base-cost');
    if (baseCostInput) {
        baseCostInput.addEventListener('input', updateCalculation);
    }
    
    // Service checkboxes
    const serviceCheckboxes = document.querySelectorAll('input[name="services[]"]');
    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedServices);
    });
    
    // Payment method radio buttons
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentMethod);
    });
    
    // Terminal charge checkbox
    const terminalChargeCheckbox = document.getElementById('terminal-charge');
    if (terminalChargeCheckbox) {
        terminalChargeCheckbox.addEventListener('change', updateCalculation);
    }
    
    // Add charge button
    const addChargeBtn = document.getElementById('add-charge');
    if (addChargeBtn) {
        addChargeBtn.addEventListener('click', addOtherCharge);
    }
    
    // Calculate button
    const calculateBtn = document.getElementById('calculate-btn');
    if (calculateBtn) {
        calculateBtn.addEventListener('click', performCalculation);
    }
    
    // Form submission
    const receiptForm = document.getElementById('receipt-form');
    if (receiptForm) {
        receiptForm.addEventListener('submit', handleFormSubmission);
    }
    
    // Print button
    const printBtn = document.getElementById('print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', printReceipt);
    }
    
    // Reset button
    const resetBtn = document.querySelector('button[type="reset"]');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetForm);
    }
}

function generateInvoiceNumber() {
    const today = new Date();
    const dateStr = today.getFullYear().toString() + 
                   (today.getMonth() + 1).toString().padStart(2, '0') + 
                   today.getDate().toString().padStart(2, '0');
    const timeStr = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    
    const invoiceNumber = `INV-${dateStr}-${timeStr}`;
    document.getElementById('invoice-number').value = invoiceNumber;
}

function updateSelectedServices() {
    calculationData.selectedServices = [];
    
    const serviceCheckboxes = document.querySelectorAll('input[name="services[]"]:checked');
    serviceCheckboxes.forEach(checkbox => {
        calculationData.selectedServices.push({
            name: checkbox.dataset.service,
            percentage: parseFloat(checkbox.dataset.percentage),
            amount: 0 // Will be calculated
        });
    });
    
    updateCalculation();
}

function updatePaymentMethod() {
    const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
    if (selectedPayment) {
        calculationData.paymentMethod = selectedPayment.value;
        calculationData.paymentFeePercentage = parseFloat(selectedPayment.dataset.fee);
        updateCalculation();
    }
}

function addOtherCharge() {
    const container = document.getElementById('other-charges-container');
    const chargeItem = document.createElement('div');
    chargeItem.className = 'charge-item';
    
    chargeItem.innerHTML = `
        <input type="text" placeholder="Description" class="charge-description">
        <input type="number" placeholder="0.00" min="0" step="0.01" class="charge-amount">
        <button type="button" class="btn-remove" onclick="removeCharge(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(chargeItem);
    
    // Add event listeners to new inputs
    const amountInput = chargeItem.querySelector('.charge-amount');
    amountInput.addEventListener('input', updateOtherCharges);
}

function removeCharge(button) {
    button.closest('.charge-item').remove();
    updateOtherCharges();
}

function updateOtherCharges() {
    calculationData.otherCharges = [];
    
    const chargeItems = document.querySelectorAll('.charge-item');
    chargeItems.forEach(item => {
        const description = item.querySelector('.charge-description').value;
        const amount = parseFloat(item.querySelector('.charge-amount').value) || 0;
        
        if (description && amount > 0) {
            calculationData.otherCharges.push({
                description: description,
                amount: amount
            });
        }
    });
    
    updateCalculation();
}

function updateCalculation() {
    // Get base cost
    calculationData.baseCost = parseFloat(document.getElementById('base-cost').value) || 0;
    
    // Calculate services total
    let servicesTotal = 0;
    calculationData.selectedServices.forEach(service => {
        service.amount = calculationData.baseCost * (service.percentage / 100);
        servicesTotal += service.amount;
    });
    
    // Calculate other charges total
    const otherChargesTotal = calculationData.otherCharges.reduce((sum, charge) => sum + charge.amount, 0);
    
    // Calculate subtotal
    const subtotal = calculationData.baseCost + servicesTotal + otherChargesTotal;
    
    // Calculate payment fee
    const paymentFeeAmount = subtotal * (calculationData.paymentFeePercentage / 100);
    
    // Calculate terminal charge
    const terminalChargeEnabled = document.getElementById('terminal-charge').checked;
    const terminalChargeAmount = terminalChargeEnabled ? subtotal * (calculationData.terminalChargePercentage / 100) : 0;
    
    // Calculate final total
    const totalAmount = subtotal + paymentFeeAmount + terminalChargeAmount;
    
    // Update display
    document.getElementById('services-total').textContent = `RM ${servicesTotal.toFixed(2)}`;
    document.getElementById('other-charges-total').textContent = `RM ${otherChargesTotal.toFixed(2)}`;
    document.getElementById('payment-fee').textContent = `RM ${paymentFeeAmount.toFixed(2)}`;
    document.getElementById('terminal-charge-amount').textContent = `RM ${terminalChargeAmount.toFixed(2)}`;
    document.getElementById('subtotal-amount').textContent = `RM ${subtotal.toFixed(2)}`;
    document.getElementById('total-amount').textContent = `RM ${totalAmount.toFixed(2)}`;
    
    // Store calculated values
    calculationData.servicesTotal = servicesTotal;
    calculationData.otherChargesTotal = otherChargesTotal;
    calculationData.paymentFeeAmount = paymentFeeAmount;
    calculationData.terminalChargeAmount = terminalChargeAmount;
    calculationData.subtotal = subtotal;
    calculationData.totalAmount = totalAmount;
}

function performCalculation() {
    updateCalculation();
    
    // Enable save and print buttons
    document.getElementById('save-btn').disabled = false;
    document.getElementById('print-btn').disabled = false;
    
    // Update hidden form fields
    updateHiddenFields();
    
    showNotification('Calculation completed successfully!', 'success');
}

function updateHiddenFields() {
    // Update all hidden form fields with calculation data
    document.getElementById('selected-services-data').value = JSON.stringify(calculationData.selectedServices);
    document.getElementById('other-charges-data').value = JSON.stringify(calculationData.otherCharges);
    document.getElementById('services-total-input').value = calculationData.servicesTotal;
    document.getElementById('other-charges-input').value = calculationData.otherChargesTotal;
    document.getElementById('payment-fee-percentage-input').value = calculationData.paymentFeePercentage;
    document.getElementById('payment-fee-amount-input').value = calculationData.paymentFeeAmount;
    document.getElementById('terminal-charge-percentage-input').value = calculationData.terminalChargePercentage;
    document.getElementById('terminal-charge-amount-input').value = calculationData.terminalChargeAmount;
    document.getElementById('subtotal-input').value = calculationData.subtotal;
    document.getElementById('total-amount-input').value = calculationData.totalAmount;
}

function handleFormSubmission(e) {
    e.preventDefault();
    
    // Validate form
    if (!validateReceiptForm()) {
        return false;
    }
    
    // Show loading
    showLoading();
    
    // Update hidden fields before submission
    updateHiddenFields();
    
    // Submit form
    e.target.submit();
}

function validateReceiptForm() {
    const requiredFields = [
        'invoice-date',
        'invoice-number',
        'customer-name',
        'base-cost'
    ];
    
    let isValid = true;
    
    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Check if at least one service or charge is selected
    if (calculationData.selectedServices.length === 0 && calculationData.otherCharges.length === 0) {
        showNotification('Please select at least one service or add a charge', 'error');
        isValid = false;
    }
    
    // Check if calculation has been performed
    if (calculationData.totalAmount === undefined || calculationData.totalAmount === 0) {
        showNotification('Please perform calculation before saving', 'error');
        isValid = false;
    }
    
    return isValid;
}

function printReceipt() {
    if (!calculationData.totalAmount) {
        showNotification('Please calculate first before printing', 'error');
        return;
    }
    
    // Generate receipt HTML
    const receiptHTML = generateReceiptHTML();
    
    // Open print window
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(receiptHTML);
    printWindow.document.close();
    printWindow.print();
}

function generateReceiptHTML() {
    const invoiceNumber = document.getElementById('invoice-number').value;
    const invoiceDate = document.getElementById('invoice-date').value;
    const customerName = document.getElementById('customer-name').value;
    
    let servicesHTML = '';
    calculationData.selectedServices.forEach(service => {
        servicesHTML += `
            <tr>
                <td>${service.name}</td>
                <td>${service.percentage}%</td>
                <td>RM ${service.amount.toFixed(2)}</td>
            </tr>
        `;
    });
    
    let chargesHTML = '';
    calculationData.otherCharges.forEach(charge => {
        chargesHTML += `
            <tr>
                <td colspan="2">${charge.description}</td>
                <td>RM ${charge.amount.toFixed(2)}</td>
            </tr>
        `;
    });
    
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - ${invoiceNumber}</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #2563eb; margin: 0; }
                .invoice-details { margin-bottom: 20px; }
                .invoice-details table { width: 100%; }
                .invoice-details td { padding: 5px 0; }
                .services-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .services-table th, .services-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                .services-table th { background-color: #f8f9fa; }
                .summary { margin-top: 20px; }
                .summary table { width: 100%; max-width: 400px; margin-left: auto; }
                .summary td { padding: 5px 10px; }
                .total-row { border-top: 2px solid #2563eb; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🦷 DENTAL PRACTICE</h1>
                <h2>RECEIPT</h2>
            </div>
            
            <div class="invoice-details">
                <table>
                    <tr><td><strong>Invoice Number:</strong></td><td>${invoiceNumber}</td></tr>
                    <tr><td><strong>Date:</strong></td><td>${new Date(invoiceDate).toLocaleDateString()}</td></tr>
                    <tr><td><strong>Patient Name:</strong></td><td>${customerName}</td></tr>
                    <tr><td><strong>Payment Method:</strong></td><td>${calculationData.paymentMethod}</td></tr>
                </table>
            </div>
            
            <table class="services-table">
                <thead>
                    <tr>
                        <th>Service/Item</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Base Cost</td>
                        <td>-</td>
                        <td>RM ${calculationData.baseCost.toFixed(2)}</td>
                    </tr>
                    ${servicesHTML}
                    ${chargesHTML}
                </tbody>
            </table>
            
            <div class="summary">
                <table>
                    <tr><td>Services Total:</td><td>RM ${calculationData.servicesTotal.toFixed(2)}</td></tr>
                    <tr><td>Other Charges:</td><td>RM ${calculationData.otherChargesTotal.toFixed(2)}</td></tr>
                    <tr><td>Payment Fee (${calculationData.paymentFeePercentage}%):</td><td>RM ${calculationData.paymentFeeAmount.toFixed(2)}</td></tr>
                    <tr><td>Terminal Charge (${calculationData.terminalChargePercentage}%):</td><td>RM ${calculationData.terminalChargeAmount.toFixed(2)}</td></tr>
                    <tr><td>Subtotal:</td><td>RM ${calculationData.subtotal.toFixed(2)}</td></tr>
                    <tr class="total-row"><td><strong>TOTAL AMOUNT:</strong></td><td><strong>RM ${calculationData.totalAmount.toFixed(2)}</strong></td></tr>
                </table>
            </div>
            
            <div class="footer">
                <p>Thank you for choosing our dental practice!</p>
                <p>Generated on ${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `;
}

function resetForm() {
    // Reset calculation data
    calculationData = {
        baseCost: 0,
        selectedServices: [],
        otherCharges: [],
        paymentMethod: 'Cash',
        paymentFeePercentage: 0,
        terminalChargeEnabled: true,
        terminalChargePercentage: 8
    };
    
    // Reset display
    document.getElementById('services-total').textContent = 'RM 0.00';
    document.getElementById('other-charges-total').textContent = 'RM 0.00';
    document.getElementById('payment-fee').textContent = 'RM 0.00';
    document.getElementById('terminal-charge-amount').textContent = 'RM 0.00';
    document.getElementById('subtotal-amount').textContent = 'RM 0.00';
    document.getElementById('total-amount').textContent = 'RM 0.00';
    
    // Disable buttons
    document.getElementById('save-btn').disabled = true;
    document.getElementById('print-btn').disabled = true;
    
    // Generate new invoice number
    generateInvoiceNumber();
    
    // Clear other charges container
    const container = document.getElementById('other-charges-container');
    container.innerHTML = `
        <div class="charge-item">
            <input type="text" placeholder="Description" class="charge-description">
            <input type="number" placeholder="0.00" min="0" step="0.01" class="charge-amount">
            <button type="button" class="btn-remove" onclick="removeCharge(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    showNotification('Form reset successfully', 'info');
}

// Add event listeners to existing charge inputs
document.addEventListener('DOMContentLoaded', function() {
    const chargeAmountInputs = document.querySelectorAll('.charge-amount');
    chargeAmountInputs.forEach(input => {
        input.addEventListener('input', updateOtherCharges);
    });
});