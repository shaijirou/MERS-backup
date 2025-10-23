<?php
require_once 'config/config.php';
require_once 'config/emailjs.php';
require_once 'includes/EmailService.php';
require_once 'includes/PasswordResetHelper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$email = sanitizeInput($_POST['email'] ?? '');

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if user exists
    $query = "SELECT id, first_name, last_name, email FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // For security, don't reveal if email exists
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'If an account exists with this email, you will receive a password reset code shortly.'
        ]);
        exit();
    }
    
    $user = $stmt->fetch();
    $user_id = $user['id'];
    $user_name = $user['first_name'] . ' ' . $user['last_name'];
    
    $resetHelper = new PasswordResetHelper($db);
    
    // Generate reset code and expiry time
    $reset_code = $resetHelper->generateResetCode();
    $expiry_time = $resetHelper->getResetCodeExpiry();
    
    error_log("[Password Reset] Generated code for user: $user_id | Email: $email");
    
    // Create reset request in database
    if (!$resetHelper->createResetRequest($user_id, $reset_code, $expiry_time)) {
        error_log("[Password Reset] Failed to create reset request in database");
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to create reset request. Please try again later.'
        ]);
        exit();
    }
    
    error_log("[Password Reset] Reset request created successfully");
    
    // Send email via EmailJS
    $emailService = new EmailService();
    
    error_log("[Password Reset] Attempting to send email to: $email");
    
    $email_sent = $emailService->sendPasswordResetEmail(
        $email,
        $user_name,
        $reset_code
    );
    
    if ($email_sent) {
        logActivity($user_id, 'Password reset code requested');
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset code sent to your email. Please check your inbox.',
            'redirect' => 'reset-password.php'
        ]);
        error_log("[Password Reset] Email sent successfully to: $email");
    } else {
        error_log("[Password Reset] Failed to send email to: $email");
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send reset code. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("[Password Reset] Exception: " . $e->getMessage());
    error_log("[Password Reset] Stack Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>
