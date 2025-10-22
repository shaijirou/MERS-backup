<?php
require_once '../config/config.php';

// Check if user is logged in and is police
if (!isLoggedIn() || !isPolice()) {
    redirect('../index.php');
}

$page_title = 'Police Dashboard';
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

// Get assigned incidents
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.phone 
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE ir.approval_status = 'approved' 
                   AND (ir.assigned_to = :user_id OR ir.responder_type = 'police')
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
                AND (assigned_to = :user_id OR responder_type = 'police')";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch();

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

        <div class="container-fluid px-4">
            <!-- Welcome Section -->
            <div class="row g-3 my-3">
                <div class="col-12">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h4 class="card-title mb-1">Welcome, Officer <?php echo htmlspecialchars($current_user['last_name']); ?></h4>
                            <p class="card-text">Badge: <?php echo htmlspecialchars($current_user['badge_number']); ?> | Department: <?php echo htmlspecialchars($current_user['department']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['total_assigned']; ?></h3>
                                    <p class="fs-6 mb-0">Total Assigned</p>
                                </div>
                                <i class="bi bi-clipboard-check fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['responding']; ?></h3>
                                    <p class="fs-6 mb-0">Responding</p>
                                </div>
                                <i class="bi bi-car-front fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['on_scene']; ?></h3>
                                    <p class="fs-6 mb-0">On Scene</p>
                                </div>
                                <i class="bi bi-geo-alt-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fs-2 mb-0"><?php echo $stats['resolved']; ?></h3>
                                    <p class="fs-6 mb-0">Resolved</p>
                                </div>
                                <i class="bi bi-check-circle-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Incidents -->
            <div class="row my-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Assigned Incidents</h5>
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
                                                    <td><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                    
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewIncident(<?php echo $incident['id']; ?>)" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'responding')"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-car-front"></i>
                                                            </button>
                                                            <button class="btn btn-outline-warning" 
                                                                    onclick="updateStatus(<?php echo $incident['id']; ?>, 'on_scene')"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-geo-alt"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success" onclick="updateStatus(<?php echo $incident['id']; ?>, 'resolved')">
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
                                    <i class="bi bi-clipboard-check fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No incidents assigned</h5>
                                    <p class="text-muted">You will be notified when new incidents are assigned to you.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incident Details Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Incident Details</h5>
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

function updateStatus(incidentId, status) {
    if (confirm('Are you sure you want to update the status to ' + status.replace('_', ' ') + '?')) {
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

include '../includes/footer.php';
?>
