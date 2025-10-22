<?php
// Notification system for sending notifications to different user types
require_once '../config/database.php';

function sendNotificationToUserType($incident_id, $user_type, $message) {
    global $pdo;
    
    try {
        // Get all users of the specified type
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_type = ? AND status = 'active'");
        $stmt->execute([$user_type]);
        $users = $stmt->fetchAll();
        
        // Send notification to each user
        foreach ($users as $user) {
            $insert_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, incident_id, message, type, created_at) 
                VALUES (?, ?, ?, 'incident_approved', NOW())
            ");
            $insert_stmt->execute([$user['id'], $incident_id, $message]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function approveIncidentAndNotify($incident_id) {
    global $pdo;
    
    try {
        // Get incident details
        $stmt = $pdo->prepare("SELECT * FROM incident_reports WHERE id = ?");
        $stmt->execute([$incident_id]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            return false;
        }
        
        // Update incident status to approved
        $update_stmt = $pdo->prepare("UPDATE incident_reports SET status = 'approved' WHERE id = ?");
        $update_stmt->execute([$incident_id]);
        
        // Determine which user types to notify based on incident type
        $user_types_to_notify = [];
        $incident_type = strtolower($incident['incident_type']);
        
        switch ($incident_type) {
            case 'fire':
                $user_types_to_notify = ['firefighter', 'emergency'];
                break;
            case 'medical emergency':
            case 'accident':
                $user_types_to_notify = ['emergency', 'police'];
                break;
            case 'crime':
            case 'theft':
            case 'violence':
                $user_types_to_notify = ['police'];
                break;
            case 'flood':
            case 'landslide':
            case 'earthquake':
                $user_types_to_notify = ['emergency', 'barangay', 'police'];
                break;
            default:
                $user_types_to_notify = ['emergency', 'police', 'barangay'];
                break;
        }
        
        // Send notifications to relevant user types
        foreach ($user_types_to_notify as $user_type) {
            $message = "New incident approved: {$incident['incident_type']} at {$incident['location']}.";
            sendNotificationToUserType($incident_id, $user_type, $message);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Approval error: " . $e->getMessage());
        return false;
    }
}

// AJAX handler for approving incidents
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve_incident') {
    $incident_id = $_POST['incident_id'];
    
    if (approveIncidentAndNotify($incident_id)) {
        echo json_encode(['success' => true, 'message' => 'Incident approved and notifications sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve incident']);
    }
    exit();
}
?>
