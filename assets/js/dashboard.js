// Dashboard JavaScript Functions
// Professional dental practice management system

document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Auto-refresh dashboard data every 5 minutes
    setInterval(refreshDashboardData, 300000);
    
    // Initialize tooltips and interactive elements
    initializeInteractiveElements();
    
    // Set current date in forms
    setCurrentDate();
    
    console.log('Dashboard initialized successfully');
}

function refreshDashboardData() {
    // Auto-refresh dashboard statistics
    const currentPage = window.location.pathname.split('/').pop();
    
    if (currentPage === 'index.php' || currentPage === '') {
        // Only refresh if on dashboard page
        showLoading();
        
        fetch('api/dashboard_stats.php')
            .then(response => response.json())
            .then(data => {
                updateDashboardCards(data);
                hideLoading();
            })
            .catch(error => {
                console.error('Error refreshing dashboard:', error);
                hideLoading();
            });
    }
}

function updateDashboardCards(data) {
    // Update revenue cards with new data
    if (data.today_revenue) {
        const todayRevenueCard = document.querySelector('.dashboard-card .card-value');
        if (todayRevenueCard) {
            todayRevenueCard.textContent = `RM ${parseFloat(data.today_revenue).toFixed(2)}`;
        }
    }
    
    // Update other stats as needed
    if (data.month_revenue) {
        const monthRevenueCards = document.querySelectorAll('.dashboard-card .card-value');
        if (monthRevenueCards[1]) {
            monthRevenueCards[1].textContent = `RM ${parseFloat(data.month_revenue).toFixed(2)}`;
        }
    }
}

function initializeInteractiveElements() {
    // Add hover effects and interactions
    const dashboardCards = document.querySelectorAll('.dashboard-card');
    
    dashboardCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Initialize quick action buttons
    const quickActionButtons = document.querySelectorAll('.quick-actions .btn');
    quickActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Add loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            this.disabled = true;
            
            // Re-enable after navigation (fallback)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 2000);
        });
    });
}

function setCurrentDate() {
    // Set today's date in date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    const today = new Date().toISOString().split('T')[0];
    
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Search functionality for tables
function searchTable(searchInputId, tableId) {
    const searchInput = document.getElementById(searchInputId);
    const table = document.getElementById(tableId);
    
    if (!searchInput || !table) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        Array.from(rows).forEach(row => {
            const textContent = row.textContent.toLowerCase();
            const shouldShow = textContent.includes(searchTerm);
            row.style.display = shouldShow ? '' : 'none';
        });
    });
}

// Export functionality
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            return `"${col.textContent.replace(/"/g, '""')}"`;
        });
        csv.push(rowData.join(','));
    });
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2
    }).format(amount);
}

// Validation helpers
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9+\-\s()]+$/;
    return phoneRegex.test(phone) && phone.length >= 10;
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validate email fields
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !validateEmail(field.value)) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validate phone fields
    const phoneFields = form.querySelectorAll('input[type="tel"]');
    phoneFields.forEach(field => {
        if (field.value && !validatePhone(field.value)) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Auto-save functionality for forms
function initializeAutoSave(formId, saveInterval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    setInterval(() => {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Save to localStorage as backup
        localStorage.setItem(`autosave_${formId}`, JSON.stringify({
            data: data,
            timestamp: new Date().getTime()
        }));
        
        console.log(`Form ${formId} auto-saved`);
    }, saveInterval);
}

// Load auto-saved data
function loadAutoSavedData(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const saved = localStorage.getItem(`autosave_${formId}`);
    if (!saved) return;
    
    try {
        const { data, timestamp } = JSON.parse(saved);
        const age = new Date().getTime() - timestamp;
        
        // Only restore if less than 1 hour old
        if (age < 3600000) {
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && !field.value) {
                    field.value = data[key];
                }
            });
            
            showNotification('Auto-saved data restored', 'info');
        }
    } catch (error) {
        console.error('Error loading auto-saved data:', error);
    }
}