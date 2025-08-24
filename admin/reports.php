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
                   SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
                   SUM(CASE WHEN urgency_level = 'critical' THEN 1 ELSE 0 END) as critical_incidents
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
        $query = "SELECT ir.*, u.first_name, u.last_name, u.email, u.barangay 
                 FROM incident_reports ir 
                 LEFT JOIN users u ON ir.user_id = u.id 
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
                    <button class="btn btn-success" onclick="exportToCSV()">
                        <i class="bi bi-download"></i> Export CSV
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

            <!-- Report Header -->
            <div class="text-center mb-4">
                <h2><?php echo APP_NAME; ?></h2>
                <h4><?php echo $title; ?></h4>
                <p>Period: <?php echo date('F j, Y', strtotime($date_from)); ?> to <?php echo date('F j, Y', strtotime($date_to)); ?></p>
                <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
            </div>

            <?php if ($report_type == 'summary'): ?>
                <!-- Summary Statistics -->
                <div class="row g-3 mb-4">
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

                <!-- Charts -->
                <div class="row g-3 mb-4">
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
                                            <th>Severity</th>
                                            <th>Status</th>
                                            <th>Description</th>
                                        <?php elseif ($report_type == 'alerts'): ?>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Severity</th>
                                            <th>Barangay</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                        <?php elseif ($report_type == 'evacuation'): ?>
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Barangay</th>
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
                                                <td><?php echo ucfirst($row['severity']); ?></td>
                                                <td><?php echo ucfirst($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($row['description'], 0, 100)) . '...'; ?></td>
                                            <?php elseif ($report_type == 'alerts'): ?>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td><?php echo ucfirst($row['alert_type']); ?></td>
                                                <td><?php echo ucfirst($row['severity_level']); ?></td>
                                                <td><?php echo htmlspecialchars($row['affected_barangays']); ?></td>
                                                <td><?php echo ucfirst($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['admin_first_name'] . ' ' . $row['admin_last_name']); ?></td>
                                            <?php elseif ($report_type == 'evacuation'): ?>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                                                <td><?php echo number_format($row['capacity']); ?></td>
                                                <td><?php echo number_format($row['current_occupancy']); ?></td>
                                                <td><?php echo ucfirst($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_person']) . '<br>' . htmlspecialchars($row['contact_number']); ?></td>
                                            <?php elseif ($report_type == 'users'): ?>
                                                <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                                                <td><?php echo $row['is_verified'] ? 'Yes' : 'No'; ?></td>
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});
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
<?php endif; ?>

// Export to CSV function
function exportToCSV() {
    const table = document.getElementById('detailedTable');
    if (!table) {
        alert('No data to export');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        csv.push(row.join(','));
    }

    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    downloadLink.download = '<?php echo strtolower(str_replace(' ', '_', $title)); ?>_<?php echo date('Y-m-d'); ?>.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<style>
@media print {
    .no-print { display: none !important; }
    .card { border: 1px solid #ddd !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
