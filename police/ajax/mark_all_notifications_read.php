<?php
require_once '../../config/config.php';

// Check if user is logged in and is police
if (!isLoggedIn() || !isPolice()) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE user_notifications 
              SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
              WHERE user_id = :user_id AND is_read = FALSE";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}
?>
