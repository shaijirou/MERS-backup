<?php
require_once '../../config/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all approved incidents for emergency responders
    // Emergency personnel see ALL incidents, not filtered by barangay
    $query = "SELECT ir.*, 
                     u.first_name, 
                     u.last_name, 
                     u.phone, 
                     u.email, 
                     u.barangay
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.approval_status = 'approved'
              ORDER BY 
                  CASE ir.urgency_level
                      WHEN 'critical' THEN 1
                      WHEN 'high' THEN 2
                      WHEN 'medium' THEN 3
                      WHEN 'low' THEN 4
                      ELSE 5
                  END,
                  CASE ir.response_status
                      WHEN 'notified' THEN 1
                      WHEN 'responding' THEN 2
                      WHEN 'on_scene' THEN 3
                      WHEN 'resolved' THEN 4
                      ELSE 5
                  END,
                  ir.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'incidents' => $incidents
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_incidents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading incidents: ' . $e->getMessage()
    ]);
}
?>
