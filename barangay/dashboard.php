<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php');
}

// Check if user is barangay personnel, if not redirect to their appropriate dashboard
if (!isBarangay()) {
    // Redirect to appropriate dashboard based on user type
    switch ($_SESSION['user_type']) {
        case 'admin':
            redirect('../admin/dashboard.php');
            break;
        case 'police':
            redirect('../police/dashboard.php');
            break;
        case 'emergency':
            redirect('../emergency/dashboard.php');
            break;
        case 'firefighter':
            redirect('../firefighter/dashboard.php');
            break;
        default:
            redirect('../user/dashboard.php');
            break;
    }
}

$page_title = 'Barangay Emergency Dashboard';
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

// Get assigned incidents (filter by assigned_to user_id)
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.barangay 
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE ir.approval_status = 'approved' 
                   AND (ir.assigned_to = :user_id OR (ir.responder_type = 'barangay' AND ir.assigned_to IS NULL))";

$incidents_query .= " ORDER BY ir.created_at DESC";

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

$stats_query = "SELECT 
                COUNT(*) as total_assigned,
                SUM(CASE WHEN response_status = 'responding' THEN 1 ELSE 0 END) as responding,
                SUM(CASE WHEN response_status = 'on_scene' THEN 1 ELSE 0 END) as on_scene,
                SUM(CASE WHEN response_status = 'resolved' THEN 1 ELSE 0 END) as resolved
                FROM incident_reports ir
                JOIN users u ON ir.user_id = u.id
                WHERE ir.approval_status = 'approved' 
                AND (ir.assigned_to = :user_id OR (ir.responder_type = 'barangay' AND ir.assigned_to IS NULL))";

$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch();

// Get barangay residents count
$residents_query = "SELECT COUNT(*) as total_residents FROM users WHERE barangay = :barangay AND user_type = 'resident'";
$stmt = $db->prepare($residents_query);
$barangay_name = $current_user['assigned_barangay'] ?: $current_user['barangay'];
$stmt->bindParam(':barangay', $barangay_name);
$stmt->execute();
$residents_count = $stmt->fetch()['total_residents'];

include '../includes/header.php';

?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
    <div class="row my-4">
               

        <div class="container-fluid px-4">
            <!-- Statistics Cards -->
            <div class="row g-3 my-3">
                <div class="col-md-3">
                    <div class="p-3 bg-primary shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['total_assigned']; ?></h3>
                            <p class="fs-5">Local Incidents</p>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-white"></i>
                    </div>
                </div>
 
                <div class="col-md-3">
                    <div class="p-3 bg-warning shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['responding']; ?></h3>
                            <p class="fs-5">Responding</p>
                        </div>
                        <i class="bi bi-person-running fs-1 text-white"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-danger shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['on_scene']; ?></h3>
                            <p class="fs-5">On Scene</p>
                        </div>
                        <i class="bi bi-geo-alt-fill fs-1 text-white"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-success shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $residents_count; ?></h3>
                            <p class="fs-5">Registered Residents</p>
                        </div>
                        <i class="bi bi-people-fill fs-1 text-white"></i>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row my-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Local Incidents - Barangay <?php echo htmlspecialchars($barangay_name); ?></h5>
                                <span class="badge bg-primary"><?php echo count($assigned_incidents); ?> Total</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($assigned_incidents) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Report #</th>
                                                <th>Type</th>
                                                <th>Reporter</th>
                                                <th>Location</th>
                                                
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_incidents as $incident): ?>
                                                <tr>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                   
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?> rounded-pill">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary btn-sm" onclick="viewIncident(<?php echo $incident['id']; ?>)" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-warning btn-sm" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'responding')" 
                                                                    title="Responding"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-truck"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger btn-sm" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'on_scene')" 
                                                                    title="On Scene"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-geo-alt"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success btn-sm" onclick="updateStatus(<?php echo $incident['id']; ?>, 'resolved')" title="Resolved">
                                                                <i class="bi bi-check-circle"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-house-check text-muted" style="font-size: 4rem;"></i>
                                    <h5 class="text-muted mt-3">No local incidents assigned</h5>
                                    <p class="text-muted">Your barangay emergency unit will be notified when incidents occur in your area.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Barangay Information</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get barangay details
                            $barangay_query = "SELECT * FROM barangays WHERE name = :barangay_name";
                            $stmt = $db->prepare($barangay_query);
                            $stmt->bindParam(':barangay_name', $barangay_name);
                            $stmt->execute();
                            $barangay_info = $stmt->fetch();
                            
                            if ($barangay_info):
                            ?>
                                <div class="mb-4">
                                    <h6 class="text-success fw-bold mb-3">Barangay <?php echo htmlspecialchars($barangay_info['name']); ?></h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="text-center p-3 bg-light rounded">
                                                <i class="bi bi-people text-primary fs-4"></i>
                                                <div class="fw-bold mt-2"><?php echo number_format($barangay_info['population']); ?></div>
                                                <small class="text-muted">Population</small>
                                            </div>
                                        </div>
                                       
                                    </div>
                                   
                                </div>
                            <?php endif; ?>
                            
                           
                            
                            <div class="alert alert-light border border-info">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-telephone text-info me-3 fs-5"></i>
                                    <div>
                                        <h6 class="alert-heading mb-2">Emergency Contacts</h6>
                                        <div class="small">
                                            <div class="mb-1"><strong>Police:</strong> 117</div>
                                            <div class="mb-1"><strong>Fire:</strong> 116</div>
                                            <div><strong>Medical:</strong> 911</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            
        </div>
    </div>
</div>

<!-- Incident Details Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Local Incident Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incidentDetails">
                <!-- Incident details will be loaded here -->
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
    pendingStatus = status;
    document.getElementById('incidentIdHidden').value = incidentId;
    document.getElementById('responderStatus').value = status.replace('_', ' ').toUpperCase();
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

function viewResidents() {
    window.location.href = 'residents.php';
}

function createAlert() {
    window.location.href = 'create_alert.php';
}

function viewEvacuationCenters() {
    window.location.href = 'evacuation.php';
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

function getRiskColor($risk) {
    switch ($risk) {
        case 'low': return 'success';
        case 'medium': return 'warning';
        case 'high': return 'danger';
        case 'critical': return 'dark';
        default: return 'secondary';
    }
}

include '../includes/footer.php';
?>
