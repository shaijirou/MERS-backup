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
<div class="container py-4">
    <!-- Profile Header -->
    <div class="card mb-4 shadow-sm border-0 rounded-3">
        <div class="card-body text-center">
            <img src="<?php echo !empty($user['selfie_photo']) ? htmlspecialchars('../' . $user['selfie_photo']) : 'assets/img/default-avatar.png'; ?>" 
                 alt="Profile Photo" 
                 class="rounded-circle shadow-sm mb-3" 
                 style="width: 250px; height: 250px; object-fit: cover; border: 4px solid #dee2e6;">
            <h4 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
            <p class="text-muted mb-0"><?php echo ucfirst($user['user_type']); ?></p>
            <small class="text-muted">Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></small>
        </div>
    </div>

    <div class="row g-4">
        <!-- Personal Information -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="bi bi-person-fill me-2"></i>
                    <h6 class="mb-0">Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Full Name:</strong><br>
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                    <div class="mb-2">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div class="mb-2">
                        <strong>Phone:</strong><br>
                        <?php echo htmlspecialchars($user['phone']); ?>
                    </div>
                    <div class="mb-2">
                        <strong>Barangay:</strong><br>
                        <?php echo htmlspecialchars($user['barangay']); ?>
                    </div>
                    <div>
                        <strong>Complete Address:</strong><br>
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
                        <?php echo htmlspecialchars($user['barangay']); ?> Agoncillo, Batangas City, Philippines
                    </div>
                </div>
            </div>
        </div>

        <!-- Uploaded Documents -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-secondary text-white d-flex align-items-center">
                    <i class="bi bi-card-text me-2"></i>
                    <h6 class="mb-0">ID Document</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($user['id_document'])): ?>
                        <img src="<?php echo htmlspecialchars('../' . $user['id_document']); ?>" 
                             alt="ID Document" 
                             class="img-fluid rounded shadow-sm" 
                             style="max-height: 250px; object-fit: contain;">
                    <?php else: ?>
                        <p class="text-muted"><i class="bi bi-image"></i><br>No ID document uploaded</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Verification & Status -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class="bi bi-shield-check me-2"></i>
                    <h6 class="mb-0">Verification & Status</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>Verification Status:</strong><br>
                        <?php if ($user['verification_status'] === 'verified'): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Verified</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Pending</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['verification_status'] === 'verified' && $user['verified_by_name']): ?>
                        <div class="mb-2">
                            <strong>Verified By:</strong><br>
                            <?php echo htmlspecialchars($user['verified_by_name'] . ' ' . $user['verified_by_lastname']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <strong>User Type:</strong><br>
                        <span class="badge bg-info"><?php echo ucfirst($user['user_type']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        

        <!-- Activity Stats -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="bi bi-bar-chart me-2"></i>
                    <h6 class="mb-0">Activity Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <h4 class="text-primary mb-0"><?php echo $user['report_count']; ?></h4>
                            <small class="text-muted">Total Reports</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success mb-0"><?php echo $user['resolved_reports']; ?></h4>
                            <small class="text-muted">Resolved Reports</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-dark text-white d-flex align-items-center">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <h6 class="mb-0">Recent Reports</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_reports)): ?>
                        <p class="text-muted mb-0">No reports submitted yet.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_reports as $report): ?>
                                <div class="list-group-item border-0 px-0 py-2 d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($report['incident_type_name'] ?? 'Unknown Type'); ?></h6>
                                        <p class="mb-1 small text-muted"><?php echo htmlspecialchars(substr($report['description'], 0, 50)) . '...'; ?></p>
                                        <small class="text-muted"><?php echo timeAgo($report['created_at']); ?></small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        echo $report['status'] === 'resolved' ? 'success' : 
                                            ($report['status'] === 'in_progress' ? 'warning text-dark' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
