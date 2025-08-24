<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Admin Dashboard';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Active alerts
$count_query = "SELECT COUNT(*) as total FROM alerts WHERE status = 'active'";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$stats['active_alerts'] = $count_stmt->fetch()['total'];



// Incident reports
$query = "SELECT COUNT(*) as count FROM incident_reports WHERE status IN ('pending', 'in_progress')";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['incident_reports'] = $stmt->fetch()['count'];

// Registered users
$query = "SELECT COUNT(*) as count FROM users WHERE user_type = 'resident'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['registered_users'] = $stmt->fetch()['count'];

// Evacuation centers
$query = "SELECT COUNT(*) as count FROM evacuation_centers WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['evacuation_centers'] = $stmt->fetch()['count'];

// Recent incident reports
$query = "SELECT ir.*, u.first_name, u.last_name 
          FROM incident_reports ir 
          JOIN users u ON ir.user_id = u.id 
          ORDER BY ir.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_incidents = $stmt->fetchAll();

// Recent alerts
$query = "SELECT a.*, dt.name as disaster_type_name 
          FROM alerts a 
          LEFT JOIN disaster_types dt ON a.disaster_type_id = dt.id 
          ORDER BY a.created_at DESC 
          LIMIT 4";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_alerts = $stmt->fetchAll();

// User registration by barangay
$query = "SELECT barangay, COUNT(*) as count 
          FROM users 
          WHERE user_type = 'resident' 
          GROUP BY barangay 
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetchAll();

// Add these database queries for map data
// Get evacuation centers with coordinates
$centers_query = "SELECT * FROM evacuation_centers WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY name";
$centers_stmt = $db->prepare($centers_query);
$centers_stmt->execute();
$centers_result = $centers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent incidents with location data for map
$map_incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.barangay 
                       FROM incident_reports ir 
                       LEFT JOIN users u ON ir.user_id = u.id 
                       WHERE ir.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       ORDER BY ir.created_at DESC 
                       LIMIT 20";
$map_incidents_stmt = $db->prepare($map_incidents_query);
$map_incidents_stmt->execute();
$map_incidents_result = $map_incidents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active alerts for map
$map_alerts_query = "SELECT * FROM alerts WHERE status = 'active' ORDER BY created_at DESC LIMIT 10";
$map_alerts_stmt = $db->prepare($map_alerts_query);
$map_alerts_stmt->execute();
$map_alerts_result = $map_alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Barangay coordinates (approximate center points for Agoncillo, Batangas)
$barangay_coords = [
    'Adia' => ['lat' => 13.944797659673066, 'lng' => 120.92568019700752],
    'Bagong Sikat' => ['lat' => 13.939676016453648, 'lng' => 120.93410167921587],
    'Balangon' => ['lat' => 13.9213634, 'lng' => 120.9134835],
    'Bangin' => ['lat' => 13.92426470980458, 'lng' => 120.92256655803891],
    'Banyaga' => ['lat' => 14.009857, 'lng' => 120.9506093],
    'Barigon' => ['lat' => 14.0001836, 'lng' => 120.9135546],
    'Bilibinwang' => ['lat' => 13.9917838, 'lng' => 120.9500635],
    'Coral na Munti' => ['lat' => 13.9358335, 'lng' => 120.915762],
    'Guitna' => ['lat' => 13.935841976579193, 'lng' => 120.93559547104182],
    'Mabini' => ['lat' => 13.9313799, 'lng' => 120.9149862],
    'Pamiga' => ['lat' => 13.937018, 'lng' => 120.9231897],
    'Panhulan' => ['lat' => 13.9417959, 'lng' => 120.941934],
    'Pansipit' => ['lat' => 13.9286354, 'lng' =>120.9448801],
    'Poblacion' => ['lat' => 13.934735, 'lng' => 120.9281217],
    'Pook' => ['lat' => 13.9300112, 'lng' => 120.9297605],
    'San Jacinto' => ['lat' => 13.9428713, 'lng' => 120.9169704],
    'San Teodoro' => ['lat' => 13.9348872, 'lng' => 120.9450933],
    'Santa Cruz' => ['lat' => 13.9146655, 'lng' => 120.9185542],
    'Santo Tomas' => ['lat' => 13.9392874, 'lng' => 120.9422622],
    'Subic Ibaba' => ['lat' => 13.9475472, 'lng' => 120.9411149],
    'Subic Ilaya' => ['lat' => 13.9535154, 'lng' => 120.9404725]
    
];

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">
   
<style>
#dashboardMap {
    height: 400px;
    width: 100%;
    border-radius: 8px;
    z-index: 1;
}
.map-controls-dashboard {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: white;
    padding: 8px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.map-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.1);
    border-radius: 8px;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none; /* Allow mouse events to pass through */
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 999;
}
.map-overlay .btn {
    pointer-events: auto; /* Re-enable pointer events for the button */
}
.map-container:hover .map-overlay {
    opacity: 1;
}
.map-container {
    position: relative;
}
</style>

<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Navigation -->
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <!-- Statistics Cards -->
            <div class="row g-3 my-3">
                <div class="col-md-3">
                    <div class="p-3 bg-primary shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['active_alerts']; ?></h3>
                            <p class="fs-5">Active Alerts</p>
                        </div>
                        <i class="bi bi-bell-fill fs-1 text-white "></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-warning shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['incident_reports']; ?></h3>
                            <p class="fs-5">Incident Reports</p>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill fs-1 text-white"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-success shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['registered_users']; ?></h3>
                            <p class="fs-5">Registered Users</p>
                        </div>
                        <i class="bi bi-people-fill fs-1 text-white"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-3 bg-danger shadow-sm d-flex justify-content-around align-items-center rounded">
                        <div class="text-white">
                            <h3 class="fs-2"><?php echo $stats['evacuation_centers']; ?></h3>
                            <p class="fs-5">Evacuation Centers</p>
                        </div>
                        <i class="bi bi-house-fill fs-1 text-white"></i>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row my-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Emergency Response Map</h5>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterMapData('flood')">Flood</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterMapData('volcanic')">Volcanic</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterMapData('earthquake')">Earthquake</button>
                                    <a href="map.php" class="btn btn-sm btn-primary">Full Map</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="map-container">
                                <div class="map-controls-dashboard">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="showDashIncidents" checked>
                                        <label class="form-check-label" for="showDashIncidents">
                                            <small>Incidents</small>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" id="showDashEvacuation" checked>
                                        <label class="form-check-label" for="showDashEvacuation">
                                            <small>Centers</small>
                                        </label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="showDashAlerts" checked>
                                        <label class="form-check-label" for="showDashAlerts">
                                            <small>Alerts</small>
                                        </label>
                                    </div>
                                </div>
                                <div id="dashboardMap"></div>
                                <div class="map-overlay">
                                    <a href="map.php" class="btn btn-primary">
                                        <i class="bi bi-arrows-fullscreen"></i> Open Full Map
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Incident Reports</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_incidents as $incident): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <span class="badge bg-<?php echo $incident['urgency_level'] == 'critical' ? 'danger' : ($incident['urgency_level'] == 'high' ? 'warning' : 'info'); ?> me-2">
                                            <?php echo ucfirst($incident['urgency_level']); ?>
                                        </span>
                                        <span><?php echo ucfirst($incident['incident_type']); ?></span>
                                        <small class="d-block text-muted">Reported by: <?php echo $incident['first_name'] . ' ' . $incident['last_name']; ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($incident['created_at']); ?></small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="incidents.php" class="btn btn-sm btn-outline-primary">View All Incidents</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Content Row -->
            <div class="row my-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Alert History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Alert Type</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_alerts as $alert): ?>
                                        <tr>
                                            <td><?php echo $alert['disaster_type_name'] ?: 'General'; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($alert['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $alert['status'] == 'sent' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($alert['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">User Registration Statistics</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userStatsChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row my-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="alerts.php" class="btn btn-danger w-100 py-3">
                                        <i class="bi bi-exclamation-circle-fill fs-4 d-block mb-2"></i>
                                        Send Emergency Alert
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="map.php" class="btn btn-primary w-100 py-3">
                                        <i class="bi bi-map-fill fs-4 d-block mb-2"></i>
                                        Update Evacuation Map
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="reports.php" class="btn btn-success w-100 py-3">
                                        <i class="bi bi-file-earmark-text-fill fs-4 d-block mb-2"></i>
                                        Generate Reports
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="users.php" class="btn btn-warning w-100 py-3 text-dark">
                                        <i class="bi bi-person-plus-fill fs-4 d-block mb-2"></i>
                                        Verify New Users
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

// Initialize dashboard map with proper settings
var dashboardMap = L.map('dashboardMap', {
    dragging: true,
    touchZoom: true,
    doubleClickZoom: true,
    scrollWheelZoom: true,
    boxZoom: true,
    keyboard: true,
    zoomControl: true
}).setView([13.934542301563013, 120.92846530878772], 15);

// Add OpenStreetMap tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(dashboardMap);

// Layer groups for different marker types
var dashIncidentLayer = L.layerGroup().addTo(dashboardMap);
var dashEvacuationLayer = L.layerGroup().addTo(dashboardMap);
var dashAlertLayer = L.layerGroup().addTo(dashboardMap);

// Barangay coordinates
var barangayCoords = <?php echo json_encode($barangay_coords); ?>;

// Map data
var evacuationCenters = <?php echo json_encode($centers_result); ?>;
var mapIncidents = <?php echo json_encode($map_incidents_result); ?>;
var mapAlerts = <?php echo json_encode($map_alerts_result); ?>;

// Add evacuation center markers
evacuationCenters.forEach(function(center) {
    var occupancyRate = center.capacity > 0 ? (center.current_occupancy / center.capacity * 100) : 0;
    var markerColor = center.status === 'active' ? '#28a745' : '#6c757d';
    
    var marker = L.circleMarker([center.latitude, center.longitude], {
        radius: 6,
        fillColor: markerColor,
        color: '#fff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.8
    });
    
    var popupContent = `
        <div class="popup-content">
            <h6><i class="bi bi-house"></i> ${center.name}</h6>
            <p><strong>Capacity:</strong> ${center.current_occupancy}/${center.capacity}</p>
            <p><strong>Status:</strong> <span class="badge bg-${center.status === 'active' ? 'success' : 'secondary'}">${center.status}</span></p>
        </div>
    `;
    
    marker.bindPopup(popupContent);
    dashEvacuationLayer.addLayer(marker);
});

// Add incident markers
mapIncidents.forEach(function(incident) {
    if (incident.barangay && barangayCoords[incident.barangay]) {
        var coords = barangayCoords[incident.barangay];
        var markerColor = getSeverityColor(incident.severity || 'medium');
        
        var lat = coords.lat + (Math.random() - 0.5) * 0.01;
        var lng = coords.lng + (Math.random() - 0.5) * 0.01;
        
        var marker = L.circleMarker([lat, lng], {
            radius: 5,
            fillColor: markerColor,
            color: '#fff',
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        });
        
        var popupContent = `
            <div class="popup-content">
                <h6><i class="bi bi-exclamation-triangle"></i> ${incident.incident_type}</h6>
                <p><strong>Location:</strong> ${incident.barangay}</p>
                <p><strong>Date:</strong> ${new Date(incident.created_at).toLocaleDateString()}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        dashIncidentLayer.addLayer(marker);
    }
});

// Add alert area markers
mapAlerts.forEach(function(alert) {
    if (alert.affected_barangays && barangayCoords[alert.affected_barangays]) {
        var coords = barangayCoords[alert.affected_barangays];
        var markerColor = getSeverityColor(alert.severity_level || 'medium');
        
        var marker = L.circle([coords.lat, coords.lng], {
            radius: 800,
            fillColor: markerColor,
            color: markerColor,
            weight: 2,
            opacity: 0.6,
            fillOpacity: 0.2
        });
        
        var popupContent = `
            <div class="popup-content">
                <h6><i class="bi bi-bell"></i> ${alert.title}</h6>
                <p><strong>Area:</strong> ${alert.affected_barangays}</p>
                <p><strong>Severity:</strong> ${alert.severity_level}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        dashAlertLayer.addLayer(marker);
    }
});

// Helper functions
function getSeverityColor(severity) {
    switch(severity) {
        case 'low': return '#28a745';
        case 'medium': return '#ffc107';
        case 'high': return '#fd7e14';
        case 'critical': return '#dc3545';
        default: return '#6c757d';
    }
}

// Toggle layer visibility for dashboard map
document.getElementById('showDashIncidents').addEventListener('change', function() {
    if (this.checked) {
        dashboardMap.addLayer(dashIncidentLayer);
    } else {
        dashboardMap.removeLayer(dashIncidentLayer);
    }
});

document.getElementById('showDashEvacuation').addEventListener('change', function() {
    if (this.checked) {
        dashboardMap.addLayer(dashEvacuationLayer);
    } else {
        dashboardMap.removeLayer(dashEvacuationLayer);
    }
});

document.getElementById('showDashAlerts').addEventListener('change', function() {
    if (this.checked) {
        dashboardMap.addLayer(dashAlertLayer);
    } else {
        dashboardMap.removeLayer(dashAlertLayer);
    }
});

// Filter map data by disaster type
function filterMapData(type) {
    // This would filter the displayed data based on disaster type
    console.log('Filtering map data for:', type);
    // Implementation would depend on your specific filtering requirements
}

// Ensure map renders properly after page load
setTimeout(function() {
    dashboardMap.invalidateSize();
}, 100);

// User statistics chart
const ctx = document.getElementById('userStatsChart').getContext('2d');
const userStatsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($user_stats, 'barangay')) . "'"; ?>],
        datasets: [{
            label: 'Registered Users by Barangay',
            data: [<?php echo implode(',', array_column($user_stats, 'count')); ?>],
            backgroundColor: 'rgba(13, 110, 253, 0.7)',
            borderColor: 'rgba(13, 110, 253, 1)',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
