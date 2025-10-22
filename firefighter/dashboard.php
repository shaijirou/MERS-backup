<?php
require_once '../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    redirect('../index.php');
}

$page_title = 'Fire Department Dashboard';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get current user info
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$current_user = $stmt->fetch();

// Get assigned fire-related incidents
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.phone 
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE ir.approval_status = 'approved' 
                   AND (ir.assigned_to = :user_id OR ir.responder_type = 'firefighter' 
                        OR ir.incident_type LIKE '%fire%' OR ir.incident_type LIKE '%burn%' 
                        OR ir.incident_type LIKE '%explosion%' OR ir.incident_type LIKE '%smoke%')
                   ORDER BY ir.created_at DESC";
$stmt = $db->prepare($incidents_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$assigned_incidents = $stmt->fetchAll();

// Get unread notifications
$notifications_query = "SELECT * FROM user_notifications 
                       WHERE user_id = :user_id AND is_read = FALSE 
                       ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($notifications_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_assigned,
                SUM(CASE WHEN response_status = 'responding' THEN 1 ELSE 0 END) as responding,
                SUM(CASE WHEN response_status = 'on_scene' THEN 1 ELSE 0 END) as on_scene,
                SUM(CASE WHEN response_status = 'resolved' THEN 1 ELSE 0 END) as resolved
                FROM incident_reports 
                WHERE approval_status = 'approved' 
                AND (assigned_to = :user_id OR responder_type = 'firefighter' 
                     OR incident_type LIKE '%fire%' OR incident_type LIKE '%burn%' 
                     OR incident_type LIKE '%explosion%' OR incident_type LIKE '%smoke%')";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch();

// Get fire-related incident types count for today
$today_fires_query = "SELECT COUNT(*) as today_fires 
                     FROM incident_reports 
                     WHERE DATE(created_at) = CURDATE() 
                     AND approval_status = 'approved'
                     AND (incident_type LIKE '%fire%' OR incident_type LIKE '%burn%' 
                          OR incident_type LIKE '%explosion%' OR incident_type LIKE '%smoke%')";
$stmt = $db->prepare($today_fires_query);
$stmt->execute();
$today_fires = $stmt->fetch()['today_fires'];

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<div class="d-flex" id="wrapper">
   
    <?php include 'includes/sidebar.php'; ?>
    
    
    <div id="page-content-wrapper">
        
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
         
            <div class="row g-3 my-3">
                <div class="col-12">
                    <div class="card text-white" style="background-color: #dc3545;">
                        <div class="card-body">
                            <h4 class="card-title mb-1">Welcome, Firefighter <?php echo htmlspecialchars($current_user['last_name']); ?></h4>
                            <p class="card-text">Badge: <?php echo htmlspecialchars($current_user['badge_number']); ?> | Department: <?php echo htmlspecialchars($current_user['department']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

         
            <div class="row g-3 my-3">
                <div class="col-md-3">
                    <div class="p-3 bg-info shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['total_assigned']; ?></h3>
                            <p class="fs-5">Fire Incidents</p>
                        </div>
                        <i class="bi bi-fire fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-warning shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['responding']; ?></h3>
                            <p class="fs-5">En Route</p>
                        </div>
                        <i class="bi bi-truck fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-danger shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['on_scene']; ?></h3>
                            <p class="fs-5">Fighting Fire</p>
                        </div>
                        <i class="bi bi-droplet fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-dark shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $today_fires; ?></h3>
                            <p class="fs-5">Today's Fires</p>
                        </div>
                        <i class="bi bi-calendar-day fs-1 text-white"></i>
                    </div>
                </div>
            </div>

      
            <div class="row my-4">
                 
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Fire & Emergency Incidents</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($assigned_incidents) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Report #</th>
                                                <th>Type</th>
                                                <th>Location</th>
                                                
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_incidents as $incident): ?>
                                                <tr class="<?php echo isFireRelated($incident['incident_type']) ? 'table-danger' : ''; ?>">
                                                    <td><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                                    <td>
                                                        <?php if (isFireRelated($incident['incident_type'])): ?>
                                                            <i class="bi bi-fire text-danger me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($incident['incident_type']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                    
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>">
                                                            <?php echo getFireStatus($incident['response_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewIncident(<?php echo $incident['id']; ?>)" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-warning" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'responding')" 
                                                                    title="En Route"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-truck"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'on_scene')" 
                                                                    title="Fighting Fire"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-droplet"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success" onclick="updateStatus(<?php echo $incident['id']; ?>, 'resolved')" title="Fire Out">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-droplet fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No fire incidents assigned</h5>
                                    <p class="text-muted">Fire department will be notified when fire emergencies are reported.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

              
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Fire Safety Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h6 class="text-muted">Emergency Response Priority</h6>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Life Safety First</strong><br>
                                    <small>1. Rescue<br>2. Exposures<br>3. Confinement<br>4. Extinguishment<br>5. Overhaul</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-danger btn-sm" onclick="viewFireIncidents()">
                                        <i class="bi bi-fire me-2"></i>View All Fire Incidents
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="checkEquipment()">
                                        <i class="bi bi-tools me-2"></i>Equipment Status
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="weatherAlert()">
                                        <i class="bi bi-cloud me-2"></i>Weather Conditions
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted">Fire Hazard Levels</h6>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 60%">
                                        <small>Current: Moderate</small>
                                    </div>
                                </div>
                                <small class="text-muted">Based on weather conditions and recent incidents</small>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-telephone me-2"></i>
                                <strong>Emergency Contacts:</strong><br>
                                <small>Fire Emergency: 116<br>
                                Police: 117<br>
                                Medical: 911<br>
                                MDRRMO: (043) 123-4567</small>
                            </div>
                        </div>
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
                <h5 class="modal-title"><i class="bi bi-fire me-2"></i>Fire Incident Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incidentDetails">
                 Incident details will be loaded here 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Added responder name modal for recording responder information -->
<div class="modal fade" id="responderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Responder Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="responderForm">
                    <div class="mb-3">
                        <label for="responderName" class="form-label">Your Full Name</label>
                        <input type="text" class="form-control" id="responderName" placeholder="Enter your full name" required>
                    </div>
                    <div class="mb-3">
                        <label for="responderStatus" class="form-label">Response Status</label>
                        <input type="text" class="form-control" id="responderStatus" readonly>
                    </div>
                    <input type="hidden" id="incidentIdHidden">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitResponderInfo()">Confirm Response</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);

function viewIncident(incidentId) {
    // Load incident details via AJAX using the same endpoint as incidents page
    fetch('get_incident_details.php?id=' + incidentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('incidentDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        })
        .catch(error => {
            console.error('Error loading incident details:', error);
            document.getElementById('incidentDetails').innerHTML = 
                '<div class="alert alert-danger">Error loading incident details</div>';
        });
}

let pendingStatus = null;

function updateStatus(incidentId, status) {
    let statusText = getFireStatusText(status);
    
    pendingStatus = status;
    document.getElementById('incidentIdHidden').value = incidentId;
    document.getElementById('responderStatus').value = statusText.toUpperCase();
    document.getElementById('responderName').value = '';
    new bootstrap.Modal(document.getElementById('responderModal')).show();
}

function submitResponderInfo() {
    const responderName = document.getElementById('responderName').value.trim();
    const incidentId = document.getElementById('incidentIdHidden').value;
    
    if (!responderName) {
        alert('Please enter your full name');
        return;
    }
    
    const formData = new FormData();
    formData.append('incident_id', incidentId);
    formData.append('status', pendingStatus);
    formData.append('responder_name', responderName);
    
    console.log("[v0] Submitting responder info:", {
        incident_id: incidentId,
        status: pendingStatus,
        responder_name: responderName
    });
    
    // Close the modal
    bootstrap.Modal.getInstance(document.getElementById('responderModal')).hide();
    
    // Send the update with responder name
    fetch('ajax/update_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log("[v0] Response from server:", data);
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error("[v0] Error:", error);
        alert('Error: ' + error.message);
    });
}

function getFireStatusText(status) {
    switch(status) {
        case 'responding': return 'En Route';
        case 'on_scene': return 'Fighting Fire';
        case 'resolved': return 'Fire Out';
        default: return status.replace('_', ' ');
    }
}

function markAsRead(notificationId) {
    fetch('ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(() => location.reload());
}

function markAllAsRead() {
    fetch('ajax/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(() => location.reload());
}

function viewFireIncidents() {
    window.location.href = 'incidents.php';
}

function checkEquipment() {
    window.location.href = 'equipment.php';
}

function weatherAlert() {
    alert('Weather conditions: Moderate fire risk. Wind speed: 15 km/h. Humidity: 45%');
}
</script>

<?php
// function getUrgencyColor($urgency) {
//     switch ($urgency) {
//         case 'low': return 'success';
//         case 'medium': return 'warning';
//         case 'high': return 'danger';
//         case 'critical': return 'dark';
//         default: return 'secondary';
//     }
// }

function getStatusColor($status) {
    switch ($status) {
        case 'notified': return 'info';
        case 'responding': return 'warning';
        case 'on_scene': return 'danger';
        case 'resolved': return 'success';
        default: return 'secondary';
    }
}

function getFireStatus($status) {
    switch ($status) {
        case 'notified': return 'Notified';
        case 'responding': return 'En Route';
        case 'on_scene': return 'Fighting Fire';
        case 'resolved': return 'Fire Out';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}

function isFireRelated($incident_type) {
    $fire_keywords = ['fire', 'burn', 'explosion', 'smoke'];
    foreach ($fire_keywords as $keyword) {
        if (stripos($incident_type, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

include '../includes/footer.php';
?>
