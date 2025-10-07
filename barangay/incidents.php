<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php');
}

// Check if user is barangay personnel
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

$page_title = 'Barangay Incidents';
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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $incident_id = $_POST['incident_id'];
    $new_status = $_POST['status'];
    
    $update_query = "UPDATE incident_reports SET response_status = :status WHERE id = :incident_id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':status', $new_status);
    $stmt->bindParam(':incident_id', $incident_id);
    $stmt->execute();
    
    // Create notification for admin
    $notification_query = "INSERT INTO user_notifications (user_id, title, message, type, created_at) 
                          SELECT id, 'Incident Status Updated', 
                          CONCAT('Barangay unit has updated incident #', :incident_id, ' status to ', :status), 
                          'info', NOW() FROM users WHERE user_type = 'admin'";
    $stmt = $db->prepare($notification_query);
    $stmt->bindParam(':incident_id', $incident_id);
    $stmt->bindParam(':status', $new_status);
    $stmt->execute();
    
    header('Location: incidents.php?success=Status updated successfully');
    exit;
}

// Get assigned incidents (filter by barangay if assigned_barangay is set)
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.barangay 
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE ir.approval_status = 'approved' 
                   AND (ir.assigned_to = :user_id OR ir.responder_type = 'barangay')";

// If user has assigned barangay, filter incidents by that barangay
if (!empty($current_user['assigned_barangay'])) {
    $incidents_query .= " AND u.barangay = :assigned_barangay";
}

$incidents_query .= " ORDER BY ir.created_at DESC";

$stmt = $db->prepare($incidents_query);
$stmt->bindParam(':user_id', $user_id);
if (!empty($current_user['assigned_barangay'])) {
    $stmt->bindParam(':assigned_barangay', $current_user['assigned_barangay']);
}
$stmt->execute();
$incidents = $stmt->fetchAll();

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
             
            <div class="d-flex justify-content-between align-items-center my-4">
                <div>
                    <h1 class="h3 mb-0">🚨 Barangay Emergency Incidents</h1>
                    <p class="text-muted">Manage and respond to community emergency incidents</p>
                </div>
                <span class="badge bg-primary fs-6"><?php echo count($incidents); ?> Total Incidents</span>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>Assigned Incidents
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($incidents)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-house-check text-muted" style="font-size: 4rem;"></i>
                                    <h5 class="text-muted mt-3">No incidents assigned</h5>
                                    <p class="text-muted">You will be notified when new incidents are assigned to the barangay emergency response team.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-4">
                                    <?php foreach ($incidents as $incident): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card h-100 border-start border-4 border-<?php echo getUrgencyColor($incident['urgency_level']); ?>">
                                                <div class="card-header bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <?php
                                                            $icons = [
                                                                'Fire' => 'bi-fire',
                                                                'Flood' => 'bi-water',
                                                                'Landslide' => 'bi-mountain',
                                                                'Earthquake' => 'bi-globe',
                                                                'Typhoon' => 'bi-tornado',
                                                                'Medical Emergency' => 'bi-heart-pulse'
                                                            ];
                                                            $icon = $icons[$incident['incident_type']] ?? 'bi-exclamation-triangle';
                                                            ?>
                                                            <i class="<?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($incident['incident_type']); ?>
                                                        </h6>
                                                        <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?> rounded-pill">
                                                            <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>Location</small>
                                                        <span><?php echo htmlspecialchars($incident['location']); ?></span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block"><i class="bi bi-person me-1"></i>Reporter</small>
                                                        <span><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i>Contact</small>
                                                        <span><?php echo htmlspecialchars($incident['phone']); ?></span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block"><i class="bi bi-exclamation-triangle me-1"></i>Urgency</small>
                                                        <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?> rounded-pill">
                                                            <?php echo ucfirst($incident['urgency_level']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted d-block"><i class="bi bi-calendar me-1"></i>Reported</small>
                                                        <span><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></span>
                                                    </div>
                                                    <?php if (!empty($incident['description'])): ?>
                                                        <div class="mb-3">
                                                            <small class="text-muted d-block"><i class="bi bi-file-text me-1"></i>Description</small>
                                                            <span class="small"><?php echo htmlspecialchars(substr($incident['description'], 0, 100)) . (strlen($incident['description']) > 100 ? '...' : ''); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <?php if ($incident['response_status'] == 'notified'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                                                                <input type="hidden" name="status" value="responding">
                                                                <button type="submit" name="update_status" class="btn btn-warning btn-sm">
                                                                    <i class="bi bi-person-running me-1"></i>Start Response
                                                                </button>
                                                            </form>
                                                        <?php elseif ($incident['response_status'] == 'responding'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                                                                <input type="hidden" name="status" value="on_scene">
                                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm">
                                                                    <i class="bi bi-geo-alt me-1"></i>On Scene
                                                                </button>
                                                            </form>
                                                        <?php elseif ($incident['response_status'] == 'on_scene'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="incident_id" value="<?php echo $incident['id']; ?>">
                                                                <input type="hidden" name="status" value="resolved">
                                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                                    <i class="bi bi-check-circle me-1"></i>Mark Resolved
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-primary btn-sm" onclick="viewIncident(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-eye me-1"></i>View Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
            <div class="modal-header">
                <h5 class="modal-title">Incident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="incidentDetails">
                 Incident details will be loaded here 
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

function viewIncident(incidentId) {
    // Load incident details via AJAX
    fetch('ajax/get_incident.php?id=' + incidentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('incidentDetails').innerHTML = data;
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
