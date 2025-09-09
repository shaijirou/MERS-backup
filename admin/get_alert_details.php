<?php
require_once '../config/config.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Alert ID required']);
    exit;
}

$alert_id = $_GET['id'];

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT a.*, u.first_name, u.last_name,
              CONCAT(u.first_name, ' ', u.last_name) as created_by_name
              FROM alerts a 
              LEFT JOIN users u ON a.sent_by = u.id 
              WHERE a.id = :alert_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':alert_id', $alert_id);
    $stmt->execute();
    
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alert) {
        echo json_encode([
            'success' => true,
            'alert' => $alert
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Alert not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
