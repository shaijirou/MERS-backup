<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Center ID is required']);
    exit;
}

try {
    $barangay_stmt = $conn->prepare("SELECT id FROM barangays WHERE name = ?");
    $barangay_stmt->bind_param("s", $input['barangay']);
    $barangay_stmt->execute();
    $barangay_result = $barangay_stmt->get_result();
    
    if ($barangay_result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid barangay']);
        exit;
    }
    
    $barangay_row = $barangay_result->fetch_assoc();
    $barangay_id = $barangay_row['id'];
    
    $stmt = $conn->prepare("
        UPDATE evacuation_centers 
        SET name = ?, address = ?, barangay_id = ?, capacity = ?, 
            current_occupancy = ?, contact_person = ?, contact_number = ?, 
            facilities = ?, status = ?, latitude = ?, longitude = ?
        WHERE id = ?
    ");
    
    $facilities_json = json_encode($input['facilities']);
    
    $stmt->bind_param("ssiiisssssddi", 
        $input['name'],
        $input['address'], 
        $barangay_id,
        $input['capacity'],
        $input['current_occupancy'],
        $input['contact_person'],
        $input['contact_number'],
        $facilities_json,
        $input['status'],
        $input['latitude'],
        $input['longitude'],
        $input['id']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Center updated successfully']);
    } else {
        throw new Exception('Failed to update center');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
