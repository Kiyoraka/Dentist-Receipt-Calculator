<?php
$page_title = 'Patient Management - Dental Practice Management';
require_once '../config/database.php';
require_once '../config/config.php';

// Add patient-specific CSS
$additional_css = [CSS_URL . '/patients.css'];

require_once '../includes/header.php';

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Handle actions
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_patient') {
            $stmt = $conn->prepare("INSERT INTO patients (name, phone, email, address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['address']
            ]);
            $success_message = "Patient added successfully!";
            
        } elseif ($_POST['action'] === 'update_patient') {
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
            $success_message = "Patient deleted successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get search term
$search = $_GET['search'] ?? '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get total patient count for pagination
try {
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM patients p";
    $count_params = [];
    
    if ($search) {
        $count_sql .= " WHERE p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?";
        $searchTerm = "%{$search}%";
        $count_params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get patients with their receipt counts and total spending (with pagination)
    $sql = "
        SELECT 
            p.*,
            COUNT(r.id) as receipt_count,
            COALESCE(SUM(r.total_amount), 0) as total_spent,
            MAX(r.created_at) as last_visit
        FROM patients p 
        LEFT JOIN receipts r ON p.id = r.patient_id 
    ";
    
    $params = [];
    if ($search) {
        $sql .= " WHERE p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
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
    <div class="management-controls">
        <div class="control-row">
            <div class="search-box">
                <form method="GET" class="search-form">
                    <div class="input-group">
                        <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="action-buttons">
                <button type="button" class="btn btn-success" onclick="openAddPatientModal()">
                    <i class="fas fa-user-plus"></i> Add Patient
                </button>
                <button type="button" class="btn btn-info" onclick="exportAllPatients()">
                    <i class="fas fa-file-export"></i> Export All
                </button>
            </div>
        </div>
    </div>

    <!-- Patients Table -->
    <div class="content-section">
        <div class="table-responsive">
            <?php if (!empty($patients)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">ID</th>
                            <th style="width: 20%">Patient Name</th>
                            <th style="width: 15%">Phone</th>
                            <th style="width: 20%">Email</th>
                            <th style="width: 8%" class="text-center">Visits</th>
                            <th style="width: 12%" class="text-right">Total Spent</th>
                            <th style="width: 10%">Last Visit</th>
                            <th style="width: 10%" class="text-center">Actions</th>
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
                        <tr data-patient-id="<?php echo $patient['id']; ?>">
                            <td class="text-center"><?php echo str_pad($patient['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div class="patient-info-cell">
                                    <i class="fas fa-user-circle" style="color: #6b7280; margin-right: 8px;"></i>
                                    <span><?php echo htmlspecialchars($patient['name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if($patient['phone']): ?>
                                    <span style="color: #374151;"><?php echo htmlspecialchars($patient['phone']); ?></span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($patient['email']): ?>
                                    <span style="color: #374151;"><?php echo htmlspecialchars($patient['email']); ?></span>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge" style="background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 4px;">
                                    <?php echo $patient['receipt_count']; ?>
                                </span>
                            </td>
                            <td class="text-right" style="font-weight: 600;">
                                RM <?php echo number_format($patient['total_spent'], 2); ?>
                            </td>
                            <td>
                                <?php 
                                if ($patient['last_visit']) {
                                    $days_ago = floor((time() - strtotime($patient['last_visit'])) / (60 * 60 * 24));
                                    if ($days_ago == 0) {
                                        echo '<span style="color: #059669;">Today</span>';
                                    } elseif ($days_ago == 1) {
                                        echo '<span style="color: #0891b2;">Yesterday</span>';
                                    } elseif ($days_ago < 7) {
                                        echo '<span>' . $days_ago . ' days ago</span>';
                                    } else {
                                        echo date('M j, Y', strtotime($patient['last_visit']));
                                    }
                                } else {
                                    echo '<span style="color: #9ca3af;">Never</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <div class="action-buttons" style="display: flex; gap: 4px; justify-content: center;">
                                    <button type="button" class="btn-action" onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" title="View Details"
                                            style="padding: 6px; border: none; background: #f3f4f6; color: #374151; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-eye" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" class="btn-action" onclick="editPatient(<?php echo $patient['id']; ?>)" title="Edit"
                                            style="padding: 6px; border: none; background: #dbeafe; color: #1e40af; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-edit" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" class="btn-action" onclick="exportPatientData(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>')" title="Export"
                                            style="padding: 6px; border: none; background: #d1fae5; color: #065f46; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-download" style="font-size: 12px;"></i>
                                    </button>
                                    <button type="button" class="btn-action" onclick="deletePatient(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>')" title="Delete"
                                            style="padding: 6px; border: none; background: #fee2e2; color: #991b1b; border-radius: 4px; cursor: pointer;">
                                        <i class="fas fa-trash" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        
                        // Fill empty rows to maintain consistent table height
                        for ($i = $display_count; $i < min(10, $records_per_page); $i++):
                        ?>
                        <tr style="height: 52px;">
                            <td colspan="8" style="background: transparent; border: none;">&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px 15px; border-top: 1px solid #e5e7eb;">
                    <div style="color: #6b7280; font-size: 14px;">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> patients
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                                <i class="fas fa-angle-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               style="padding: 8px 12px; border: 1px solid <?php echo $i == $page ? '#2563eb' : '#e5e7eb'; ?>; 
                                      border-radius: 6px; color: <?php echo $i == $page ? 'white' : '#374151'; ?>; 
                                      background: <?php echo $i == $page ? '#2563eb' : 'white'; ?>; text-decoration: none; font-weight: 500;">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                                Next <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               style="padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; color: #374151; text-decoration: none; background: white;">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-users" style="font-size: 64px; color: #d1d5db; margin-bottom: 20px;"></i>
                    <h3 style="font-size: 24px; color: #374151; margin-bottom: 10px;">No patients found</h3>
                    <p style="color: #6b7280; margin-bottom: 30px;"><?php echo $search ? 'Try adjusting your search criteria' : 'Add your first patient to get started'; ?></p>
                    <button type="button" class="btn btn-primary" onclick="openAddPatientModal()">
                        <i class="fas fa-user-plus"></i> Add Patient
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Patient Modal -->
    <div id="patient-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add New Patient</h2>
                <button type="button" class="modal-close" onclick="closePatientModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="patient-form" method="POST">
                <input type="hidden" name="action" id="form-action" value="add_patient">
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
                        <i class="fas fa-save"></i> Save Patient
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

<?php 
$additional_js = ['../assets/js/patients.js'];
include '../includes/footer.php'; 
?>