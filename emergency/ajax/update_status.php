<?php
require_once '../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    
    // Validate status
    $valid_statuses = ['notified', 'responding', 'on_scene', 'resolved'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Update incident status
        $stmt = $db->prepare("
            UPDATE incident_reports 
            SET response_status = :status,
                updated_at = NOW()
            WHERE id = :incident_id 
            AND approval_status = 'approved'
        ");
        
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':incident_id', $incident_id);
        
        if ($stmt->execute()) {
            // Log the status change
            $user_id = $_SESSION['user_id'];
            $log_stmt = $db->prepare("
                INSERT INTO incident_logs (incident_id, user_id, action, details, created_at)
                VALUES (:incident_id, :user_id, 'status_update', :details, NOW())
            ");
            
            $details = "Status changed to: " . $new_status;
            $log_stmt->bindParam(':incident_id', $incident_id);
            $log_stmt->bindParam(':user_id', $user_id);
            $log_stmt->bindParam(':details', $details);
            $log_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update status'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error in update_status.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
