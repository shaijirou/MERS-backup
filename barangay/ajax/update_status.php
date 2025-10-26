<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/config.php';

try {
    // Check if user is logged in and is barangay personnel
    if (!isLoggedIn() || !isBarangay()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
        $responder_name = isset($_POST['responder_name']) ? sanitizeInput($_POST['responder_name']) : null;
        $user_id = $_SESSION['user_id'];
        
        if (!$incident_id || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Update incident status
        $query = "UPDATE incident_reports 
                  SET response_status = :status, 
                      assigned_to = :user_id,
                      responder_name = :responder_name,
                      updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :incident_id 
                  AND approval_status = 'approved'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':responder_name', $responder_name);
        $stmt->bindParam(':incident_id', $incident_id);
        
        if ($stmt->execute()) {
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, incident_id, action, description) 
                          VALUES (:user_id, :incident_id, :action, :description)";
            $log_stmt = $db->prepare($log_query);
            $action = "Barangay Status Updated";
            $description = "Updated local incident status to " . $status . " by " . ($responder_name ?? 'Unknown');
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
} catch (Exception $e) {
    error_log("Barangay update_status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
