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
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
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
                                                                    title="On Scene"
                                                                    <?php echo ($incident['response_status'] === 'resolved') ? 'disabled' : ''; ?>>
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

                <!-- Evacuation Centers Status with Pagination -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Evacuation Centers Status</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Pagination setup
                            $per_page = 5;
                            $page = isset($_GET['evac_page']) && is_numeric($_GET['evac_page']) ? (int)$_GET['evac_page'] : 1;
                            $offset = ($page - 1) * $per_page;

                            // Get total count
                            $count_stmt = $db->query("SELECT COUNT(*) FROM evacuation_centers");
                            $total_centers = $count_stmt->fetchColumn();
                            $total_pages = ceil($total_centers / $per_page);

                            // Get paginated data
                            $evacuation_query = "SELECT name, capacity, current_occupancy, status FROM evacuation_centers ORDER BY current_occupancy DESC LIMIT :offset, :per_page";
                            $stmt = $db->prepare($evacuation_query);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
                            $stmt->execute();
                            $evacuation_centers_paginated = $stmt->fetchAll();
                            ?>

                            <?php if (count($evacuation_centers_paginated) > 0): ?>
                                <?php foreach ($evacuation_centers_paginated as $center): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($center['name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo $center['current_occupancy']; ?> / <?php echo $center['capacity']; ?> capacity
                                            </small>
                                        </div>
                                        <div>
                                            <?php 
                                            $occupancy_percentage = ($center['capacity'] > 0) ? ($center['current_occupancy'] / $center['capacity']) * 100 : 0;
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

                                <!-- Modern Centered Pagination Controls -->
                                <nav>
                                    <ul class="pagination justify-content-center mt-3 mb-0" style="width:100%;">
                                        <!-- Previous button -->
                                        <li class="page-item<?php if ($page <= 1) echo ' disabled'; ?>" style="flex:1;">
                                            <a class="page-link rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:2.5rem;height:2.5rem;" href="?evac_page=<?php echo max(1, $page - 1); ?>" aria-label="Previous">
                                                <span aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                                            </a>
                                        </li>
                                        <?php
                                        $max_pages_to_show = 4;
                                        $start_page = max(1, $page - floor($max_pages_to_show / 2));
                                        $end_page = $start_page + $max_pages_to_show - 1;
                                        if ($end_page > $total_pages) {
                                            $end_page = $total_pages;
                                            $start_page = max(1, $end_page - $max_pages_to_show + 1);
                                        }
                                        // Show first page and ellipsis if needed
                                        if ($start_page > 1) {
                                            ?>
                                            <li class="page-item" style="flex:1;">
                                                <a class="page-link rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:2.5rem;height:2.5rem;" href="?evac_page=1">1</a>
                                            </li>
                                            <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled" style="flex:1;">
                                                    <span class="page-link" style="width:2.5rem;height:2.5rem;">...</span>
                                                </li>
                                            <?php endif; ?>
                                        <?php
                                        }
                                        // Main page numbers
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item<?php if ($i == $page) echo ' active'; ?>" style="flex:1;">
                                                <a class="page-link rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:2.5rem;height:2.5rem;" href="?evac_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor;
                                        // Show ellipsis and last page if needed
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled" style="flex:1;">
                                                    <span class="page-link" style="width:2.5rem;height:2.5rem;">...</span>
                                                </li>
                                            <?php endif; ?>
                                            <li class="page-item" style="flex:1;">
                                                <a class="page-link rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:2.5rem;height:2.5rem;" href="?evac_page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                            </li>
                                        <?php } ?>
                                        <!-- Next button -->
                                        <li class="page-item<?php if ($page >= $total_pages) echo ' disabled'; ?>" style="flex:1;">
                                            <a class="page-link rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width:2.5rem;height:2.5rem;" href="?evac_page=<?php echo min($total_pages, $page + 1); ?>" aria-label="Next">
                                                <span aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>

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
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-ambulance me-2"></i>Emergency Incident Details</h5>
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
