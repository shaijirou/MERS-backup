<?php
require_once '../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    redirect('../index.php');
}

$page_title = 'Emergency Dashboard';
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
                   AND (ir.assigned_to = :user_id OR ir.responder_type = 'emergency')
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
                AND (assigned_to = :user_id OR responder_type = 'emergency')";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch();

// Get evacuation centers status
$evacuation_query = "SELECT name, capacity, current_occupancy, status FROM evacuation_centers ORDER BY current_occupancy DESC LIMIT 5";
$stmt = $db->prepare($evacuation_query);
$stmt->execute();
$evacuation_centers = $stmt->fetchAll();

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
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h4 class="card-title mb-1">Welcome, <?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></h4>
                            <p class="card-text">ID: <?php echo htmlspecialchars($current_user['badge_number']); ?> | Department: <?php echo htmlspecialchars($current_user['department']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-3 my-3">
                <div class="col-md-3">
                    <div class="p-3 bg-info shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['total_assigned']; ?></h3>
                            <p class="fs-5">Total Assigned</p>
                        </div>
                        <i class="bi bi-clipboard-check fs-1 text-white"></i>
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
                            <p class="fs-5">On Scene</p>
                        </div>
                        <i class="bi bi-person-badge fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['resolved']; ?></h3>
                            <p class="fs-5">Resolved</p>
                        </div>
                        <i class="bi bi-check-circle-fill fs-1 text-white"></i>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row my-4">
                <!-- Recent Incidents -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Emergency Incidents</h5>
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
                                                <th>Urgency</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_incidents as $incident): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['incident_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?>">
                                                            <?php echo ucfirst($incident['urgency_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary" onclick="viewIncident(<?php echo $incident['id']; ?>)">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-warning" onclick="updateStatus(<?php echo $incident['id']; ?>, 'responding')" title="En Route">
                                                                <i class="bi bi-truck"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger" onclick="updateStatus(<?php echo $incident['id']; ?>, 'on_scene')" title="On Scene">
                                                                <i class="bi bi-person-badge"></i>
                                                            </button>
                                                            <button class="btn btn-outline-success" onclick="updateStatus(<?php echo $incident['id']; ?>, 'resolved')" title="Resolved">
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
                                    <i class="bi bi-truck fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No emergency incidents assigned</h5>
                                    <p class="text-muted">You will be notified when new emergencies are assigned to your unit.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Evacuation Centers Status -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Evacuation Centers Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($evacuation_centers) > 0): ?>
                                <?php foreach ($evacuation_centers as $center): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($center['name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo $center['current_occupancy']; ?> / <?php echo $center['capacity']; ?> capacity
                                            </small>
                                        </div>
                                        <div>
                                            <?php 
                                            $occupancy_percentage = ($center['current_occupancy'] / $center['capacity']) * 100;
                                            $status_color = 'success';
                                            if ($occupancy_percentage > 80) $status_color = 'danger';
                                            elseif ($occupancy_percentage > 60) $status_color = 'warning';
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo round($occupancy_percentage); ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-3">
                                    <a href="evacuation.php" class="btn btn-outline-primary btn-sm">View All Centers</a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-house fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No evacuation centers data</p>
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
            <div class="modal-header">
                <h5 class="modal-title">Emergency Incident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incidentDetails">
                <!-- Incident details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    // Load incident details via AJAX
    fetch('ajax/get_incident.php?id=' + incidentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('incidentDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('incidentModal')).show();
        });
}

function updateStatus(incidentId, status) {
    let statusText = status.replace('_', ' ');
    if (status === 'responding') statusText = 'En Route';
    
    if (confirm('Are you sure you want to update the status to ' + statusText + '?')) {
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
