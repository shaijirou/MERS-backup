<?php
require_once '../config/config.php';
require_once '../config/semaphore.php';
require_once '../includes/SemaphoreAPI.php';
requireAdmin();

$page_title = 'Alert Management';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Initialize Semaphore API
$sms_api = new SemaphoreAPI();

function sendAlertEmails($db, $alert_id, $alert_title, $alert_message, $alert_type, $severity_level, $affected_barangays) {
    try {
        // Get recipient emails based on affected barangays
        if ($affected_barangays !== 'All') {
            $recipient_query = "SELECT email FROM users WHERE barangay = :barangay AND email IS NOT NULL AND email != '' AND verification_status = 'verified'";
            $recipient_stmt = $db->prepare($recipient_query);
            $recipient_stmt->bindParam(':barangay', $affected_barangays);
        } else {
            $recipient_query = "SELECT email FROM users WHERE email IS NOT NULL AND email != '' AND verification_status = 'verified'";
            $recipient_stmt = $db->prepare($recipient_query);
        }
        
        $recipient_stmt->execute();
        $recipients = $recipient_stmt->fetchAll();
        
        $emails = array_column($recipients, 'email');
        
        // Store email list in session for JavaScript to use
        $_SESSION['alert_emails'] = $emails;
        $_SESSION['alert_data'] = [
            'title' => $alert_title,
            'message' => $alert_message,
            'type' => $alert_type,
            'severity' => $severity_level,
            'barangay' => $affected_barangays
        ];
        
        return count($emails);
    } catch (Exception $e) {
        error_log('Error getting alert recipients: ' . $e->getMessage());
        return 0;
    }
}

// Handle alert actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_alert':
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $alert_type = $_POST['alert_type'] ?? '';
            $severity_level = $_POST['severity_level'] ?? '';
            $affected_barangays = ($_POST['affected_barangays']) ?? '';
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $send_sms = isset($_POST['send_sms']) ? 1 : 0;
            
            $sent_by = $_SESSION['user_id'];
            
            $query = "INSERT INTO alerts (title, message, alert_type, severity_level, affected_barangays, sent_by, expires_at, created_at) 
                     VALUES (:title, :message, :alert_type, :severity_level, :affected_barangays, :sent_by, :expires_at, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':alert_type', $alert_type);
            $stmt->bindParam(':severity_level', $severity_level);
            $stmt->bindParam(':affected_barangays', $affected_barangays);
            $stmt->bindParam(':sent_by', $sent_by);
            $stmt->bindParam(':expires_at', $expires_at);
            
            if ($stmt->execute()) {
                $alert_id = $db->lastInsertId();
                $success_message = "Alert created successfully!";
                
                if (ENABLE_SMS_NOTIFICATIONS && $send_sms) {
                    $sms_message = "ALERT: " . substr($title, 0, 50) . " - " . substr($message, 0, 80);
                    
                    // Get recipient phone numbers based on settings
                    if (SMS_ALERT_RECIPIENTS === 'barangay' && $affected_barangays !== 'All') {
                        // Send only to users in affected barangay
                        $recipient_query = "SELECT phone FROM users WHERE barangay = :barangay AND phone IS NOT NULL AND phone != ''";
                        $recipient_stmt = $db->prepare($recipient_query);
                        $recipient_stmt->bindParam(':barangay', $affected_barangays);
                    } else {
                        // Send to all users
                        $recipient_query = "SELECT phone FROM users WHERE phone IS NOT NULL AND phone != ''";
                        $recipient_stmt = $db->prepare($recipient_query);
                    }
                    
                    $recipient_stmt->execute();
                    $recipients = $recipient_stmt->fetchAll();
                    
                    $phone_numbers = array_column($recipients, 'phone');
                    
                    if (!empty($phone_numbers)) {
                        $sms_results = $sms_api->sendBulkSMS($phone_numbers, $sms_message);
                        
                        // Log SMS sending results
                        $successful_sms = count(array_filter($sms_results, function($r) { return $r['success']; }));
                        $failed_sms = count($sms_results) - $successful_sms;
                        
                        $success_message .= " SMS sent to " . count($phone_numbers) . " recipients (" . $successful_sms . " successful, " . $failed_sms . " failed).";
                        
                        // Store SMS log in database if table exists
                        $log_alert_id = $alert_id;
                        $log_total = count($phone_numbers);
                        $log_successful = $successful_sms;
                        $log_failed = $failed_sms;
                        
                        $log_query = "INSERT INTO sms_logs (alert_id, total_recipients, successful, failed, created_at) 
                                     VALUES (:alert_id, :total, :successful, :failed, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':alert_id', $log_alert_id);
                        $log_stmt->bindParam(':total', $log_total);
                        $log_stmt->bindParam(':successful', $log_successful);
                        $log_stmt->bindParam(':failed', $log_failed);
                        
                        try {
                            $log_stmt->execute();
                        } catch (Exception $e) {
                            // SMS log table might not exist, continue anyway
                        }
                    }
                }
                
                $email_count = sendAlertEmails($db, $alert_id, $title, $message, $alert_type, $severity_level, $affected_barangays);
                if ($email_count > 0) {
                    $success_message .= " Email notifications queued for " . $email_count . " recipients.";
                }
                
                logActivity($_SESSION['user_id'], 'Alert created', 'alerts', $alert_id);
            } else {
                $error_message = "Error creating alert.";
            }
            break;
            
        case 'update_status':
            $alert_id = $_POST['alert_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            $query = "UPDATE alerts SET status = :status, notes = :notes, updated_at = NOW() WHERE id = :alert_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':notes', $notes);
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
                                            case 'high': $severity_class = 'bg-danger'; break;
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
                                <select class="form-select" id="affected_barangays" name="affected_barangays" required>
                                    <option value="">Select Barangay</option>
                                    <option value="All">All</option>
                                    <option value="Adia">Adia</option>
                                    <option value="Balangon">Balangon</option>
                                    <option value="Banyaga">Banyaga</option>
                                    <option value="Bilibinwang">Bilibinwang</option>
                                    <option value="Coral na Munti">Coral na Munti</option>
                                    <option value="Guitna">Guitna</option>
                                    <option value="Mabacong">Mabacong</option>
                                    <option value="Panhulan">Panhulan</option>
                                    <option value="Poblacion">Poblacion</option>
                                    <option value="Pook">Pook</option>
                                    <option value="Pulang Bato">Pulang Bato</option>
                                    <option value="San Jacinto">San Jacinto</option>
                                    <option value="San Teodoro">San Teodoro</option>
                                    <option value="Santa Rosa">Santa Rosa</option>
                                    <option value="Santo Tomas">Santo Tomas</option>
                                    <option value="Subic Ilaya">Subic Ilaya</option>
                                    <option value="Subic Ibaba">Subic Ibaba</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Expiration Date/Time</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_sms" name="send_sms" value="1">
                            <label class="form-check-label" for="send_sms">
                                <i class="bi bi-telephone"></i> Send SMS Notification to Users
                            </label>
                            <small class="d-block text-muted mt-1">
                                <?php echo SMS_ALERT_RECIPIENTS === 'barangay' ? 'SMS will be sent to users in the affected barangay.' : 'SMS will be sent to all registered users.'; ?>
                            </small>
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

<!-- View Alert Modal -->
<div class="modal fade" id="viewAlertModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alert Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertDetails">
                    <!-- Alert details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editAlertFromView()">
                    <i class="bi bi-pencil"></i> Edit Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
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
                    
                    <div class="mb-3">
                        <label for="status_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="status_notes" name="notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
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
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
        e.preventDefault();
        document.getElementById("wrapper").classList.toggle("toggled");
    });

    function viewAlert(alertId) {
        // Show loading state
        document.getElementById('alertDetails').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Show modal
        const viewModal = new bootstrap.Modal(document.getElementById('viewAlertModal'));
        viewModal.show();
        
        // Fetch alert details
        fetch(`get_alert_details.php?id=${alertId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayAlertDetails(data.alert);
                } else {
                    document.getElementById('alertDetails').innerHTML = '<div class="alert alert-danger">Error loading alert details.</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('alertDetails').innerHTML = '<div class="alert alert-danger">Error loading alert details.</div>';
            });
    }

    function displayAlertDetails(alert) {
        const severityColors = {
            'low': 'success',
            'medium': 'warning', 
            'high': 'danger',
            'critical': 'danger'
        };
        
        const statusColors = {
            'active': 'success',
            'resolved': 'primary',
            'expired': 'secondary'
        };
        
        const typeIcons = {
            'flood': 'bi-water',
            'earthquake': 'bi-globe',
            'fire': 'bi-fire',
            'typhoon': 'bi-cloud-rain',
            'landslide': 'bi-mountain',
            'other': 'bi-exclamation-triangle'
        };
        
        const html = `
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-3">${alert.title}</h4>
                    <div class="mb-3">
                        <span class="badge bg-${severityColors[alert.severity_level]} me-2">
                            <i class="bi bi-exclamation-circle"></i> ${alert.severity_level.toUpperCase()} SEVERITY
                        </span>
                        <span class="badge bg-${statusColors[alert.status]} me-2">
                            <i class="bi bi-circle-fill"></i> ${alert.status.toUpperCase()}
                        </span>
                        <span class="badge bg-secondary">
                            <i class="${typeIcons[alert.alert_type] || 'bi-exclamation-triangle'}"></i> ${alert.alert_type.toUpperCase()}
                        </span>
                    </div>
                    <div class="alert alert-light">
                        <h6><i class="bi bi-chat-text"></i> Message:</h6>
                        <p class="mb-0">${alert.message}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-info-circle"></i> Alert Information</h6>
                            <hr>
                            <div class="mb-2">
                                <strong><i class="bi bi-geo-alt"></i> Affected Area:</strong><br>
                                <span class="text-muted">${alert.affected_barangays || 'Municipality-wide'}</span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="bi bi-person"></i> Created By:</strong><br>
                                <span class="text-muted">${alert.created_by_name}</span>
                            </div>
                            <div class="mb-2">
                                <strong><i class="bi bi-calendar"></i> Created:</strong><br>
                                <span class="text-muted">${new Date(alert.created_at).toLocaleString()}</span>
                            </div>
                            ${alert.updated_at ? `
                            <div class="mb-2">
                                <strong><i class="bi bi-clock"></i> Last Updated:</strong><br>
                                <span class="text-muted">${new Date(alert.updated_at).toLocaleString()}</span>
                            </div>
                            ` : ''}
                            ${alert.expires_at ? `
                            <div class="mb-2">
                                <strong><i class="bi bi-hourglass"></i> Expires:</strong><br>
                                <span class="text-muted">${new Date(alert.expires_at).toLocaleString()}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('alertDetails').innerHTML = html;
        
        // Store alert ID for potential editing
        window.currentViewingAlertId = alert.id;
        window.currentViewingAlertStatus = alert.status;
    }

    function editAlertFromView() {
        if (window.currentViewingAlertId) {
            // Hide view modal
            bootstrap.Modal.getInstance(document.getElementById('viewAlertModal')).hide();
            
            // Show update modal after a brief delay
            setTimeout(() => {
                updateStatus(window.currentViewingAlertId, window.currentViewingAlertStatus);
            }, 300);
        }
    }

    function updateStatus(alertId, currentStatus) {
        document.getElementById('update_alert_id').value = alertId;
        document.getElementById('update_status').value = currentStatus;
        
        const updateModal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
        updateModal.show();
    }

    function deleteAlert(alertId) {
        if (confirm('Are you sure you want to delete this alert? This action cannot be undone.')) {
            document.getElementById('actionType').value = 'delete_alert';
            document.getElementById('actionAlertId').value = alertId;
            document.getElementById('actionForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
