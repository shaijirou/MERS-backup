<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Center ID is required']);
    exit;
}

$center_id = intval($_GET['id']);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("
        SELECT ec.*, b.name as barangay_name 
        FROM evacuation_centers ec 
        LEFT JOIN barangays b ON ec.barangay_id = b.id 
        WHERE ec.id = ?
    ");
    $stmt->bindParam(1, $center_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Center not found']);
        exit;
    }
    
    $center = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Don't parse facilities as JSON - it's stored as plain text
    
    echo json_encode($center);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
