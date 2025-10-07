<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Incident Management';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Handle incident actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $incident_id = $_POST['incident_id'] ?? '';
    
    switch ($action) {
        case 'approve_incident':
            $incident_id = $_POST['incident_id'] ?? '';
            $admin_id = $_SESSION['user_id'] ?? null;

            if (empty($incident_id) || !$admin_id) {
                $error_message = "Missing required data for approving incident.";
                break;
            }

            $query = "UPDATE incident_reports SET 
                     approval_status = 'approved',
                     approved_by = :admin_id,
                     approved_at = NOW(),
                     updated_at = NOW() 
                     WHERE id = :incident_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':admin_id', $admin_id);
            $stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Incident approved successfully!";
                logActivity($admin_id, 'Incident approved', 'incident_reports', $incident_id);
            } else {
                $error_message = "Error approving incident.";
            }
            break;

        case 'reject_incident':
            $incident_id = $_POST['incident_id'] ?? '';
            $rejection_reason = $_POST['rejection_reason'] ?? '';
            $admin_id = $_SESSION['user_id'] ?? null;

            if (empty($incident_id) || !$admin_id) {
                $error_message = "Missing required data for rejecting incident.";
                break;
            }

            $query = "UPDATE incident_reports SET 
                     approval_status = 'rejected',
                     approved_by = :admin_id,
                     approved_at = NOW(),
                     resolution_notes = :rejection_reason,
                     updated_at = NOW() 
                     WHERE id = :incident_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':admin_id', $admin_id);
            $stmt->bindParam(':rejection_reason', $rejection_reason);
            $stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Incident rejected successfully!";
                logActivity($admin_id, 'Incident rejected', 'incident_reports', $incident_id);
            } else {
                $error_message = "Error rejecting incident.";
            }
            break;

        case 'assign_to_department':
            $incident_id = $_POST['incident_id'] ?? '';
            $responder_type = $_POST['responder_type'] ?? '';
            $admin_id = $_SESSION['user_id'] ?? null;

            if (empty($incident_id) || empty($responder_type) || !$admin_id) {
                $error_message = "Missing required data for assigning incident.";
                break;
            }

            $query = "UPDATE incident_reports SET 
                     responder_type = :responder_type,
                     response_status = 'notified',
                     updated_at = NOW() 
                     WHERE id = :incident_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':responder_type', $responder_type);
            $stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Incident assigned to " . ucfirst($responder_type) . " department successfully!";
                logActivity($admin_id, "Incident assigned to $responder_type department", 'incident_reports', $incident_id);
            } else {
                $error_message = "Error assigning incident to department.";
            }
            break;

        case 'assign_to_responder':
            $incident_id = $_POST['incident_id'] ?? '';
            $assigned_to = $_POST['assigned_to'] ?? '';
            $responder_type = $_POST['responder_type'] ?? '';
            $admin_id = $_SESSION['user_id'] ?? null;

            if (empty($incident_id) || empty($assigned_to) || !$admin_id) {
                $error_message = "Missing required data for assigning responder.";
                break;
            }

            $query = "UPDATE incident_reports SET 
                     assigned_to = :assigned_to,
                     responder_type = :responder_type,
                     response_status = 'notified',
                     updated_at = NOW() 
                     WHERE id = :incident_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':assigned_to', $assigned_to, PDO::PARAM_INT);
            $stmt->bindParam(':responder_type', $responder_type);
            $stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Incident assigned to responder successfully!";
                logActivity($admin_id, "Incident assigned to responder", 'incident_reports', $incident_id);
            } else {
                $error_message = "Error assigning incident to responder.";
            }
            break;
            
        case 'update_status':
            $status = $_POST['status'] ?? '';
            $resolution_notes = $_POST['resolution_notes'] ?? '';
            $incident_id = $_POST['incident_id'] ?? '';
            $admin_id = $_SESSION['user_id'] ?? null;

            if (empty($incident_id) || empty($status) || !$admin_id) {
                $error_message = "Missing required data for updating incident.";
                break;
            }

            $query = "UPDATE incident_reports SET 
                     status = :status, 
                     resolution_notes = :resolution_notes,
                     reviewed_by = :admin_id,
                     reviewed_at = NOW(),
                     updated_at = NOW() 
                     WHERE id = :incident_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':resolution_notes', $resolution_notes);
            $stmt->bindParam(':admin_id', $admin_id);
            $stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success_message = "Incident status updated successfully!";
                logActivity($admin_id, 'Incident status updated', 'incident_reports', $incident_id);
            } else {
                $error_message = "Error updating incident.";
            }
            break;
            
        case 'delete_incident':
            $query = "DELETE FROM incident_reports WHERE id = :incident_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':incident_id', $incident_id);
            
            if ($stmt->execute()) {
                $success_message = "Incident deleted successfully!";
                logActivity($_SESSION['user_id'], 'Incident deleted', 'incident_reports', $incident_id);
            } else {
                $error_message = "Error deleting incident.";
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$approval_filter = $_GET['approval'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ir.status = :status";
    $params[':status'] = $status_filter;
}
if ($type_filter) {
    $where_conditions[] = "ir.incident_type = :type";
    $params[':type'] = $type_filter;
}
if ($severity_filter) {
    $where_conditions[] = "ir.severity = :severity";
    $params[':severity'] = $severity_filter;
}
if ($barangay_filter) {
    $where_conditions[] = "u.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}
if ($approval_filter) {
    $where_conditions[] = "ir.approval_status = :approval";
    $params[':approval'] = $approval_filter;
}
if ($search) {
    $where_conditions[] = "(ir.description LIKE :search OR ir.location LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($date_from) {
    $where_conditions[] = "DATE(ir.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(ir.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM incident_reports ir 
                LEFT JOIN users u ON ir.user_id = u.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT ir.*, u.first_name, u.last_name, u.email, u.phone, u.barangay,
                 admin.first_name as reviewed_by_name, admin.last_name as reviewed_by_lastname,
                 approver.first_name as approved_by_name, approver.last_name as approved_by_lastname,
                 responder.first_name as responder_first_name, responder.last_name as responder_last_name,
                 responder.user_type as responder_user_type
          FROM incident_reports ir 
          LEFT JOIN users u ON ir.user_id = u.id 
          LEFT JOIN users admin ON ir.reviewed_by = admin.id
          LEFT JOIN users approver ON ir.approved_by = approver.id
          LEFT JOIN users responder ON ir.assigned_to = responder.id
          $where_clause 
          ORDER BY ir.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$incidents = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_incidents,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_incidents,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_incidents,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_incidents,
                SUM(CASE WHEN urgency_level = 'critical' THEN 1 ELSE 0 END) as critical_incidents,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_incidents,
                SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approval,
                SUM(CASE WHEN approval_status = 'approved' AND assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_incidents
                FROM incident_reports";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get barangays for filter
$barangays_query = "SELECT DISTINCT u.barangay FROM incident_reports ir 
                   LEFT JOIN users u ON ir.user_id = u.id 
                   WHERE u.barangay IS NOT NULL ORDER BY u.barangay";
$barangays_stmt = $db->prepare($barangays_query);
$barangays_stmt->execute();
$barangays = $barangays_stmt->fetchAll();

$responders_query = "SELECT id, first_name, last_name, user_type, department, badge_number, assigned_barangay 
                     FROM users 
                     WHERE user_type IN ('police', 'emergency', 'barangay', 'firefighter') 
                     AND verification_status = 'verified'
                     ORDER BY user_type, first_name";
$responders_stmt = $db->prepare($responders_query);
$responders_stmt->execute();
$responders = $responders_stmt->fetchAll();

// Group responders by type
$responders_by_type = [
    'police' => [],
    'emergency' => [],
    'barangay' => [],
    'firefighter' => []
];
foreach ($responders as $responder) {
    $responders_by_type[$responder['user_type']][] = $responder;
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
             <!-- Page Header  -->
            <div class="d-flex justify-content-between align-items-center py-3">
                <h1 class="h3 mb-0">Incident Management</h1>
                <div>
                    <button class="btn btn-success" onclick="exportIncidents()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>

             <!-- Success/Error Messages  -->
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

             <!-- Statistics Cards  -->
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['total_incidents']; ?></h4>
                                    <p class="mb-0">Total</p>
                                </div>
                                <i class="bi bi-clipboard-list fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                 <!-- Added pending approval card  -->
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['pending_approval']; ?></h4>
                                    <p class="mb-0">Pending Approval</p>
                                </div>
                                <i class="bi bi-hourglass-split fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                 <!-- Added unassigned incidents card  -->
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['unassigned_incidents']; ?></h4>
                                    <p class="mb-0">Unassigned</p>
                                </div>
                                <i class="bi bi-person-x fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['resolved_incidents']; ?></h4>
                                    <p class="mb-0">Resolved</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['critical_incidents']; ?></h4>
                                    <p class="mb-0">Critical</p>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-dark text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['today_incidents']; ?></h4>
                                    <p class="mb-0">Today</p>
                                </div>
                                <i class="bi bi-calendar-week fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Filters  -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                         <!-- Added approval status filter  -->
                        <div class="col-md-2">
                            <label class="form-label">Approval Status</label>
                            <select class="form-select" name="approval">
                                <option value="">All Approvals</option>
                                <option value="pending" <?php echo $approval_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $approval_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $approval_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
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
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

             <!-- Incidents Table  -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Incidents (<?php echo $total_records; ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reporter</th>
                                    <th>Incident Type</th>
                                    <th>Location</th>
                                    <th>Severity</th>
                                     <!-- Added approval status column  -->
                                    <th>Approval</th>
                                     <!-- Added assigned department column  -->
                                    <th>Department</th>
                                     <!-- Added assigned responder column  -->
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Reported At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents as $incident): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($incident['email']); ?></small>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($incident['barangay']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst($incident['incident_type']); ?></span>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($incident['description'], 0, 50)) . '...'; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                    <td>
                                        <?php
                                        $urgency = isset($incident['urgency_level']) && $incident['urgency_level'] !== null ? $incident['urgency_level'] : '';
                                        $urgency_class = '';
                                        switch ($urgency) {
                                            case 'low': $urgency_class = 'bg-success'; break;
                                            case 'medium': $urgency_class = 'bg-warning'; break;
                                            case 'high': $urgency_class = 'bg-danger'; break;
                                            case 'critical': $urgency_class = 'bg-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $urgency_class; ?>"><?php echo $urgency !== '' ? ucfirst($urgency) : 'N/A'; ?></span>
                                    </td>
                                     <!-- Added approval status display  -->
                                    <td>
                                        <?php
                                        $approval_status = $incident['approval_status'] ?? 'pending';
                                        $approval_class = '';
                                        switch ($approval_status) {
                                            case 'pending': $approval_class = 'bg-warning'; break;
                                            case 'approved': $approval_class = 'bg-success'; break;
                                            case 'rejected': $approval_class = 'bg-danger'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $approval_class; ?>"><?php echo ucfirst($approval_status); ?></span>
                                    </td>
                                     <!-- Added department display  -->
                                    <td>
                                        <?php if ($incident['responder_type']): ?>
                                            <span class="badge bg-info"><?php echo ucfirst($incident['responder_type']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                     <!-- Added assigned responder display  -->
                                    <td>
                                        <?php if ($incident['assigned_to']): ?>
                                            <div class="fw-bold"><?php echo htmlspecialchars($incident['responder_first_name'] . ' ' . $incident['responder_last_name']); ?></div>
                                            <small class="text-muted"><?php echo ucfirst($incident['responder_user_type']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($incident['status']) {
                                            case 'pending': $status_class = 'bg-warning'; break;
                                            case 'in_progress': $status_class = 'bg-info'; break;
                                            case 'resolved': $status_class = 'bg-success'; break;
                                            case 'closed': $status_class = 'bg-secondary'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?></span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($incident['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewIncident(<?php echo $incident['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                     <!-- Added approval/rejection options  -->
                                                    <?php if ($incident['approval_status'] == 'pending'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="#" onclick="approveIncident(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-check-circle"></i> Approve Incident
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="rejectIncident(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-x-circle"></i> Reject Incident
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                     <!-- Added assignment options  -->
                                                    <?php if ($incident['approval_status'] == 'approved'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="assignToDepartment(<?php echo $incident['id']; ?>, '<?php echo $incident['responder_type'] ?? ''; ?>')">
                                                            <i class="bi bi-building text-primary"></i> Assign to Department
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="assignToResponder(<?php echo $incident['id']; ?>, '<?php echo $incident['responder_type'] ?? ''; ?>', <?php echo $incident['assigned_to'] ?? 'null'; ?>)">
                                                            <i class="bi bi-person-plus text-info"></i> Assign to Responder
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="updateIncident(<?php echo $incident['id']; ?>, '<?php echo $incident['status']; ?>', '<?php echo htmlspecialchars($incident['resolution_notes']); ?>')">
                                                            <i class="bi bi-pencil text-warning"></i> Update Status
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteIncident(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Delete Incident
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="viewIncidentPhotos(<?php echo $incident['id']; ?>)">
                                                            <i class="bi bi-image"></i> View Photo
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
                
                 <!-- Pagination  -->
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

 <!-- Added Reject Incident Modal  -->
<div class="modal fade" id="rejectIncidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Incident</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_incident">
                    <input type="hidden" name="incident_id" id="reject_incident_id">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Are you sure you want to reject this incident?
                    </div>
                    
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" placeholder="Explain why this incident is being rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

 <!-- Added Assign to Department Modal  -->
<div class="modal fade" id="assignDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Assign to Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_to_department">
                    <input type="hidden" name="incident_id" id="assign_dept_incident_id">
                    
                    <div class="mb-3">
                        <label for="responder_type" class="form-label">Select Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="responder_type" name="responder_type" required>
                            <option value="">Choose department...</option>
                            <option value="police">Police Department</option>
                            <option value="firefighter">Fire Department</option>
                            <option value="emergency">Emergency/Medical Services</option>
                            <option value="barangay">Barangay Response Team</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will notify all responders in the selected department.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign to Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

 <!-- Added Assign to Responder Modal  -->
<div class="modal fade" id="assignResponderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Assign to Specific Responder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_to_responder">
                    <input type="hidden" name="incident_id" id="assign_resp_incident_id">
                    
                    <div class="mb-3">
                        <label for="assign_responder_type" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-select" id="assign_responder_type" name="responder_type" required onchange="filterResponders(this.value)">
                            <option value="">Choose department first...</option>
                            <option value="police">Police Department</option>
                            <option value="firefighter">Fire Department</option>
                            <option value="emergency">Emergency/Medical Services</option>
                            <option value="barangay">Barangay Response Team</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Select Responder <span class="text-danger">*</span></label>
                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                            <option value="">Select department first...</option>
                        </select>
                    </div>
                    
                    <div id="responderInfo" class="alert alert-light d-none">
                        <h6>Responder Information:</h6>
                        <div id="responderDetails"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Assign to Responder</button>
                </div>
            </form>
        </div>
    </div>
</div>

 <!-- Update Incident Modal  -->
<div class="modal fade" id="updateIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Incident Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="incident_id" id="update_incident_id">
                    
                    <div class="mb-3">
                        <label for="update_status" class="form-label">Status</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="resolution_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="resolution_notes" name="resolution_notes" rows="4" placeholder="Add notes about the incident status or actions taken..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

 <!-- View Incident Modal  -->
<div class="modal fade" id="viewIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Incident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

 <!-- Image Modal for viewing photos  -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Incident Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="/placeholder.svg" alt="Incident Photo" class="img-fluid" style="max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

 <!-- Hidden form for actions  -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="incident_id" id="actionIncidentId">
</form>

 <!-- Added responders data for JavaScript  -->
<script>
    // Store responders data for filtering
    const respondersData = <?php echo json_encode($responders_by_type); ?>;
    
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
        e.preventDefault();
        document.getElementById("wrapper").classList.toggle("toggled");
    });
    
    function approveIncident(incidentId) {
        if (confirm('Are you sure you want to approve this incident? It will be available for assignment to departments.')) {
            document.getElementById('actionType').value = 'approve_incident';
            document.getElementById('actionIncidentId').value = incidentId;
            document.getElementById('actionForm').submit();
        }
    }
    
    function rejectIncident(incidentId) {
        document.getElementById('reject_incident_id').value = incidentId;
        new bootstrap.Modal(document.getElementById('rejectIncidentModal')).show();
    }
    
    function assignToDepartment(incidentId, currentDept) {
        document.getElementById('assign_dept_incident_id').value = incidentId;
        if (currentDept) {
            document.getElementById('responder_type').value = currentDept;
        }
        new bootstrap.Modal(document.getElementById('assignDepartmentModal')).show();
    }
    
    function assignToResponder(incidentId, currentDept, currentResponder) {
        document.getElementById('assign_resp_incident_id').value = incidentId;
        if (currentDept) {
            document.getElementById('assign_responder_type').value = currentDept;
            filterResponders(currentDept);
            if (currentResponder) {
                document.getElementById('assigned_to').value = currentResponder;
            }
        }
        new bootstrap.Modal(document.getElementById('assignResponderModal')).show();
    }
    
    function filterResponders(responderType) {
        const responderSelect = document.getElementById('assigned_to');
        responderSelect.innerHTML = '<option value="">Choose a responder...</option>';
        
        if (responderType && respondersData[responderType]) {
            respondersData[responderType].forEach(responder => {
                const option = document.createElement('option');
                option.value = responder.id;
                option.textContent = `${responder.first_name} ${responder.last_name}`;
                if (responder.badge_number) {
                    option.textContent += ` (${responder.badge_number})`;
                }
                if (responder.assigned_barangay) {
                    option.textContent += ` - ${responder.assigned_barangay}`;
                }
                option.dataset.department = responder.department || '';
                option.dataset.badge = responder.badge_number || '';
                option.dataset.barangay = responder.assigned_barangay || '';
                responderSelect.appendChild(option);
            });
        }
        
        // Show responder info when selected
        responderSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const infoDiv = document.getElementById('responderInfo');
                const detailsDiv = document.getElementById('responderDetails');
                detailsDiv.innerHTML = `
                    <p class="mb-1"><strong>Name:</strong> ${selectedOption.textContent.split('(')[0].trim()}</p>
                    ${selectedOption.dataset.department ? `<p class="mb-1"><strong>Department:</strong> ${selectedOption.dataset.department}</p>` : ''}
                    ${selectedOption.dataset.badge ? `<p class="mb-1"><strong>Badge:</strong> ${selectedOption.dataset.badge}</p>` : ''}
                    ${selectedOption.dataset.barangay ? `<p class="mb-1"><strong>Assigned Barangay:</strong> ${selectedOption.dataset.barangay}</p>` : ''}
                `;
                infoDiv.classList.remove('d-none');
            } else {
                document.getElementById('responderInfo').classList.add('d-none');
            }
        });
    }

    function updateIncident(incidentId, currentStatus, currentNotes) {
        document.getElementById('update_incident_id').value = incidentId;
        document.getElementById('update_status').value = currentStatus;
        document.getElementById('resolution_notes').value = currentNotes;
        new bootstrap.Modal(document.getElementById('updateIncidentModal')).show();
    }

    function deleteIncident(incidentId) {
        if (confirm('Are you sure you want to delete this incident report? This action cannot be undone.')) {
            document.getElementById('actionType').value = 'delete_incident';
            document.getElementById('actionIncidentId').value = incidentId;
            document.getElementById('actionForm').submit();
        }
    }

    function viewIncident(incidentId) {
        fetch(`get_incident_details.php?id=${incidentId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('incidentDetails').innerHTML = data;
                new bootstrap.Modal(document.getElementById('viewIncidentModal')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading incident details');
            });
    }

    function showImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }

    function exportIncidents() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', '1');
        window.location.href = 'export_incidents.php?' + params.toString();
    }

    function viewIncidentPhotos(incidentId) {
        fetch(`get_incident_details.php?id=${incidentId}`)
            .then(response => response.text())
            .then(data => {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const photoElements = tempDiv.querySelectorAll('img[onclick*="showImageModal"]');
                
                if (photoElements.length > 0) {
                    const firstPhotoSrc = photoElements[0].getAttribute('onclick').match(/'([^']+)'/)[1];
                    showImageModal(firstPhotoSrc);
                } else {
                    alert('No photos available for this incident.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading incident photos');
            });
    }
</script>

<?php include '../includes/footer.php'; ?>
