<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/config.php';

try {
    // Check if user is logged in and is emergency personnel
    if (!isLoggedIn() || !isEmergency()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
        $new_status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
        $responder_name = isset($_POST['responder_name']) ? sanitizeInput($_POST['responder_name']) : null;
        
        if (!$incident_id || !$new_status) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        // Validate status
        $valid_statuses = ['notified', 'responding', 'on_scene', 'resolved'];
        if (!in_array($new_status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Update incident status
        $stmt = $db->prepare("
            UPDATE incident_reports 
            SET response_status = :status,
                assigned_to = :user_id,
                responder_name = :responder_name,
                updated_at = NOW()
            WHERE id = :incident_id 
            AND approval_status = 'approved'
        ");
        
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':incident_id', $incident_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':responder_name', $responder_name);
        
        if ($stmt->execute()) {
            // Log the status change
            $user_id = $_SESSION['user_id'];
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, incident_id, action, description) 
                VALUES (:user_id, :incident_id, :action, :description)
            ");
            
            $action = "Emergency Status Updated";
            $details = "Status changed to: " . $new_status . " by " . ($responder_name ?? 'Unknown');
            $log_stmt->bindParam(':user_id', $user_id);
            $log_stmt->bindParam(':incident_id', $incident_id);
            $log_stmt->bindParam(':action', $action);
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
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    error_log("Emergency update_status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
