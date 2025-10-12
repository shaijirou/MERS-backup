<?php
require_once '../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    redirect('../index.php');
}

$page_title = 'Fire Incidents';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get current user info
$user_id = $_SESSION['user_id'];

// Get incident details if viewing specific incident
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$incident_details = null;

if ($incident_id) {
    $stmt = $db->prepare("
        SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay
        FROM incident_reports ir 
        JOIN users u ON ir.user_id = u.id 
        WHERE ir.id = :incident_id AND ir.approval_status = 'approved'
    ");
    $stmt->bindParam(':incident_id', $incident_id);
    $stmt->execute();
    $incident_details = $stmt->fetch();
}

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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="bi bi-fire text-danger me-2"></i>Fire Incidents Management</h2>
                            <p class="text-muted">Monitor and respond to fire emergencies</p>
                        </div>
                        <button class="btn btn-outline-danger" onclick="refreshIncidents()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

             <!-- Fire Safety Alert  -->
            <div class="alert alert-warning border-start border-warning border-4 shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle fs-2 text-warning me-3"></i>
                    <div>
                        <h6 class="mb-1">Fire Safety Reminder</h6>
                        <small>Always follow RECEO-VS protocols: Rescue, Exposures, Confinement, Extinguishment, Overhaul, Ventilation, Salvage</small>
                    </div>
                </div>
            </div>

             <!-- Statistics Cards  -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="active-fires">0</h3>
                                    <p class="fs-6 mb-0">Active Fires</p>
                                </div>
                                <i class="bi bi-fire fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="en-route">0</h3>
                                    <p class="fs-6 mb-0">En Route</p>
                                </div>
                                <i class="bi bi-truck fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="resolved-today">0</h3>
                                    <p class="fs-6 mb-0">Resolved Today</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="total-month">0</h3>
                                    <p class="fs-6 mb-0">Total This Month</p>
                                </div>
                                <i class="bi bi-bar-chart fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Incidents Table  -->
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-list me-2"></i>Fire Incident Reports</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="incidents-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Report #</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Reporter</th>
                                    <th>Date/Time</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="incidents-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="spinner-border text-danger" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading fire incidents...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

 <!-- Incident Details Modal  -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-fire me-2"></i>Fire Incident Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incident-details">
                 <!-- Details will be loaded here  -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="respond-btn" onclick="updateStatus('responding')">
                    <i class="bi bi-truck me-1"></i> En Route
                </button>
                <button type="button" class="btn btn-danger" id="fighting-btn" onclick="updateStatus('on_scene')" style="display: none;">
                    <i class="bi bi-droplet me-1"></i> Fighting Fire
                </button>
                <button type="button" class="btn btn-success" id="resolve-btn" onclick="updateStatus('resolved')" style="display: none;">
                    <i class="bi bi-check me-1"></i> Fire Out
                </button>
            </div>
        </div>
    </div>
</div>


<script>
let currentIncidentId = null;

// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

document.addEventListener('DOMContentLoaded', function() {
    loadIncidents();
    loadStatistics();
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
        loadIncidents();
        loadStatistics();
    }, 30000);

    // Show specific incident if ID provided
    <?php if ($incident_id && $incident_details): ?>
        viewIncident(<?php echo $incident_id; ?>);
    <?php endif; ?>
});

function loadStatistics() {
    fetch('ajax/get_fire_statistics.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('active-fires').textContent = data.active_fires || 0;
                document.getElementById('en-route').textContent = data.en_route || 0;
                document.getElementById('resolved-today').textContent = data.resolved_today || 0;
                document.getElementById('total-month').textContent = data.total_month || 0;
            }
        })
        .catch(error => console.error('Error loading statistics:', error));
}

function loadIncidents() {
    fetch('ajax/get_fire_incidents.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('incidents-tbody');
            
            if (data.success && data.incidents && data.incidents.length > 0) {
                let html = '';
                data.incidents.forEach(incident => {
                    const urgencyClass = getUrgencyClass(incident.urgency_level);
                    const statusClass = getStatusClass(incident.response_status);
                    const isFireRelated = incident.incident_type.toLowerCase().includes('fire') || 
                                         incident.incident_type.toLowerCase().includes('explosion') ||
                                         incident.incident_type.toLowerCase().includes('burn');
                    const fireIcon = isFireRelated ? '<i class="bi bi-fire text-danger me-1"></i>' : '';
                    
                    html += `
                        <tr ${isFireRelated ? 'class="table-danger"' : ''}>
                            <td class="fw-medium">${incident.report_number}</td>
                            <td>${fireIcon}${incident.incident_type}</td>
                            <td>${incident.location}<br><small class="text-muted">${incident.barangay || 'N/A'}</small></td>
                            <td>${incident.first_name} ${incident.last_name}<br><small class="text-muted">${incident.phone}</small></td>
                            <td>${new Date(incident.created_at).toLocaleDateString()}<br><small class="text-muted">${new Date(incident.created_at).toLocaleTimeString()}</small></td>
                            <td><span class="badge ${urgencyClass} rounded-pill">${incident.urgency_level}</span></td>
                            <td><span class="badge ${statusClass} rounded-pill">${getStatusText(incident.response_status)}</span></td>
                            <td>
                                <div class="btn-group">
                                    ${incident.latitude && incident.longitude ? 
                                        `<a href="https://www.google.com/maps/dir/?api=1&destination=${incident.latitude},${incident.longitude}" 
                                            target="_blank" 
                                            class="btn btn-sm btn-info" 
                                            title="Get Directions">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </a>` : ''}
                                    <button class="btn btn-sm btn-outline-danger" onclick="viewIncident(${incident.id})">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No fire incidents assigned</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading incidents:', error);
            document.getElementById('incidents-tbody').innerHTML = 
                '<tr><td colspan="8" class="text-center text-danger py-4">Error loading incidents</td></tr>';
        });
}

function viewIncident(incidentId) {
    currentIncidentId = incidentId;
    
    fetch(`get_incident_details.php?id=${incidentId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('incident-details').innerHTML = data;
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        })
        .catch(error => {
            console.error('Error loading incident details:', error);
            document.getElementById('incident-details').innerHTML = 
                '<div class="alert alert-danger">Error loading incident details</div>';
        });
}

function updateStatus(newStatus) {
    if (!currentIncidentId) return;
    
    fetch('ajax/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `incident_id=${currentIncidentId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and refresh data
            bootstrap.Modal.getInstance(document.getElementById('incidentModal')).hide();
            loadIncidents();
            loadStatistics();
            
            // Show success message
            showAlert('Status updated successfully!', 'success');
        } else {
            showAlert('Error updating status: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        showAlert('Error updating status', 'danger');
    });
}

function refreshIncidents() {
    loadIncidents();
    loadStatistics();
    showAlert('Data refreshed!', 'info');
}

function getUrgencyClass(urgency) {
    switch(urgency?.toLowerCase()) {
        case 'low': return 'bg-success';
        case 'medium': return 'bg-warning';
        case 'high': return 'bg-danger';
        case 'critical': return 'bg-dark';
        default: return 'bg-secondary';
    }
}

function getStatusClass(status) {
    switch(status) {
        case 'notified': return 'bg-info';
        case 'responding': return 'bg-warning';
        case 'on_scene': return 'bg-danger';
        case 'resolved': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'notified': return 'Notified';
        case 'responding': return 'En Route';
        case 'on_scene': return 'Fighting Fire';
        case 'resolved': return 'Fire Out';
        default: return 'Unknown';
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}
</script>

<?php include '../includes/footer.php'; ?>
