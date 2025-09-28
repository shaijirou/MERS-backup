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
    
    // Get fire-related statistics
    $stats_query = "SELECT 
                    COUNT(CASE WHEN ir.response_status IN ('notified', 'responding', 'on_scene') 
                              AND (ir.incident_type LIKE '%fire%' 
                                   OR ir.incident_type LIKE '%explosion%' 
                                   OR ir.incident_type LIKE '%burn%' 
                                   OR ir.incident_type LIKE '%smoke%') THEN 1 END) as active_fires,
                    COUNT(CASE WHEN ir.response_status = 'responding' 
                              AND (ir.incident_type LIKE '%fire%' 
                                   OR ir.incident_type LIKE '%explosion%' 
                                   OR ir.incident_type LIKE '%burn%' 
                                   OR ir.incident_type LIKE '%smoke%'
                                   OR ir.urgency_level IN ('high', 'critical')) THEN 1 END) as en_route,
                    COUNT(CASE WHEN ir.response_status = 'resolved' 
                              AND DATE(ir.updated_at) = CURDATE()
                              AND (ir.incident_type LIKE '%fire%' 
                                   OR ir.incident_type LIKE '%explosion%' 
                                   OR ir.incident_type LIKE '%burn%' 
                                   OR ir.incident_type LIKE '%smoke%'
                                   OR ir.urgency_level IN ('high', 'critical')) THEN 1 END) as resolved_today,
                    COUNT(CASE WHEN MONTH(ir.created_at) = MONTH(CURDATE()) 
                              AND YEAR(ir.created_at) = YEAR(CURDATE())
                              AND (ir.incident_type LIKE '%fire%' 
                                   OR ir.incident_type LIKE '%explosion%' 
                                   OR ir.incident_type LIKE '%burn%' 
                                   OR ir.incident_type LIKE '%smoke%'
                                   OR ir.urgency_level IN ('high', 'critical')) THEN 1 END) as total_month
                    FROM incident_reports ir 
                    WHERE ir.approval_status = 'approved'";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'active_fires' => (int)$stats['active_fires'],
        'en_route' => (int)$stats['en_route'],
        'resolved_today' => (int)$stats['resolved_today'],
        'total_month' => (int)$stats['total_month']
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_fire_statistics.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading statistics: ' . $e->getMessage(),
        'active_fires' => 0,
        'en_route' => 0,
        'resolved_today' => 0,
        'total_month' => 0
    ]);
}
?>
