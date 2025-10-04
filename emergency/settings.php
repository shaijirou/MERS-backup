<?php
require_once '../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    redirect('../index.php');
}

$page_title = 'Emergency Settings';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Handle settings update
if ($_POST && isset($_POST['update_settings'])) {
    $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;
    $email_alerts = isset($_POST['email_alerts']) ? 1 : 0;
    $sms_alerts = isset($_POST['sms_alerts']) ? 1 : 0;
    
    // Update user preferences
    $stmt = $db->prepare("
        INSERT INTO user_preferences (user_id, notifications_enabled, email_alerts, sms_alerts) 
        VALUES (:user_id, :notifications_enabled, :email_alerts, :sms_alerts) 
        ON DUPLICATE KEY UPDATE 
        notifications_enabled = VALUES(notifications_enabled),
        email_alerts = VALUES(email_alerts),
        sms_alerts = VALUES(sms_alerts)
    ");
    
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':notifications_enabled', $notifications_enabled);
    $stmt->bindParam(':email_alerts', $email_alerts);
    $stmt->bindParam(':sms_alerts', $sms_alerts);
    
    if ($stmt->execute()) {
        $success_message = "Settings updated successfully!";
    } else {
        $error_message = "Error updating settings.";
    }
}

// Get current settings
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$preferences = $stmt->fetch();

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current user
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            if ($stmt->execute()) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = "Error changing password.";
            }
        } else {
            $password_error = "New passwords do not match.";
        }
    } else {
        $password_error = "Current password is incorrect.";
    }
}

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<div class="d-flex" id="wrapper">
     <!-- Sidebar  -->
    <?php include 'includes/sidebar.php'; ?>
    
     <!-- Page Content  -->
    <div id="page-content-wrapper">
         <!-- Navigation  -->
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
             <!-- Page Header  -->
            <div class="row g-3 my-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1"><i class="bi bi-gear me-2"></i>Emergency Settings</h2>
                            <p class="text-muted mb-0">Configure your emergency response preferences and account settings</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                     <!-- Notification Settings  -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0"><i class="bi bi-bell me-2"></i>Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="bi bi-check-circle me-2"></i><?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="notifications_enabled" name="notifications_enabled" 
                                           <?php echo ($preferences && $preferences['notifications_enabled']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notifications_enabled">
                                        <strong>Enable Emergency Notifications</strong>
                                        <small class="d-block text-muted">Receive real-time notifications for new emergency incidents</small>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="email_alerts" name="email_alerts" 
                                           <?php echo ($preferences && $preferences['email_alerts']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_alerts">
                                        <strong>Email Alerts for Critical Incidents</strong>
                                        <small class="d-block text-muted">Get email notifications for high-priority emergencies</small>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="sms_alerts" name="sms_alerts" 
                                           <?php echo ($preferences && $preferences['sms_alerts']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_alerts">
                                        <strong>SMS Alerts for Emergency Dispatch</strong>
                                        <small class="d-block text-muted">Receive text messages when dispatched to emergencies</small>
                                    </label>
                                </div>
                                
                                <button type="submit" name="update_settings" class="btn btn-danger">
                                    <i class="bi bi-check-circle me-1"></i>Save Notification Settings
                                </button>
                            </form>
                        </div>
                    </div>

                     <!-- Password Change  -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($password_success)): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="bi bi-check-circle me-2"></i><?php echo $password_success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($password_error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $password_error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-danger">
                                    <i class="bi bi-key me-1"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                     <!-- Emergency Protocols  -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0"><i class="bi bi-clipboard-check me-2"></i>Emergency Response Protocols</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Standard Operating Procedures</h6>
                                <ul class="mb-0">
                                    <li><strong>Priority 1 (Critical):</strong> Respond within 5 minutes</li>
                                    <li><strong>Priority 2 (High):</strong> Respond within 10 minutes</li>
                                    <li><strong>Priority 3 (Medium):</strong> Respond within 15 minutes</li>
                                    <li><strong>Priority 4 (Low):</strong> Respond within 30 minutes</li>
                                </ul>
                                <hr>
                                <p class="mb-0">
                                    <strong>Important:</strong> Always confirm arrival on scene and provide status updates every 15 minutes during active response.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Toggle sidebar
$(document).ready(function () {
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
