<?php
require_once '../../config/config.php';

// Check if user is logged in and is barangay personnel
if (!isLoggedIn() || !isBarangay()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get incidents that are approved and relevant to barangay
    // Focus on community-related incidents and local matters
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.approval_status = 'approved' 
              AND ir.responder_type = 'barangay'
              AND (
                   ir.incident_type LIKE '%community%' 
                   OR ir.incident_type LIKE '%barangay%' 
                   OR ir.incident_type LIKE '%flood%' 
                   OR ir.incident_type LIKE '%road accident%'
                   OR ir.incident_type LIKE '%public%'
                   OR ir.incident_type LIKE '%local%'
                   OR ir.incident_type LIKE '%neighborhood%'
                   OR ir.incident_type LIKE '%other%'
              )
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
    error_log("Error in get_barangay_incidents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading incidents: ' . $e->getMessage()
    ]);
}
?>
