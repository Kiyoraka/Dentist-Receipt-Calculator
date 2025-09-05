<?php
require_once '../config/database.php';
require_once '../config/config.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Handle AJAX requests FIRST, before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'get_patient') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['patient_id'])) {
        echo json_encode(['error' => 'Patient ID required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$_GET['patient_id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            echo json_encode($patient);
        } else {
            echo json_encode(['error' => 'Patient not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX requests for patient receipts
if (isset($_GET['action']) && $_GET['action'] === 'get_patient_receipts') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['patient_id'])) {
        echo json_encode(['error' => 'Patient ID required']);
        exit;
    }
    
    try {
        // Get receipts for the specific patient
        $stmt = $conn->prepare("SELECT * FROM receipts WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([$_GET['patient_id']]);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If we have receipts, try to get services info
        if (!empty($receipts)) {
            try {
                $stmt = $conn->prepare("
                    SELECT r.*, 
                           GROUP_CONCAT(rs.service_name SEPARATOR ', ') as services
                    FROM receipts r 
                    LEFT JOIN receipt_services rs ON r.id = rs.receipt_id 
                    WHERE r.patient_id = ? 
                    GROUP BY r.id 
                    ORDER BY r.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$_GET['patient_id']]);
                $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                // If receipt_services has issues, just use basic receipts
            }
        }
        
        echo json_encode(['receipts' => $receipts]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Regular page rendering starts here
$page_title = 'Patient Management - Dental Practice Management';

// Add financial management CSS for consistent styling
$additional_css = ['../assets/css/charge-calculator.css', CSS_URL . '/patients.css'];

require_once '../includes/header.php';

// Handle actions
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_patient') {
            $stmt = $conn->prepare("UPDATE patients SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['address'],
                $_POST['patient_id']
            ]);
            $success_message = "Patient updated successfully!";
            
        } elseif ($_POST['action'] === 'delete_patient') {
            $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->execute([$_POST['patient_id']]);
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get search term and filters
$search = $_GET['search'] ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$filter_year = $_GET['filter_year'] ?? '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get total patient count for pagination
try {
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM patients p";
    $count_params = [];
    $where_conditions = [];
    
    if ($search) {
        $where_conditions[] = "(p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
        $searchTerm = "%{$search}%";
        $count_params = array_merge($count_params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Add date filtering if month/year specified
    if ($filter_month || $filter_year) {
        $count_sql .= " LEFT JOIN receipts r ON p.id = r.patient_id";
        if ($filter_month && $filter_year) {
            $where_conditions[] = "DATE_FORMAT(r.created_at, '%m') = ? AND DATE_FORMAT(r.created_at, '%Y') = ?";
            $count_params = array_merge($count_params, [$filter_month, $filter_year]);
        } elseif ($filter_month) {
            $where_conditions[] = "DATE_FORMAT(r.created_at, '%m') = ?";
            $count_params[] = $filter_month;
        } elseif ($filter_year) {
            $where_conditions[] = "DATE_FORMAT(r.created_at, '%Y') = ?";
            $count_params[] = $filter_year;
        }
    }
    
    if (!empty($where_conditions)) {
        $count_sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get patients with their receipt counts, total spending, and fee breakdowns (with pagination)
    $sql = "
        SELECT 
            p.*,
            COUNT(r.id) as receipt_count,
            COALESCE(SUM(r.total_amount), 0) as total_spent,
            COALESCE(SUM(r.doctor_fee), 0) as total_doctor_fee,
            COALESCE(SUM(r.clinic_fee), 0) as total_clinic_fee,
            MAX(r.created_at) as last_visit
        FROM patients p 
        LEFT JOIN receipts r ON p.id = r.patient_id 
    ";
    
    $params = [];
    $where_conditions = [];
    
    if ($search) {
        $where_conditions[] = "(p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Add date filtering for receipts
    if ($filter_month && $filter_year) {
        $where_conditions[] = "DATE_FORMAT(r.created_at, '%m') = ? AND DATE_FORMAT(r.created_at, '%Y') = ?";
        $params = array_merge($params, [$filter_month, $filter_year]);
    } elseif ($filter_month) {
        $where_conditions[] = "DATE_FORMAT(r.created_at, '%m') = ?";
        $params[] = $filter_month;
    } elseif ($filter_year) {
        $where_conditions[] = "DATE_FORMAT(r.created_at, '%Y') = ?";
        $params[] = $filter_year;
    }
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.name ASC LIMIT $records_per_page OFFSET $offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
    $error_message = "Database error: " . $e->getMessage();
}

// Get patient details for modal (if requested)
$selected_patient = null;
if (isset($_GET['patient_id'])) {
    try {
        // Get patient info
        $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$_GET['patient_id']]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selected_patient) {
            // Get patient's receipts
            $stmt = $conn->prepare("
                SELECT r.*, 
                       GROUP_CONCAT(CONCAT(rs.service_name, ' (', rs.percentage, '%)') SEPARATOR ', ') as services
                FROM receipts r 
                LEFT JOIN receipt_services rs ON r.id = rs.receipt_id 
                WHERE r.patient_id = ? 
                GROUP BY r.id 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$_GET['patient_id']]);
            $selected_patient['receipts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $selected_patient = null;
    }
}
?>

<?php include '../includes/sidebar.php'; ?>

    <div class="content-header">
        <h1 class="content-title">
            <i class="fas fa-users"></i>
            Patient Management
        </h1>
        <p class="content-subtitle">Manage patients and export their treatment history</p>
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

    <!-- Patient Management Controls -->
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Patient Directory
            </h2>
            <div class="section-actions">
                <button type="button" class="btn btn-info" onclick="exportAllPatients()">
                    <i class="fas fa-file-export"></i> Export All
                </button>
            </div>
        </div>

        <!-- Search Controls -->
        <div class="search-controls">
            <form method="GET" class="search-form">
                <div class="input-group">
                    <input type="text" name="search" placeholder="Search by name, phone, or email..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    
                    <select name="filter_month" class="filter-select">
                        <option value="">All Months</option>
                        <?php
                        $months = [
                            '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                            '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                            '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                        ];
                        $selected_month = $_GET['filter_month'] ?? '';
                        foreach ($months as $num => $name) {
                            $selected = ($selected_month == $num) ? 'selected' : '';
                            echo "<option value=\"$num\" $selected>$name</option>";
                        }
                        ?>
                    </select>
                    
                    <select name="filter_year" class="filter-select">
                        <option value="">All Years</option>
                        <?php
                        $current_year = date('Y');
                        $selected_year = $_GET['filter_year'] ?? '';
                        for ($year = $current_year; $year >= 2020; $year--) {
                            $selected = ($selected_year == $year) ? 'selected' : '';
                            echo "<option value=\"$year\" $selected>$year</option>";
                        }
                        ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

                <!-- Patients Table -->
                <?php if (!empty($patients)): ?>
                <div class="charges-table-wrapper">
                    <table class="charges-table patients-table">
                        <thead class="table-header">
                            <tr>
                                <th class="patient-id-col">ID</th>
                                <th class="patient-name-col">Patient Name</th>
                                <th class="visits-col">Visits</th>
                                <th class="lastvisit-col">Last Visit</th>
                                <th class="clinic-fee-col">Clinic Fee</th>
                                <th class="doctor-fee-col">Doctor Fee</th>
                                <th class="spent-col">Total Spent</th>
                                <th class="action-col">Actions</th>
                            </tr>
                        </thead>
                    <tbody>
                        <?php 
                        // If no data but not on page 1, fill with empty rows
                        $display_count = count($patients);
                        if ($display_count == 0 && $page == 1) {
                            // Show empty state message
                            $display_count = 0;
                        } elseif ($display_count < $records_per_page) {
                            // We have some records, but less than full page
                        }
                        
                        foreach ($patients as $index => $patient): 
                        ?>
                        <tr class="charge-row" data-patient-id="<?php echo $patient['id']; ?>" style="transition: all 0.3s ease; background-color: <?php echo $index % 2 === 0 ? '#ffffff' : '#f8fafc'; ?>;" onmouseover="this.style.backgroundColor='#f0f9ff'" onmouseout="this.style.backgroundColor='<?php echo $index % 2 === 0 ? '#ffffff' : '#f8fafc'; ?>'">
                            <td style="text-align: center; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9; font-weight: bold; color: #374151;"><?php echo $patient['id']; ?></td>
                            <td class="charge-service" style="font-weight: bold; color: #2563eb; text-align: left; font-size: 15px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">
                                <i class="fas fa-user-circle" style="margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($patient['name']); ?>
                            </td>
                            <td class="charge-amount" style="text-align: center; font-weight: bold; color: #374151; font-size: 15px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;"><?php echo $patient['receipt_count']; ?></td>
                            <td style="text-align: center; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9; color: #374151;">
                                <?php 
                                if ($patient['last_visit']) {
                                    echo date('M j, Y', strtotime($patient['last_visit']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </td>
                            <td class="charge-clinic" style="text-align: center; font-weight: bold; color: #dc2626; font-size: 14px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM <?php echo number_format($patient['total_clinic_fee'], 2); ?></td>
                            <td class="charge-doctor" style="text-align: center; font-weight: bold; color: #059669; font-size: 14px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM <?php echo number_format($patient['total_doctor_fee'], 2); ?></td>
                            <td class="charge-doctor" style="text-align: center; font-weight: bold; color: #059669; font-size: 14px; padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #f1f5f9;">RM <?php echo number_format($patient['total_spent'], 2); ?></td>
                            <td class="charge-action">
                                <div class="action-buttons-row">
                                    <button type="button" class="btn-action btn-view" onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-edit" onclick="editPatient(<?php echo $patient['id']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="deletePatient(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        
                        // Fill empty rows to maintain consistent table height and eliminate white space
                        for ($i = $display_count; $i < $records_per_page; $i++):
                        ?>
                        <tr style="height: 48px;">
                            <td colspan="8" style="background: transparent; border: none;">&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                </div>
                
                <!-- Pagination Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f9fafb; border-top: 2px solid #e5e7eb; margin-top: -1px;">
                    <div style="color: #6b7280; font-size: 14px;">
                        <?php if($total_records > 0): ?>
                            Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $records_per_page, $total_records); ?></strong> of <strong><?php echo $total_records; ?></strong> patients
                        <?php else: ?>
                            No patients to display
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <?php 
                        // Build query parameters for pagination links
                        $query_params = [];
                        if (!empty($search)) $query_params[] = 'search=' . urlencode($search);
                        if (!empty($filter_month)) $query_params[] = 'filter_month=' . urlencode($filter_month);
                        if (!empty($filter_year)) $query_params[] = 'filter_year=' . urlencode($filter_year);
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
        <?php else: ?>
            <div class="empty-state" style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-users" style="font-size: 48px; opacity: 0.3; margin-bottom: 20px;"></i>
                <h3>No patients found</h3>
                <p><?php echo $search ? 'Try adjusting your search criteria' : 'Add your first patient to get started'; ?></p>
                <p style="color: #999; font-size: 14px;">Patients are automatically added when processing receipts in Financial Management.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Patient Modal -->
    <div id="patient-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Edit Patient</h2>
                <button type="button" class="modal-close" onclick="closePatientModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="patient-form" method="POST">
                <input type="hidden" name="action" id="form-action" value="update_patient">
                <input type="hidden" name="patient_id" id="patient-id-input">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient-name">Full Name *</label>
                        <input type="text" id="patient-name" name="name" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient-phone">Phone Number</label>
                        <input type="tel" id="patient-phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="patient-email">Email Address</label>
                        <input type="email" id="patient-email" name="email">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient-address">Address</label>
                        <textarea id="patient-address" name="address" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePatientModal()">Cancel</button>
                    <button type="submit" class="btn btn-success" id="save-patient-btn">
                        <i class="fas fa-save"></i> Update Patient
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patient-details-modal" class="modal hidden">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Patient Details</h2>
                <button type="button" class="modal-close" onclick="closePatientDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="patient-details-content">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-confirmation-modal" class="modal hidden">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
                <button type="button" class="modal-close" onclick="closeDeleteModal()" style="color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px; text-align: center;">
                <i class="fas fa-user-times" style="font-size: 3em; color: #ef4444; margin-bottom: 20px;"></i>
                <p style="font-size: 16px; margin-bottom: 10px;">Are you sure you want to delete patient:</p>
                <h3 id="delete-patient-name" style="color: #1e293b; margin: 10px 0;"></h3>
                <div class="delete-patient-info" style="background: #f8fafc; padding: 16px; border-radius: 8px; margin: 16px 0; border: 1px solid #e2e8f0;">
                    <p style="color: #64748b; margin: 0;">Patient information will be loaded here</p>
                </div>
                <p style="color: #ef4444; font-weight: 500; margin-top: 15px;">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
                </p>
                <p style="color: #64748b; font-size: 14px; margin-top: 10px;">
                    All associated receipts and records will be permanently removed.
                </p>
            </div>
            <form id="delete-patient-form" method="POST" style="display: none;">
                <input type="hidden" name="action" value="delete_patient">
                <input type="hidden" name="patient_id" id="delete-patient-id">
            </form>
            <div class="modal-actions" style="justify-content: center; gap: 10px; padding: 20px; background: #f9fafb;">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmDeletePatient()">
                    <i class="fas fa-trash"></i> Delete Patient
                </button>
            </div>
        </div>
    </div>

<?php 
$additional_js = ['../assets/js/patients.js'];
include '../includes/footer.php'; 
?>