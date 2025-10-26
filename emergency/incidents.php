<?php
require_once '../config/config.php';

// Check if user is logged in and is medical responder
if (!isLoggedIn() || !isEmergency()) {
    redirect('../index.php');
}

$page_title = 'Emergency Medical Incidents';
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
    $stmt = $db->prepare("SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.barangay FROM incident_reports ir JOIN users u ON ir.user_id = u.id WHERE ir.id = :incident_id AND ir.approval_status = 'approved'");
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
    <?php include 'includes/sidebar.php'; ?>
    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>
        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="bi bi-heart-pulse text-danger me-2"></i>Emergency Medical Incidents</h2>
                            <p class="text-muted">Monitor and respond to medical emergencies</p>
                        </div>
                        <button class="btn btn-outline-danger" onclick="refreshIncidents()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            <div class="alert alert-danger border-start border-danger border-4 shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle fs-2 text-danger me-3"></i>
                    <div>
                        <h6 class="mb-1">Medical Emergency Protocol</h6>
                        <small>Follow ABC protocol: Airway, Breathing, Circulation. Assess scene safety before approaching patient.</small>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0" id="active-emergencies">0</h3>
                                    <p class="fs-6 mb-0">Active Emergencies</p>
                                </div>
                                <i class="bi bi-heart-pulse fs-1"></i>
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
                                <i class="bi bi-truck-front fs-1"></i>
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
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-list me-2"></i>Emergency Medical Incident Reports</h5>
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
                                        <p class="mt-2 text-muted">Loading emergency incidents...</p>
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

<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-heart-pulse me-2"></i>Emergency Medical Incident Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incident-details">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="respond-btn" onclick="showResponderModal('responding')">
                    <i class="bi bi-truck-front me-1"></i> Respond to Emergency
                </button>
                <button type="button" class="btn btn-danger" id="onscene-btn" onclick="showResponderModal('on_scene')" style="display: none;">
                    <i class="bi bi-geo-alt me-1"></i> On Scene Treating Patient
                </button>
                <button type="button" class="btn btn-success" id="resolve-btn" onclick="showResponderModal('resolved')" style="display: none;">
                    <i class="bi bi-check me-1"></i> Patient Transported
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Added responder name modal for recording responder identity -->
<div class="modal fade" id="responderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Confirm Your Response</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please enter your name to record your response to this medical emergency for transparency and record-keeping.</p>
                <div class="mb-3">
                    <label for="responder_name_input" class="form-label">Your Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="responder_name_input" placeholder="Enter your full name" required>
                    <small class="text-muted">This will be recorded in the incident report</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmResponderResponse()">Confirm Response</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentIncidentId = null;
let pendingStatus = null;

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
    fetch('ajax/get_emergency_statistics.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('active-emergencies').textContent = data.active_emergencies || 0;
                document.getElementById('en-route').textContent = data.en_route || 0;
                document.getElementById('resolved-today').textContent = data.resolved_today || 0;
                document.getElementById('total-month').textContent = data.total_month || 0;
            }
        })
        .catch(error => console.error('Error loading statistics:', error));
}

function loadIncidents() {
    fetch('ajax/get_emergency_incidents.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('incidents-tbody');
            
            if (data.success && data.incidents && data.incidents.length > 0) {
                let html = '';
                data.incidents.forEach(incident => {
                    const statusClass = getStatusClass(incident.response_status);
                    const isMedicalIncident = incident.incident_type.toLowerCase().includes('medical') || 
                                             incident.incident_type.toLowerCase().includes('emergency') ||
                                             incident.incident_type.toLowerCase().includes('accident');
                    const medicalIcon = isMedicalIncident ? '<i class="bi bi-heart-pulse text-danger me-1"></i>' : '';
                    
                    html += `
                        <tr ${isMedicalIncident ? 'class="table-danger"' : ''}>
                            <td class="fw-medium">${incident.report_number}</td>
                            <td>${medicalIcon}${incident.incident_type}</td>
                            <td>${incident.location}<br><small class="text-muted">${incident.barangay || 'N/A'}</small></td>
                            <td>${incident.first_name} ${incident.last_name}<br><small class="text-muted">${incident.phone}</small></td>
                            <td>${new Date(incident.created_at).toLocaleDateString()}<br><small class="text-muted">${new Date(incident.created_at).toLocaleTimeString()}</small></td>
                            
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
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No emergency incidents assigned</td></tr>';
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
            
            fetch(`ajax/get_emergency_incidents.php`)
                .then(response => response.json())
                .then(incidentsData => {
                    if (incidentsData.success && incidentsData.incidents) {
                        const incident = incidentsData.incidents.find(inc => inc.id == incidentId);
                        if (incident && incident.response_status === 'resolved') {
                            // Disable all action buttons if incident is resolved
                            document.getElementById('respond-btn').disabled = true;
                            document.getElementById('onscene-btn').disabled = true;
                            document.getElementById('resolve-btn').disabled = true;
                        }
                    }
                });
            
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        })
        .catch(error => {
            console.error('Error loading incident details:', error);
            document.getElementById('incident-details').innerHTML = 
                '<div class="alert alert-danger">Error loading incident details</div>';
        });
}

function showResponderModal(status) {
    if (!currentIncidentId) return;
    
    pendingStatus = status;
    document.getElementById('responder_name_input').value = '';
    new bootstrap.Modal(document.getElementById('responderModal')).show();
}

function confirmResponderResponse() {
    const responderName = document.getElementById('responder_name_input').value.trim();
    
    if (!responderName) {
        alert('Please enter your name');
        return;
    }
    
    // Close responder modal
    bootstrap.Modal.getInstance(document.getElementById('responderModal')).hide();
    
    // Call updateStatus with responder name
    updateStatus(pendingStatus, responderName);
}

function updateStatus(newStatus, responderName = null) {
    if (!currentIncidentId) return;
    
    const formData = new FormData();
    formData.append('incident_id', currentIncidentId);
    formData.append('status', newStatus);
    if (responderName) {
        formData.append('responder_name', responderName);
    }
    
    fetch('ajax/update_status.php', {
        method: 'POST',
        body: formData
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

// function getUrgencyClass(urgency) {
//     switch(urgency?.toLowerCase()) {
//         case 'low': return 'bg-success';
//         case 'medium': return 'bg-warning';
//         case 'high': return 'bg-danger';
//         case 'critical': return 'bg-dark';
//         default: return 'bg-secondary';
//     }
// }

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
        case 'on_scene': return 'On Scene';
        case 'resolved': return 'Resolved';
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
