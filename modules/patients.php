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

// Get patients with their receipt counts and total spending
try {
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
    
    $sql .= " GROUP BY p.id ORDER BY p.name ASC";
    
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
    <div class="patients-table-container">
        <?php if (!empty($patients)): ?>
            <table class="patients-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Visits</th>
                        <th>Total Spent</th>
                        <th>Last Visit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                    <tr data-patient-id="<?php echo $patient['id']; ?>">
                        <td><?php echo $patient['id']; ?></td>
                        <td class="patient-name-cell">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($patient['name']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($patient['phone'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($patient['email'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo $patient['receipt_count']; ?></td>
                        <td class="text-right">RM <?php echo number_format($patient['total_spent'], 2); ?></td>
                        <td>
                            <?php 
                            if ($patient['last_visit']) {
                                echo date('M j, Y', strtotime($patient['last_visit']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </td>
                        <td class="actions-cell">
                            <button type="button" class="btn btn-outline btn-sm" onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="editPatient(<?php echo $patient['id']; ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-info btn-sm" onclick="exportPatientData(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>')" title="Export">
                                <i class="fas fa-download"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deletePatient(<?php echo $patient['id']; ?>, '<?php echo htmlspecialchars($patient['name']); ?>')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No patients found</h3>
                <p><?php echo $search ? 'Try adjusting your search criteria' : 'Add your first patient to get started'; ?></p>
                <button type="button" class="btn btn-primary" onclick="openAddPatientModal()">
                    <i class="fas fa-user-plus"></i> Add Patient
                </button>
            </div>
        <?php endif; ?>
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