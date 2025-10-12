<?php
require_once '../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    redirect('../index.php');
}

$page_title = 'Emergency Profile';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Verify current password if changing password
        if (!empty($new_password)) {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("New password must be at least 6 characters long");
            }
        }
        
        // Update profile
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET department = :department, last_name = :last_name, phone = :phone, email = :email, password = :password WHERE id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
        } else {
            $stmt = $db->prepare("UPDATE users SET department = :department, last_name = :last_name, phone = :phone, email = :email WHERE id = :user_id");
        }
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Update session
        $_SESSION['department'] = $department;
        $_SESSION['last_name'] = $last_name;
        
        $success_message = "Profile updated successfully!";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

// Get emergency statistics
$stats_query = "SELECT 
    COUNT(*) as total_responses,
    COUNT(CASE WHEN response_status = 'resolved' THEN 1 END) as resolved_incidents,
    COUNT(CASE WHEN response_status = 'responding' OR response_status = 'on_scene' THEN 1 END) as active_responses,
    COUNT(CASE WHEN DATE(updated_at) = CURDATE() THEN 1 END) as today_responses
    FROM incident_reports 
    WHERE approval_status = 'approved' 
    AND (incident_type LIKE '%medical%' OR incident_type LIKE '%emergency%' OR incident_type LIKE '%accident%')";
$stmt = $db->prepare($stats_query);
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
            <div class="row my-4">
                <div class="col-12">
                    <h2><i class="bi bi-person-badge text-danger me-2"></i>Emergency Responder Profile</h2>
                    <p class="text-muted">Manage your emergency responder profile and account settings</p>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Response Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h4 class="text-danger"><?php echo $stats['total_responses']; ?></h4>
                                    <small class="text-muted">Total Responses</small>
                                </div>
                                <div class="col-6 mb-3">
                                    <h4 class="text-success"><?php echo $stats['resolved_incidents']; ?></h4>
                                    <small class="text-muted">Cases Resolved</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-warning"><?php echo $stats['active_responses']; ?></h4>
                                    <small class="text-muted">Active Responses</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-info"><?php echo $stats['today_responses']; ?></h4>
                                    <small class="text-muted">Today's Responses</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Important Reminders</h6>
                        </div>
                        <div class="card-body">
    <div class="mb-2 py-2 border-bottom">
        <small><i class="bi bi-heart-pulse me-1"></i><strong>Medical Kit</strong></small>
        <div class="small">Always check first-aid kits before dispatch.</div>
    </div>
    <div class="mb-2 py-2 border-bottom">
        <small><i class="bi bi-lightning-charge me-1"></i><strong>Quick Response</strong></small>
        <div class="small">Arrive fast and assess situations safely.</div>
    </div>
    <div class="mb-2 py-2 border-bottom">
        <small><i class="bi bi-telephone-inbound me-1"></i><strong>Call Updates</strong></small>
        <div class="small">Stay in contact with command while en route.</div>
    </div>
    <div class="py-2">
        <small><i class="bi bi-geo-alt me-1"></i><strong>Accurate Location</strong></small>
        <div class="small">Report exact locations for faster coordination.</div>
    </div>
</div>

                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Profile</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="department" class="form-label">Department Name</label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo htmlspecialchars($user['department']); ?>" required>
                                    </div>
                                    
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <hr>
                                <h6 class="text-danger">Change Password (Optional)</h6>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-check me-1"></i> Update Profile
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

// Password validation
document.getElementById('new_password').addEventListener('input', function() {
    const currentPassword = document.getElementById('current_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (this.value) {
        currentPassword.required = true;
        confirmPassword.required = true;
    } else {
        currentPassword.required = false;
        confirmPassword.required = false;
    }
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    
    if (this.value !== newPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
