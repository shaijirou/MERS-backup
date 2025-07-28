<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$verification_filter = $_GET['verified'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$search = $_GET['search'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build WHERE clause
$where_conditions = ["user_type = 'resident'"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}
if ($verification_filter !== '') {
    $where_conditions[] = "verification_status = :verified";
    $params[':verified'] = $verification_filter;
}
if ($barangay_filter) {
    $where_conditions[] = "barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}
if ($search) {
    $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get users data
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.address, u.barangay, 
                 u.verification_status, u.status, u.created_at,
                 COUNT(ir.id) as incident_reports_count
          FROM users u 
          LEFT JOIN incident_reports ir ON u.id = ir.user_id
          WHERE $where_clause
          GROUP BY u.id
          ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

if (!$stmt) {
    die('Error executing query');
}

$filename = 'users_export_' . date('Y-m-d_H-i-s');

if ($format == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, [
        'ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Address',
        'Barangay',
        'Verified',
        'Status',
        'Incident Reports',
        'Registration Date'
    ]);

    // Write data rows
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'],
            $row['address'],
            $row['barangay'],
            $row['verification_status'] ? 'Yes' : 'No',
            ucfirst($row['status']),
            $row['incident_reports_count'],
            date('Y-m-d H:i:s', strtotime($row['created_at']))
        ]);
    }

    fclose($output);
    exit();

} elseif ($format == 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>First Name</th>';
    echo '<th>Last Name</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Address</th>';
    echo '<th>Barangay</th>';
    echo '<th>Verified</th>';
    echo '<th>Status</th>';
    echo '<th>Incident Reports</th>';
    echo '<th>Registration Date</th>';
    echo '</tr>';

    while ($row = $stmt->fetch()) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['first_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['last_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
        echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($row['address']) . '</td>';
        echo '<td>' . htmlspecialchars($row['barangay']) . '</td>';
        echo '<td>' . ($row['verification_status'] ? 'Yes' : 'No') . '</td>';
        echo '<td>' . ucfirst($row['status']) . '</td>';
        echo '<td>' . $row['incident_reports_count'] . '</td>';
        echo '<td>' . date('Y-m-d H:i:s', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit();

} else {
    // Invalid format
    header('Location: users.php?error=invalid_format');
    exit();
}
?>
