<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Alert Management';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Handle alert actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_alert':
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $alert_type = $_POST['alert_type'] ?? '';
            $severity_level = $_POST['severity_level'] ?? '';
            $affected_barangays = $_POST['affected_barangays'] ?? '';
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            
            $query = "INSERT INTO alerts (title, message, alert_type, severity_level, affected_barangays, sent_by, expires_at, created_at) 
                     VALUES (:title, :message, :alert_type, :severity_level, :affected_barangays, :sent_by, :expires_at, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':alert_type', $alert_type);
            $stmt->bindParam(':severity_level', $severity_level);
            $stmt->bindParam(':affected_barangays', $affected_barangays);
            $stmt->bindParam(':sent_by', $_SESSION['user_id']);
            $stmt->bindParam(':expires_at', $expires_at);
            
            if ($stmt->execute()) {
                $success_message = "Alert created successfully!";
                logActivity($_SESSION['user_id'], 'Alert created', 'alerts', $db->lastInsertId());
            } else {
                $error_message = "Error creating alert.";
            }
            break;
            
        case 'update_status':
            $alert_id = $_POST['alert_id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            $query = "UPDATE alerts SET status = :status, updated_at = NOW() WHERE id = :alert_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':alert_id', $alert_id);
            
            if ($stmt->execute()) {
                $success_message = "Alert status updated successfully!";
                logActivity($_SESSION['user_id'], 'Alert status updated', 'alerts', $alert_id);
            } else {
                $error_message = "Error updating alert status.";
            }
            break;
            
        case 'delete_alert':
            $alert_id = $_POST['alert_id'] ?? '';
            
            $query = "DELETE FROM alerts WHERE id = :alert_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':alert_id', $alert_id);
            
            if ($stmt->execute()) {
                $success_message = "Alert deleted successfully!";
                logActivity($_SESSION['user_id'], 'Alert deleted', 'alerts', $alert_id);
            } else {
                $error_message = "Error deleting alert.";
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "a.status = :status";
    $params[':status'] = $status_filter;
}
if ($type_filter) {
    $where_conditions[] = "a.alert_type = :type";
    $params[':type'] = $type_filter;
}
if ($severity_filter) {
    $where_conditions[] = "a.severity_level = :severity";
    $params[':severity'] = $severity_filter;
}
if ($barangay_filter) {
    $where_conditions[] = "a.affected_barangays LIKE :barangay";
    $params[':barangay'] = "%$barangay_filter%";
}
if ($search) {
    $where_conditions[] = "(a.title LIKE :search OR a.message LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM alerts a $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get alerts
$query = "SELECT a.*, u.first_name, u.last_name 
          FROM alerts a 
          LEFT JOIN users u ON a.sent_by = u.id 
          $where_clause 
          ORDER BY a.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$alerts = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_alerts,
                SUM(CASE WHEN severity_level = 'critical' THEN 1 ELSE 0 END) as critical_alerts,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_alerts
                FROM alerts";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get barangays for filter
$barangays = ['Adia', 'Balangon', 'Banyaga', 'Bilibinwang', 'Coral na Munti', 'Guitna', 'Mabacong', 'Panhulan', 'Poblacion', 'Pook', 'Pulang Bato', 'San Jacinto', 'San Teodoro', 'Santa Rosa', 'Santo Tomas', 'Subic Ilaya', 'Subic Ibaba'];

include '../includes/header.php';
?>

<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Navigation -->
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center py-3">
                <h1 class="h3 mb-0">Alert Management</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlertModal">
                        <i class="bi bi-plus-lg"></i> Create Alert
                    </button>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['total_alerts']; ?></h4>
                                    <p class="mb-0">Total Alerts</p>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['active_alerts']; ?></h4>
                                    <p class="mb-0">Active Alerts</p>
                                </div>
                                <i class="bi bi-bell fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['critical_alerts']; ?></h4>
                                    <p class="mb-0">Critical Alerts</p>
                                </div>
                                <i class="bi bi-exclamation-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['today_alerts']; ?></h4>
                                    <p class="mb-0">Today's Alerts</p>
                                </div>
                                <i class="bi bi-calendar-day fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Search alerts..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All Types</option>
                                <option value="flood" <?php echo $type_filter == 'flood' ? 'selected' : ''; ?>>Flood</option>
                                <option value="earthquake" <?php echo $type_filter == 'earthquake' ? 'selected' : ''; ?>>Earthquake</option>
                                <option value="fire" <?php echo $type_filter == 'fire' ? 'selected' : ''; ?>>Fire</option>
                                <option value="typhoon" <?php echo $type_filter == 'typhoon' ? 'selected' : ''; ?>>Typhoon</option>
                                <option value="landslide" <?php echo $type_filter == 'landslide' ? 'selected' : ''; ?>>Landslide</option>
                                <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="severity">
                                <option value="">All Severity</option>
                                <option value="low" <?php echo $severity_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $severity_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $severity_filter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $severity_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay; ?>" <?php echo $barangay_filter == $barangay ? 'selected' : ''; ?>>
                                        <?php echo $barangay; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alerts Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Alerts (<?php echo $total_records; ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Barangay</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($alert['title']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($alert['message'], 0, 50)) . '...'; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst($alert['alert_type']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $severity_class = '';
                                        switch ($alert['severity_level']) {
                                            case 'low': $severity_class = 'bg-success'; break;
                                            case 'medium': $severity_class = 'bg-warning'; break;
                                            case 'high': $severity_class = 'bg-orange'; break;
                                            case 'critical': $severity_class = 'bg-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $severity_class; ?>"><?php echo ucfirst($alert['severity_level']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['affected_barangays']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($alert['status']) {
                                            case 'active': $status_class = 'bg-success'; break;
                                            case 'resolved': $status_class = 'bg-primary'; break;
                                            case 'expired': $status_class = 'bg-secondary'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($alert['status']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></td>
                                    <td>
                                        <div><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($alert['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewAlert(<?php echo $alert['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $alert['id']; ?>, '<?php echo $alert['status']; ?>')">
                                                            <i class="bi bi-pencil text-warning"></i> Update Status
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteAlert(<?php echo $alert['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Delete Alert
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Alert Modal -->
<div class="modal fade" id="createAlertModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_alert">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Alert Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Alert Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="alert_type" class="form-label">Alert Type *</label>
                                <select class="form-select" id="alert_type" name="alert_type" required>
                                    <option value="">Select Type</option>
                                    <option value="flood">Flood</option>
                                    <option value="earthquake">Earthquake</option>
                                    <option value="fire">Fire</option>
                                    <option value="typhoon">Typhoon</option>
                                    <option value="landslide">Landslide</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="severity_level" class="form-label">Severity Level *</label>
                                <select class="form-select" id="severity_level" name="severity_level" required>
                                    <option value="">Select Severity</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="affected_barangays" class="form-label">Affected Barangay</label>
                                <input type="text" class="form-control" id="affected_barangays" name="affected_barangays" placeholder="Leave blank for municipality-wide">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Expiration Date/Time</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Alert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Alert Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="alert_id" id="update_alert_id">
                    
                    <div class="mb-3">
                        <label for="update_status" class="form-label">New Status</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="resolved">Resolved</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden form for actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="alert_id" id="actionAlertId">
</form>

<script>
function updateStatus(alertId, currentStatus) {
    document.getElementById('update_alert_id').value = alertId;
    document.getElementById('update_status').value = currentStatus;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function deleteAlert(alertId) {
    if (confirm('Are you sure you want to delete this alert? This action cannot be undone.')) {
        document.getElementById('actionType').value = 'delete_alert';
        document.getElementById('actionAlertId').value = alertId;
        document.getElementById('actionForm').submit();
    }
}

function viewAlert(alertId) {
    // Implement view alert functionality
    alert('View alert functionality to be implemented');
}
</script>

<?php include '../includes/footer.php'; ?>
