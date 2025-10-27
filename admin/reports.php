<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Reports & Analytics';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Get date range for reports
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Generate reports based on type
switch ($report_type) {
    case 'incidents':
        $title = 'Incident Reports';
        break;
    case 'alerts':
        $title = 'Alert Reports';
        break;
    case 'evacuation':
        $title = 'Evacuation Center Reports';
        break;
    case 'users':
        $title = 'User Activity Reports';
        break;
    default:
        $title = 'Summary Report';
        break;
}

// Get summary statistics
$summary_stats = [];

// Incidents statistics
$incidents_query = "SELECT 
                   COUNT(*) as total_incidents,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_incidents,
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents
                   FROM incident_reports 
                   WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
$incidents_stmt = $db->prepare($incidents_query);
$incidents_stmt->bindParam(':date_from', $date_from);
$incidents_stmt->bindParam(':date_to', $date_to);
$incidents_stmt->execute();
$summary_stats['incidents'] = $incidents_stmt->fetch(PDO::FETCH_ASSOC);

// Alerts statistics
$alerts_query = "SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_alerts,
                SUM(CASE WHEN severity_level = 'critical' THEN 1 ELSE 0 END) as critical_alerts
                FROM alerts 
                WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
$alerts_stmt = $db->prepare($alerts_query);
$alerts_stmt->bindParam(':date_from', $date_from);
$alerts_stmt->bindParam(':date_to', $date_to);
$alerts_stmt->execute();
$summary_stats['alerts'] = $alerts_stmt->fetch(PDO::FETCH_ASSOC);

// Users statistics
$users_query = "SELECT 
               COUNT(*) as total_users,
               SUM(CASE WHEN verification_status = 1 THEN 1 ELSE 0 END) as verified_users,
               SUM(CASE WHEN DATE(created_at) BETWEEN :date_from AND :date_to THEN 1 ELSE 0 END) as new_users
               FROM users WHERE user_type = 'resident'";
$users_stmt = $db->prepare($users_query);
$users_stmt->bindParam(':date_from', $date_from);
$users_stmt->bindParam(':date_to', $date_to);
$users_stmt->execute();
$summary_stats['users'] = $users_stmt->fetch(PDO::FETCH_ASSOC);

// Evacuation centers statistics
$evacuation_query = "SELECT 
                    COUNT(*) as total_centers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_centers,
                    SUM(capacity) as total_capacity,
                    SUM(current_occupancy) as total_occupancy
                    FROM evacuation_centers";
$evacuation_stmt = $db->prepare($evacuation_query);
$evacuation_stmt->execute();
$summary_stats['evacuation'] = $evacuation_stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed data based on report type
$detailed_data = [];
switch ($report_type) {
    case 'incidents':
        $query = "SELECT ir.*, u.first_name, u.last_name, u.email, u.barangay,
                         responder_name
                 FROM incident_reports ir 
                 LEFT JOIN users u ON ir.user_id = u.id 
                 LEFT JOIN users responder ON ir.assigned_to = responder.id
                 WHERE DATE(ir.created_at) BETWEEN :date_from AND :date_to
                 ORDER BY ir.created_at DESC";
        break;
    case 'alerts':
        $query = "SELECT a.*, u.first_name as admin_first_name, u.last_name as admin_last_name 
                 FROM alerts a 
                 LEFT JOIN users u ON a.sent_by = u.id 
                 WHERE DATE(a.created_at) BETWEEN :date_from AND :date_to
                 ORDER BY a.created_at DESC";
        break;
    case 'evacuation':
        $query = "SELECT * FROM evacuation_centers ORDER BY name";
        break;
    case 'users':
        $query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM incident_reports WHERE user_id = u.id) as incident_count
                 FROM users u 
                 WHERE DATE(u.created_at) BETWEEN :date_from AND :date_to
                 AND u.user_type = 'resident'
                 ORDER BY u.created_at DESC";
        break;
}

if (isset($query)) {
    $detailed_stmt = $db->prepare($query);
    if ($report_type !== 'evacuation') {
        $detailed_stmt->bindParam(':date_from', $date_from);
        $detailed_stmt->bindParam(':date_to', $date_to);
    }
    $detailed_stmt->execute();
    $detailed_data = $detailed_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get incidents by barangay
$barangay_query = "SELECT u.barangay, COUNT(*) as incident_count 
                  FROM incident_reports ir 
                  LEFT JOIN users u ON ir.user_id = u.id 
                  WHERE DATE(ir.created_at) BETWEEN :date_from AND :date_to
                  AND u.barangay IS NOT NULL
                  GROUP BY u.barangay 
                  ORDER BY incident_count DESC";
$barangay_stmt = $db->prepare($barangay_query);
$barangay_stmt->bindParam(':date_from', $date_from);
$barangay_stmt->bindParam(':date_to', $date_to);
$barangay_stmt->execute();
$barangay_data = $barangay_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get incidents by type
$type_query = "SELECT incident_type, COUNT(*) as count 
              FROM incident_reports 
              WHERE DATE(created_at) BETWEEN :date_from AND :date_to
              GROUP BY incident_type 
              ORDER BY count DESC";
$type_stmt = $db->prepare($type_query);
$type_stmt->bindParam(':date_from', $date_from);
$type_stmt->bindParam(':date_to', $date_to);
$type_stmt->execute();
$type_data = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<style>
@media print {
    * {
        margin: 0;
        padding: 0;
    }
    
    @page {
        margin: 0;
    }
    
    body {
        margin: 0;
        padding: 10px;
        font-size: 12px;
        line-height: 1.4;
    }
    
    /* Hide all non-print elements including navbar and administrator section */
    .no-print,
    #page-content-wrapper > .navbar,
    .navbar,
    .sidebar,
    #wrapper > div:first-child {
        display: none !important;
    }
    
    /* Make page content full width in print */
    #page-content-wrapper {
        margin-left: 0 !important;
        width: 100% !important;
    }
    
    .print-header {
        display: flex !important;
        justify-content: center;
        align-items: center;
        border-bottom: 3px solid #333;
        padding: 8px 0;
        margin-bottom: 10px;
        page-break-after: avoid;
        text-align: center;
    }
    
    .print-header img {
        height: 50px;
        width: auto;
    }
    
    .print-header-center {
        text-align: center;
        flex: 1;
        padding: 0 10px;
    }
    
    .print-header-center h2 {
        margin: 0;
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    
    .print-header-center p {
        margin: 2px 0;
        font-size: 12px;
        color: #666;
    }
    
    .print-charts {
        display: flex !important;
        gap: 10px;
        margin-bottom: 10px;
        page-break-inside: avoid;
        justify-content: center;
    }
    
    .print-chart-container {
        flex: 1;
        max-width: 350px;
        border: 1px solid #999;
        padding: 8px;
        background-color: #f9f9f9;
        page-break-inside: avoid;
        text-align: center;
    }
    
    .print-chart-container h6 {
        font-size: 12px;
        font-weight: bold;
        margin: 0 0 8px 0;
        text-align: center;
        border-bottom: 1px solid #ddd;
        padding-bottom: 4px;
    }
    
    .print-chart-container canvas {
        max-width: 100%;
        height: auto !important;
        margin: 0 auto;
        display: block;
    }
    
    .print-footer {
        display: block !important;
        border-top: 3px solid #333;
        text-align: center;
        padding: 8px 0;
        margin-top: 10px;
        font-size: 12px;
        color: #666;
        page-break-before: avoid;
        position: fixed;
        bottom: 10px;
        left: 10px;
        right: 10px;
        width: calc(100% - 20px);
    }
    
    .print-footer p {
        margin: 1px 0;
        text-align: center;
    }
    
    .print-footer p:first-child {
        font-weight: bold;
    }
    
    /* Added print disclaimer styling */
    .print-disclaimer {
        display: block !important;
        border: 1px solid #999;
        padding: 10px;
        margin: 10px 0;
        background-color: #f5f5f5;
        text-align: justify;
        font-size: 11px;
        line-height: 1.5;
        page-break-inside: avoid;
    }
    
    .print-disclaimer p {
        margin: 0;
        color: #333;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        page-break-inside: avoid;
        margin: 0 auto 70px auto;
    }
    
    table th, table td {
        border: 1px solid #999;
        padding: 6px;
        text-align: center;
        font-size: 12px;
        line-height: 1.3;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    table th {
        background-color: #e8e8e8;
        font-weight: bold;
        text-align: center;
        padding: 5px;
    }
    
    tbody tr {
        page-break-inside: avoid;
    }
    
    .description-cell {
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 120px;
        line-height: 1.3;
        text-align: center;
    }
    
    .summary-stats-print {
        display: flex;
        justify-content: center;
        margin: 8px 0;
        page-break-inside: avoid;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .stat-box {
        flex: 1;
        min-width: 120px;
        border: 1px solid #999;
        padding: 6px;
        text-align: center;
        background-color: #f5f5f5;
    }
    
    .stat-box h4 {
        font-size: 14px;
        font-weight: bold;
        margin: 2px 0;
    }
    
    .stat-box p {
        font-size: 12px;
        margin: 0;
    }
    
    .card {
        border: 1px solid #999 !important;
        page-break-inside: avoid;
        margin: 8px auto;
        max-width: 100%;
    }
    
    .card-header {
        background-color: #e8e8e8;
        border-bottom: 1px solid #999;
        padding: 5px;
        text-align: center;
    }
    
    .card-header h5 {
        font-size: 12px;
        font-weight: bold;
        margin: 0;
        text-align: center;
    }
    
    .card-body {
        padding: 5px;
    }
    
    /* Prevent empty pages from printing */
    tr:empty {
        display: none;
    }
    
    .container-fluid {
        padding-bottom: 80px;
        text-align: center;
    }
}

@media screen {
    .print-header, .print-footer, .summary-stats-print, .chart-container {
        display: none;
    }
    
    /* Hide print charts on screen view */
    .print-charts {
        display: none !important;
    }
    
    /* Hide disclaimer on screen view */
    .print-disclaimer {
        display: none !important;
    }
}

/* Screen view styling */
.print-header, .print-footer, .summary-stats-print, .chart-container {
    display: none;
}

/* Screen view table spacing and justified text */
@media screen {
    table th, table td {
        padding: 12px;
        text-align: left;
    }
    
    .description-cell {
        max-width: 300px;
        white-space: normal;
        word-wrap: break-word;
        text-align: justify;
    }
}
</style>

<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Navigation -->
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center py-3 no-print">
                <h1 class="h3 mb-0"><?php echo $title; ?></h1>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="card mb-4 no-print">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" id="report_type">
                                <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="incidents" <?php echo $report_type == 'incidents' ? 'selected' : ''; ?>>Incident Reports</option>
                                <option value="alerts" <?php echo $report_type == 'alerts' ? 'selected' : ''; ?>>Alert Reports</option>
                                <option value="evacuation" <?php echo $report_type == 'evacuation' ? 'selected' : ''; ?>>Evacuation Centers</option>
                                <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Activity</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Generate Report</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Print header with logos and professional styling -->
            <div class="print-header">
                <div>
                    <?php if (file_exists('../assets/img/logo.png')): ?>
                        <img src="../assets/img/logo.png" alt="MERS Logo">
                    <?php endif; ?>
                </div>
                <div class="print-header-center">
                    <h2><?php echo APP_NAME; ?></h2>
                    <p><?php echo $title; ?></p>
                    <p>Period: <?php echo date('F j, Y', strtotime($date_from)); ?> to <?php echo date('F j, Y', strtotime($date_to)); ?></p>
                </div>
                <div>
                    <?php if (file_exists('../assets/img/agonlogo.png')): ?>
                        <img src="../assets/img/agonlogo.png" alt="Municipality Logo">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Added disclaimer paragraph for print output -->
            <div class="print-disclaimer">
                <p>This report is computer-generated by MERS and is provided to ensure proper documentation, accountability, and transparency. All records are securely saved and serve as official references for monitoring activities, verifying data accuracy, and maintaining a clear and reliable audit trail.</p>
            </div>

            <!-- Report Header (Screen View) -->
            <div class="text-center mb-4 no-print">
                <h2><?php echo APP_NAME; ?></h2>
                <h4><?php echo $title; ?></h4>
                <p>Period: <?php echo date('F j, Y', strtotime($date_from)); ?> to <?php echo date('F j, Y', strtotime($date_to)); ?></p>
                <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
            </div>

            <?php if ($report_type == 'summary'): ?>
                <!-- Summary Statistics -->
                <div class="row g-3 mb-4 no-print">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $summary_stats['incidents']['total_incidents'] ?? 0; ?></h4>
                                        <p class="card-text">Total Incidents</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $summary_stats['alerts']['total_alerts'] ?? 0; ?></h4>
                                        <p class="card-text">Total Alerts</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-bell fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $summary_stats['users']['new_users'] ?? 0; ?></h4>
                                        <p class="card-text">New Users</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-person-plus fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $summary_stats['evacuation']['active_centers'] ?? 0; ?></h4>
                                        <p class="card-text">Active Centers</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-house fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Print summary statistics with improved layout -->
                <div class="summary-stats-print">
                    <div class="stat-box">
                        <h4><?php echo $summary_stats['incidents']['total_incidents'] ?? 0; ?></h4>
                        <p>Total Incidents</p>
                    </div>
                    <div class="stat-box">
                        <h4><?php echo $summary_stats['alerts']['total_alerts'] ?? 0; ?></h4>
                        <p>Total Alerts</p>
                    </div>
                    <div class="stat-box">
                        <h4><?php echo $summary_stats['users']['new_users'] ?? 0; ?></h4>
                        <p>New Users</p>
                    </div>
                    <div class="stat-box">
                        <h4><?php echo $summary_stats['evacuation']['active_centers'] ?? 0; ?></h4>
                        <p>Active Centers</p>
                    </div>
                </div>

                <!-- Added print-friendly charts section -->
                <div class="print-charts">
                    <div class="print-chart-container">
                        <h6>Incidents by Barangay</h6>
                        <canvas id="printBarangayChart"></canvas>
                    </div>
                    <div class="print-chart-container">
                        <h6>Incidents by Type</h6>
                        <canvas id="printTypeChart"></canvas>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row g-3 mb-4 no-print">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Incidents by Barangay</h5>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px; margin-bottom: 20px;">
                                    <canvas id="barangayChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Incidents by Type</h5>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px; margin-bottom: 20px;">
                                    <canvas id="typeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Tables -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Incidents by Barangay</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Barangay</th>
                                                <th>Incidents</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($barangay_data as $data): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($data['barangay']); ?></td>
                                                    <td><?php echo $data['incident_count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Incidents by Type</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Type</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($type_data as $data): ?>
                                                <tr>
                                                    <td><?php echo ucfirst($data['incident_type']); ?></td>
                                                    <td><?php echo $data['count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Data Table -->
            <?php if (!empty($detailed_data)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Detailed <?php echo $title; ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="detailedTable">
                                <thead class="table-light">
                                    <tr>
                                        <?php if ($report_type == 'incidents'): ?>
                                            <th>Date</th>
                                            <th>Reporter</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Department</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Responder Name</th>
                                        <?php elseif ($report_type == 'alerts'): ?>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Barangay</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                        <?php elseif ($report_type == 'evacuation'): ?>
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Capacity</th>
                                            <th>Occupancy</th>
                                            <th>Status</th>
                                            <th>Contact</th>
                                        <?php elseif ($report_type == 'users'): ?>
                                            <th>Registration Date</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Barangay</th>
                                            <th>Verified</th>
                                            <th>Incidents Reported</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailed_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type == 'incidents'): ?>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo ucfirst($row['incident_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                                <td><?php echo $row['responder_type'] ? ucfirst($row['responder_type']) : 'Not Assigned'; ?></td>
                                                <!-- Description with proper text wrapping -->
                                                <td class="description-cell"><?php echo htmlspecialchars($row['description']); ?></td>
                                                <!-- Changed from status to response_status -->
                                                <td><?php echo !empty($row['response_status']) ? ucfirst($row['response_status']) : 'Pending'; ?></td>
                                                <!-- Display only responder_name without badge number -->
                                                <td>
                                                    <?php if ($row['assigned_to'] && !empty($row['responder_name'])): ?>
                                                        <?php echo htmlspecialchars($row['responder_name']); ?>
                                                    <?php else: ?>
                                                        Not Assigned
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($report_type == 'alerts'): ?>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td><?php echo ucfirst($row['alert_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['affected_barangays']); ?></td>
                                                <td><?php echo ucfirst($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['admin_first_name'] . ' ' . $row['admin_last_name']); ?></td>
                                            <?php elseif ($report_type == 'evacuation'): ?>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                                <td><?php echo number_format($row['capacity']); ?></td>
                                                <td><?php echo number_format($row['current_occupancy']); ?></td>
                                                <td><?php echo ucfirst($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_person']) . '<br>' . htmlspecialchars($row['contact_number']); ?></td>
                                            <?php elseif ($report_type == 'users'): ?>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                                                <td><?php echo $row['verification_status'] ; ?></td>
                                                <td><?php echo $row['incident_count']; ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Professional print footer matching print_incident_report.php -->
            <div class="print-footer">
                <p>All Rights Reserved - MERS</p>
                <p>Mobile Emergency Response System</p>
                <p>Report Generated: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

function convertChartsToImages() {
    // Convert barangay chart to image
    const barangayCanvas = document.getElementById('barangayChart');
    if (barangayCanvas && barangayCanvas.offsetParent !== null) {
        const barangayImage = barangayCanvas.toDataURL('image/png');
        const printBarangayCanvas = document.getElementById('printBarangayChart');
        if (printBarangayCanvas) {
            const ctx = printBarangayCanvas.getContext('2d');
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0);
            };
            img.src = barangayImage;
        }
    }

    // Convert type chart to image
    const typeCanvas = document.getElementById('typeChart');
    if (typeCanvas && typeCanvas.offsetParent !== null) {
        const typeImage = typeCanvas.toDataURL('image/png');
        const printTypeCanvas = document.getElementById('printTypeChart');
        if (printTypeCanvas) {
            const ctx = printTypeCanvas.getContext('2d');
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0);
            };
            img.src = typeImage;
        }
    }
}

// Initialize charts for summary report
<?php if ($report_type == 'summary'): ?>
    // Barangay Chart
    const barangayCtx = document.getElementById('barangayChart').getContext('2d');
    const barangayChart = new Chart(barangayCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['barangay'] . "'"; }, $barangay_data)); ?>],
            datasets: [{
                label: 'Incidents',
                data: [<?php echo implode(',', array_column($barangay_data, 'incident_count')); ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Type Chart
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    const typeChart = new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . ucfirst($item['incident_type']) . "'"; }, $type_data)); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($type_data, 'count')); ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    const printBarangayCtx = document.getElementById('printBarangayChart').getContext('2d');
    const printBarangayChart = new Chart(printBarangayCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['barangay'] . "'"; }, $barangay_data)); ?>],
            datasets: [{
                label: 'Incidents',
                data: [<?php echo implode(',', array_column($barangay_data, 'incident_count')); ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: true,
            width: 300,
            height: 200,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 10 }
                    }
                },
                x: {
                    ticks: {
                        font: { size: 10 }
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        font: { size: 10 }
                    }
                }
            }
        }
    });

    const printTypeCtx = document.getElementById('printTypeChart').getContext('2d');
    const printTypeChart = new Chart(printTypeCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . ucfirst($item['incident_type']) . "'"; }, $type_data)); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($type_data, 'count')); ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ]
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: true,
            width: 300,
            height: 200,
            plugins: {
                legend: {
                    labels: {
                        font: { size: 10 }
                    }
                }
            }
        }
    });

    window.addEventListener('load', function() {
        convertChartsToImages();
    });

    window.addEventListener('beforeprint', function() {
        convertChartsToImages();
    });
<?php endif; ?>

// Export to CSV function

</script>

<?php include '../includes/footer.php'; ?>
