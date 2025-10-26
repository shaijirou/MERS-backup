<?php
require_once 'config/config.php';

$page_title = 'Forgot Password';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: user/dashboard.php");
    exit();
}

$success_message = '';
$error_message = '';
$step = 1; // Step 1: Enter email, Step 2: Enter reset code, Step 3: Reset password

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'request_reset') {
        // Step 1: User requests password reset
        $email = sanitizeInput($_POST['email']);
        
        if (empty($email)) {
            $error_message = 'Please enter your email address.';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if user exists
            $query = "SELECT id, first_name, last_name, email FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                // Store email in session for next step
                $_SESSION['reset_email'] = $email;
                
                // $success_message = 'A password reset code has been sent to your email. Please check your inbox.';
                $step = 2;
            } else {
                $error_message = 'No account found with this email address.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'verify_code') {
        $reset_code = sanitizeInput($_POST['reset_code']);
        
        if (empty($reset_code)) {
            $error_message = 'Please enter the reset code.';
            $step = 2;
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Verify code against database
            $query = "SELECT id FROM users WHERE email = :email AND password_reset_code = :code AND password_reset_code_expiry > NOW()";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $_SESSION['reset_email']);
            $stmt->bindParam(':code', $reset_code);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                // Code is valid, move to password reset step
                $_SESSION['reset_code_verified'] = true;
                // $success_message = 'Code verified! Please enter your new password.';
                $step = 3;
            } else {
                $error_message = 'Invalid or expired reset code. Please try again.';
                $step = 2;
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all fields.';
            $step = 3;
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
            $step = 3;
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
            $step = 3;
        } elseif (!isset($_SESSION['reset_code_verified']) || !$_SESSION['reset_code_verified']) {
            $error_message = 'Please verify your reset code first.';
            $step = 2;
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password, password_reset_code = NULL, password_reset_code_expiry = NULL WHERE email = :email";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':email', $_SESSION['reset_email']);
            $update_stmt->execute();
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_code_verified']);
            unset($_SESSION['reset_code_time']);
            
            $success_message = 'Your password has been reset successfully. You can now login with your new password.';
            $step = 4; // Success step
        }
    }
}

// Determine current step based on session
if (isset($_SESSION['reset_code_verified']) && $_SESSION['reset_code_verified']) {
    $step = 3;
} elseif (isset($_SESSION['reset_email'])) {
    $step = 2;
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
                        <p class="text-muted">Reset Your Password</p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Step 1: Request Reset -->
                    <?php if ($step == 1): ?>
                        <form method="POST" id="resetRequestForm">
                            <input type="hidden" name="action" value="request_reset">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-envelope-fill"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your registered email" required>
                                </div>
                                <small class="form-text text-muted">We'll send you a password reset code via email.</small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="sendCodeBtn">Send Reset Code</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Step 2: Verify Reset Code -->
                    <?php if ($step == 2): ?>
                        <form method="POST" id="verifyCodeForm">
                            <input type="hidden" name="action" value="verify_code">
                            
                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle"></i> A reset code has been sent to <strong><?php echo isset($_SESSION['reset_email']) ? htmlspecialchars($_SESSION['reset_email']) : 'your email'; ?></strong>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reset_code" class="form-label">Enter Reset Code</label>
                                <input type="text" class="form-control form-control-lg text-center" id="reset_code" name="reset_code" placeholder="000000" maxlength="6" required>
                                <small class="form-text text-muted">Enter the 6-digit code sent to your email.</small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Verify Code</button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="forgot-password.php" class="text-muted">Didn't receive the code? Request a new one</a>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Step 3: Reset Password -->
                    <?php if ($step == 3): ?>
                        <form method="POST" id="resetPasswordForm">
                            <input type="hidden" name="action" value="reset_password">
                            
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> Code verified! Now set your new password.
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                                </div>
                                <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                            </div>
                            
                            <div class="mb-3">
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
                    
                    <!-- Step 4: Success -->
                    <?php if ($step == 4): ?>
                        <div class="text-center">
                            <div class="mb-4">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h4 class="mb-2">Password Reset Successful!</h4>
                            <p class="mb-4 text-muted">Your password has been successfully reset. You can now login with your new password.</p>
                            <a href="index.php" class="btn btn-primary btn-lg">Back to Login</a>
                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
<script>
    // Initialize EmailJS
    emailjs.init('6vawme8A87iNfFCsC'); // Replace with your EmailJS public key
    
    // Handle form submission for sending reset code
    document.getElementById('resetRequestForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const btn = document.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        
        btn.textContent = 'Sending...';
        btn.disabled = true;
        
        // Generate reset code (6 digits)
        const resetCode = Math.floor(100000 + Math.random() * 900000).toString();
        
        // Email parameters for EmailJS
        const emailParams = {
            to_email: email,
            user_name: email.split('@')[0],
            reset_code: resetCode,
            reset_link: window.location.origin + '/forgot-password.php'
        };
        
        // Send email via EmailJS
        emailjs.send('service_k9i98tr', 'template_f5ceyhw', emailParams)
            .then(() => {
                // Store reset code in database via AJAX
                fetch('api/store-reset-code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        reset_code: resetCode
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reset code sent to your email!');
                        document.getElementById('resetRequestForm').submit();
                    } else {
                        alert('Error: ' + data.message);
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                });
            })
            .catch((err) => {
                console.error('Error sending email:', err);
                alert('Failed to send reset code. Please try again later.');
                btn.textContent = originalText;
                btn.disabled = false;
            });
    });
    
    // Password confirmation validation
    document.getElementById('confirm_password')?.addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        
        if (newPassword !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
