// Enhanced Charge-Based Financial Calculator
// Professional dental practice management system with itemized billing

let calculationData = {
    charges: [],          // Array of {service, amount, doctorFee, clinicFee}
    totalCharges: 0,
    totalDoctorFee: 0,
    totalClinicFee: 0,
    otherCharges: [],
    paymentMethod: 'Cash',
    paymentFeePercentage: 0
};

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing financial calculator...');
    initializeFinancialCalculator();
    
    // Check if receipt was just saved and keep print button enabled
    if (localStorage.getItem('receiptSaved') === 'true' && localStorage.getItem('printEnabled') === 'true') {
        const printBtn = document.getElementById('print-btn');
        if (printBtn) {
            printBtn.disabled = false;
            console.log('Print button enabled after successful save');
        }
        // Clear the flags after use
        localStorage.removeItem('receiptSaved');
        localStorage.removeItem('printEnabled');
    }
    
    // Verify critical elements exist
    const chargesRows = document.getElementById('charges-rows');
    const addChargeBtn = document.getElementById('add-charge-btn');
    console.log('Charges rows element:', chargesRows);
    console.log('Add charge button:', addChargeBtn);
    
    if (!chargesRows) {
        console.error('ERROR: charges-rows element not found!');
    }
    if (!addChargeBtn) {
        console.error('ERROR: add-charge-btn element not found!');
    }
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
    // Add charge button
    const addChargeBtn = document.getElementById('add-charge-btn');
    if (addChargeBtn) {
        addChargeBtn.addEventListener('click', addCharge);
    }
    
    // Payment method radio buttons
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentMethod);
    });
    
    
    // Other charges
    const addOtherChargeBtn = document.getElementById('add-charge');
    if (addOtherChargeBtn) {
        addOtherChargeBtn.addEventListener('click', addOtherCharge);
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

    // Enter key support for charge amount
    const chargeAmountInput = document.getElementById('charge-amount');
    if (chargeAmountInput) {
        chargeAmountInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCharge();
            }
        });
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
            name: checkbox.dataset.service
        });
    });
    
    // No calculation needed - services are display-only now
}

// New Charge-Based Calculator Functions
function addCharge() {
    const chargeAmount = parseFloat(document.getElementById('charge-amount').value) || 0;
    const serviceSelect = document.getElementById('service-select');
    const serviceName = serviceSelect.options[serviceSelect.selectedIndex].dataset.service || '';
    
    // Validation
    if (chargeAmount <= 0) {
        showNotification('Please enter a valid charge amount', 'error');
        return;
    }
    
    if (serviceSelect.value === '' || !serviceName) {
        showNotification('Please select a dental service', 'error');
        return;
    }
    
    // Get service percentage after validation (can be 0 for Medication)
    const servicePercentage = parseFloat(serviceSelect.value);
    
    // Calculate doctor and clinic fees
    const doctorFee = chargeAmount * (servicePercentage / 100);
    const clinicFee = chargeAmount - doctorFee;
    
    // Create charge object
    const charge = {
        id: Date.now(), // Unique ID for removal
        service: serviceName,
        percentage: servicePercentage,
        amount: chargeAmount,
        doctorFee: doctorFee,
        clinicFee: clinicFee
    };
    
    // Add to charges array
    calculationData.charges.push(charge);
    
    // Update running totals
    updateRunningTotals();
    
    // Update charges display
    updateChargesDisplay();
    
    // Clear input fields
    document.getElementById('charge-amount').value = '';
    document.getElementById('service-select').value = '';
    
    // Focus back on charge amount for next entry
    document.getElementById('charge-amount').focus();
    
    showNotification(`Added: ${serviceName} - RM ${chargeAmount.toFixed(2)}`, 'success');
}

function removeCharge(chargeId) {
    // Remove charge from array
    calculationData.charges = calculationData.charges.filter(charge => charge.id !== chargeId);
    
    // Update displays
    updateRunningTotals();
    updateChargesDisplay();
    updateFinalCalculation();
    
    showNotification('Charge removed successfully', 'info');
}

function updateRunningTotals() {
    // Calculate totals
    calculationData.totalCharges = calculationData.charges.reduce((sum, charge) => sum + charge.amount, 0);
    calculationData.totalDoctorFee = calculationData.charges.reduce((sum, charge) => sum + charge.doctorFee, 0);
    calculationData.totalClinicFee = calculationData.charges.reduce((sum, charge) => sum + charge.clinicFee, 0);
    
    // Update final summary only
    document.getElementById('final-doctor-fee').textContent = `RM ${calculationData.totalDoctorFee.toFixed(2)}`;
    document.getElementById('final-clinic-fee').textContent = `RM ${calculationData.totalClinicFee.toFixed(2)}`;
    
    // Trigger final calculation update
    updateFinalCalculation();
}

function updateChargesDisplay() {
    const chargesRows = document.getElementById('charges-rows');
    if (!chargesRows) {
        console.error('charges-rows tbody element not found');
        return;
    }
    
    // Clear existing rows
    chargesRows.innerHTML = '';
    
    if (calculationData.charges.length === 0) {
        // Hide charges table when no charges
        const chargesContainer = document.getElementById('charges-list');
        if (chargesContainer) chargesContainer.style.display = 'none';
        return;
    }
    
    // Show charges table when charges exist
    const chargesContainer = document.getElementById('charges-list');
    if (chargesContainer) chargesContainer.style.display = 'block';
    
    // Add charge rows
    calculationData.charges.forEach((charge, index) => {
        const row = document.createElement('tr');
        row.className = 'charge-row';
        
        // Apply inline styles to ensure they work
        row.style.cssText = `
            transition: all 0.3s ease;
            background-color: ${index % 2 === 0 ? '#ffffff' : '#f8fafc'};
        `;
        
        // Add hover effects
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f0f9ff';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = index % 2 === 0 ? '#ffffff' : '#f8fafc';
        });
        
        // Create table cells with proper classes and inline styles
        row.innerHTML = `
            <td class="charge-service" style="font-weight: bold; color: #2563eb; text-align: left; font-size: 15px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">${charge.service}</td>
            <td class="charge-amount" style="text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold; color: #374151; font-size: 15px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM ${charge.amount.toFixed(2)}</td>
            <td class="charge-doctor" style="text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold; color: #059669; font-size: 14px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM ${charge.doctorFee.toFixed(2)} (${charge.percentage}%)</td>
            <td class="charge-clinic" style="text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold; color: #dc2626; font-size: 14px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM ${charge.clinicFee.toFixed(2)} (${(100-charge.percentage)}%)</td>
            <td class="charge-action" style="text-align: center; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb;">
                <button type="button" class="btn-remove" onclick="removeCharge(${charge.id})" title="Remove" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; border-radius: 8px; width: 36px; height: 36px; color: white; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        
        chargesRows.appendChild(row);
    });
    
    console.log('Updated charges display with', calculationData.charges.length, 'charges');
}

function updateFinalCalculation() {
    // Calculate other charges total
    const otherChargesTotal = calculationData.otherCharges.reduce((sum, charge) => sum + charge.amount, 0);
    
    // Calculate base subtotal (clinic + doctor + other charges)
    const baseSubtotal = calculationData.totalClinicFee + calculationData.totalDoctorFee + otherChargesTotal;
    
    // Calculate payment fee
    const paymentFeeAmount = baseSubtotal * (calculationData.paymentFeePercentage / 100);
    
    // Calculate final total (payment fees are subtracted)
    const finalTotal = baseSubtotal - paymentFeeAmount;
    
    // Update display
    document.getElementById('other-charges-total').textContent = `RM ${otherChargesTotal.toFixed(2)}`;
    document.getElementById('payment-fee').textContent = paymentFeeAmount > 0 ? `- RM ${paymentFeeAmount.toFixed(2)}` : `RM 0.00`;
    document.getElementById('subtotal-amount').textContent = `RM ${baseSubtotal.toFixed(2)}`;
    document.getElementById('total-amount').textContent = `RM ${finalTotal.toFixed(2)}`;
    
    // Store values for form submission
    calculationData.paymentFeeAmount = paymentFeeAmount;
    calculationData.subtotal = baseSubtotal;
    calculationData.totalAmount = finalTotal;
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
    // Use calculated totals from charges (already calculated in updateRunningTotals)
    const clinicFee = calculationData.totalClinicFee || 0;
    const doctorFee = calculationData.totalDoctorFee || 0;
    
    // Calculate other charges total
    const otherChargesTotal = calculationData.otherCharges.reduce((sum, charge) => sum + charge.amount, 0);
    
    // Calculate subtotal (Clinic Fee + Doctor Fee + Other Charges)
    const subtotal = clinicFee + doctorFee + otherChargesTotal;
    
    // Calculate payment fee
    const paymentFeeAmount = subtotal * (calculationData.paymentFeePercentage / 100);
    
    // Calculate final total (payment fees are subtracted)
    const totalAmount = subtotal - paymentFeeAmount;
    
    // Update display
    document.getElementById('final-clinic-fee').textContent = `RM ${clinicFee.toFixed(2)}`;
    document.getElementById('final-doctor-fee').textContent = `RM ${doctorFee.toFixed(2)}`;
    document.getElementById('other-charges-total').textContent = `RM ${otherChargesTotal.toFixed(2)}`;
    document.getElementById('payment-fee').textContent = paymentFeeAmount > 0 ? `- RM ${paymentFeeAmount.toFixed(2)}` : `RM 0.00`;
    document.getElementById('subtotal-amount').textContent = `RM ${subtotal.toFixed(2)}`;
    document.getElementById('total-amount').textContent = `RM ${totalAmount.toFixed(2)}`;
    
    // Store calculated values
    calculationData.servicesTotal = 0; // No longer calculated
    calculationData.otherChargesTotal = otherChargesTotal;
    calculationData.paymentFeeAmount = paymentFeeAmount;
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
    document.getElementById('charges-list-data').value = JSON.stringify(calculationData.charges);
    document.getElementById('clinic-fee-input').value = calculationData.totalClinicFee;
    document.getElementById('doctor-fee-input').value = calculationData.totalDoctorFee;
    document.getElementById('selected-services-data').value = JSON.stringify(calculationData.charges.map(c => ({name: c.service})));
    document.getElementById('other-charges-data').value = JSON.stringify(calculationData.otherCharges);
    document.getElementById('services-total-input').value = 0; // No longer used
    document.getElementById('other-charges-input').value = calculationData.otherCharges.reduce((sum, charge) => sum + charge.amount, 0);
    document.getElementById('payment-fee-percentage-input').value = calculationData.paymentFeePercentage;
    document.getElementById('payment-fee-amount-input').value = calculationData.paymentFeeAmount;
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
    
    // Mark that we're about to save successfully - keep print enabled
    localStorage.setItem('receiptSaved', 'true');
    localStorage.setItem('printEnabled', 'true');
    
    // Submit form
    e.target.submit();
}

function validateReceiptForm() {
    const requiredFields = [
        'invoice-date',
        'invoice-number',
        'customer-name'
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
    
    // Check if at least one charge is added
    if (calculationData.charges.length === 0) {
        showNotification('Please add at least one charge before saving the receipt', 'error');
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
    
    // Generate itemized charges list
    let chargesHTML = '';
    if (calculationData.charges.length > 0) {
        chargesHTML = '<div style="margin: 20px 0;"><h4 style="color: #2563eb;">Itemized Charges:</h4>';
        calculationData.charges.forEach(charge => {
            chargesHTML += `
                <div style="margin: 10px 0; padding: 10px; background: #f8f9ff; border-left: 4px solid #2563eb;">
                    <strong>${charge.service}</strong> - RM ${charge.amount.toFixed(2)}<br>
                    <small style="color: #059669;">Doctor Fee: RM ${charge.doctorFee.toFixed(2)} (${charge.percentage}%)</small><br>
                    <small style="color: #dc2626;">Clinic Fee: RM ${charge.clinicFee.toFixed(2)} (${100-charge.percentage}%)</small>
                </div>
            `;
        });
        chargesHTML += '</div>';
    }
    
    // Generate other charges section
    let otherChargesHTML = '';
    if (calculationData.otherCharges.length > 0) {
        otherChargesHTML = `<div style="margin: 15px 0;"><strong>Additional Charges: RM ${calculationData.otherCharges.reduce((sum, charge) => sum + charge.amount, 0).toFixed(2)}</strong></div>`;
    }
    
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - ${invoiceNumber}</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; line-height: 1.4; }
                .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px; }
                .header h1 { color: #2563eb; margin: 0; font-size: 24px; }
                .header-row { display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: bold; }
                .customer-name { font-size: 18px; margin: 20px 0; font-weight: bold; }
                .fee-section { margin: 15px 0; }
                .fee-amount { font-size: 16px; font-weight: bold; }
                .services-section { margin: 10px 0 15px 0; }
                .total-section { margin: 20px 0; padding: 10px; border: 2px solid #2563eb; background-color: #f8f9ff; }
                .total-amount { font-size: 20px; font-weight: bold; color: #2563eb; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🦷 DENTAL PRACTICE</h1>
            </div>
            
            <div class="header-row">
                <div>${new Date(invoiceDate).toLocaleDateString()}</div>
                <div>${invoiceNumber}</div>
            </div>
            
            <div class="customer-name">${customerName}</div>
            
            ${chargesHTML}
            
            <div class="fee-totals" style="margin: 20px 0; padding: 15px; background: #f0f9ff; border: 1px solid #2563eb; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                    <span><strong>Total Doctor Fee:</strong></span>
                    <span><strong style="color: #059669;">RM ${calculationData.totalDoctorFee.toFixed(2)}</strong></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin: 5px 0;">
                    <span><strong>Total Clinic Fee:</strong></span>
                    <span><strong style="color: #dc2626;">RM ${calculationData.totalClinicFee.toFixed(2)}</strong></span>
                </div>
            </div>
            
            ${otherChargesHTML}
            
            <div class="total-section">
                <div class="total-amount">Total Amount: RM ${calculationData.totalAmount.toFixed(2)}</div>
            </div>
            
            <div class="footer">
                <p>Payment Method: ${calculationData.paymentMethod}</p>
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
        charges: [],
        totalCharges: 0,
        totalDoctorFee: 0,
        totalClinicFee: 0,
        otherCharges: [],
        paymentMethod: 'Cash',
        paymentFeePercentage: 0
    };
    
    // Clear any saved receipt flags
    localStorage.removeItem('receiptSaved');
    localStorage.removeItem('printEnabled');
    
    // Reset display elements
    const finalDoctorFee = document.getElementById('final-doctor-fee');
    const finalClinicFee = document.getElementById('final-clinic-fee');
    const otherChargesTotal = document.getElementById('other-charges-total');
    const paymentFee = document.getElementById('payment-fee');
    const subtotalAmount = document.getElementById('subtotal-amount');
    const totalAmount = document.getElementById('total-amount');
    
    if (finalDoctorFee) finalDoctorFee.textContent = 'RM 0.00';
    if (finalClinicFee) finalClinicFee.textContent = 'RM 0.00';
    if (otherChargesTotal) otherChargesTotal.textContent = 'RM 0.00';
    if (paymentFee) paymentFee.textContent = 'RM 0.00';
    if (subtotalAmount) subtotalAmount.textContent = 'RM 0.00';
    if (totalAmount) totalAmount.textContent = 'RM 0.00';
    
    // Clear charges display
    updateChargesDisplay();
    
    // Disable buttons
    document.getElementById('save-btn').disabled = true;
    document.getElementById('print-btn').disabled = true;
    
    // Generate new invoice number
    generateInvoiceNumber();
    
    showNotification('Form reset successfully', 'info');
    
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

// Clear All Receipts Functionality (Hide, not delete)
function clearAllReceipts() {
    // Show confirmation modal
    const modalHTML = `
        <div id="clear-receipts-modal" class="modal">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white;">
                    <h2><i class="fas fa-eye-slash"></i> Hide All Receipts</h2>
                    <button type="button" class="modal-close" onclick="closeClearModal()" style="color: white;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 30px; text-align: center;">
                    <div style="font-size: 48px; color: #dc2626; margin-bottom: 20px;">
                        <i class="fas fa-eye-slash"></i>
                    </div>
                    <h3 style="margin-bottom: 20px; color: #2563eb;">Hide Receipt Display</h3>
                    <p style="margin-bottom: 20px; font-size: 16px;">
                        This will temporarily hide all receipts from view. The data will NOT be deleted and can be shown again later.
                    </p>
                    <p style="font-weight: bold; color: #2563eb; margin-top: 20px;">
                        Do you want to hide all receipts from display?
                    </p>
                    <div style="margin-top: 30px;">
                        <button type="button" class="btn btn-primary" onclick="confirmHideAllReceipts()" style="background: #2563eb; margin-right: 10px;">
                            <i class="fas fa-eye-slash"></i> Yes, Hide All Receipts
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeClearModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function closeClearModal() {
    const modal = document.getElementById('clear-receipts-modal');
    if (modal) {
        modal.remove();
    }
}

function confirmHideAllReceipts() {
    closeClearModal();
    
    // Hide all receipt items instead of deleting
    const receiptItems = document.querySelectorAll('.receipt-item');
    receiptItems.forEach(item => {
        item.classList.add('hidden');
    });
    
    // Store hide state in localStorage
    localStorage.setItem('receiptsHidden', 'true');
    
    // Show empty state message
    const receiptsList = document.querySelector('.receipts-list');
    if (receiptsList && receiptsList.querySelectorAll('.receipt-item:not(.hidden)').length === 0) {
        // Check if empty state doesn't already exist
        if (!receiptsList.querySelector('.empty-state')) {
            receiptsList.innerHTML += `
                <div class="empty-state">
                    <i class="fas fa-eye-slash"></i>
                    <p>Receipts are hidden</p>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showAllReceipts()" style="margin-top: 10px;">
                        <i class="fas fa-eye"></i> Show Receipts
                    </button>
                </div>
            `;
        }
    }
    
    showNotification('All receipts have been hidden from view', 'success');
}

// Function to show all hidden receipts
function showAllReceipts() {
    const receiptItems = document.querySelectorAll('.receipt-item.hidden');
    receiptItems.forEach(item => {
        item.classList.remove('hidden');
    });
    
    // Remove hide state from localStorage
    localStorage.removeItem('receiptsHidden');
    
    // Remove empty state message
    const emptyState = document.querySelector('.receipts-list .empty-state');
    if (emptyState) {
        emptyState.remove();
    }
    
    showNotification('All receipts are now visible', 'success');
}

// Check on page load if receipts should be hidden
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('receiptsHidden') === 'true') {
        // Hide all receipts on load if previously hidden
        setTimeout(() => {
            const receiptItems = document.querySelectorAll('.receipt-item');
            if (receiptItems.length > 0) {
                confirmHideAllReceipts();
            }
        }, 100);
    }
});