<?php
require_once '../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    redirect('../index.php');
}

$page_title = 'Emergency Reports';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get date range for filtering
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get emergency response reports
$reports_query = "SELECT ir.*, u.first_name, u.last_name, u.barangay
                  FROM incident_reports ir 
                  JOIN users u ON ir.user_id = u.id 
                  WHERE ir.approval_status = 'approved'
                  AND (ir.incident_type LIKE '%medical%' OR ir.incident_type LIKE '%emergency%' OR ir.incident_type LIKE '%accident%')
                  AND DATE(ir.created_at) BETWEEN :start_date AND :end_date
                  ORDER BY ir.created_at DESC";
$stmt = $db->prepare($reports_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$reports = $stmt->fetchAll();

// Get summary statistics
$summary_query = "SELECT 
                  COUNT(*) as total_incidents,
                  SUM(CASE WHEN response_status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
                  SUM(CASE WHEN response_status = 'responding' OR response_status = 'on_scene' THEN 1 ELSE 0 END) as active_incidents,
                  SUM(CASE WHEN urgency_level = 'critical' THEN 1 ELSE 0 END) as critical_incidents
                  FROM incident_reports 
                  WHERE approval_status = 'approved'
                  AND (incident_type LIKE '%medical%' OR incident_type LIKE '%emergency%' OR incident_type LIKE '%accident%')
                  AND DATE(created_at) BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($summary_query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetch();

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
             <!-- Page Header  -->
            <div class="row g-3 my-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2"></i>Emergency Response Reports</h2>
                            <p class="text-muted mb-0">View and analyze emergency response activities and statistics</p>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Filters  -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-funnel me-1"></i>Filter Reports
                                        </button>
                                        <button type="button" class="btn btn-success" onclick="exportReports()">
                                            <i class="bi bi-download me-1"></i>Export CSV
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Summary Statistics  -->
            <div class="row g-3 my-3">
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-clipboard-data fs-1 mb-2"></i>
                            <h3 class="fs-2"><?php echo $summary['total_incidents']; ?></h3>
                            <p class="mb-0">Total Incidents</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-check-circle-fill fs-1 mb-2"></i>
                            <h3 class="fs-2"><?php echo $summary['resolved_incidents']; ?></h3>
                            <p class="mb-0">Resolved Cases</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-truck fs-1 mb-2"></i>
                            <h3 class="fs-2"><?php echo $summary['active_incidents']; ?></h3>
                            <p class="mb-0">Active Cases</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-exclamation-triangle-fill fs-1 mb-2"></i>
                            <h3 class="fs-2"><?php echo $summary['critical_incidents']; ?></h3>
                            <p class="mb-0">Critical Cases</p>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Reports Table  -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Emergency Response Reports</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($reports)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-file-earmark-x fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No emergency reports found</h5>
                                    <p class="text-muted">No emergency reports found for the selected date range.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Incident Type</th>
                                                <th>Reporter</th>
                                                <th>Location</th>
                                                <th>Urgency</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reports as $report): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($report['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($report['location'] . ', ' . $report['barangay']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getUrgencyColor($report['urgency_level']); ?>">
                                                            <?php echo ucfirst($report['urgency_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($report['response_status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $report['response_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-outline-primary btn-sm" onclick="viewDetails(<?php echo $report['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle sidebar
$(document).ready(function () {
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
    });
});

function viewDetails(incidentId) {
    fetch('ajax/get_incident.php?id=' + incidentId)
        .then(response => response.text())
        .then(data => {
            // Create modal dynamically
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade" id="incidentModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Emergency Incident Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">${data}</div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        });
}

function exportReports() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    window.open(`../admin/export_incidents.php?start_date=${startDate}&end_date=${endDate}&department=emergency`, '_blank');
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
