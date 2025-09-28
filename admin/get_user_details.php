<?php
require_once '../config/config.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "User ID is required";
    exit;
}

$user_id = $_GET['id'];

// Get user details with verification info and report count
$query = "SELECT u.*, 
                 admin.first_name AS verified_by_name, 
                 admin.last_name AS verified_by_lastname,
                 (SELECT COUNT(*) FROM incident_reports WHERE user_id = u.id) AS report_count,
                 (SELECT COUNT(*) FROM incident_reports WHERE user_id = u.id AND status = 'resolved') AS resolved_reports
          FROM users u
          LEFT JOIN users AS admin ON u.verified_by = admin.id
          WHERE u.id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo "User not found";
    exit;
}

// Get recent reports by this user
$reports_query = "SELECT ir.* 
                  FROM incident_reports ir
                  WHERE ir.user_id = :user_id 
                  ORDER BY ir.created_at DESC 
                  LIMIT 5";
$reports_stmt = $db->prepare($reports_query);
$reports_stmt->bindParam(':user_id', $user_id);
$reports_stmt->execute();
$recent_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-person"></i> Personal Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Full Name</label>
                        <p class="mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Email</label>
                        <p class="mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Phone</label>
                        <p class="mb-2"><?php echo htmlspecialchars($user['phone']); ?></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Barangay</label>
                        <p class="mb-2"><?php echo htmlspecialchars($user['barangay']); ?></p>
                    </div>
                    <div class="col-12">
    <label class="form-label fw-bold">Complete Address</label>
    <p class="mb-2">
        <?php 
            if (!empty($user['house_number']) || !empty($user['street']) || !empty($user['landmark'])) {
                echo htmlspecialchars($user['house_number'] . ' ' . $user['street']);
                if (!empty($user['landmark'])) {
                    echo ' (Near ' . htmlspecialchars($user['landmark']) . ')';
                }
            } else {
                echo '<em class="text-muted">Not specified</em>';
            }
        ?>
    </p>
</div>
                </div>
            </div>
        </div>
    </div>

     
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-shield-check"></i> Status & Verification</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Verification Status</label>
                        <p class="mb-2">
                            <?php if ($user['verification_status'] === 'verified'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="bi bi-clock"></i> Pending</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($user['verification_status'] === 'verified' && $user['verified_by_name']): ?>
                    <div class="col-12">
                        <label class="form-label fw-bold">Verified By</label>
                        <p class="mb-2"><?php echo htmlspecialchars($user['verified_by_name'] . ' ' . $user['verified_by_lastname']); ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label fw-bold">User Type</label>
                        <p class="mb-2">
                            <span class="badge bg-info"><?php echo ucfirst($user['user_type']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-bar-chart"></i> Activity Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-primary mb-1"><?php echo $user['report_count']; ?></h4>
                            <small class="text-muted">Total Reports</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <h4 class="text-success mb-1"><?php echo $user['resolved_reports']; ?></h4>
                            <small class="text-muted">Resolved Reports</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Member Since</label>
                        <p class="mb-2"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        <small class="text-muted"><?php echo timeAgo($user['created_at']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

      
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-file-text"></i> Recent Reports</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_reports)): ?>
                    <p class="text-muted mb-0">No reports submitted yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_reports as $report): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($report['incident_type_name'] ?? 'Unknown Type'); ?></h6>
                                        <p class="mb-1 small"><?php echo htmlspecialchars(substr($report['description'], 0, 50)) . '...'; ?></p>
                                        <small class="text-muted"><?php echo timeAgo($report['created_at']); ?></small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $report['status'] === 'resolved' ? 'success' : 
                                            ($report['status'] === 'in_progress' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
