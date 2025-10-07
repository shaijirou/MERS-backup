<?php
require_once '../config/config.php';

// Check if user is logged in and is police
if (!isLoggedIn() || !isPolice()) {
    redirect('../index.php');
}

$page_title = 'Police Incidents';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get incidents assigned to police
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.barangay
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE ir.approval_status = 'approved'
                   AND (ir.incident_type LIKE '%crime%' OR ir.incident_type LIKE '%accident%' OR ir.incident_type LIKE '%violence%' OR ir.responder_type = 'police')
                   ORDER BY ir.created_at DESC";
$stmt = $db->prepare($incidents_query);
$stmt->execute();
$incidents = $stmt->fetchAll();

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
                            <h2 class="mb-1"><i class="bi bi-shield-check me-2"></i>Police Incidents</h2>
                            <p class="text-muted mb-0">Manage and respond to incidents requiring police intervention</p>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Incidents List  -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($incidents)): ?>
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-shield-check fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No incidents assigned</h5>
                                <p class="text-muted">There are currently no incidents requiring police response.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($incidents as $incident): ?>
                            <div class="card shadow-sm mb-3 <?php echo 'border-' . getUrgencyColor($incident['urgency_level']) . ' border-start border-4'; ?>">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">
                                                <i class="bi bi-exclamation-triangle-fill text-primary me-2"></i>
                                                <?php echo htmlspecialchars($incident['incident_type']); ?>
                                            </h5>
                                            <small class="text-muted">
                                                Reported by: <?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?> fs-6">
                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong><i class="bi bi-geo-alt me-1"></i>Location:</strong> <?php echo htmlspecialchars($incident['location']); ?></p>
                                            <p class="mb-2"><strong><i class="bi bi-building me-1"></i>Barangay:</strong> <?php echo htmlspecialchars($incident['barangay']); ?></p>
                                            <p class="mb-2"><strong><i class="bi bi-exclamation-circle me-1"></i>Urgency:</strong> 
                                                <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?>">
                                                    <?php echo ucfirst($incident['urgency_level']); ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong><i class="bi bi-telephone me-1"></i>Contact:</strong> <?php echo htmlspecialchars($incident['phone']); ?></p>
                                            <p class="mb-2"><strong><i class="bi bi-clock me-1"></i>Reported:</strong> <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <strong><i class="bi bi-file-text me-1"></i>Description:</strong>
                                        <p class="mt-1"><?php echo htmlspecialchars($incident['description']); ?></p>
                                    </div>
                                    
                                    <?php if ($incident['response_status'] == 'notified'): ?>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-primary" onclick="updateStatus(<?php echo $incident['id']; ?>, 'responding')">
                                                <i class="bi bi-car-front me-1"></i>Respond to Incident
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="viewDetails(<?php echo $incident['id']; ?>)">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </button>
                                        </div>
                                    <?php elseif ($incident['response_status'] == 'responding'): ?>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-warning" onclick="updateStatus(<?php echo $incident['id']; ?>, 'on_scene')">
                                                <i class="bi bi-geo-alt me-1"></i>Arrived On Scene
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="viewDetails(<?php echo $incident['id']; ?>)">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </button>
                                        </div>
                                    <?php elseif ($incident['response_status'] == 'on_scene'): ?>
                                        <div class="d-flex gap-2 mt-3">
                                            <button class="btn btn-success" onclick="updateStatus(<?php echo $incident['id']; ?>, 'resolved')">
                                                <i class="bi bi-check-circle me-1"></i>Mark as Resolved
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="viewDetails(<?php echo $incident['id']; ?>)">
                                                <i class="bi bi-eye me-1"></i>View Details
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Toggle sidebar
$(document).ready(function () {
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
    });
});

function updateStatus(incidentId, status) {
    let confirmMessage = 'Are you sure you want to update this incident status?';
    if (status === 'responding') confirmMessage = 'Dispatch unit to respond to this incident?';
    if (status === 'on_scene') confirmMessage = 'Confirm arrival on scene?';
    if (status === 'resolved') confirmMessage = 'Mark this incident as resolved?';
        
    if (confirm(confirmMessage)) {
        fetch('ajax/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'incident_id=' + incidentId + '&status=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error updating status: ' + data.message);
            }
        });
    }
}

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
                                <h5 class="modal-title">Police Incident Details</h5>
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
