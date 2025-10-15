<?php
require_once '../../config/config.php';

// Check if user is logged in and is police officer
if (!isLoggedIn() || !isPolice()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get incidents that are approved and relevant to police
    // Focus on crime-related incidents and law enforcement matters
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.approval_status = 'approved' 
              AND ir.responder_type = 'police'
              AND (ir.incident_type LIKE '%crime%' 
                   OR ir.incident_type LIKE '%theft%' 
                   OR ir.incident_type LIKE '%robbery%' 
                   OR ir.incident_type LIKE '%assault%'
                   OR ir.incident_type LIKE '%violence%'
                   OR ir.incident_type LIKE '%disturbance%'
                   OR ir.incident_type LIKE '%vandalism%'
                   OR ir.incident_type LIKE '%trespassing%')
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
    error_log("Error in get_police_incidents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading incidents: ' . $e->getMessage()
    ]);
}
?>
