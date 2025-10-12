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

// Get population data from barangays table
$population_query = "SELECT name, population 
                    FROM barangays 
                    WHERE population > 0 
                    ORDER BY population DESC";
$population_stmt = $db->prepare($population_query);
$population_stmt->execute();
$population_stats = $population_stmt->fetchAll();

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

$dashboard_hazard_zones_query = "SELECT * FROM hazard_zones ORDER BY risk_level DESC, name";
$dashboard_hazard_zones_stmt = $db->prepare($dashboard_hazard_zones_query);
$dashboard_hazard_zones_stmt->execute();
$dashboard_hazard_zones_result = $dashboard_hazard_zones_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    pointer-events: none;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 999;
}
.map-overlay .btn {
    pointer-events: auto;
}
.map-container:hover .map-overlay {
    opacity: 1;
}
.map-container {
    position: relative;
}
.map-controls-dashboard .collapsed {
    max-height: 0 !important;
    overflow: hidden !important;
    transition: max-height 0.3s ease-out, opacity 0.3s ease-out !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
.map-controls-dashboard #controlsBody {
    max-height: 500px;
    transition: max-height 0.3s ease-in, opacity 0.3s ease-in;
}
</style>

<div class="d-flex" id="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
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

            <div class="row my-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Emergency Response Map</h5>
                                <div class="btn-group">
                                    <a href="map.php" class="btn btn-sm btn-primary">Full Map</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="map-container">
                                <div class="map-controls-dashboard" id="mapControls">
                                    <div class="controls-header d-flex justify-content-between align-items-center mb-2">
                                        <strong>Map Layers</strong>
                                        <button class="btn btn-sm btn-light toggle-btn" id="toggleControlsBtn">
                                            <i class="bi bi-x-lg" id="toggleIcon"></i>
                                        </button>
                                    </div>

                                    <div class="controls-body" id="controlsBody">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showIncidents" checked>
                                            <label class="form-check-label" for="showIncidents">
                                                <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                                <small>Incidents</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showEvacuation" checked>
                                            <label class="form-check-label" for="showEvacuation">
                                                <i class="bi bi-house-heart-fill text-success me-1"></i>
                                                <small>Evacuation Centers</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showAlerts" checked>
                                            <label class="form-check-label" for="showAlerts">
                                                <i class="bi bi-bell-fill text-warning me-1"></i>
                                                <small>Alerts</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showFloodZones" checked>
                                            <label class="form-check-label" for="showFloodZones">
                                                <i class="bi bi-water text-info me-1"></i>
                                                <small>Flood Prone Areas</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showLandslideZones" checked>
                                            <label class="form-check-label" for="showLandslideZones">
                                                <i class="bi bi-triangle text-secondary me-1"></i>
                                                <small>Landslide Prone Areas</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="showAccidentAreas" checked>
                                            <label class="form-check-label" for="showAccidentAreas">
                                                <i class="bi bi-car-front-fill text-primary me-1"></i>
                                                <small>Accident Prone Areas</small>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="showVolcanicRisk" checked>
                                            <label class="form-check-label" for="showVolcanicRisk">
                                                <i class="bi bi-fire text-danger me-1"></i>
                                                <small>Volcanic Risk</small>
                                            </label>
                                        </div>
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

            <div class="row my-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Population Distribution by Barangay</h5>
                                <small class="text-muted">Total Population: <?php echo number_format(array_sum(array_column($population_stats, 'population'))); ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <canvas id="populationChart" width="400" height="200"></canvas>
                                </div>
                                <div class="col-md-4">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Barangay</th>
                                                    <th>Population</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($population_stats as $barangay): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($barangay['name']); ?></td>
                                                    <td><?php echo number_format($barangay['population']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar
    const menuToggle = document.getElementById("menu-toggle");
    if (menuToggle) {
        menuToggle.addEventListener("click", function(e) {
            e.preventDefault();
            document.getElementById("wrapper").classList.toggle("toggled");
        });
    }

    // Initialize dashboard map with proper settings
    var dashboardMap = L.map('dashboardMap', {
        dragging: true,
        touchZoom: true,
        doubleClickZoom: true,
        scrollWheelZoom: true,
        boxZoom: true,
        keyboard: true,
        zoomControl: true
    }).setView([13.934542301563013, 120.92846530878772], 13);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(dashboardMap);

    // Layer groups for different marker types
    var dashIncidentLayer = L.layerGroup().addTo(dashboardMap);
    var dashEvacuationLayer = L.layerGroup().addTo(dashboardMap);
    var dashAlertLayer = L.layerGroup().addTo(dashboardMap);
    var dashFloodLayer = L.layerGroup().addTo(dashboardMap);
    var dashLandslideLayer = L.layerGroup().addTo(dashboardMap);
    var dashAccidentLayer = L.layerGroup().addTo(dashboardMap);
    var dashVolcanicLayer = L.layerGroup().addTo(dashboardMap);

    // Barangay coordinates
    var barangayCoords = <?php echo json_encode($barangay_coords); ?>;

    // Map data
    var evacuationCenters = <?php echo json_encode($centers_result); ?>;
    var mapIncidents = <?php echo json_encode($map_incidents_result); ?>;
    var mapAlerts = <?php echo json_encode($map_alerts_result); ?>;
    var dashboardHazardZones = <?php echo json_encode($dashboard_hazard_zones_result); ?>;

    console.log('[v0] Total hazard zones loaded:', dashboardHazardZones.length);

    // Add evacuation center markers
    evacuationCenters.forEach(function(center) {
        var occupancyRate = center.capacity > 0 ? (center.current_occupancy / center.capacity * 100) : 0;
        var markerColor = center.status === 'active' ? '#28a745' : '#6c757d';
        
        var marker = L.circleMarker([center.latitude, center.longitude], {
            radius: 8,
            fillColor: markerColor,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        });
        
        var popupContent = `
            <div class="popup-content">
                <h6><i class="bi bi-house-heart-fill"></i> ${center.name}</h6>
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
            var markerColor = getSeverityColor(incident.severity);
            
            var lat = coords.lat + (Math.random() - 0.5) * 0.01;
            var lng = coords.lng + (Math.random() - 0.5) * 0.01;
            
            var marker = L.circleMarker([lat, lng], {
                radius: 6,
                fillColor: markerColor,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            });
            
            var popupContent = `
                <div class="popup-content">
                    <h6><i class="bi bi-exclamation-triangle-fill"></i> ${incident.incident_type.charAt(0).toUpperCase() + incident.incident_type.slice(1)} Incident</h6>
                    <p><strong>Location:</strong> ${incident.location || incident.barangay}, ${incident.barangay}</p>
                    <p><strong>Description:</strong> ${(incident.description || 'No description available').substring(0, 100)}...</p>
                    <p><strong>Severity:</strong> <span class="badge bg-${getSeverityBadgeClass(incident.severity)}">${incident.severity || 'Not specified'}</span></p>
                    <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeClass(incident.status)}">${(incident.status || 'pending').replace('_', ' ')}</span></p>
                    <p><strong>Reported by:</strong> ${incident.first_name} ${incident.last_name}</p>
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
            var markerColor = getSeverityColor(alert.severity_level);
            
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
                    <h6><i class="bi bi-bell-fill"></i> ${alert.title}</h6>
                    <p><strong>Area:</strong> ${alert.affected_barangays}</p>
                    <p><strong>Severity:</strong> <span class="badge bg-${getSeverityBadgeClass(alert.severity_level)}">${alert.severity_level || 'Not specified'}</span></p>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            dashAlertLayer.addLayer(marker);
        }
    });

    dashboardHazardZones.forEach(function(zone) {
        if (zone.coordinates) {
            var coordinates;
            try {
                coordinates = JSON.parse(zone.coordinates);
            } catch (e) {
                console.error('Invalid coordinates for zone:', zone.name);
                return;
            }
            
            var latLngs = coordinates.map(function(coord) {
                return [coord.lat, coord.lng];
            });
            
            var fillColor, borderColor, fillOpacity, weight;
            switch (zone.zone_type) {
                case 'flood_prone':
                    fillColor = '#3b82f6';
                    borderColor = '#1e40af';
                    fillOpacity = 0.4;
                    weight = 2;
                    break;
                case 'landslide_prone':
                    fillColor = '#d97706';
                    borderColor = '#92400e';
                    fillOpacity = 0.4;
                    weight = 2;
                    break;
                case 'fault_line': // Accident-prone roadways
                    fillColor = '#ef4444';
                    borderColor = '#dc2626';
                    fillOpacity = 0.6;
                    weight = 4;
                    break;
                case 'volcanic_risk':
                    fillColor = '#ea580c';
                    borderColor = '#7c2d12';
                    fillOpacity = 0.15;
                    weight = 1;
                    break;
                default:
                    fillColor = '#6b7280';
                    borderColor = '#374151';
                    fillOpacity = 0.3;
                    weight = 2;
            }
            
            var hazardShape;
            if (zone.zone_type === 'fault_line') {
                hazardShape = L.polyline(latLngs, {
                    color: borderColor,
                    weight: weight,
                    opacity: 0.8,
                    dashArray: zone.risk_level === 'critical' ? '8, 4' : null
                });
            } else if (zone.zone_type === 'volcanic_risk') {
                var center = getPolygonCenter(latLngs);
                var radius = getPolygonRadius(latLngs, center);
                hazardShape = L.circle(center, {
                    radius: radius,
                    fillColor: fillColor,
                    fillOpacity: fillOpacity,
                    color: borderColor,
                    weight: weight,
                    opacity: 0.7
                });
            } else {
                hazardShape = L.polygon(latLngs, {
                    fillColor: fillColor,
                    fillOpacity: fillOpacity,
                    color: borderColor,
                    weight: weight,
                    opacity: 0.7
                });
            }
            
            var popupContent = `
                <div class="popup-content">
                    <h6>${zone.name}</h6>
                    <p><strong>Type:</strong> ${zone.zone_type === 'fault_line' ? 'Accident Prone Road' : 
                                               zone.zone_type === 'volcanic_risk' ? 'Volcanic Risk' :
                                               zone.zone_type === 'flood_prone' ? 'Flood Prone' : 'Landslide Prone'}</p>
                    <p><strong>Risk:</strong> ${zone.risk_level}</p>
                    <p>${zone.description.substring(0, 80)}...</p>
                </div>
            `;
            
            hazardShape.bindPopup(popupContent);
            
            switch (zone.zone_type) {
                case 'flood_prone':
                    dashFloodLayer.addLayer(hazardShape);
                    break;
                case 'landslide_prone':
                    dashLandslideLayer.addLayer(hazardShape);
                    break;
                case 'fault_line':
                    dashAccidentLayer.addLayer(hazardShape);
                    break;
                case 'volcanic_risk':
                    dashVolcanicLayer.addLayer(hazardShape);
                    break;
            }
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

    function getSeverityBadgeClass(severity) {
        switch(severity) {
            case 'low': return 'success';
            case 'medium': return 'warning';
            case 'high': return 'warning';
            case 'critical': return 'danger';
            default: return 'secondary';
        }
    }

    function getStatusBadgeClass(status) {
        switch(status) {
            case 'pending': return 'warning';
            case 'in_progress': return 'info';
            case 'resolved': return 'success';
            case 'closed': return 'secondary';
            case 'active': return 'success';
            default: return 'secondary';
        }
    }

    function getPolygonCenter(latLngs) {
        var lat = 0, lng = 0;
        latLngs.forEach(function(coord) {
            lat += coord[0];
            lng += coord[1];
        });
        return [lat / latLngs.length, lng / latLngs.length];
    }

    function getPolygonRadius(latLngs, center) {
        var maxDistance = 0;
        latLngs.forEach(function(coord) {
            var distance = Math.sqrt(
                Math.pow(coord[0] - center[0], 2) + Math.pow(coord[1] - center[1], 2)
            );
            maxDistance = Math.max(maxDistance, distance);
        });
        return maxDistance * 111000;
    }

    document.getElementById('showIncidents').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashIncidentLayer);
        } else {
            dashboardMap.removeLayer(dashIncidentLayer);
        }
    });

    document.getElementById('showEvacuation').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashEvacuationLayer);
        } else {
            dashboardMap.removeLayer(dashEvacuationLayer);
        }
    });

    document.getElementById('showAlerts').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashAlertLayer);
        } else {
            dashboardMap.removeLayer(dashAlertLayer);
        }
    });

    document.getElementById('showFloodZones').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashFloodLayer);
        } else {
            dashboardMap.removeLayer(dashFloodLayer);
        }
    });

    document.getElementById('showLandslideZones').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashLandslideLayer);
        } else {
            dashboardMap.removeLayer(dashLandslideLayer);
        }
    });

    document.getElementById('showAccidentAreas').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashAccidentLayer);
        } else {
            dashboardMap.removeLayer(dashAccidentLayer);
        }
    });

    document.getElementById('showVolcanicRisk').addEventListener('change', function() {
        if (this.checked) {
            dashboardMap.addLayer(dashVolcanicLayer);
        } else {
            dashboardMap.removeLayer(dashVolcanicLayer);
        }
    });

    // Ensure map renders properly after page load
    setTimeout(function() {
        dashboardMap.invalidateSize();
    }, 100);

    // User statistics chart
    const userStatsCanvas = document.getElementById('userStatsChart');
    if (userStatsCanvas) {
        const ctx = userStatsCanvas.getContext('2d');
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
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true
                    }
                }
            }
        });
    }

    // Population statistics chart
    const populationCanvas = document.getElementById('populationChart');
    if (populationCanvas) {
        const populationCtx = populationCanvas.getContext('2d');
        const populationChart = new Chart(populationCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($population_stats, 'name')) . "'"; ?>],
                datasets: [{
                    label: 'Population',
                    data: [<?php echo implode(',', array_column($population_stats, 'population')); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(199, 199, 199, 0.8)',
                        'rgba(83, 102, 255, 0.8)',
                        'rgba(255, 99, 255, 0.8)',
                        'rgba(99, 255, 132, 0.8)',
                        'rgba(255, 159, 132, 0.8)',
                        'rgba(132, 255, 235, 0.8)',
                        'rgba(255, 132, 86, 0.8)',
                        'rgba(192, 75, 192, 0.8)',
                        'rgba(102, 153, 255, 0.8)',
                        'rgba(64, 255, 159, 0.8)',
                        'rgba(199, 132, 199, 0.8)',
                        'rgba(255, 102, 83, 0.8)',
                        'rgba(99, 132, 255, 0.8)',
                        'rgba(255, 255, 99, 0.8)',
                        'rgba(132, 99, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)',
                        'rgba(83, 102, 255, 1)',
                        'rgba(255, 99, 255, 1)',
                        'rgba(99, 255, 132, 1)',
                        'rgba(255, 159, 132, 1)',
                        'rgba(132, 255, 235, 1)',
                        'rgba(255, 132, 86, 1)',
                        'rgba(192, 75, 192, 1)',
                        'rgba(102, 153, 255, 1)',
                        'rgba(64, 255, 159, 1)',
                        'rgba(199, 132, 199, 1)',
                        'rgba(255, 102, 83, 1)',
                        'rgba(99, 132, 255, 1)',
                        'rgba(255, 255, 99, 1)',
                        'rgba(132, 99, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toLocaleString() + ' residents';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // Toggle controls functionality
    const toggleBtn = document.getElementById("toggleControlsBtn");
    const toggleIcon = document.getElementById("toggleIcon");
    const controlsBody = document.getElementById("controlsBody");

    if (toggleBtn && toggleIcon && controlsBody) {
        toggleBtn.addEventListener("click", () => {
            controlsBody.classList.toggle("collapsed");

            if (controlsBody.classList.contains("collapsed")) {
                toggleIcon.classList.remove("bi-x-lg");
                toggleIcon.classList.add("bi-list");
            } else {
                toggleIcon.classList.remove("bi-list");
                toggleIcon.classList.add("bi-x-lg");
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
