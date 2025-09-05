// Patient Management JavaScript
// Professional dental practice management system with export functionality

// Notification system functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
        max-width: 400px;
    `;
    
    // Add icon
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    notification.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function showLoading() {
    // Create loading overlay if it doesn't exist
    if (!document.getElementById('loading-overlay')) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loading-overlay';
        loadingOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        loadingOverlay.innerHTML = `
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #2563eb;"></i>
                <p style="margin-top: 10px; color: #333;">Processing...</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
    }
}

function hideLoading() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
}

// Add animation styles if not already present
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

document.addEventListener('DOMContentLoaded', function() {
    initializePatientManagement();
});

function initializePatientManagement() {
    // Initialize event listeners
    setupPatientEventListeners();
    
    // Initialize search functionality
    initializePatientSearch();
    
    console.log('Patient management initialized');
}

function setupPatientEventListeners() {
    // Patient form submission
    const patientForm = document.getElementById('patient-form');
    if (patientForm) {
        patientForm.addEventListener('submit', handlePatientFormSubmission);
    }
    
    // Modal close events
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Keyboard events
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

function initializePatientSearch() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        // Auto-submit search after typing stops
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.closest('form').submit();
            }, 500);
        });
    }
}

// Add patient functionality removed - patients are created automatically from financial management

function editPatient(patientId) {
    // Get patient data from the table row
    const patientRow = document.querySelector(`[data-patient-id=\"${patientId}\"]`);
    const patientName = patientRow.querySelector('.patient-name-cell').textContent.trim();
    const phone = patientRow.children[3].textContent;
    const email = patientRow.children[4].textContent;
    
    // Set modal for editing
    document.getElementById('modal-title').textContent = 'Edit Patient';
    document.getElementById('form-action').value = 'update_patient';
    document.getElementById('patient-id-input').value = patientId;
    document.getElementById('save-patient-btn').innerHTML = '<i class=\"fas fa-save\"></i> Update Patient';
    
    // Load patient data
    document.getElementById('patient-name').value = patientName.replace('👤', '').trim();
    document.getElementById('patient-phone').value = phone !== '-' ? phone : '';
    document.getElementById('patient-email').value = email !== '-' ? email : '';
    
    // Show modal
    document.getElementById('patient-modal').classList.remove('hidden');
    document.getElementById('patient-name').focus();
}

function deletePatient(patientId, patientName) {
    // Set patient details in modal
    document.getElementById('delete-patient-name').textContent = patientName;
    document.getElementById('delete-patient-id').value = patientId;
    
    // Show the delete confirmation modal
    document.getElementById('delete-confirmation-modal').classList.remove('hidden');
}

function confirmDeletePatient() {
    // Submit the delete form
    document.getElementById('delete-patient-form').submit();
}

function closeDeleteModal() {
    // Hide the delete confirmation modal
    document.getElementById('delete-confirmation-modal').classList.add('hidden');
}

function viewPatientDetails(patientId) {
    showLoading();
    
    // Fetch patient details via AJAX
    fetch(`patients.php?patient_id=${patientId}`)
        .then(response => response.text())
        .then(html => {
            // Parse the response to extract patient data
            // For now, we'll create a placeholder
            displayPatientDetails(patientId);
            hideLoading();
        })
        .catch(error => {
            console.error('Error fetching patient details:', error);
            hideLoading();
            showNotification('Error loading patient details', 'error');
        });
}

function displayPatientDetails(patientId) {
    const patientCard = document.querySelector(`[data-patient-id=\"${patientId}\"]`);
    const patientName = patientCard.querySelector('.patient-name').textContent;
    const stats = patientCard.querySelectorAll('.stat-value');
    
    const detailsHTML = `
        <div class=\"patient-details-content\">
            <div class=\"patient-overview\">
                <div class=\"overview-header\">
                    <div class=\"patient-avatar-large\">
                        <i class=\"fas fa-user-circle\"></i>
                    </div>
                    <div class=\"patient-summary\">
                        <h3>${patientName}</h3>
                        <div class=\"summary-stats\">
                            <span><i class=\"fas fa-calendar-check\"></i> ${stats[0].textContent} Visits</span>
                            <span><i class=\"fas fa-money-bill-wave\"></i> ${stats[1].textContent} Total</span>
                            <span><i class=\"fas fa-clock\"></i> Last visit: ${stats[2].textContent}</span>
                        </div>
                    </div>
                    <div class=\"overview-actions\">
                        <button type=\"button\" class=\"btn btn-primary\" onclick=\"exportPatientData(${patientId}, '${patientName}')\">
                            <i class=\"fas fa-download\"></i> Export PDF
                        </button>
                    </div>
                </div>
            </div>
            
            <div class=\"treatment-history\">
                <h4><i class=\"fas fa-history\"></i> Treatment History</h4>
                <div class=\"treatment-timeline\">
                    <div class=\"timeline-item\">
                        <div class=\"timeline-date\">Loading treatment history...</div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('patient-details-content').innerHTML = detailsHTML;
    document.getElementById('patient-details-modal').classList.remove('hidden');
}

function closePatientModal() {
    document.getElementById('patient-modal').classList.add('hidden');
}

function closePatientDetailsModal() {
    document.getElementById('patient-details-modal').classList.add('hidden');
}

function closeAllModals() {
    closePatientModal();
    closePatientDetailsModal();
    closeDeleteModal();
}

function handlePatientFormSubmission(e) {
    e.preventDefault();
    
    // Validate form
    if (!validatePatientForm()) {
        return false;
    }
    
    // Show loading
    showLoading();
    
    // Submit form
    e.target.submit();
}

function validatePatientForm() {
    const patientName = document.getElementById('patient-name');
    const patientEmail = document.getElementById('patient-email');
    const patientPhone = document.getElementById('patient-phone');
    
    let isValid = true;
    
    // Validate name
    if (!patientName.value.trim()) {
        patientName.classList.add('error');
        isValid = false;
    } else {
        patientName.classList.remove('error');
    }
    
    // Validate email if provided
    if (patientEmail.value && !validateEmail(patientEmail.value)) {
        patientEmail.classList.add('error');
        showNotification('Please enter a valid email address', 'error');
        isValid = false;
    } else {
        patientEmail.classList.remove('error');
    }
    
    // Validate phone if provided
    if (patientPhone.value && !validatePhone(patientPhone.value)) {
        patientPhone.classList.add('error');
        showNotification('Please enter a valid phone number', 'error');
        isValid = false;
    } else {
        patientPhone.classList.remove('error');
    }
    
    return isValid;
}

function exportPatientData(patientId, patientName) {
    showLoading();
    
    // Get patient data from the DOM
    const patientCard = document.querySelector(`[data-patient-id=\"${patientId}\"]`);
    const stats = patientCard.querySelectorAll('.stat-value');
    const contactInfo = patientCard.querySelector('.patient-contact');
    
    // Create patient report HTML
    const reportHTML = generatePatientReportHTML({
        id: patientId,
        name: patientName,
        visits: stats[0].textContent,
        totalSpent: stats[1].textContent,
        lastVisit: stats[2].textContent,
        contact: contactInfo ? contactInfo.textContent : ''
    });
    
    // Create and download PDF
    downloadPatientReport(reportHTML, patientName);
    
    hideLoading();
    showNotification(`Patient report for ${patientName} exported successfully!`, 'success');
}

function generatePatientReportHTML(patient) {
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Patient Report - ${patient.name}</title>
            <meta charset=\"utf-8\">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    max-width: 800px; 
                    margin: 0 auto; 
                    padding: 20px; 
                    line-height: 1.6;
                    color: #333;
                }
                .header { 
                    text-align: center; 
                    border-bottom: 3px solid #2563eb; 
                    padding-bottom: 20px; 
                    margin-bottom: 30px; 
                }
                .header h1 { 
                    color: #2563eb; 
                    margin: 0; 
                    font-size: 2.5em;
                }
                .header h2 { 
                    color: #64748b; 
                    margin: 10px 0 0 0; 
                    font-weight: normal;
                }
                .patient-info {
                    background: #f8fafc;
                    padding: 20px;
                    border-radius: 10px;
                    margin-bottom: 30px;
                }
                .patient-info h3 {
                    margin-top: 0;
                    color: #2563eb;
                    font-size: 1.5em;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin-top: 15px;
                }
                .info-item {
                    padding: 15px;
                    background: white;
                    border-radius: 8px;
                    border-left: 4px solid #2563eb;
                }
                .info-item label {
                    display: block;
                    font-weight: bold;
                    color: #64748b;
                    margin-bottom: 5px;
                    font-size: 0.9em;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .info-item value {
                    display: block;
                    font-size: 1.2em;
                    color: #1e293b;
                    font-weight: 600;
                }
                .statistics {
                    margin: 30px 0;
                }
                .statistics h3 {
                    color: #2563eb;
                    border-bottom: 2px solid #e2e8f0;
                    padding-bottom: 10px;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    margin-top: 20px;
                }
                .stat-card {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: white;
                    padding: 20px;
                    border-radius: 10px;
                    text-align: center;
                }
                .stat-card .stat-number {
                    font-size: 2em;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .stat-card .stat-label {
                    font-size: 0.9em;
                    opacity: 0.9;
                }
                .treatment-history {
                    margin: 30px 0;
                }
                .treatment-history h3 {
                    color: #2563eb;
                    border-bottom: 2px solid #e2e8f0;
                    padding-bottom: 10px;
                }
                .treatment-placeholder {
                    background: #f8fafc;
                    padding: 30px;
                    border-radius: 10px;
                    text-align: center;
                    color: #64748b;
                    border: 2px dashed #e2e8f0;
                }
                .footer { 
                    margin-top: 50px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #64748b;
                    border-top: 1px solid #e2e8f0;
                    padding-top: 20px;
                }
                .footer .generated-info {
                    background: #f1f5f9;
                    padding: 10px;
                    border-radius: 5px;
                    margin-top: 10px;
                }
                @media print {
                    body { margin: 0; padding: 15px; }
                    .header h1 { font-size: 2em; }
                }
            </style>
        </head>
        <body>
            <div class=\"header\">
                <h1>🦷 DENTAL PRACTICE</h1>
                <h2>Patient Treatment Report</h2>
            </div>
            
            <div class=\"patient-info\">
                <h3>Patient Information</h3>
                <div class=\"info-grid\">
                    <div class=\"info-item\">
                        <label>Patient Name</label>
                        <value>${patient.name}</value>
                    </div>
                    <div class=\"info-item\">
                        <label>Patient ID</label>
                        <value>#${patient.id.toString().padStart(6, '0')}</value>
                    </div>
                    <div class=\"info-item\">
                        <label>Contact Information</label>
                        <value>${patient.contact || 'Not provided'}</value>
                    </div>
                    <div class=\"info-item\">
                        <label>Last Visit</label>
                        <value>${patient.lastVisit}</value>
                    </div>
                </div>
            </div>
            
            <div class=\"statistics\">
                <h3>Treatment Statistics</h3>
                <div class=\"stats-grid\">
                    <div class=\"stat-card\">
                        <div class=\"stat-number\">${patient.visits}</div>
                        <div class=\"stat-label\">Total Visits</div>
                    </div>
                    <div class=\"stat-card\">
                        <div class=\"stat-number\">${patient.totalSpent}</div>
                        <div class=\"stat-label\">Total Amount</div>
                    </div>
                    <div class=\"stat-card\">
                        <div class=\"stat-number\">${patient.lastVisit}</div>
                        <div class=\"stat-label\">Last Visit</div>
                    </div>
                </div>
            </div>
            
            <div class=\"treatment-history\">
                <h3>Treatment History</h3>
                <div class=\"treatment-placeholder\">
                    <i class=\"fas fa-history\" style=\"font-size: 2em; margin-bottom: 10px; display: block;\"></i>
                    <h4>Treatment History Available</h4>
                    <p>Detailed treatment records and receipt history for this patient are maintained in the system database. Contact the practice administration for complete treatment timeline and detailed service records.</p>
                </div>
            </div>
            
            <div class=\"footer\">
                <p><strong>Confidential Medical Report</strong></p>
                <p>This report contains confidential patient information and should be handled according to medical privacy regulations.</p>
                <div class=\"generated-info\">
                    <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
                    <p><strong>System:</strong> Dental Practice Management System v1.0</p>
                </div>
            </div>
        </body>
        </html>
    `;
}

function downloadPatientReport(html, patientName) {
    // Create a blob with the HTML content
    const blob = new Blob([html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    
    // Create download link
    const a = document.createElement('a');
    a.href = url;
    a.download = `Patient_Report_${patientName.replace(/[^a-z0-9]/gi, '_')}_${new Date().toISOString().split('T')[0]}.html`;
    a.style.display = 'none';
    
    // Trigger download
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // Clean up
    URL.revokeObjectURL(url);
}

function exportAllPatients() {
    showLoading();
    
    // Show export options modal
    showExportOptionsModal();
}

function showExportOptionsModal() {
    const modalHTML = `
        <div id="export-options-modal" class="modal">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <h2><i class="fas fa-file-export"></i> Export Options</h2>
                    <button type="button" class="modal-close" onclick="closeExportModal()" style="color: white;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 30px; text-align: center;">
                    <h3 style="margin-bottom: 20px;">Choose Export Type</h3>
                    <div style="display: grid; gap: 15px;">
                        <button type="button" class="btn btn-primary" onclick="exportBasicPatients()">
                            <i class="fas fa-users"></i> Patient Summary Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportFinancialBreakdown()">
                            <i class="fas fa-chart-line"></i> Financial Breakdown Report
                        </button>
                        <button type="button" class="btn btn-info" onclick="exportDoctorPayments()">
                            <i class="fas fa-user-md"></i> Doctor Payment Report  
                        </button>
                        <button type="button" class="btn btn-warning" onclick="exportClinicRevenue()">
                            <i class="fas fa-building"></i> Clinic Revenue Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    hideLoading();
}

function closeExportModal() {
    const modal = document.getElementById('export-options-modal');
    if (modal) {
        modal.remove();
    }
}

function exportBasicPatients() {
    closeExportModal();
    showLoading();
    
    // Get all patient data
    const patientCards = document.querySelectorAll('.patient-card');
    const patients = [];
    
    patientCards.forEach(card => {
        const name = card.querySelector('.patient-name').textContent;
        const id = card.dataset.patientId;
        const stats = card.querySelectorAll('.stat-value');
        const contact = card.querySelector('.patient-contact');
        
        patients.push({
            id: id,
            name: name,
            visits: stats[0].textContent,
            totalSpent: stats[1].textContent,
            lastVisit: stats[2].textContent,
            contact: contact ? contact.textContent : ''
        });
    });
    
    // Generate CSV report
    const csvData = generatePatientsCSV(patients);
    downloadCSV(csvData, `Patient_Summary_${new Date().toISOString().split('T')[0]}.csv`);
    
    hideLoading();
    showNotification(`Patient summary exported successfully!`, 'success');
}

function exportFinancialBreakdown() {
    closeExportModal();
    showLoading();
    
    // Fetch detailed financial data from server
    fetch('export_financial_breakdown.php')
        .then(response => response.json())
        .then(data => {
            const csvData = generateFinancialBreakdownCSV(data);
            downloadCSV(csvData, `Financial_Breakdown_${new Date().toISOString().split('T')[0]}.csv`);
            hideLoading();
            showNotification(`Financial breakdown exported successfully!`, 'success');
        })
        .catch(error => {
            hideLoading();
            showNotification('Export failed. Please try again.', 'error');
        });
}

function exportDoctorPayments() {
    closeExportModal();
    showLoading();
    
    // Fetch doctor payment data from server
    fetch('export_doctor_payments.php')
        .then(response => response.json())
        .then(data => {
            const csvData = generateDoctorPaymentsCSV(data);
            downloadCSV(csvData, `Doctor_Payments_${new Date().toISOString().split('T')[0]}.csv`);
            hideLoading();
            showNotification(`Doctor payments report exported successfully!`, 'success');
        })
        .catch(error => {
            hideLoading();
            showNotification('Export failed. Please try again.', 'error');
        });
}

function exportClinicRevenue() {
    closeExportModal();
    showLoading();
    
    // Fetch clinic revenue data from server
    fetch('export_clinic_revenue.php')
        .then(response => response.json())
        .then(data => {
            const csvData = generateClinicRevenueCSV(data);
            downloadCSV(csvData, `Clinic_Revenue_${new Date().toISOString().split('T')[0]}.csv`);
            hideLoading();
            showNotification(`Clinic revenue report exported successfully!`, 'success');
        })
        .catch(error => {
            hideLoading();
            showNotification('Export failed. Please try again.', 'error');
        });
}

function generatePatientsCSV(patients) {
    const headers = ['Patient ID', 'Name', 'Total Visits', 'Total Spent', 'Last Visit', 'Contact Info'];
    let csv = headers.join(',') + '\\n';
    
    patients.forEach(patient => {
        const row = [
            `#${patient.id.padStart(6, '0')}`,
            `\"${patient.name}\"`,
            patient.visits,
            `\"${patient.totalSpent}\"`,
            `\"${patient.lastVisit}\"`,
            `\"${patient.contact.replace(/"/g, '""')}\"`
        ];
        csv += row.join(',') + '\\n';
    });
    
    return csv;
}

function generateFinancialBreakdownCSV(data) {
    const headers = ['Date', 'Invoice', 'Patient Name', 'Service Type', 'Doctor Fee', 'Material Fee', 'Payment Method', 'Total Amount'];
    let csv = headers.join(',') + '\\n';
    
    data.forEach(record => {
        const row = [
            record.invoice_date,
            record.invoice_number,
            `\"${record.patient_name}\"`,
            `\"${record.service_types}\"`,
            `RM ${record.doctor_fee}`,
            `RM ${record.material_fee}`,
            record.payment_method,
            `RM ${record.total_amount}`
        ];
        csv += row.join(',') + '\\n';
    });
    
    return csv;
}

function generateDoctorPaymentsCSV(data) {
    const headers = ['Date', 'Invoice', 'Patient Name', 'Service Type', 'Doctor Fee', 'Payment Method'];
    let csv = headers.join(',') + '\\n';
    
    data.forEach(record => {
        const row = [
            record.invoice_date,
            record.invoice_number,
            `\"${record.patient_name}\"`,
            `\"${record.service_types}\"`,
            `RM ${record.doctor_fee}`,
            record.payment_method
        ];
        csv += row.join(',') + '\\n';
    });
    
    // Add summary row
    const totalDoctorFees = data.reduce((sum, record) => sum + parseFloat(record.doctor_fee), 0);
    csv += `\\n\"TOTAL DOCTOR PAYMENTS:\",\"\",\"\",\"\",\"RM ${totalDoctorFees.toFixed(2)}\",\"\"\\n`;
    
    return csv;
}

function generateClinicRevenueCSV(data) {
    const headers = ['Date', 'Invoice', 'Patient Name', 'Service Type', 'Material Fee', 'Other Charges', 'Payment Fees', 'Clinic Revenue'];
    let csv = headers.join(',') + '\\n';
    
    data.forEach(record => {
        const clinicRevenue = parseFloat(record.material_fee) + parseFloat(record.other_charges) + parseFloat(record.payment_fee_amount);
        const row = [
            record.invoice_date,
            record.invoice_number,
            `\"${record.patient_name}\"`,
            `\"${record.service_types}\"`,
            `RM ${record.material_fee}`,
            `RM ${record.other_charges}`,
            `RM ${record.payment_fee_amount}`,
            `RM ${clinicRevenue.toFixed(2)}`
        ];
        csv += row.join(',') + '\\n';
    });
    
    // Add summary row
    const totalClinicRevenue = data.reduce((sum, record) => {
        return sum + parseFloat(record.material_fee) + parseFloat(record.other_charges) + parseFloat(record.payment_fee_amount);
    }, 0);
    csv += `\\n\"TOTAL CLINIC REVENUE:\",\"\",\"\",\"\",\"\",\"\",\"\",\"RM ${totalClinicRevenue.toFixed(2)}\"\\n`;
    
    return csv;
}

// Utility functions (if not already defined in dashboard.js)
function validateEmail(email) {
    const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9+\\-\\s()]+$/;
    return phoneRegex.test(phone) && phone.length >= 10;
}

function downloadCSV(csvData, filename) {
    // Create blob with CSV data
    const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
    
    // Create download link
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Clean up
    URL.revokeObjectURL(url);
}