<?php
require_once 'config/config.php';
require_once 'includes/PasswordResetHelper.php';

$page_title = 'Reset Password';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['user_type']) {
        case 'super_admin':
        case 'admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'police':
            header("Location: police/dashboard.php");
            exit();
        case 'emergency':
            header("Location: emergency/dashboard.php");
            exit();
        case 'barangay':
            header("Location: barangay/dashboard.php");
            exit();
        case 'firefighter':
            header("Location: firefighter/dashboard.php");
            exit();
        default:
            header("Location: user/dashboard.php");
            exit();
    }
}

$message = '';
$message_type = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $reset_code = sanitizeInput($_POST['reset_code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($email) || empty($reset_code) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $resetHelper = new PasswordResetHelper($db);
        
        // Validate password strength
        $password_validation = $resetHelper->validatePassword($new_password);
        if (!$password_validation['valid']) {
            $error_message = $password_validation['message'];
        } else {
            // Validate reset code
            $code_validation = $resetHelper->validateResetCode($email, $reset_code);
            
            if (!$code_validation['valid']) {
                $error_message = $code_validation['message'];
            } else {
                // Reset password
                $user_id = $code_validation['user_id'];
                
                if ($resetHelper->resetPassword($user_id, $new_password)) {
                    logActivity($user_id, 'Password reset successfully');
                    
                    $message = 'Password reset successfully! Redirecting to login...';
                    $message_type = 'success';
                    
                    // Redirect after 2 seconds
                    header("refresh:2;url=index.php");
                } else {
                    $error_message = 'Failed to reset password. Please try again later.';
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row vh-100 d-flex justify-content-center align-items-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/img/logo.png" alt="Agoncillo Logo" class="img-fluid mb-3" style="max-height: 100px;">
                        <h2 class="fw-bold text-primary"><?php echo APP_NAME; ?></h2>
                        <p class="text-muted">Create New Password</p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($message)): ?>
                        <p class="text-muted mb-4">Enter the reset code sent to your email and create a new password.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-envelope-fill"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reset_code" class="form-label">Reset Code</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-key-fill"></i></span>
                                    <input type="text" class="form-control" id="reset_code" name="reset_code" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required>
                                </div>
                                <small class="text-muted">Check your email for the 6-digit code (expires in 15 minutes)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                                </div>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <p>Remember your password? <a href="index.php" class="text-primary">Login here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted">
                <small>&copy; 2025 Municipality of Agoncillo. All rights reserved.</small>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
