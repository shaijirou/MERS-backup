<?php
require_once '../../config/config.php';

// Check if user is logged in and is emergency medical personnel
if (!isLoggedIn() || !isEmergency()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get incidents that are approved and relevant to emergency medical services
    // Focus on medical emergencies and health-related incidents
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.approval_status = 'approved'
               AND ir.responder_type = 'emergency'
              AND (ir.incident_type LIKE '%medical%' 
                   OR ir.incident_type LIKE '%health%' 
                   OR ir.incident_type LIKE '%injury%' 
                   OR ir.incident_type LIKE '%accident%'
                   OR ir.incident_type LIKE '%emergency%'
                   OR ir.incident_type LIKE '%ambulance%'
                   OR ir.incident_type LIKE '%cardiac%'
                   OR ir.incident_type LIKE '%trauma%'
                   OR ir.injuries = 'yes')
              ORDER BY 
                CASE 
                    WHEN ir.response_status = 'notified' THEN 1
                    WHEN ir.response_status = 'responding' THEN 2
                    WHEN ir.response_status = 'on_scene' THEN 3
                    WHEN ir.response_status = 'resolved' THEN 4
                    ELSE 5
                END,
                ir.urgency_level = 'critical' DESC,
                ir.urgency_level = 'high' DESC,
                ir.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'incidents' => $incidents
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_emergency_incidents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading incidents: ' . $e->getMessage()
    ]);
}
?>
