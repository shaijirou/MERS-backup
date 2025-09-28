<?php
require_once '../config/config.php';

// Check if user is logged in and is police
if (!isLoggedIn() || !isPolice()) {
    redirect('../index.php');
}

$page_title = 'Police Settings';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password.";
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}

// Handle notification settings
if (isset($_POST['update_notifications'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    
    $stmt = $db->prepare("UPDATE users SET email_notifications = :email_notifications, sms_notifications = :sms_notifications WHERE id = :user_id");
    $stmt->bindParam(':email_notifications', $email_notifications);
    $stmt->bindParam(':sms_notifications', $sms_notifications);
    $stmt->bindParam(':user_id', $user_id);
    if ($stmt->execute()) {
        $success_message = "Notification settings updated!";
    }
}

// Get current settings
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

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
            <div class="row my-4">
                <div class="col-12">
                    <h2><i class="bi bi-gear me-2"></i>Police Settings</h2>
                    <p class="text-muted">Manage your account settings and preferences</p>
                </div>
            </div>

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

            <div class="row">
                 <!-- Password Change Section  -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-lock me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="bi bi-check me-1"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                 <!-- Notification Settings  -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-bell me-2"></i>Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            <i class="bi bi-envelope me-1"></i>Email Notifications
                                        </label>
                                        <div class="form-text">Receive incident alerts via email</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_notifications" id="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            <i class="bi bi-phone me-1"></i>SMS Notifications
                                        </label>
                                        <div class="form-text">Receive incident alerts via SMS</div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="update_notifications" class="btn btn-info">
                                    <i class="bi bi-check me-1"></i>Update Notifications
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Profile Information  -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-person me-2"></i>Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Badge Number:</strong></td>
                                            <td><?php echo htmlspecialchars($user['badge_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Department:</strong></td>
                                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>User Type:</strong></td>
                                            <td><span class="badge bg-primary"><?php echo ucfirst($user['user_type']); ?></span></td>
                                        </tr>
                                    </table>
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
