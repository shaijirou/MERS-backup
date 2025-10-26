<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['reset_code'])) {
        throw new Exception('Missing required fields');
    }
    
    $email = sanitizeInput($data['email']);
    $reset_code = sanitizeInput($data['reset_code']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Update user with reset code and expiry time (15 minutes)
    $reset_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $update_query = "UPDATE users SET password_reset_code = :code, password_reset_code_expiry = :expiry WHERE email = :email";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':code', $reset_code);
    $update_stmt->bindParam(':expiry', $reset_expiry);
    $update_stmt->bindParam(':email', $email);
    $update_stmt->execute();
    
    // Also store in session for quick access
    $_SESSION['reset_code_email'] = $email;
    $_SESSION['reset_code_time'] = time();
    
    echo json_encode(['success' => true, 'message' => 'Reset code stored']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
