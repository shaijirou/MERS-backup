<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Alerts';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$type_filter = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$urgency_filter = isset($_GET['urgency']) ? sanitizeInput($_GET['urgency']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query conditions
$conditions = ["a.status = 'sent'"];
$params = [];

if (!empty($type_filter)) {
    $conditions[] = "a.alert_type = :type_filter";
    $params[':type_filter'] = $type_filter;
}

if (!empty($urgency_filter)) {
    $conditions[] = "a.urgency_level = :urgency_filter";
    $params[':urgency_filter'] = $urgency_filter;
}

if (!empty($search)) {
    $conditions[] = "(a.title LIKE :search OR a.message LIKE :search OR dt.name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Add barangay filter for user
$conditions[] = "(a.affected_barangays IS NULL OR JSON_CONTAINS(a.affected_barangays, :user_barangay))";
$params[':user_barangay'] = json_encode($user['barangay']);

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM alerts a 
                LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get alerts
$query = "SELECT a.*, dt.name as disaster_type_name,
          CASE 
            WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'new'
            WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'recent'
            ELSE 'old'
          END as recency
          FROM alerts a 
          LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
          $where_clause
          ORDER BY a.created_at DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alerts = $stmt->fetchAll();

include '../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="../assets/img/logo.png" alt="Agoncillo Logo" class="me-2" style="height: 40px;">
            <span>MERS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-house-fill me-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="alerts.php"><i class="bi bi-bell-fill me-1"></i> Alerts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <!-- Added My Reports link to navbar -->
                <li class="nav-item">
                    <a class="nav-link" href="my-reports.php"><i class="bi bi-file-earmark-text-fill me-1"></i> My Reports</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                          <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-bell-fill me-2 text-primary"></i>Alerts & Notifications</h2>
            <p class="text-muted mb-0">Stay updated with the latest emergency alerts and advisories for Agoncillo</p>
        </div>
        <div class="d-none d-md-block">
            <span class="badge bg-primary fs-6"><?php echo $total_records; ?> Total Alerts</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search alerts..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Alert Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="emergency" <?php echo $type_filter == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                        <option value="warning" <?php echo $type_filter == 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="advisory" <?php echo $type_filter == 'advisory' ? 'selected' : ''; ?>>Advisory</option>
                        <option value="info" <?php echo $type_filter == 'info' ? 'selected' : ''; ?>>Information</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="urgency" class="form-label">Urgency Level</label>
                    <select class="form-select" id="urgency" name="urgency">
                        <option value="">All Levels</option>
                        <option value="critical" <?php echo $urgency_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high" <?php echo $urgency_filter == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $urgency_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $urgency_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="alerts.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Emergency Alerts Summary -->
    <?php
    $emergency_count = 0;
    $warning_count = 0;
    foreach ($alerts as $alert) {
        if ($alert['alert_type'] == 'emergency' && $alert['recency'] == 'new') $emergency_count++;
        if ($alert['alert_type'] == 'warning' && $alert['recency'] == 'new') $warning_count++;
    }
    if ($emergency_count > 0 || $warning_count > 0):
    ?>
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
        <div class="flex-grow-1">
            <strong>Active Alerts:</strong>
            <?php if ($emergency_count > 0): ?>
                <?php echo $emergency_count; ?> Emergency Alert<?php echo $emergency_count > 1 ? 's' : ''; ?>
            <?php endif; ?>
            <?php if ($warning_count > 0): ?>
                <?php echo $emergency_count > 0 ? ' • ' : ''; ?><?php echo $warning_count; ?> Warning<?php echo $warning_count > 1 ? 's' : ''; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alerts List -->
    <div class="row">
        <?php if (!empty($alerts)): ?>
            <?php foreach ($alerts as $alert): ?>
            <div class="col-12 mb-3">
                <div class="card shadow-sm alert-card alert-<?php echo $alert['alert_type']; ?> h-100 <?php echo $alert['recency'] == 'new' ? 'border-warning' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center flex-wrap">
                                <span class="badge bg-<?php echo $alert['alert_type'] == 'emergency' ? 'danger' : ($alert['alert_type'] == 'warning' ? 'warning text-dark' : ($alert['alert_type'] == 'advisory' ? 'info text-dark' : 'secondary')); ?> me-2">
                                    <?php echo strtoupper($alert['alert_type']); ?>
                                </span>
                                <span class="badge bg-<?php echo $alert['urgency_level'] == 'critical' ? 'danger' : ($alert['urgency_level'] == 'high' ? 'warning text-dark' : ($alert['urgency_level'] == 'medium' ? 'info text-dark' : 'secondary')); ?> me-2">
                                    <?php echo strtoupper($alert['urgency_level']); ?>
                                </span>
                                <?php if ($alert['recency'] == 'new'): ?>
                                <span class="badge bg-success">NEW</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo timeAgo($alert['created_at']); ?></small>
                        </div>
                        
                        <h5 class="card-title"><?php echo $alert['title']; ?></h5>
                        <p class="card-text"><?php echo nl2br(substr($alert['message'], 0, 200)) . (strlen($alert['message']) > 200 ? '...' : ''); ?></p>
                        
                        <div class="row text-sm mb-3">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="bi bi-tag-fill me-1"></i>
                                    Type: <?php echo $alert['disaster_type_name'] ?: 'General'; ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="bi bi-clock-fill me-1"></i>
                                    <?php echo formatDateTime($alert['created_at']); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="alert-details.php?id=<?php echo $alert['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye-fill me-1"></i>View Details
                            </a>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm share-btn" 
                                        data-title="<?php echo htmlspecialchars($alert['title']); ?>"
                                        data-url="<?php echo BASE_URL; ?>user/alert-details.php?id=<?php echo $alert['id']; ?>">
                                    <i class="bi bi-share-fill"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm bookmark-btn" 
                                        data-alert-id="<?php echo $alert['id']; ?>">
                                    <i class="bi bi-bookmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-bell-slash display-1 text-muted mb-3"></i>
                        <h4 class="text-muted">No Alerts Found</h4>
                        <p class="text-muted">There are no alerts matching your current filters.</p>
                        <a href="alerts.php" class="btn btn-primary">View All Alerts</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav aria-label="Alerts pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo $type_filter; ?>&urgency=<?php echo $urgency_filter; ?>&search=<?php echo urlencode($search); ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type_filter; ?>&urgency=<?php echo $urgency_filter; ?>&search=<?php echo urlencode($search); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo $type_filter; ?>&urgency=<?php echo $urgency_filter; ?>&search=<?php echo urlencode($search); ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="shareOnFacebook()">
                        <i class="bi bi-facebook me-2"></i>Share on Facebook
                    </button>
                    <button class="btn btn-info" onclick="shareOnTwitter()">
                        <i class="bi bi-twitter me-2"></i>Share on Twitter
                    </button>
                    <button class="btn btn-success" onclick="shareOnWhatsApp()">
                        <i class="bi bi-whatsapp me-2"></i>Share on WhatsApp
                    </button>
                    <button class="btn btn-secondary" onclick="copyShareLink()">
                        <i class="bi bi-clipboard me-2"></i>Copy Link
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let shareData = {};

// Share functionality
document.querySelectorAll('.share-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        shareData = {
            title: this.dataset.title,
            url: this.dataset.url
        };
        new bootstrap.Modal(document.getElementById('shareModal')).show();
    });
});

function shareOnFacebook() {
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareData.url)}`, '_blank');
}

function shareOnTwitter() {
    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(shareData.title)}&url=${encodeURIComponent(shareData.url)}`, '_blank');
}

function shareOnWhatsApp() {
    window.open(`https://wa.me/?text=${encodeURIComponent(shareData.title + ' ' + shareData.url)}`, '_blank');
}

function copyShareLink() {
    navigator.clipboard.writeText(shareData.url).then(() => {
        alert('Link copied to clipboard!');
        bootstrap.Modal.getInstance(document.getElementById('shareModal')).hide();
    });
}

// Bookmark functionality
document.querySelectorAll('.bookmark-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const alertId = this.dataset.alertId;
        // This would typically make an AJAX call to bookmark the alert
        this.innerHTML = '<i class="bi bi-bookmark-fill"></i>';
        this.classList.add('text-warning');
        AgoncilloAlert.showInAppNotification('Bookmarked', 'Alert has been bookmarked', 'success');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
