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
            // Close specific modals without notifications
            if (e.target.id === 'patient-details-modal') {
                closePatientDetailsModal();
            } else if (e.target.id === 'patient-modal') {
                closePatientModal();
            } else if (e.target.id === 'delete-confirmation-modal') {
                closeDeleteModal();
            } else {
                closeAllModalsQuietly();
            }
        }
    });
    
    // Keyboard events
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModalsQuietly();
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
    showLoading();
    
    // Fetch patient details via AJAX for accurate data
    fetch(`patients.php?action=get_patient&patient_id=${patientId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(patient => {
            hideLoading();
            
            if (patient.error) {
                showNotification(patient.error, 'error');
                return;
            }
            
            // Set modal for editing
            document.getElementById('modal-title').textContent = 'Edit Patient Information';
            document.getElementById('form-action').value = 'update_patient';
            document.getElementById('patient-id-input').value = patientId;
            document.getElementById('save-patient-btn').innerHTML = '<i class="fas fa-save"></i> Update Patient';
            
            // Load patient data
            document.getElementById('patient-name').value = patient.name || '';
            document.getElementById('patient-phone').value = patient.phone || '';
            document.getElementById('patient-email').value = patient.email || '';
            document.getElementById('patient-address').value = patient.address || '';
            
            // Show modal with smooth animation
            document.getElementById('patient-modal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('patient-name').focus();
            }, 100);
            
            showNotification('Patient information loaded successfully', 'success');
        })
        .catch(error => {
            hideLoading();
            showNotification('Error loading patient information: ' + error.message, 'error');
        });
}

function deletePatient(patientId, patientName) {
    // Set patient details in modal with enhanced confirmation info
    document.getElementById('delete-patient-name').textContent = patientName;
    document.getElementById('delete-patient-id').value = patientId;
    
    // Add patient ID to confirmation for extra clarity
    const patientInfo = document.querySelector('.delete-patient-info');
    if (patientInfo) {
        patientInfo.innerHTML = `
            <strong>Patient ID:</strong> #${patientId.toString().padStart(6, '0')}<br>
            <strong>Patient Name:</strong> ${patientName}
        `;
    }
    
    // Show the delete confirmation modal with smooth animation
    document.getElementById('delete-confirmation-modal').classList.remove('hidden');
    
    showNotification('Please confirm patient deletion', 'info');
}

function confirmDeletePatient() {
    showLoading();
    
    const patientId = document.getElementById('delete-patient-id').value;
    const patientName = document.getElementById('delete-patient-name').textContent;
    
    // Show confirmation message
    showNotification(`Deleting patient: ${patientName}...`, 'info');
    
    // Submit the delete form
    document.getElementById('delete-patient-form').submit();
}

function closeDeleteModal() {
    // Hide the delete confirmation modal with smooth animation
    const modal = document.getElementById('delete-confirmation-modal');
    modal.classList.add('hidden');
    
    showNotification('Deletion cancelled', 'info');
}

function closeDeleteModalQuietly() {
    // Hide the delete confirmation modal without notification
    const modal = document.getElementById('delete-confirmation-modal');
    modal.classList.add('hidden');
}

function viewPatientDetails(patientId) {
    showLoading();
    
    // Get patient data directly from the current page table
    const patientRow = document.querySelector(`[data-patient-id="${patientId}"]`);
    
    if (patientRow) {
        displayPatientDetails(patientId);
        hideLoading();
    } else {
        hideLoading();
        showNotification('Patient information not found on page', 'error');
    }
}

function displayPatientDetails(patientId) {
    const patientRow = document.querySelector(`[data-patient-id="${patientId}"]`);
    
    if (!patientRow) {
        showNotification('Patient information not found', 'error');
        return;
    }
    
    // Extract data from table row - correct structure
    const cells = patientRow.querySelectorAll('td');
    const patientName = cells[1].textContent.replace('👤', '').trim();  // Patient name cell
    const visits = cells[2].textContent.trim();  // Visits
    const lastVisit = cells[3].textContent.trim();  // Last Visit
    const clinicFee = cells[4].textContent.trim();  // Clinic Fee
    const doctorFee = cells[5].textContent.trim();  // Doctor Fee
    const totalSpent = cells[6].textContent.trim();  // Total Spent
    
    // Fetch actual treatment records for this patient
    loadTreatmentRecords(patientId, patientName, visits, lastVisit, totalSpent);
}

function loadTreatmentRecords(patientId, patientName, visits, lastVisit, totalSpent) {
    // Fetch patient receipts via AJAX
    fetch(`patients.php?action=get_patient_receipts&patient_id=${patientId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const receipts = data.receipts || [];
            displayPatientWithRecords(patientId, patientName, visits, lastVisit, totalSpent, receipts);
        })
        .catch(error => {
            showNotification('Error loading treatment records', 'error');
            displayPatientWithRecords(patientId, patientName, visits, lastVisit, totalSpent, []);
        });
}

function displayPatientWithRecords(patientId, patientName, visits, lastVisit, totalSpent, receipts) {
    const detailsHTML = `
        <div class="patient-details-content">
            <div class="patient-overview">
                <div class="overview-header">
                    <div class="patient-avatar-large">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="patient-summary">
                        <h3>${patientName}</h3>
                        <p style="color: #64748b; margin-bottom: 16px;">Patient ID: #${patientId.toString().padStart(6, '0')}</p>
                        <div class="summary-stats">
                            <span><i class="fas fa-calendar-check"></i> ${visits} Total Visits</span>
                            <span><i class="fas fa-clock"></i> Last Visit: ${lastVisit}</span>
                            <span><i class="fas fa-money-bill-wave"></i> Total Spent: ${totalSpent}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="treatment-history">
                <h4><i class="fas fa-history"></i> Treatment Records</h4>
                <div class="treatment-list">
                    ${receipts.length > 0 ? generateReceiptsList(receipts) : generateNoRecordsMessage()}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('patient-details-content').innerHTML = detailsHTML;
    document.getElementById('patient-details-modal').classList.remove('hidden');
    
    // Show appropriate notification based on receipt count
    if (receipts.length > 0) {
        showNotification(`Loaded ${receipts.length} treatment records for ${patientName}`, 'success');
    } else {
        showNotification(`Patient details loaded: ${patientName}`, 'info');
    }
}

function generateReceiptsList(receipts) {
    return `
        <div class="receipts-table" style="background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
            <div class="receipts-header" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; padding: 16px 20px; font-weight: 600;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; align-items: center;">
                    <div>Receipt #</div>
                    <div>Date</div>
                    <div>Services</div>
                    <div>Amount</div>
                </div>
            </div>
            <div class="receipts-body">
                ${receipts.map(receipt => `
                    <div class="receipt-row" style="padding: 16px 20px; border-bottom: 1px solid #f1f5f9; transition: all 0.2s ease;" 
                         onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='white'">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; align-items: center;">
                            <div style="font-weight: 600; color: #2563eb;">#${receipt.invoice_number || receipt.id}</div>
                            <div style="color: #64748b;">${formatDate(receipt.created_at)}</div>
                            <div style="color: #374151; font-size: 14px;">${receipt.services || 'Treatment'}</div>
                            <div style="font-weight: 700; color: #059669;">RM ${parseFloat(receipt.total_amount || 0).toFixed(2)}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function generateNoRecordsMessage() {
    return `
        <div class="no-records" style="text-align: center; padding: 40px 20px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px;">
            <i class="fas fa-file-medical-alt" style="font-size: 3rem; color: #94a3b8; margin-bottom: 16px;"></i>
            <h5 style="margin-bottom: 8px; color: #64748b;">No Treatment Records Found</h5>
            <p style="color: #9ca3af; margin: 0;">This patient has no recorded treatments yet. Records will appear here once treatments are processed through the Financial Management system.</p>
        </div>
    `;
}

function formatDate(dateString) {
    if (!dateString) return 'Unknown';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
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

function closeAllModalsQuietly() {
    closePatientModal();
    closePatientDetailsModal();
    closeDeleteModalQuietly();
}

function handlePatientFormSubmission(e) {
    e.preventDefault();
    
    // Validate form
    if (!validatePatientForm()) {
        return false;
    }
    
    // Show loading
    showLoading();
    
    // Get form data
    const formData = new FormData(e.target);
    const patientName = formData.get('name');
    
    // Submit via AJAX instead of form submission
    fetch('patients.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        hideLoading();
        closePatientModal();
        showNotification(`Patient "${patientName}" updated successfully!`, 'success');
        
        // Reload the page to show updated data
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    })
    .catch(error => {
        hideLoading();
        showNotification('Error updating patient', 'error');
    });
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
    
    // Validate email if provided and not empty
    if (patientEmail.value.trim() && !validateEmail(patientEmail.value.trim())) {
        patientEmail.classList.add('error');
        showNotification('Please enter a valid email address', 'error');
        isValid = false;
    } else {
        patientEmail.classList.remove('error');
    }
    
    // Validate phone if provided and not empty
    if (patientPhone.value.trim() && !validatePhone(patientPhone.value.trim())) {
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
    
    // Remove any existing modal first
    const existingModal = document.getElementById('export-options-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal element
    const modal = document.createElement('div');
    modal.id = 'export-options-modal';
    modal.className = 'modal';
    modal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0;"><i class="fas fa-file-export"></i> Export Options</h2>
                <button type="button" onclick="closeExportModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px; text-align: center;">
                <h3 style="margin-bottom: 30px; color: #333;">Choose Export Type</h3>
                <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    <button type="button" onclick="exportAllReceipts()" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; border: none; font-size: 16px; padding: 20px 30px; border-radius: 8px; cursor: pointer; flex: 1; min-width: 200px;">
                        <i class="fas fa-file-pdf"></i> 📄 Export PDF Report
                    </button>
                    <button type="button" onclick="exportToExcel()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; font-size: 16px; padding: 20px 30px; border-radius: 8px; cursor: pointer; flex: 1; min-width: 200px;">
                        <i class="fas fa-file-excel"></i> 📊 Export to Excel
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
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
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9+\-\s()]+$/;
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

// New Comprehensive Export Functions
function exportAllReceipts() {
    closeExportModal();
    showLoading();
    
    showNotification('Opening comprehensive receipts report with pie chart...', 'info');
    
    // Get current filter values
    const filterMonth = document.querySelector('select[name="filter_month"]')?.value || '';
    const filterYear = document.querySelector('select[name="filter_year"]')?.value || '';
    
    // Build export URL with filters
    let exportUrl = 'export-all.php';
    const params = new URLSearchParams();
    
    if (filterMonth) params.append('filter_month', filterMonth);
    if (filterYear) params.append('filter_year', filterYear);
    
    if (params.toString()) {
        exportUrl += '?' + params.toString();
    }
    
    // Open export-all.php in new tab with filters
    window.open(exportUrl, '_blank');
    
    hideLoading();
}

function exportToExcel() {
    closeExportModal();
    showLoading();
    
    showNotification('Generating Excel report...', 'info');
    
    // Get current filter values
    const filterMonth = document.querySelector('select[name="filter_month"]')?.value || '';
    const filterYear = document.querySelector('select[name="filter_year"]')?.value || '';
    
    // Build export URL with filters
    let exportUrl = 'export-excel.php';
    const params = new URLSearchParams();
    
    if (filterMonth) params.append('filter_month', filterMonth);
    if (filterYear) params.append('filter_year', filterYear);
    
    if (params.toString()) {
        exportUrl += '?' + params.toString();
    }
    
    // Create a hidden iframe to trigger download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Remove iframe after download starts
    setTimeout(() => {
        document.body.removeChild(iframe);
        hideLoading();
        showNotification('Excel file downloaded successfully!', 'success');
    }, 2000);
}

