<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get filter parameters (same as incidents.php)
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ir.status = :status";
    $params[':status'] = $status_filter;
}
if ($type_filter) {
    $where_conditions[] = "ir.incident_type = :type";
    $params[':type'] = $type_filter;
}
if ($severity_filter) {
    $where_conditions[] = "ir.severity = :severity";
    $params[':severity'] = $severity_filter;
}
if ($barangay_filter) {
    $where_conditions[] = "u.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}
if ($search) {
    $where_conditions[] = "(ir.description LIKE :search OR ir.location LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($date_from) {
    $where_conditions[] = "DATE(ir.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(ir.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT ir.*, u.first_name, u.last_name, u.email, u.phone, u.barangay,
                 admin.first_name as reviewed_by_name, admin.last_name as reviewed_by_lastname
          FROM incident_reports ir 
          LEFT JOIN users u ON ir.user_id = u.id 
          LEFT JOIN users admin ON ir.reviewed_by = admin.id
          $where_clause 
          ORDER BY ir.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$incidents = $stmt->fetchAll();

$filename = 'incidents_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// CSV headers
$headers = [
    'ID',
    'Reporter Name',
    'Reporter Email',
    'Reporter Phone',
    'Reporter Barangay',
    'Incident Type',
    'Description',
    'Location',
    'Latitude',
    'Longitude',
    'Urgency Level',
    'Status',
    'Resolution Notes',
    'Reviewed By',
    'Reviewed At',
    'Reported At',
    'Updated At'
];

fputcsv($output, $headers);

foreach ($incidents as $incident) {
    $row = [
        $incident['id'] ?? '',
        ($incident['first_name'] ?? '') . ' ' . ($incident['last_name'] ?? ''),
        $incident['email'] ?? '',
        $incident['phone'] ?? '',
        $incident['barangay'] ?? '',
        $incident['incident_type'] ? ucfirst($incident['incident_type']) : '',
        $incident['description'] ?? '',
        $incident['location'] ?? '',
        $incident['latitude'] ?? '',
        $incident['longitude'] ?? '',
        $incident['urgency_level'] ? ucfirst($incident['urgency_level']) : '',
        $incident['status'] ? ucfirst(str_replace('_', ' ', $incident['status'])) : '',
        $incident['resolution_notes'] ?? '',
        ($incident['reviewed_by_name'] ?? '') ? ($incident['reviewed_by_name'] ?? '') . ' ' . ($incident['reviewed_by_lastname'] ?? '') : '',
        $incident['reviewed_at'] ? date('Y-m-d H:i:s', strtotime($incident['reviewed_at'])) : '',
        $incident['created_at'] ? date('Y-m-d H:i:s', strtotime($incident['created_at'])) : '',
        $incident['updated_at'] ? date('Y-m-d H:i:s', strtotime($incident['updated_at'])) : ''
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
