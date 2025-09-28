<?php
require_once '../../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
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
        // Log activity with fire-specific terminology
        $log_query = "INSERT INTO activity_logs (user_id, incident_id, action, description) 
                      VALUES (:user_id, :incident_id, :action, :description)";
        $log_stmt = $db->prepare($log_query);
        $action = "Fire Department Status Updated";
        
        $status_description = '';
        switch($status) {
            case 'responding': $status_description = 'Fire truck en route to incident'; break;
            case 'on_scene': $status_description = 'Fire department on scene, fighting fire'; break;
            case 'resolved': $status_description = 'Fire extinguished, incident resolved'; break;
            default: $status_description = 'Updated fire incident status to ' . $status;
        }
        
        $log_stmt->bindParam(':user_id', $user_id);
        $log_stmt->bindParam(':incident_id', $incident_id);
        $log_stmt->bindParam(':action', $action);
        $log_stmt->bindParam(':description', $status_description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Fire incident status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update fire incident status']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
