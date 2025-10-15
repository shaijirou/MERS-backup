<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php');
}

// Check if user is emergency personnel
if (!isEmergency()) {
    // Redirect to appropriate dashboard based on user type
    switch ($_SESSION['user_type']) {
        case 'admin':
            redirect('../admin/dashboard.php');
            break;
        case 'police':
            redirect('../police/dashboard.php');
            break;
        case 'firefighter':
            redirect('../firefighter/dashboard.php');
            break;
        case 'barangay':
            redirect('../barangay/dashboard.php');
            break;
        default:
            redirect('../user/dashboard.php');
            break;
    }
}

$page_title = 'Emergency Reports';
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

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$incident_type = $_GET['incident_type'] ?? '';
$status = $_GET['status'] ?? '';

$conditions = ["ir.approval_status = 'approved'"];
$conditions[] = "(ir.assigned_to = :user_id OR ir.responder_type = 'emergency' OR ir.incident_type LIKE '%medical%' OR ir.incident_type LIKE '%emergency%' = 1)";
$params = [':user_id' => $user_id];

if (!empty($current_user['assigned_barangay'])) {
    $conditions[] = "u.barangay = :assigned_barangay";
    $params[':assigned_barangay'] = $current_user['assigned_barangay'];
}

if ($date_from) {
    $conditions[] = "DATE(ir.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $conditions[] = "DATE(ir.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}
if ($incident_type) {
    $conditions[] = "ir.incident_type = :incident_type";
    $params[':incident_type'] = $incident_type;
}
if ($status) {
    $conditions[] = "ir.response_status = :status";
    $params[':status'] = $status;
}

$where_clause = implode(' AND ', $conditions);

// Get incidents data
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.barangay
                   FROM incident_reports ir 
                   JOIN users u ON ir.user_id = u.id 
                   WHERE $where_clause
                   ORDER BY ir.created_at DESC";
$stmt = $db->prepare($incidents_query);
$stmt->execute($params);
$incidents = $stmt->fetchAll();

// Get summary statistics
$summary_query = "SELECT 
                 COUNT(*) as total_incidents,
                 SUM(CASE WHEN ir.response_status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                 SUM(CASE WHEN ir.response_status = 'responding' OR ir.response_status = 'on_scene' THEN 1 ELSE 0 END) as active_count,
                 SUM(CASE WHEN ir.response_status = 'notified' THEN 1 ELSE 0 END) as pending_count
                 FROM incident_reports ir 
                 JOIN users u ON ir.user_id = u.id 
                 WHERE $where_clause";
$stmt = $db->prepare($summary_query);
$stmt->execute($params);
$summary = $stmt->fetch();

// Get incidents by type
$by_type_query = "SELECT ir.incident_type, COUNT(*) as count
                 FROM incident_reports ir 
                 JOIN users u ON ir.user_id = u.id 
                 WHERE $where_clause
                 GROUP BY ir.incident_type
                 ORDER BY count DESC";
$stmt = $db->prepare($by_type_query);
$stmt->execute($params);
$by_type = $stmt->fetchAll();

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
                    <h1 class="h3 mb-0">🚑 Emergency Medical Reports</h1>
                    <p class="text-muted">Comprehensive analysis of emergency medical response activities</p>
                </div>
            </div>

           
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2"></i>Filter Reports
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Incident Type</label>
                                <select name="incident_type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="Fire" <?php echo $incident_type == 'Fire' ? 'selected' : ''; ?>>Fire</option>
                                    <option value="Flood" <?php echo $incident_type == 'Flood' ? 'selected' : ''; ?>>Flood</option>
                                    <option value="Landslide" <?php echo $incident_type == 'Landslide' ? 'selected' : ''; ?>>Landslide</option>
                                    <option value="Earthquake" <?php echo $incident_type == 'Earthquake' ? 'selected' : ''; ?>>Earthquake</option>
                                    <option value="Typhoon" <?php echo $incident_type == 'Typhoon' ? 'selected' : ''; ?>>Typhoon</option>
                                    <option value="Medical Emergency" <?php echo $incident_type == 'Medical Emergency' ? 'selected' : ''; ?>>Medical Emergency</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="notified" <?php echo $status == 'notified' ? 'selected' : ''; ?>>Notified</option>
                                    <option value="responding" <?php echo $status == 'responding' ? 'selected' : ''; ?>>Responding</option>
                                    <option value="on_scene" <?php echo $status == 'on_scene' ? 'selected' : ''; ?>>On Scene</option>
                                    <option value="resolved" <?php echo $status == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-search me-2"></i>Apply Filters
                            </button>
                            <a href="reports.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 bg-danger shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $summary['total_incidents']; ?></h3>
                            <p class="fs-6">Total Incidents</p>
                        </div>
                        <i class="bi bi-heart-pulse-fill fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-success shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $summary['resolved_count']; ?></h3>
                            <p class="fs-6">Resolved</p>
                        </div>
                        <i class="bi bi-check-circle-fill fs-1 text-white"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-warning shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $summary['active_count']; ?></h3>
                            <p class="fs-6">Active</p>
                        </div>
                        <i class="bi bi-clock-fill fs-1 text-white"></i>
                    </div>
                </div>
                
            </div>

            
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-table me-2"></i>Incident Reports
                        </h5>
                        <span class="badge bg-danger"><?php echo count($incidents); ?> Records</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($incidents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-file-earmark-x text-muted" style="font-size: 4rem;"></i>
                            <h5 class="text-muted mt-3">No incidents found</h5>
                            <p class="text-muted">No incidents match the selected filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Report #</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Reporter</th>
                                        <th>Barangay</th>
                                        <th>Status</th>
                                        <th>Urgency</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidents as $incident): ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo htmlspecialchars($incident['report_number']); ?></td>
                                            <td>
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
                                                <i class="<?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars($incident['incident_type']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                            <td><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($incident['barangay']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?> rounded-pill">
                                                    <?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?> rounded-pill">
                                                    <?php echo ucfirst($incident['urgency_level']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($incident['created_at'])); ?></td>
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


<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});
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
