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
    
    // Check if code matches and is not expired
    $query = "SELECT id FROM users WHERE email = :email AND password_reset_code = :code AND password_reset_code_expiry > NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':code', $reset_code);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        // Code is valid, store verification in session
        $_SESSION['reset_code_verified'] = true;
        $_SESSION['reset_verified_email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'Code verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset code']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
