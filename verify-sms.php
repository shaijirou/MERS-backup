<?php
require_once 'config/config.php';
require_once 'includes/SemaphoreAPI.php';

$page_title = 'Verify Phone Number';

// Check if user has pending verification
if (!isset($_SESSION['pending_verification_user_id'])) {
    redirect('register.php');
}

$user_id = $_SESSION['pending_verification_user_id'];
$phone = $_SESSION['pending_verification_phone'];
$error_message = '';
$success_message = '';

$database = new Database();
$db = $database->getConnection();

// Handle verification code submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $verification_code = sanitizeInput($_POST['verification_code']);
    
    if (empty($verification_code)) {
        $error_message = 'Please enter the verification code.';
    } else {
        $query = "SELECT * FROM users 
                  WHERE id = :user_id 
                  AND password_reset_code = :code 
                  AND token_type = 'sms_verification'
                  AND is_phone_verified = FALSE 
                  AND password_reset_code_expiry > NOW()
                  AND verification_attempts < 3";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':code', $verification_code);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Mark as verified
            $query = "UPDATE users 
                      SET is_phone_verified = TRUE, 
                          phone_verified_at = NOW(),
                          password_reset_code = NULL,
                          password_reset_code_expiry = NULL,
                          token_type = NULL,
                          verification_attempts = 0
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $success_message = 'Phone number verified successfully! Your account is now on review. We will notify you through SMS if your account is approved or rejected.';
            logActivity($user_id, 'Phone number verified via SMS');
            
            // Clear session
            unset($_SESSION['pending_verification_user_id']);
            unset($_SESSION['pending_verification_phone']);
        } else {
            // Increment attempts
            $query = "UPDATE users 
                      SET verification_attempts = verification_attempts + 1 
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $error_message = 'Invalid or expired verification code. Please try again.';
        }
    }
}

// Handle resend code
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $query = "SELECT password_reset_expiry FROM users 
              WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        $last_expiry = strtotime($user['password_reset_expiry']);
        $now = time();
        
        // Allow resend if code has expired or within reasonable time
        if ($last_expiry < $now || ($now - $last_expiry) >= -540) { // 540 seconds = 9 minutes
            $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Update verification code
            $query = "UPDATE users 
                      SET password_reset_code = :token, 
                          password_reset_code_expiry = :expiry,
                          verification_attempts = 0
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':token', $verification_code);
            $stmt->bindParam(':expiry', $expiry);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                // Send SMS
                $semaphore = new SemaphoreAPI();
                $message = "Your MERS verification code is: " . $verification_code . ". Valid for 10 minutes.";
                $sms_result = $semaphore->sendSMS($phone, $message);
                
                if ($sms_result['success']) {
                    $success_message = 'Verification code resent to your phone.';
                } else {
                    $error_message = 'Failed to resend verification code. Please try again.';
                }
            }
        } else {
            $error_message = 'Please wait before requesting a new code.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row min-vh-100 d-flex justify-content-center align-items-center py-5">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/img/logo.png" alt="Agoncillo Logo" class="img-fluid mb-3" style="max-height: 80px;">
                        <h2 class="fw-bold text-primary">Verify Your Phone</h2>
                        <p class="text-muted">Enter the verification code sent to</p>
                        <p class="fw-bold">+63<?php echo substr($phone, -10); ?></p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $success_message; ?>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-primary w-100">Go to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label for="verification_code" class="form-label">Verification Code *</label>
                            <input type="text" class="form-control form-control-lg text-center" 
                                   id="verification_code" name="verification_code" 
                                   placeholder="000000" maxlength="6" 
                                   inputmode="numeric" required autofocus>
                            <div class="form-text text-center mt-2">Enter the 6-digit code</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Verify Code</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">Didn't receive the code?</p>
                        <a href="verify-sms.php?resend=1" class="btn btn-link">Resend Code</a>
                    </div>
                    
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <p><a href="register.php" class="text-primary">Back to Registration</a></p>
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
