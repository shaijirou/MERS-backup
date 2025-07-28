<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Alert Details';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get alert ID from URL
$alert_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$alert_id) {
    header('Location: alerts.php');
    exit();
}

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Get alert details
$query = "SELECT a.*, dt.name as disaster_type_name, dt.description as disaster_type_description,
          CASE 
            WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'new'
            WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'recent'
            ELSE 'old'
          END as recency
          FROM alerts a 
          LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
          WHERE a.id = :alert_id AND a.status = 'sent'";

$stmt = $db->prepare($query);
$stmt->bindParam(':alert_id', $alert_id);
$stmt->execute();
$alert = $stmt->fetch();

if (!$alert) {
    header('Location: alerts.php?error=alert_not_found');
    exit();
}

// Check if user is in affected barangays
$affected_barangays = json_decode($alert['affected_barangays'] ?: '[]', true);
$user_affected = empty($affected_barangays) || in_array($user['barangay'], $affected_barangays);

// Get related alerts
$query = "SELECT a.*, dt.name as disaster_type_name 
          FROM alerts a 
          LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
          WHERE a.id != :alert_id 
          AND a.status = 'sent'
          AND (a.disaster_type_id = :disaster_type_id OR a.alert_type = :alert_type)
          ORDER BY a.created_at DESC 
          LIMIT 5";

$stmt = $db->prepare($query);
$stmt->bindParam(':alert_id', $alert_id);
$stmt->bindParam(':disaster_type_id', $alert['disaster_type_id']);
$stmt->bindParam(':alert_type', $alert['alert_type']);
$stmt->execute();
$related_alerts = $stmt->fetchAll();

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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <img src="../<?php echo $user['profile_picture'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="alerts.php">Alerts</a></li>
            <li class="breadcrumb-item active" aria-current="page">Alert Details</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Main Alert Content -->
        <div class="col-lg-8">
            <!-- Alert Header -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center flex-wrap">
                            <span class="badge bg-<?php echo $alert['alert_type'] == 'emergency' ? 'danger' : ($alert['alert_type'] == 'warning' ? 'warning text-dark' : ($alert['alert_type'] == 'advisory' ? 'info text-dark' : 'secondary')); ?> me-2 fs-6">
                                <?php echo strtoupper($alert['alert_type']); ?>
                            </span>
                            <span class="badge bg-<?php echo $alert['urgency_level'] == 'critical' ? 'danger' : ($alert['urgency_level'] == 'high' ? 'warning text-dark' : ($alert['urgency_level'] == 'medium' ? 'info text-dark' : 'secondary')); ?> me-2 fs-6">
                                <?php echo strtoupper($alert['urgency_level']); ?>
                            </span>
                            <?php if ($alert['recency'] == 'new'): ?>
                            <span class="badge bg-success fs-6">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="shareAlert()">
                                <i class="bi bi-share-fill"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="bookmarkAlert()">
                                <i class="bi bi-bookmark"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="printAlert()">
                                <i class="bi bi-printer"></i>
                            </button>
                        </div>
                    </div>
                    
                    <h1 class="display-6 mb-3"><?php echo $alert['title']; ?></h1>
                    
                    <div class="row text-muted mb-3">
                        <div class="col-md-6">
                            <small>
                                <i class="bi bi-calendar-fill me-1"></i>
                                <?php echo formatDateTime($alert['created_at']); ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small>
                                <i class="bi bi-tag-fill me-1"></i>
                                <?php echo $alert['disaster_type_name'] ?: 'General Alert'; ?>
                            </small>
                        </div>
                    </div>

                    <?php if ($user_affected): ?>
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-geo-alt-fill fs-4 me-3"></i>
                        <div>
                            <strong>This alert affects your area (<?php echo $user['barangay']; ?>)</strong>
                            <p class="mb-0">Please pay close attention to the instructions below.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alert Content -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-text-fill me-2"></i>Alert Message
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert-content">
                        <?php echo nl2br($alert['message']); ?>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <?php if (!empty($alert['instructions'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check me-2"></i>Instructions & Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="instructions-content">
                        <?php echo nl2br($alert['instructions']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Affected Areas -->
            <?php if (!empty($affected_barangays)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-geo-alt-fill me-2"></i>Affected Areas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($affected_barangays as $barangay): ?>
                        <div class="col-md-4 mb-2">
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-geo-alt me-1"></i><?php echo $barangay; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Emergency Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Emergency Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="tel:+639123456789" class="btn btn-danger">
                                    <i class="bi bi-telephone-fill me-2"></i>Call MDRRMO
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="report.php" class="btn btn-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Report Incident
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="map.php" class="btn btn-info">
                                    <i class="bi bi-map-fill me-2"></i>View Evacuation Map
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-grid">
                                <a href="contacts.php" class="btn btn-secondary">
                                    <i class="bi bi-telephone-fill me-2"></i>Emergency Contacts
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Alert Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i>Alert Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Alert ID:</strong>
                        <span class="text-muted">#<?php echo str_pad($alert['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Type:</strong>
                        <span class="badge bg-<?php echo $alert['alert_type'] == 'emergency' ? 'danger' : ($alert['alert_type'] == 'warning' ? 'warning text-dark' : ($alert['alert_type'] == 'advisory' ? 'info text-dark' : 'secondary')); ?>">
                            <?php echo ucfirst($alert['alert_type']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Urgency:</strong>
                        <span class="badge bg-<?php echo $alert['urgency_level'] == 'critical' ? 'danger' : ($alert['urgency_level'] == 'high' ? 'warning text-dark' : ($alert['urgency_level'] == 'medium' ? 'info text-dark' : 'secondary')); ?>">
                            <?php echo ucfirst($alert['urgency_level']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Disaster Type:</strong>
                        <span class="text-muted"><?php echo $alert['disaster_type_name'] ?: 'General'; ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Issued:</strong>
                        <span class="text-muted"><?php echo formatDateTime($alert['created_at']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Time Elapsed:</strong>
                        <span class="text-muted"><?php echo timeAgo($alert['created_at']); ?></span>
                    </div>
                    <?php if ($alert['expires_at']): ?>
                    <div class="mb-3">
                        <strong>Expires:</strong>
                        <span class="text-muted"><?php echo formatDateTime($alert['expires_at']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Weather Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cloud-sun-fill me-2"></i>Current Weather
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 class="mb-0">32°C</h3>
                            <p class="text-muted mb-0">Partly Cloudy</p>
                        </div>
                        <i class="bi bi-cloud-sun display-4 text-warning"></i>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Humidity</small>
                            <strong>68%</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Wind</small>
                            <strong>12 km/h</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Rain</small>
                            <strong>20%</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Alerts -->
            <?php if (!empty($related_alerts)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bell-fill me-2"></i>Related Alerts
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($related_alerts as $related): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-<?php echo $related['alert_type'] == 'emergency' ? 'danger' : ($related['alert_type'] == 'warning' ? 'warning text-dark' : ($related['alert_type'] == 'advisory' ? 'info text-dark' : 'secondary')); ?> me-2">
                                <?php echo strtoupper($related['alert_type']); ?>
                            </span>
                            <small class="text-muted"><?php echo timeAgo($related['created_at']); ?></small>
                        </div>
                        <h6 class="mb-1">
                            <a href="alert-details.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                <?php echo substr($related['title'], 0, 50) . (strlen($related['title']) > 50 ? '...' : ''); ?>
                            </a>
                        </h6>
                        <small class="text-muted">
                            <i class="bi bi-tag me-1"></i><?php echo $related['disaster_type_name'] ?: 'General'; ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Safety Tips -->
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightbulb-fill me-2"></i>Safety Tips
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Stay calm and follow official instructions
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Keep emergency supplies ready
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Monitor official communication channels
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Have evacuation routes planned
                        </li>
                        <li class="mb-0">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Keep important documents accessible
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function shareAlert() {
    const shareData = {
        title: '<?php echo addslashes($alert['title']); ?>',
        text: '<?php echo addslashes(substr($alert['message'], 0, 100)); ?>...',
        url: window.location.href
    };
    
    if (navigator.share) {
        navigator.share(shareData);
    } else {
        navigator.clipboard.writeText(`${shareData.title}\n${shareData.text}\n${shareData.url}`).then(() => {
            AgoncilloAlert.showInAppNotification('Link Copied', 'Alert details copied to clipboard', 'success');
        });
    }
}

function bookmarkAlert() {
    // This would typically save to user's bookmarks
    AgoncilloAlert.showInAppNotification('Bookmarked', 'Alert has been bookmarked', 'success');
}

function printAlert() {
    window.print();
}

// Auto-refresh for critical alerts
<?php if ($alert['urgency_level'] == 'critical'): ?>
setInterval(function() {
    // Check for updates to this alert
    fetch(`../api/check_alert_updates.php?id=<?php echo $alert_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.updated) {
                location.reload();
            }
        })
        .catch(error => console.log('Update check failed:', error));
}, 30000); // Check every 30 seconds
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
