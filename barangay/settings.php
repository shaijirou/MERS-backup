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

$page_title = 'Barangay Settings';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        
        // Update or insert notification preferences
        $settings_to_update = [
            'email_notifications' => $email_notifications,
            'sms_notifications' => $sms_notifications,
            'push_notifications' => $push_notifications
        ];
        
        foreach ($settings_to_update as $setting_name => $setting_value) {
            $query = "INSERT INTO user_settings (user_id, setting_name, setting_value) 
                     VALUES (:user_id, :setting_name, :setting_value) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':setting_name', $setting_name);
            $stmt->bindParam(':setting_value', $setting_value);
            $stmt->execute();
        }
        
        $success_message = "Notification settings updated successfully!";
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $query = "SELECT password FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
                $stmt = $db->prepare($update_query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "New passwords do not match!";
            }
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
}

// Get current notification settings
$query = "SELECT setting_name, setting_value FROM user_settings WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

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
                    <h1 class="h3 mb-0">⚙️ Barangay Settings</h1>
                    <p class="text-muted">Manage your notification preferences and account settings</p>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell me-2"></i>Notification Preferences
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" 
                                                   name="email_notifications" <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                <i class="bi bi-envelope me-2"></i>Email Notifications
                                            </label>
                                            <div class="form-text">Receive incident alerts and updates via email</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                                   name="sms_notifications" <?php echo ($settings['sms_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_notifications">
                                                <i class="bi bi-phone me-2"></i>SMS Notifications
                                            </label>
                                            <div class="form-text">Receive critical alerts via SMS</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="push_notifications" 
                                                   name="push_notifications" <?php echo ($settings['push_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="push_notifications">
                                                <i class="bi bi-app-indicator me-2"></i>Push Notifications
                                            </label>
                                            <div class="form-text">Receive real-time notifications in your browser</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                     
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lock me-2"></i>Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">New Password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="bi bi-key me-2"></i>Change Password
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
                                <i class="bi bi-shield-check me-2"></i>Emergency Protocols
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h6 class="text-primary fw-bold mb-2">
                                    <i class="bi bi-list-ol me-2"></i>Incident Response Protocol
                                </h6>
                                <ol class="small">
                                    <li>Receive notification from admin</li>
                                    <li>Assess situation and mobilize resources</li>
                                    <li>Update status to "Responding"</li>
                                    <li>Coordinate with other agencies if needed</li>
                                    <li>Mark as "Resolved" when completed</li>
                                </ol>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-primary fw-bold mb-2">
                                    <i class="bi bi-telephone me-2"></i>Emergency Contacts
                                </h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <strong>Police:</strong> <span>117</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <strong>Fire Department:</strong> <span>116</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <strong>Medical Emergency:</strong> <span>911</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <strong>MDRRMO:</strong> <span>(043) 778-1234</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-primary fw-bold mb-2">
                                    <i class="bi bi-house me-2"></i>Barangay Responsibilities
                                </h6>
                                <ul class="small">
                                    <li>First responder for community emergencies</li>
                                    <li>Evacuation coordination</li>
                                    <li>Local disaster preparedness</li>
                                    <li>Community safety education</li>
                                    <li>Liaison with municipal agencies</li>
                                </ul>
                            </div>

                            <div class="alert alert-info">
                                <h6 class="alert-heading mb-2">
                                    <i class="bi bi-info-circle me-2"></i>Response Priorities
                                </h6>
                                <div class="small">
                                    <div class="mb-1"><strong class="text-danger">Critical:</strong> Life-threatening emergencies</div>
                                    <div class="mb-1"><strong class="text-warning">High:</strong> Property damage, injuries</div>
                                    <div class="mb-1"><strong class="text-info">Medium:</strong> Infrastructure issues</div>
                                    <div><strong class="text-success">Low:</strong> Minor incidents, follow-ups</div>
                                </div>
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
