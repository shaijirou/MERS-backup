<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Settings';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_notifications'])) {
        $sms_enabled = isset($_POST['sms_enabled']) ? 1 : 0;
        $email_enabled = isset($_POST['email_enabled']) ? 1 : 0;
        $push_enabled = isset($_POST['push_enabled']) ? 1 : 0;
        $alert_types = isset($_POST['alert_types']) ? json_encode($_POST['alert_types']) : json_encode([]);
        $quiet_hours_start = sanitizeInput($_POST['quiet_hours_start']);
        $quiet_hours_end = sanitizeInput($_POST['quiet_hours_end']);
        
        $query = "UPDATE users SET 
                  sms_notifications = :sms_enabled,
                  email_notifications = :email_enabled,
                  push_notifications = :push_enabled,
                  alert_type_preferences = :alert_types,
                  quiet_hours_start = :quiet_hours_start,
                  quiet_hours_end = :quiet_hours_end,
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':sms_enabled', $sms_enabled);
        $stmt->bindParam(':email_enabled', $email_enabled);
        $stmt->bindParam(':push_enabled', $push_enabled);
        $stmt->bindParam(':alert_types', $alert_types);
        $stmt->bindParam(':quiet_hours_start', $quiet_hours_start);
        $stmt->bindParam(':quiet_hours_end', $quiet_hours_end);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = 'Notification settings updated successfully!';
            logActivity($_SESSION['user_id'], 'Updated notification settings', 'users', $_SESSION['user_id']);
        } else {
            $error_message = 'Failed to update notification settings.';
        }
    } elseif (isset($_POST['update_privacy'])) {
        $location_sharing = isset($_POST['location_sharing']) ? 1 : 0;
        $profile_visibility = sanitizeInput($_POST['profile_visibility']);
        $data_sharing = isset($_POST['data_sharing']) ? 1 : 0;
        
        $query = "UPDATE users SET 
                  location_sharing_enabled = :location_sharing,
                  profile_visibility = :profile_visibility,
                  data_sharing_consent = :data_sharing,
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':location_sharing', $location_sharing);
        $stmt->bindParam(':profile_visibility', $profile_visibility);
        $stmt->bindParam(':data_sharing', $data_sharing);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = 'Privacy settings updated successfully!';
            logActivity($_SESSION['user_id'], 'Updated privacy settings', 'users', $_SESSION['user_id']);
        } else {
            $error_message = 'Failed to update privacy settings.';
        }
    } elseif (isset($_POST['deactivate_account'])) {
        $reason = sanitizeInput($_POST['deactivation_reason']);
        
        $query = "UPDATE users SET 
                  status = 'inactive',
                  deactivation_reason = :reason,
                  deactivated_at = NOW(),
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'Account deactivated', 'users', $_SESSION['user_id']);
            session_destroy();
            header('Location: ../index.php?message=account_deactivated');
            exit();
        } else {
            $error_message = 'Failed to deactivate account.';
        }
    }
    
    // Refresh user data
    $query = "SELECT * FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch();
}

include '../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="../assets/img/logo.png" alt="Agoncillo Logo" class="me-2" style="height: 40px;">
            <span>MERS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill me-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="alerts.php"><i class="bi bi-bell-fill me-1"></i> Alerts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my-reports.php"><i class="bi bi-file-earmark-text-fill me-1"></i> My Reports</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <img src="../<?php echo $user['profile_picture'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item active" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-gear-fill me-2 text-primary"></i>Settings</h2>
            <p class="text-muted mb-0">Manage your account preferences and privacy settings</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="#notifications" class="list-group-item list-group-item-action active" data-bs-toggle="pill">
                            <i class="bi bi-bell me-2"></i>Notifications
                        </a>
                        <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                            <i class="bi bi-shield-lock me-2"></i>Privacy & Security
                        </a>
                        <a href="#account" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                            <i class="bi bi-person-gear me-2"></i>Account Management
                        </a>
                        <a href="#about" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                            <i class="bi bi-info-circle me-2"></i>About & Support
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- Notifications Tab -->
                <div class="tab-pane fade show active" id="notifications">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell-fill me-2"></i>Notification Preferences
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <h6 class="mb-3">Notification Methods</h6>
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                                   <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_enabled">
                                                <strong>SMS Notifications</strong>
                                                <small class="d-block text-muted">Receive alerts via text message</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" 
                                                   <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_enabled">
                                                <strong>Email Notifications</strong>
                                                <small class="d-block text-muted">Receive updates via email</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="push_enabled" name="push_enabled" 
                                                   <?php echo $user['push_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="push_enabled">
                                                <strong>Push Notifications</strong>
                                                <small class="d-block text-muted">Browser notifications</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h6 class="mb-3">Alert Types</h6>
                                <?php $alert_preferences = json_decode($user['alert_type_preferences'] ?: '[]', true); ?>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="emergency_alerts" name="alert_types[]" 
                                                   value="emergency" <?php echo in_array('emergency', $alert_preferences) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="emergency_alerts">
                                                <span class="badge bg-danger me-2">EMERGENCY</span>Critical emergency alerts
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="warning_alerts" name="alert_types[]" 
                                                   value="warning" <?php echo in_array('warning', $alert_preferences) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="warning_alerts">
                                                <span class="badge bg-warning text-dark me-2">WARNING</span>Weather and safety warnings
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="advisory_alerts" name="alert_types[]" 
                                                   value="advisory" <?php echo in_array('advisory', $alert_preferences) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="advisory_alerts">
                                                <span class="badge bg-info text-dark me-2">ADVISORY</span>General advisories
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="info_alerts" name="alert_types[]" 
                                                   value="info" <?php echo in_array('info', $alert_preferences) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="info_alerts">
                                                <span class="badge bg-secondary me-2">INFO</span>Information updates
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <h6 class="mb-3">Quiet Hours</h6>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label for="quiet_hours_start" class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="quiet_hours_start" name="quiet_hours_start" 
                                               value="<?php echo $user['quiet_hours_start'] ?: '22:00'; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="quiet_hours_end" class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="quiet_hours_end" name="quiet_hours_end" 
                                               value="<?php echo $user['quiet_hours_end'] ?: '06:00'; ?>">
                                    </div>
                                </div>
                                <small class="text-muted">During quiet hours, only critical emergency alerts will be sent.</small>

                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Save Notification Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Privacy Tab -->
                <div class="tab-pane fade" id="privacy">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-lock-fill me-2"></i>Privacy & Security Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <h6 class="mb-3">Location Services</h6>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="location_sharing" name="location_sharing" 
                                           <?php echo $user['location_sharing_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="location_sharing">
                                        <strong>Enable Location Sharing</strong>
                                        <small class="d-block text-muted">Allow the system to access your location for emergency services and personalized alerts</small>
                                    </label>
                                </div>

                                <hr>
                                <h6 class="mb-3">Profile Visibility</h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="visibility_public" name="profile_visibility" 
                                               value="public" <?php echo $user['profile_visibility'] == 'public' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="visibility_public">
                                            <strong>Public</strong>
                                            <small class="d-block text-muted">Your profile is visible to other users and emergency responders</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="visibility_limited" name="profile_visibility" 
                                               value="limited" <?php echo $user['profile_visibility'] == 'limited' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="visibility_limited">
                                            <strong>Limited</strong>
                                            <small class="d-block text-muted">Only emergency responders can see your profile</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="visibility_private" name="profile_visibility" 
                                               value="private" <?php echo $user['profile_visibility'] == 'private' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="visibility_private">
                                            <strong>Private</strong>
                                            <small class="d-block text-muted">Your profile is hidden from other users</small>
                                        </label>
                                    </div>
                                </div>

                                <hr>
                                <h6 class="mb-3">Data Sharing</h6>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="data_sharing" name="data_sharing" 
                                           <?php echo $user['data_sharing_consent'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="data_sharing">
                                        <strong>Allow Anonymous Data Sharing</strong>
                                        <small class="d-block text-muted">Help improve the system by sharing anonymous usage data for research and development</small>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_privacy" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>Save Privacy Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Management Tab -->
                <div class="tab-pane fade" id="account">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="#notifications" class="list-group-item list-group-item-action active" data-bs-toggle="pill">
                                    <i class="bi bi-bell me-2"></i>Notifications
                                </a>
                                <a href="#privacy" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                    <i class="bi bi-shield-lock me-2"></i>Privacy & Security
                                </a>
                                <a href="#account" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                    <i class="bi bi-person-gear me-2"></i>Account Management
                                </a>
                                <a href="#about" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                    <i class="bi bi-info-circle me-2"></i>About & Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- About & Support Tab -->
                <div class="tab-pane fade" id="about">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle-fill me-2"></i>About & Support
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>System Information</h6>
                                    <p><strong>Version:</strong> 1.0.0</p>
                                    <p><strong>Last Update:</strong> January 2024</p>
                                    <p><strong>Developer:</strong> Agoncillo MDRRMO</p>
                                    <p><strong>Support:</strong> <a href="mailto:support@agoncillo.gov.ph">support@agoncillo.gov.ph</a></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Resources</h6>
                                    <div class="d-grid gap-2">
                                        <a href="#" class="btn btn-outline-primary">
                                            <i class="bi bi-book me-1"></i>User Guide
                                        </a>
                                        <a href="#" class="btn btn-outline-secondary">
                                            <i class="bi bi-shield-check me-1"></i>Privacy Policy
                                        </a>
                                        <a href="#" class="btn btn-outline-info">
                                            <i class="bi bi-file-text me-1"></i>Terms of Service
                                        </a>
                                        <a href="#" class="btn btn-outline-success">
                                            <i class="bi bi-chat-dots me-1"></i>Feedback
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deactivate Account Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Deactivate Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action cannot be undone. Your account will be deactivated and you will lose access to all features.
                    </div>
                    <div class="mb-3">
                        <label for="deactivation_reason" class="form-label">Reason for deactivation (optional)</label>
                        <textarea class="form-control" id="deactivation_reason" name="deactivation_reason" rows="3" 
                                  placeholder="Please let us know why you're deactivating your account..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirm_deactivation" required>
                        <label class="form-check-label" for="confirm_deactivation">
                            I understand that this action cannot be undone
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="deactivate_account" class="btn btn-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>Deactivate Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function downloadData() {
    AgoncilloAlert.showInAppNotification('Download Started', 'Your data export has been initiated. You will receive an email when ready.', 'info');
}

function requestSupport() {
    window.location.href = 'mailto:support@agoncillo.gov.ph?subject=Support Request&body=Please describe your issue...';
}

// Tab switching
document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Show corresponding content
        const target = this.getAttribute('href');
        const targetPane = document.querySelector(target);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
