<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'User Dashboard';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Get recent alerts
$query = "SELECT a.*, dt.name as disaster_type_name 
          FROM alerts a 
          LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
          WHERE a.status = 'sent' 
          AND (a.affected_barangays IS NULL OR JSON_CONTAINS(a.affected_barangays, :barangay))
          ORDER BY a.created_at DESC 
          LIMIT 3";
$stmt = $db->prepare($query);
$barangay_json = json_encode($user['barangay']);
$stmt->bindParam(':barangay', $barangay_json, PDO::PARAM_STR);
$stmt->execute();
$recent_alerts = $stmt->fetchAll();

// Get nearest evacuation centers
$query = "SELECT ec.*, b.name as barangay_name 
          FROM evacuation_centers ec 
          JOIN barangays b ON ec.barangay_id = b.id 
          WHERE ec.status = 'active' 
          ORDER BY ec.capacity DESC 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->execute();
$evacuation_centers = $stmt->fetchAll();

// Get recent incident reports from community
$query = "SELECT ir.*, u.first_name, u.last_name 
          FROM incident_reports ir 
          JOIN users u ON ir.user_id = u.id 
          ORDER BY ir.created_at DESC 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->execute();
$community_reports = $stmt->fetchAll();

include '../includes/header.php';
?>

<!-- Add Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
                    <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-fill me-1"></i> Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="alerts.php"><i class="bi bi-bell-fill me-1"></i> Alerts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        <!-- <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li> -->
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Emergency Alert Banner -->
    <?php if (!empty($recent_alerts) && $recent_alerts[0]['alert_type'] == 'emergency'): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
        <div>
            <strong>EMERGENCY ALERT:</strong> <?php echo $recent_alerts[0]['title']; ?>
            <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title">Welcome, <?php echo $user['first_name']; ?>!</h4>
                    <p class="card-text">Stay informed about emergencies and disasters in Agoncillo. Use this dashboard to receive alerts, view evacuation routes, and report incidents in your area.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="map.php" class="btn btn-primary"><i class="bi bi-map me-1"></i> View Evacuation Map</a>
                        <a href="report.php" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i> Report an Incident</a>
                        <a href="contacts.php" class="btn btn-success"><i class="bi bi-telephone me-1"></i> Emergency Contacts</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-info-circle-fill me-2 text-primary"></i>Current Status</h5>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Weather:</span>
                        <span class="badge bg-warning text-dark">Heavy Rain</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Flood Risk:</span>
                        <span class="badge bg-danger">High</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Volcanic Activity:</span>
                        <span class="badge bg-success">Normal</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Earthquake Alert:</span>
                        <span class="badge bg-success">None</span>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted">Last updated: <?php echo date('M j, Y g:i A'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Alerts -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Alerts</h5>
                    <a href="alerts.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($recent_alerts)): ?>
                            <?php foreach ($recent_alerts as $alert): ?>
                            <a href="alert-details.php?id=<?php echo $alert['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <span class="badge bg-<?php echo $alert['alert_type'] == 'emergency' ? 'danger' : ($alert['alert_type'] == 'warning' ? 'warning text-dark' : 'info text-dark'); ?> me-2">
                                            <?php echo ucfirst($alert['alert_type']); ?>
                                        </span> 
                                        <?php echo $alert['title']; ?>
                                    </h6>
                                    <small><?php echo timeAgo($alert['created_at']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo substr($alert['message'], 0, 150) . '...'; ?></p>
                                <small class="text-muted">Type: <?php echo $alert['disaster_type_name'] ?: 'General'; ?></small>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center py-4">
                                <p class="text-muted mb-0">No recent alerts</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map and Evacuation Centers -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-map-fill me-2 text-primary"></i>Evacuation Map
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="map.php" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrows-fullscreen me-1"></i>Full Map
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="dashboard-map" style="height: 350px; width: 100%;"></div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Showing <?php echo count($evacuation_centers); ?> evacuation centers
                        </small>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success">
                                <i class="bi bi-shield-check me-1"></i>Active Centers
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Nearest Evacuation Centers</h5>
                </div>
                <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                    <?php foreach ($evacuation_centers as $index => $center): ?>
                    <div class="evacuation-center-item p-3 border-bottom cursor-pointer" 
                         onclick="dashboardMap.focusOnCenter(<?php echo $index; ?>)"
                         data-bs-toggle="tooltip" 
                         title="Click to view on map">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($center['name']); ?></h6>
                                <small class="text-muted d-block mb-1">
                                    <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($center['barangay_name']); ?>, Agoncillo
                                </small>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-people me-1"></i><?php echo $center['capacity']; ?>
                                    </span>
                                    <span class="badge bg-success rounded-pill">
                                        <i class="bi bi-check-circle me-1"></i>Active
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-md btn-solid-primary" onclick="getDirections(<?php echo $center['latitude']; ?>, <?php echo $center['longitude']; ?>)">
                                    <i class="bi bi-geo-alt me-1 text-primary" style="font-size: 1.5rem;"></i>
                                </button>
                                <p style="font-size: 0.575rem;">Get Directions</p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="map.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-map me-1"></i>View All Centers
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Contacts and Safety Tips -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Emergency Contacts</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger text-white rounded-circle p-2 me-3">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">MDRRMO Agoncillo</h6>
                            <p class="mb-0"><a href="tel:+639123456789" class="text-decoration-none">+63 912 345 6789</a></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle p-2 me-3">
                            <i class="bi bi-hospital-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Agoncillo Health Center</h6>
                            <p class="mb-0"><a href="tel:+639123456790" class="text-decoration-none">+63 912 345 6790</a></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info text-white rounded-circle p-2 me-3">
                            <i class="bi bi-shield-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Agoncillo Police Station</h6>
                            <p class="mb-0"><a href="tel:+639123456791" class="text-decoration-none">+63 912 345 6791</a></p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-warning text-dark rounded-circle p-2 me-3">
                            <i class="bi bi-fire"></i>
                        </div>
                        <div>
                            <h6 class="mb-0">Fire Department</h6>
                            <p class="mb-0"><a href="tel:+639123456792" class="text-decoration-none">+63 912 345 6792</a></p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="contacts.php" class="btn btn-sm btn-outline-primary">View All Contacts</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Safety Tips</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="safetyTipsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="floodHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#floodCollapse">
                                    <i class="bi bi-water me-2 text-primary"></i> Flood Safety
                                </button>
                            </h2>
                            <div id="floodCollapse" class="accordion-collapse collapse" aria-labelledby="floodHeading" data-bs-parent="#safetyTipsAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Move to higher ground immediately</li>
                                        <li>Do not walk or drive through flood waters</li>
                                        <li>Stay away from power lines and electrical wires</li>
                                        <li>Prepare an emergency kit with essentials</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="volcanicHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#volcanicCollapse">
                                    <i class="bi bi-cloud-haze2 me-2 text-danger"></i> Volcanic Eruption Safety
                                </button>
                            </h2>
                            <div id="volcanicCollapse" class="accordion-collapse collapse" aria-labelledby="volcanicHeading" data-bs-parent="#safetyTipsAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Follow evacuation orders immediately</li>
                                        <li>Wear long-sleeved shirts and pants</li>
                                        <li>Use a dust mask or damp cloth over face</li>
                                        <li>Clear roofs of ash to prevent collapse</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="earthquakeHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#earthquakeCollapse">
                                    <i class="bi bi-buildings me-2 text-warning"></i> Earthquake Safety
                                </button>
                            </h2>
                            <div id="earthquakeCollapse" class="accordion-collapse collapse" aria-labelledby="earthquakeHeading" data-bs-parent="#safetyTipsAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Drop, cover, and hold on</li>
                                        <li>Stay away from windows and exterior walls</li>
                                        <li>If outdoors, find a clear spot away from buildings</li>
                                        <li>After shaking stops, check for injuries and damage</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="typhoonHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#typhoonCollapse">
                                    <i class="bi bi-hurricane me-2 text-info"></i> Typhoon Safety
                                </button>
                            </h2>
                            <div id="typhoonCollapse" class="accordion-collapse collapse" aria-labelledby="typhoonHeading" data-bs-parent="#safetyTipsAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li>Secure your home and property</li>
                                        <li>Prepare emergency supplies for at least 3 days</li>
                                        <li>Stay indoors during the storm</li>
                                        <li>Listen to official advisories and alerts</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="safety.php" class="btn btn-sm btn-outline-primary">View All Safety Tips</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Incident Reports -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Community Incident Reports</h5>
                    <a href="report.php" class="btn btn-sm btn-danger">Report an Incident</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Incident Type</th>
                                    <th>Location</th>
                                    <th>Reported By</th>
                                    <th>Approval Status</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($community_reports)): ?>
                                    <?php foreach ($community_reports as $report): ?>
                                    <tr>
                                        <td><?php echo ucfirst($report['incident_type']); ?></td>
                                        <td><?php echo htmlspecialchars($report['location']); ?></td>
                                        <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>

                                        <?php
                                            // Approval status: default to 'pending' if missing/empty
                                            $as = (isset($report['approval_status']) && trim($report['approval_status']) !== '') ? strtolower($report['approval_status']) : 'pending';

                                            // Map approval status to bootstrap badge classes
                                            // Use a more visible color for 'pending'
                                            $approvalBadgeClass = $as === 'approved' ? 'bg-success' :
                                                                  ($as === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');

                                            $approvalLabel = ucfirst($as);
                                        ?>
                                        <td>
                                            <span class="badge <?php echo $approvalBadgeClass; ?>">
                                                <?php echo $approvalLabel; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="badge <?php
                                                // Default to 'pending' if response_status is missing or empty
                                                $rs = (isset($report['response_status']) && trim($report['response_status']) !== '') ? $report['response_status'] : 'pending';
                                                echo $rs == 'resolved' ? 'bg-success' :
                                                     ($rs == 'on_scene' ? 'bg-warning text-dark' :
                                                     ($rs == 'responding' ? 'bg-info text-dark' :
                                                     ($rs == 'pending' ? 'bg-warning text-dark' :
                                                     ($rs == 'notified' ? 'bg-secondary' : 'bg-danger'))));
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $rs)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDateTime($report['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <p class="text-muted mb-0">No recent incident reports</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap & Icons (add inside <head>) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<footer class="bg-dark text-white py-4 mt-auto">
    <div class="container">
        <div class="row">
            <!-- About / App Name -->
            <div class="col-md-4 mb-3 mb-md-0">
                <h5><?php echo APP_NAME ?? "Mobile Emergency Response System"; ?></h5>
                <p class="text-white-50">
                    A project of the Municipality of Agoncillo, Batangas to keep residents safe during emergencies and disasters.
                </p>
            </div>

            <!-- Quick Links -->
            <div class="col-md-2 mb-3 mb-md-0">
                <h6>Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="dashboard.php" class="text-decoration-none text-white-50">Home</a></li>
                    <li><a href="alerts.php" class="text-decoration-none text-white-50">Alerts</a></li>
                    <li><a href="map.php" class="text-decoration-none text-white-50">Evacuation Map</a></li>
                    <li><a href="report.php" class="text-decoration-none text-white-50">Report Incident</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-md-3 mb-3 mb-md-0">
                <h6>Contact Information</h6>
                <ul class="list-unstyled text-white-50">
                    <li><i class="bi bi-geo-alt-fill me-2"></i>Municipal Hall, Poblacion, Agoncillo, Batangas</li>
                    <li><i class="bi bi-telephone-fill me-2"></i>(043) 123-4567</li>
                    <li><i class="bi bi-envelope-fill me-2"></i>mdrrmo@agoncillo.gov.ph</li>
                </ul>
            </div>

            <!-- Socials + Download -->
            <div class="col-md-3">
                <h6>Connect With Us</h6>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-youtube"></i></a>
                </div>
                <div class="mt-3">
                    <a href="https://a3.files.diawi.com/app-file/78LluDJh3kGY6g7H7M4U.apk" class="btn btn-sm btn-primary">
                        <i class="bi bi-download me-1"></i> Download Mobile App
                    </a>
                </div>
            </div>
        </div>
        <hr class="my-3">
        <div class="text-center text-white-50">
            <small>&copy; 2025 Municipality of Agoncillo. All rights reserved.</small>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Dashboard Map Implementation
const dashboardMap = {
    map: null,
    evacuationCenters: <?php echo json_encode($evacuation_centers); ?>,
    evacuationMarkers: [],
    centersVisible: true,

    init: function() {
        console.log('Initializing dashboard map...');
        console.log('Evacuation centers:', this.evacuationCenters);
        
        // Initialize the map centered on Agoncillo
        this.map = L.map('dashboard-map').setView([13.9333, 120.9333], 12);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Add evacuation center markers
        this.addEvacuationCenters();

        // Disable scroll wheel zoom for better dashboard experience
        this.map.scrollWheelZoom.disable();
        
        console.log('Dashboard map initialized successfully');
    },

    addEvacuationCenters: function() {
        console.log('Adding evacuation centers to map...');
        
        this.evacuationCenters.forEach((center, index) => {
            // Generate coordinates around Agoncillo if not provided
            const lat = parseFloat(center.latitude) || (13.7565 + (Math.random() - 0.5) * 0.02);
            const lng = parseFloat(center.longitude) || (120.9445 + (Math.random() - 0.5) * 0.02);

            console.log(`Adding center ${index}: ${center.name} at [${lat}, ${lng}]`);

            // Create custom icon for evacuation centers
            const evacuationIcon = L.divIcon({
                html: '<i class="bi bi-shield-check-fill text-success" style="font-size: 24px; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);"></i>',
                iconSize: [24, 24],
                iconAnchor: [12, 12],
                popupAnchor: [0, -12],
                className: 'custom-div-icon'
            });

            const marker = L.marker([lat, lng], { icon: evacuationIcon })
                .bindPopup(`
                    <div class="text-center" style="min-width: 200px;">
                        <h6 class="mb-2 text-primary">${center.name}</h6>
                        <p class="mb-1 small"><i class="bi bi-geo-alt me-1"></i>${center.barangay_name}, Agoncillo</p>
                        <p class="mb-2 small"><i class="bi bi-people me-1"></i>Capacity: ${center.capacity} people</p>
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-primary" onclick="dashboardMap.getDirections(${index})">
                                <i class="bi bi-navigation me-1"></i>Directions
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="dashboardMap.focusOnCenter(${index})">
                                <i class="bi bi-zoom-in me-1"></i>Focus
                            </button>
                        </div>
                    </div>
                `)
                .addTo(this.map);

            // Store marker with its index for easy reference
            this.evacuationMarkers.push({ 
                marker: marker, 
                index: index, 
                center: center,
                lat: lat,
                lng: lng
            });
        });
        
        console.log(`Added ${this.evacuationMarkers.length} evacuation center markers`);
    },

    focusOnCenter: function(centerIndex) {
        console.log(`Focusing on center index: ${centerIndex}`);
        
        if (centerIndex < 0 || centerIndex >= this.evacuationMarkers.length) {
            console.error('Invalid center index:', centerIndex);
            this.showNotification('Error', 'Invalid evacuation center selected', 'error');
            return;
        }

        const markerItem = this.evacuationMarkers[centerIndex];
        const center = markerItem.center;
        
        console.log(`Focusing on: ${center.name} at [${markerItem.lat}, ${markerItem.lng}]`);

        // Ensure centers are visible
        if (!this.centersVisible) {
            this.toggleEvacuationCenters();
        }

        // Center map on the selected evacuation center
        this.map.setView([markerItem.lat, markerItem.lng], 15, {
            animate: true,
            duration: 1
        });
        
        // Open the popup for this center
        setTimeout(() => {
            markerItem.marker.openPopup();
        }, 500);

        // Highlight the selected center in the sidebar
        this.highlightCenterInSidebar(centerIndex);

        // Show notification
        this.showNotification('Map Focused', `Centered on ${center.name}`, 'success');
    },

    highlightCenterInSidebar: function(centerIndex) {
        // Remove previous highlights
        document.querySelectorAll('.evacuation-center-item').forEach(item => {
            item.classList.remove('bg-light', 'border-primary');
        });

        // Highlight the selected center
        const centerItems = document.querySelectorAll('.evacuation-center-item');
        if (centerItems[centerIndex]) {
            centerItems[centerIndex].classList.add('bg-light', 'border-primary');
            centerItems[centerIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    },

    getDirections: function(centerIndex) {
        console.log(`Getting directions to center index: ${centerIndex}`);
        
        if (centerIndex < 0 || centerIndex >= this.evacuationMarkers.length) {
            console.error('Invalid center index for directions:', centerIndex);
            return;
        }

        const markerItem = this.evacuationMarkers[centerIndex];
        const lat = markerItem.lat;
        const lng = markerItem.lng;

        // Open Google Maps with the evacuation center location
        const url = `https://www.google.com/maps/place/${lat},${lng}`;
        window.open(url, '_blank');
        this.showNotification('Directions', `Opening location of ${markerItem.center.name}`, 'info');
    },

    showNotification: function(title, message, type) {
        // Remove existing notifications
        document.querySelectorAll('.dashboard-notification').forEach(n => n.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed dashboard-notification`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';
        notification.innerHTML = `
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 150);
            }
        }, 4000);
    }
};

// Initialize dashboard map when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing dashboard map...');
    
    // Small delay to ensure the map container is ready
    setTimeout(() => {
        dashboardMap.init();
    }, 100);

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Add custom CSS for map icons and styling
const style = document.createElement('style');
style.textContent = `
    .custom-div-icon {
        background: transparent !important;
        border: none !important;
    }
    .evacuation-center-item:hover {
        background-color: #f8f9fa !important;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .evacuation-center-item.bg-light {
        background-color: #e3f2fd !important;
        border-left: 4px solid #2196f3 !important;
    }
    #dashboard-map {
        border-radius: 0;
    }
    .leaflet-popup-content-wrapper {
        border-radius: 8px;
    }
    .leaflet-popup-content {
        margin: 12px 16px;
    }
    .dashboard-notification {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: none;
        border-radius: 8px;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>
