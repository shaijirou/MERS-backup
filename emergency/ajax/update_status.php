<?php
require_once '../../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $incident_id = (int)$_POST['incident_id'];
    $status = sanitizeInput($_POST['status']);
    $user_id = $_SESSION['user_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Update incident status
    $query = "UPDATE incident_reports 
              SET response_status = :status, 
                  assigned_to = :user_id,
                  updated_at = CURRENT_TIMESTAMP 
              WHERE id = :incident_id 
              AND approval_status = 'approved'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':incident_id', $incident_id);
    
    if ($stmt->execute()) {
        // Log activity
        $log_query = "INSERT INTO activity_logs (user_id, incident_id, action, description) 
                      VALUES (:user_id, :incident_id, :action, :description)";
        $log_stmt = $db->prepare($log_query);
        $action = "Emergency Status Updated";
        $description = "Updated emergency incident status to " . $status;
        $log_stmt->bindParam(':user_id', $user_id);
        $log_stmt->bindParam(':incident_id', $incident_id);
        $log_stmt->bindParam(':action', $action);
        $log_stmt->bindParam(':description', $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
