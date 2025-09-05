// Dentist Receipt Calculator - JavaScript Engine
// Professional dental service calculator with payment processing

class DentalCalculator {
    constructor() {
        this.baseCost = 0;
        this.selectedServices = [];
        this.otherCharges = [];
        this.paymentMethod = { type: 'Cash', fee: 0 };
        this.terminalCharge = true;
        this.receiptData = null;
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.setDefaultDate();
        this.updateCalculation();
    }

    bindEvents() {
        // Base cost input
        const baseCostInput = document.getElementById('base-cost');
        baseCostInput?.addEventListener('input', (e) => {
            this.baseCost = parseFloat(e.target.value) || 0;
            this.updateCalculation();
        });

        // Service checkboxes
        const serviceCheckboxes = document.querySelectorAll('input[name="services"]');
        serviceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.handleServiceSelection(e);
                this.updateCalculation();
            });
        });

        // Payment method radio buttons
        const paymentRadios = document.querySelectorAll('input[name="paymentMethod"]');
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.handlePaymentSelection(e);
                this.updateCalculation();
            });
        });

        // Terminal charge checkbox
        const terminalCheckbox = document.getElementById('terminal-charge');
        terminalCheckbox?.addEventListener('change', (e) => {
            this.terminalCharge = e.target.checked;
            this.updateCalculation();
        });

        // Other charges inputs
        this.bindOtherChargesEvents();

        // Add charge button
        const addChargeBtn = document.getElementById('add-charge');
        addChargeBtn?.addEventListener('click', () => {
            this.addChargeField();
        });

        // Calculate button
        const calculateBtn = document.getElementById('calculate-btn');
        calculateBtn?.addEventListener('click', () => {
            this.generateReceipt();
        });

        // Receipt action buttons
        const printBtn = document.getElementById('print-btn');
        printBtn?.addEventListener('click', () => {
            this.printReceipt();
        });

        const newCalcBtn = document.getElementById('new-calculation-btn');
        newCalcBtn?.addEventListener('click', () => {
            this.resetCalculator();
        });
    }

    setDefaultDate() {
        const dateInput = document.getElementById('invoice-date');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
        }
    }

    handleServiceSelection(event) {
        const checkbox = event.target;
        const service = {
            name: checkbox.dataset.service,
            percentage: parseInt(checkbox.value),
            checked: checkbox.checked
        };

        if (checkbox.checked) {
            this.selectedServices.push(service);
        } else {
            this.selectedServices = this.selectedServices.filter(s => s.name !== service.name);
        }
    }

    handlePaymentSelection(event) {
        const radio = event.target;
        this.paymentMethod = {
            type: radio.dataset.method,
            fee: parseFloat(radio.value)
        };
    }

    bindOtherChargesEvents() {
        const chargeInputs = document.querySelectorAll('input[name="otherCharge"]');
        chargeInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = parseFloat(e.target.value) || 0;
                this.otherCharges[index] = value;
                this.updateCalculation();
            });
        });
    }

    addChargeField() {
        const container = document.getElementById('other-charges-container');
        const chargeCount = container.children.length + 1;
        
        const chargeDiv = document.createElement('div');
        chargeDiv.className = 'charge-item';
        chargeDiv.innerHTML = `
            <label for="other-charge-${chargeCount}">Other Charge ${chargeCount}:</label>
            <input type="number" id="other-charge-${chargeCount}" name="otherCharge" 
                   min="0" step="0.01" placeholder="0.00">
            <button type="button" class="remove-charge" onclick="this.parentElement.remove(); calculator.updateOtherCharges(); calculator.updateCalculation();">×</button>
        `;
        
        container.appendChild(chargeDiv);
        
        // Bind event to new input
        const newInput = chargeDiv.querySelector('input');
        newInput.addEventListener('input', (e) => {
            this.updateOtherCharges();
            this.updateCalculation();
        });
    }

    updateOtherCharges() {
        const chargeInputs = document.querySelectorAll('input[name="otherCharge"]');
        this.otherCharges = [];
        chargeInputs.forEach((input, index) => {
            const value = parseFloat(input.value) || 0;
            if (value > 0) {
                this.otherCharges[index] = value;
            }
        });
    }

    calculateSubtotal() {
        let subtotal = this.baseCost;
        
        // Add service charges
        this.selectedServices.forEach(service => {
            if (service.checked) {
                subtotal += (this.baseCost * service.percentage / 100);
            }
        });

        // Add other charges
        this.otherCharges.forEach(charge => {
            if (charge > 0) {
                subtotal += charge;
            }
        });

        return subtotal;
    }

    calculatePaymentFee(subtotal) {
        if (this.paymentMethod.fee === 0) {
            return 0;
        }
        return subtotal * (this.paymentMethod.fee / 100);
    }

    calculateTerminalCharge(amount) {
        if (!this.terminalCharge || this.paymentMethod.type === 'Cash' || this.paymentMethod.type === 'Union') {
            return 0;
        }
        return amount * 0.08; // 8% terminal charge
    }

    calculateTotal() {
        const subtotal = this.calculateSubtotal();
        const paymentFee = this.calculatePaymentFee(subtotal);
        const amountAfterPaymentFee = subtotal + paymentFee;
        const terminalCharge = this.calculateTerminalCharge(amountAfterPaymentFee);
        const total = amountAfterPaymentFee + terminalCharge;

        return {
            subtotal,
            paymentFee,
            terminalCharge,
            total
        };
    }

    updateCalculation() {
        // This method can be used for real-time updates if needed
        const calculation = this.calculateTotal();
        
        // Update UI elements if they exist (for real-time display)
        const totalDisplay = document.getElementById('total-display');
        if (totalDisplay) {
            totalDisplay.textContent = `RM ${calculation.total.toFixed(2)}`;
        }
    }

    generateReceipt() {
        // Validate required fields
        if (!this.validateForm()) {
            return;
        }

        // Collect form data
        const invoiceData = this.collectInvoiceData();
        const calculation = this.calculateTotal();

        // Generate receipt HTML
        const receiptHTML = this.createReceiptHTML(invoiceData, calculation);
        
        // Display receipt
        const receiptContent = document.getElementById('receipt-content');
        const receiptSection = document.getElementById('receipt-section');
        
        if (receiptContent && receiptSection) {
            receiptContent.innerHTML = receiptHTML;
            receiptSection.classList.remove('hidden');
            
            // Scroll to receipt
            receiptSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Store receipt data
        this.receiptData = {
            invoice: invoiceData,
            calculation: calculation,
            services: this.selectedServices.filter(s => s.checked),
            otherCharges: this.otherCharges.filter(c => c > 0),
            paymentMethod: this.paymentMethod,
            terminalCharge: this.terminalCharge
        };
    }

    validateForm() {
        const customerName = document.getElementById('customer-name').value.trim();
        const baseCost = document.getElementById('base-cost').value;

        if (!customerName) {
            showNotification('Please enter customer name', 'error');
            document.getElementById('customer-name').focus();
            return false;
        }

        if (!baseCost || parseFloat(baseCost) <= 0) {
            showNotification('Please enter a valid base cost', 'error');
            document.getElementById('base-cost').focus();
            return false;
        }

        if (this.selectedServices.length === 0 && this.otherCharges.filter(c => c > 0).length === 0) {
            showNotification('Please select at least one service or add other charges', 'error');
            return false;
        }

        return true;
    }

    collectInvoiceData() {
        return {
            date: document.getElementById('invoice-date').value,
            memberInvoice: document.getElementById('member-invoice').value || 'N/A',
            customerName: document.getElementById('customer-name').value.trim()
        };
    }

    createReceiptHTML(invoiceData, calculation) {
        const currentDate = new Date().toLocaleString();
        
        let html = `
            <div class="receipt-header">
                <h3>🦷 Dental Service Receipt</h3>
                <p>Professional Dental Calculator</p>
            </div>

            <div class="receipt-info">
                <div><strong>Date:</strong> <span>${invoiceData.date}</span></div>
                <div><strong>Member Invoice:</strong> <span>${invoiceData.memberInvoice}</span></div>
                <div><strong>Customer Name:</strong> <span>${invoiceData.customerName}</span></div>
                <div><strong>Generated:</strong> <span>${currentDate}</span></div>
            </div>
        `;

        // Services section
        if (this.selectedServices.filter(s => s.checked).length > 0) {
            html += `
                <div class="receipt-services">
                    <h4>🦷 Dental Services</h4>
                    <div class="service-line">
                        <span>Base Cost:</span>
                        <span>RM ${this.baseCost.toFixed(2)}</span>
                    </div>
            `;

            this.selectedServices.forEach(service => {
                if (service.checked) {
                    const serviceAmount = (this.baseCost * service.percentage / 100);
                    html += `
                        <div class="service-line">
                            <span>${service.name} (${service.percentage}%):</span>
                            <span>RM ${serviceAmount.toFixed(2)}</span>
                        </div>
                    `;
                }
            });

            html += `</div>`;
        }

        // Other charges section
        const validOtherCharges = this.otherCharges.filter(c => c > 0);
        if (validOtherCharges.length > 0) {
            html += `
                <div class="receipt-charges">
                    <h4>💰 Other Charges</h4>
            `;

            validOtherCharges.forEach((charge, index) => {
                html += `
                    <div class="charge-line">
                        <span>Other Charge ${index + 1}:</span>
                        <span>RM ${charge.toFixed(2)}</span>
                    </div>
                `;
            });

            html += `</div>`;
        }

        // Payment section
        html += `
            <div class="receipt-payment">
                <h4>💳 Payment Details</h4>
                <div class="payment-line">
                    <span>Subtotal:</span>
                    <span>RM ${calculation.subtotal.toFixed(2)}</span>
                </div>
        `;

        if (calculation.paymentFee > 0) {
            html += `
                <div class="payment-line">
                    <span>${this.paymentMethod.type} Fee (${this.paymentMethod.fee}%):</span>
                    <span>RM ${calculation.paymentFee.toFixed(2)}</span>
                </div>
            `;
        }

        if (calculation.terminalCharge > 0) {
            html += `
                <div class="payment-line">
                    <span>Terminal Service Charge (8%):</span>
                    <span>RM ${calculation.terminalCharge.toFixed(2)}</span>
                </div>
            `;
        }

        html += `
                <div class="payment-line">
                    <span><strong>Payment Method:</strong></span>
                    <span><strong>${this.paymentMethod.type}</strong></span>
                </div>
            </div>
        `;

        // Total section
        html += `
            <div class="receipt-total">
                <h3>Total Amount: RM ${calculation.total.toFixed(2)}</h3>
            </div>
        `;

        return html;
    }

    printReceipt() {
        if (!this.receiptData) {
            showNotification('No receipt to print. Please calculate first.', 'error');
            return;
        }

        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        const receiptContent = document.getElementById('receipt-content').innerHTML;
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Dental Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
                    .receipt-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2563eb; padding-bottom: 20px; }
                    .receipt-header h3 { color: #2563eb; font-size: 1.5rem; margin-bottom: 5px; }
                    .receipt-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 30px; }
                    .receipt-info div { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #ccc; }
                    .receipt-services, .receipt-charges, .receipt-payment { margin-bottom: 25px; }
                    .receipt-services h4, .receipt-charges h4, .receipt-payment h4 { color: #2563eb; margin-bottom: 15px; border-bottom: 1px solid #2563eb; padding-bottom: 5px; }
                    .service-line, .charge-line, .payment-line { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #ccc; }
                    .receipt-total { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px; }
                    .receipt-total h3 { margin: 0; font-size: 1.5rem; }
                </style>
            </head>
            <body>
                ${receiptContent}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        
        // Small delay to ensure content is loaded before printing
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    resetCalculator() {
        // Reset all form fields
        document.getElementById('invoice-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('member-invoice').value = '';
        document.getElementById('customer-name').value = '';
        document.getElementById('base-cost').value = '';

        // Uncheck all services
        const serviceCheckboxes = document.querySelectorAll('input[name="services"]');
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });

        // Reset payment method to cash
        const cashRadio = document.getElementById('cash');
        if (cashRadio) {
            cashRadio.checked = true;
        }

        // Reset terminal charge
        const terminalCheckbox = document.getElementById('terminal-charge');
        if (terminalCheckbox) {
            terminalCheckbox.checked = true;
        }

        // Clear other charges (keep first field)
        const chargeContainer = document.getElementById('other-charges-container');
        const charges = chargeContainer.querySelectorAll('.charge-item');
        charges.forEach((charge, index) => {
            if (index === 0) {
                charge.querySelector('input').value = '';
            } else {
                charge.remove();
            }
        });

        // Hide receipt
        const receiptSection = document.getElementById('receipt-section');
        if (receiptSection) {
            receiptSection.classList.add('hidden');
        }

        // Reset calculator state
        this.baseCost = 0;
        this.selectedServices = [];
        this.otherCharges = [];
        this.paymentMethod = { type: 'Cash', fee: 0 };
        this.terminalCharge = true;
        this.receiptData = null;

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Focus on customer name
        document.getElementById('customer-name').focus();
    }
}

// Initialize calculator when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.calculator = new DentalCalculator();
});

// Export for module use if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DentalCalculator;
}