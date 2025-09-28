<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php');
}

// Check if user is barangay personnel
if (!isBarangay()) {
    // Redirect to appropriate dashboard based on user type
    switch ($_SESSION['user_type']) {
        case 'admin':
            redirect('../admin/dashboard.php');
            break;
        case 'police':
            redirect('../police/dashboard.php');
            break;
        case 'emergency':
            redirect('../emergency/dashboard.php');
            break;
        case 'firefighter':
            redirect('../firefighter/dashboard.php');
            break;
        default:
            redirect('../user/dashboard.php');
            break;
    }
}

$page_title = 'Barangay Profile';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $assigned_barangay = $_POST['assigned_barangay'];
    
    $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                     email = :email, phone = :phone, assigned_barangay = :assigned_barangay 
                     WHERE id = :user_id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':assigned_barangay', $assigned_barangay);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    
    $success_message = "Profile updated successfully!";
}

// Get user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

// Get response statistics
$stats_query = "SELECT 
                COUNT(*) as total_responses,
                SUM(CASE WHEN response_status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN response_status = 'responding' OR response_status = 'on_scene' THEN 1 ELSE 0 END) as active_count
                FROM incident_reports ir
                JOIN users u ON ir.user_id = u.id
                WHERE ir.approval_status = 'approved' 
                AND (ir.assigned_to = :user_id OR ir.responder_type = 'barangay')";

if (!empty($user['assigned_barangay'])) {
    $stats_query .= " AND u.barangay = :assigned_barangay";
}

$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
if (!empty($user['assigned_barangay'])) {
    $stmt->bindParam(':assigned_barangay', $user['assigned_barangay']);
}
$stmt->execute();
$stats = $stmt->fetch();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<div class="d-flex" id="wrapper">
    
    <?php include 'includes/sidebar.php'; ?>
    
    
    <div id="page-content-wrapper">
        
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
             
            <div class="d-flex justify-content-between align-items-center my-4">
                <div>
                    <h1 class="h3 mb-0">👤 Barangay Profile</h1>
                    <p class="text-muted">Manage your profile information and view response statistics</p>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-circle me-2"></i>Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">First Name</label>
                                        <input type="text" name="first_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Phone</label>
                                        <input type="text" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Assigned Barangay</label>
                                        <select name="assigned_barangay" class="form-select" required>
                                            <option value="">Select Barangay</option>
                                            <?php
                                            // Get barangays from database
                                            $barangay_query = "SELECT name FROM barangays ORDER BY name";
                                            $stmt = $db->prepare($barangay_query);
                                            $stmt->execute();
                                            $barangays = $stmt->fetchAll();
                                            
                                            foreach ($barangays as $brgy) {
                                                $selected = ($user['assigned_barangay'] == $brgy['name']) ? 'selected' : '';
                                                echo "<option value='{$brgy['name']}' $selected>{$brgy['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

              
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up me-2"></i>Response Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="p-3 bg-primary bg-opacity-10 rounded">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 text-primary"><?php echo $stats['total_responses'] ?? 0; ?></h4>
                                                <small class="text-muted">Total Responses</small>
                                            </div>
                                            <i class="bi bi-exclamation-triangle text-primary fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-success bg-opacity-10 rounded">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 text-success"><?php echo $stats['resolved_count'] ?? 0; ?></h4>
                                                <small class="text-muted">Resolved Cases</small>
                                            </div>
                                            <i class="bi bi-check-circle text-success fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 bg-warning bg-opacity-10 rounded">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h4 class="mb-0 text-warning"><?php echo $stats['active_count'] ?? 0; ?></h4>
                                                <small class="text-muted">Active Cases</small>
                                            </div>
                                            <i class="bi bi-clock text-warning fs-3"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-check me-2"></i>Role Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong class="text-success">Role:</strong>
                                <p class="mb-0">Barangay Emergency Response Unit</p>
                            </div>
                            <div class="mb-3">
                                <strong class="text-success">Responsibilities:</strong>
                                <ul class="small mb-0 ps-3">
                                    <li>Community-level emergency response</li>
                                    <li>Evacuation coordination</li>
                                    <li>Local disaster management</li>
                                    <li>First aid and rescue operations</li>
                                </ul>
                            </div>
                            <div>
                                <strong class="text-success">Coverage Area:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($user['assigned_barangay'] ?: 'Not assigned'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});
</script>

<?php include '../includes/footer.php'; ?>
