<?php
require_once '../config/config.php';

// Check if user is logged in and is police
if (!isLoggedIn() || !isPolice()) {
    redirect('../index.php');
}

$page_title = 'Police Reports';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get police reports and statistics
$stats_query = "SELECT 
    COUNT(*) as total_incidents,
    SUM(CASE WHEN response_status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
    SUM(CASE WHEN response_status = 'responding' OR response_status = 'on_scene' THEN 1 ELSE 0 END) as active_incidents,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_incidents
    FROM incident_reports 
    WHERE approval_status = 'approved' 
    AND (responder_type = 'police' OR incident_type LIKE '%crime%' OR incident_type LIKE '%accident%' OR incident_type LIKE '%violence%')";
$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch();

// Get recent incidents for reports
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.barangay
    FROM incident_reports ir 
    JOIN users u ON ir.user_id = u.id 
    WHERE ir.approval_status = 'approved'
    AND (ir.responder_type = 'police' OR ir.incident_type LIKE '%crime%' OR ir.incident_type LIKE '%accident%' OR ir.incident_type LIKE '%violence%')
    ORDER BY ir.created_at DESC 
    LIMIT 50";
$stmt = $db->prepare($incidents_query);
$stmt->execute();
$recent_incidents = $stmt->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<div class="d-flex" id="wrapper">
     <!-- Sidebar  -->
    <?php include 'includes/sidebar.php'; ?>
    
     <!-- Page Content  -->
    <div id="page-content-wrapper">
         <!-- Navigation  -->
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <h2><i class="bi bi-file-earmark-text me-2"></i>Police Reports & Statistics</h2>
                    <p class="text-muted">View comprehensive reports and statistics for police operations</p>
                </div>
            </div>

             <!-- Statistics Cards  -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['total_incidents']; ?></h3>
                                    <p class="fs-6 mb-0">Total Incidents</p>
                                </div>
                                <i class="bi bi-clipboard-data fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['resolved_incidents']; ?></h3>
                                    <p class="fs-6 mb-0">Resolved Cases</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['active_incidents']; ?></h3>
                                    <p class="fs-6 mb-0">Active Cases</p>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['today_incidents']; ?></h3>
                                    <p class="fs-6 mb-0">Today's Reports</p>
                                </div>
                                <i class="bi bi-calendar-day fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Export Buttons  -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="exportReports('all')">
                            <i class="bi bi-download me-1"></i>Export All Reports
                        </button>
                        <button class="btn btn-success" onclick="exportReports('monthly')">
                            <i class="bi bi-calendar-month me-1"></i>Monthly Report
                        </button>
                        <button class="btn btn-info" onclick="printReports()">
                            <i class="bi bi-printer me-1"></i>Print Reports
                        </button>
                    </div>
                </div>
            </div>

             <!-- Reports Table  -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Police Incidents</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Report #</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Reporter</th>
                                            <th>Urgency</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($recent_incidents) > 0): ?>
                                            <?php foreach ($recent_incidents as $incident): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($incident['created_at'])); ?></td>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?> rounded-pill">
                                                            <?php echo ucfirst($incident['urgency_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?> rounded-pill">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewIncident(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success" onclick="generateReport(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-file-earmark-pdf"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                                    <h5 class="text-muted mt-2">No police incidents found</h5>
                                                    <p class="text-muted">Police reports will appear here when incidents are assigned.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

function exportReports(type) {
    // Implement export functionality
    alert('Export functionality will be implemented: ' + type);
}

function printReports() {
    window.print();
}

function viewIncident(incidentId) {
    // Redirect to incidents page with specific incident
    window.location.href = 'incidents.php?id=' + incidentId;
}

function generateReport(incidentId) {
    // Generate PDF report for specific incident
    alert('PDF report generation will be implemented for incident #' + incidentId);
}
</script>

<?php
function getUrgencyColor($urgency) {
    switch ($urgency) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        case 'critical': return 'dark';
        default: return 'secondary';
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'notified': return 'info';
        case 'responding': return 'warning';
        case 'on_scene': return 'danger';
        case 'resolved': return 'success';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?>
