<?php
require_once '../../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get incidents that are approved and relevant to firefighters
    // Focus on fire-related incidents and high-priority emergencies
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.approval_status = 'approved' 
              AND (ir.incident_type LIKE '%fire%' 
                   OR ir.incident_type LIKE '%explosion%' 
                   OR ir.incident_type LIKE '%burn%' 
                   OR ir.incident_type LIKE '%smoke%')
              ORDER BY 
                CASE 
                    WHEN ir.response_status = 'notified' THEN 1
                    WHEN ir.response_status = 'responding' THEN 2
                    WHEN ir.response_status = 'on_scene' THEN 3
                    WHEN ir.response_status = 'resolved' THEN 4
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
    error_log("Error in get_fire_incidents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading incidents: ' . $e->getMessage()
    ]);
}
?>
