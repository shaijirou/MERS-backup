<?php
require_once '../../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    $user_id = $_SESSION['user_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE user_notifications 
              SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
              WHERE id = :notification_id AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':notification_id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}
?>
