<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Evacuation Map';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Get evacuation centers
$query = "SELECT ec.*, b.name as barangay_name 
          FROM evacuation_centers ec 
          JOIN barangays b ON ec.barangay_id = b.id 
          WHERE ec.status = 'active' 
          ORDER BY ec.name";
$stmt = $db->prepare($query);
$stmt->execute();
$evacuation_centers = $stmt->fetchAll();

// Get recent incidents for map display
$query = "SELECT ir.*, u.first_name, u.last_name 
          FROM incident_reports ir 
          JOIN users u ON ir.user_id = u.id 
          WHERE ir.latitude IS NOT NULL AND ir.longitude IS NOT NULL
          AND ir.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          ORDER BY ir.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_incidents = $stmt->fetchAll();

// Get hazard zones
$query = "SELECT * FROM hazard_zones ORDER BY risk_level DESC, name";
$stmt = $db->prepare($query);
$stmt->execute();
$hazard_zones = $stmt->fetchAll();

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<!-- Custom CSS for the new Map Controls -->
<style>
.map-controls-container {
    /* Position controls over the map in the top right corner */
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1001; /* Increased z-index to ensure it sits above the map and Leaflet controls */
    background: white;
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 300px;
}
/* Styles for the collapsible body (copied from dashboard fix) */
.map-controls-container .collapsed {
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
.map-controls-container #mapControlsBody {
    max-height: 500px; /* A safe maximum height for transition */
    transition: max-height 0.3s ease-in, opacity 0.3s ease-in;
}
/* Ensure the map container can properly contain the absolute element */
#map-container {
    position: relative;
}
/* Ensure the Leaflet map occupies the full space */
#leaflet-map {
    height: 500px; 
    width: 100%;
}
</style>

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
                    <a class="nav-link" href="alerts.php"><i class="bi bi-bell-fill me-1"></i> Alerts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
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

<div class="container-fluid my-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2><i class="bi bi-map-fill me-2 text-primary"></i>Evacuation Map</h2>
                    <p class="text-muted mb-0">Interactive map showing evacuation centers, emergency routes, and hazard zones in Agoncillo</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#legendModal">
                        <i class="bi bi-info-circle me-1"></i>Legend
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Map Container -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Interactive Map</h5>
                </div>
                <div class="card-body p-0">
                    <div id="map-container" style="height: 500px; background: #f8f9fa; position: relative;">
                        
                        <!-- MAP LAYER CONTROLS (Burger Menu Style) -->
                        <div class="map-controls-container">
                            <!-- Header/Toggle Button -->
                            <div class="controls-header d-flex justify-content-between align-items-center mb-2">
                                <strong>Map Layers</strong>
                                <button class="btn btn-sm btn-light toggle-btn" id="toggleMapControlsBtn">
                                    <!-- Default icon is 'x' (bi-x-lg) because controls start open -->
                                    <i class="bi bi-x-lg" id="toggleMapIcon"></i> 
                                </button>
                            </div>
                            
                            <!-- Collapsible Body with Checkboxes -->
                            <div id="mapControlsBody">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleEvacuation" checked>
                                    <label class="form-check-label" for="toggleEvacuation"><small><i class="bi bi-shield-check me-1"></i>Evacuation Centers</small></label>
                                </div>
                                
                                <h6 class="mt-2 mb-1 small text-muted border-top pt-2">Hazard Zones</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleFlood">
                                    <label class="form-check-label" for="toggleFlood"><small><i class="bi bi-droplet me-1"></i>Flood Prone Areas</small></label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleLandslide">
                                    <label class="form-check-label" for="toggleLandslide"><small><i class="bi bi-triangle me-1"></i>Landslide Prone Areas</small></label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleAccident">
                                    <label class="form-check-label" for="toggleAccident"><small><i class="bi bi-exclamation-diamond me-1"></i>Accident Prone Areas</small></label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleVolcanic" checked>
                                    <label class="form-check-label" for="toggleVolcanic"><small><i class="bi bi-fire me-1"></i>Volcanic Risk</small></label>
                                </div>

                                <h6 class="mt-2 mb-1 small text-muted border-top pt-2">Other Layers</h6>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleRoutes">
                                    <label class="form-check-label" for="toggleRoutes"><small><i class="bi bi-signpost-2 me-1"></i>Emergency Routes</small></label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="toggleIncidents">
                                    <label class="form-check-label" for="toggleIncidents"><small><i class="bi bi-exclamation-triangle me-1"></i>Recent Incidents</small></label>
                                </div>
                            </div>
                        </div>

                        <!-- Interactive Map will be loaded here -->
                        <div id="map-placeholder" class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading map...</span>
                                </div>
                                <p class="text-muted">Loading interactive map...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar (Unchanged) -->
        <div class="col-lg-4">
            <!-- Evacuation Centers List (Unchanged) -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-check me-2 text-success"></i>Evacuation Centers
                    </h5>
                </div>
                <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($evacuation_centers as $center): ?>
                    <div class="evacuation-center p-3 border-bottom cursor-pointer" 
                         onclick="focusOnLocation(<?php echo $center['latitude']; ?>, <?php echo $center['longitude']; ?>)"
                         data-bs-toggle="tooltip" title="Click to view on map">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo $center['name']; ?></h6>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-geo-alt me-1"></i><?php echo $center['address']; ?>
                                </p>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-building me-1"></i><?php echo $center['barangay_name']; ?>
                                </p>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary me-2">
                                        <i class="bi bi-people me-1"></i><?php echo $center['capacity']; ?> capacity
                                    </span>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i><?php echo ucfirst($center['status']); ?>
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
            </div>

            <!-- Emergency Contacts (Unchanged) -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-telephone-fill me-2 text-danger"></i>Emergency Contacts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-danger text-white rounded-circle p-2 me-3">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">MDRRMO Agoncillo</h6>
                            <a href="tel:+639123456789" class="text-decoration-none">+63 912 345 6789</a>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="callEmergency('+639123456789')">
                            <i class="bi bi-telephone"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary text-white rounded-circle p-2 me-3">
                            <i class="bi bi-hospital-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Health Center</h6>
                            <a href="tel:+639123456790" class="text-decoration-none">+63 912 345 6790</a>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="callEmergency('+639123456790')">
                            <i class="bi bi-telephone"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="bg-info text-white rounded-circle p-2 me-3">
                            <i class="bi bi-shield-fill"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Police Station</h6>
                            <a href="tel:+639123456791" class="text-decoration-none">+63 912 345 6791</a>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="callEmergency('+639123456791')">
                            <i class="bi bi-telephone"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Weather Information (Unchanged) -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cloud-sun me-2 text-warning"></i>Current Weather
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h3 class="mb-0" id="temperature">--°C</h3>
                            <p class="text-muted mb-0" id="weather-desc">Loading...</p>
                        </div>
                        <i class="bi bi-cloud-sun display-4 text-warning" id="weather-icon"></i>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Humidity</small>
                            <strong id="humidity">--%</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Wind</small>
                            <strong id="wind">-- km/h</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Rain</small>
                            <strong id="rain">--%</strong>
                        </div>
                    </div>
                    <hr>
                    <div class="alert alert-warning alert-sm mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <small>Flood risk level: <strong id="flood-risk">Calculating...</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Legend Modal (Unchanged) -->
<div class="modal fade" id="legendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Map Legend</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6">
                        <h6>Evacuation Centers</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-success rounded-circle me-2" style="width: 16px; height: 16px;"></div>
                            <small>Active Center</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-warning rounded-circle me-2" style="width: 16px; height: 16px;"></div>
                            <small>At Capacity</small>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger rounded-circle me-2" style="width: 16px; height: 16px;"></div>
                            <small>Unavailable</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h6>Incidents</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-danger triangle me-2" style="width: 16px; height: 16px;"></div>
                            <small>Critical</small>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="bg-warning triangle me-2" style="width: 16px; height: 16px;"></div>
                            <small>High Priority</small>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info triangle me-2" style="width: 16px; height: 16px;"></div>
                            <small>Medium Priority</small>
                        </div>
                    </div>
                </div>
                <hr>
                <h6>Emergency Routes</h6>
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 20px; height: 3px; background-color: #0d6efd;" class="me-2"></div>
                    <small>Primary Route</small>
                </div>
                <div class="d-flex align-items-center">
                    <div style="width: 20px; height: 3px; background-color: #6c757d; border-style: dashed;" class="me-2"></div>
                    <small>Alternative Route</small>
                </div>
                <!-- Add hazard zones legend -->
                <hr>
                <h6>Hazard Zones</h6>
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 16px; height: 16px; background-color: #3b82f6; opacity: 0.6;" class="me-2"></div>
                    <small>Flood Prone</small>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 16px; height: 16px; background-color: #d97706; opacity: 0.6;" class="me-2"></div>
                    <small>Landslide Prone</small>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 16px; height: 16px; background-color: #ef4444; opacity: 0.6;" class="me-2"></div>
                    <small>Accident Prone</small>
                </div>
                <div class="d-flex align-items-center">
                    <div style="width: 16px; height: 16px; background-color: #ea580c; opacity: 0.6;" class="me-2"></div>
                    <small>Volcanic Risk</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Map functionality with Leaflet
let map;
let userLocationMarker;
let evacuationCenterMarkers = [];
let incidentMarkers = [];
let evacuationLayer, incidentLayer, routeLayer, floodLayer, landslideLayer, accidentLayer, volcanicLayer; 

// Agoncillo coordinates (approximate center)
const AGONCILLO_CENTER = [13.9333, 120.9333];

// --- Map Utility Functions (Zoom/Reset) ---

// zoomIn/zoomOut/resetView functions are kept as they are called from the Leaflet control buttons
function zoomIn() { map.zoomIn(); }
function zoomOut() { map.zoomOut(); }
function resetView() {
    if (map) {
        map.setView(AGONCILLO_CENTER, 13);
        AgoncilloAlert.showInAppNotification('View Reset', 'Map view has been reset', 'info');
    }
}

// Initialize map on page load
document.addEventListener('DOMContentLoaded', function() {
    // Wait for the DOM to be fully ready before setting timeout for map init
    // This timeout is usually needed in embedded environments like Canvas.
    setTimeout(() => {
        initializeMap();
    }, 1000);
});

function initializeMap() {
    const mapContainer = document.getElementById('map-container');
    const placeholder = document.getElementById('map-placeholder');
    
    // Remove placeholder
    if (placeholder) {
        placeholder.remove();
    }
    
    // Create map div
    mapContainer.innerHTML += '<div id="leaflet-map"></div>';
    
    // Initialize Leaflet map
    map = L.map('leaflet-map').setView(AGONCILLO_CENTER, 13);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Create layer groups. ONLY Evacuation and Volcanic are added to map by default.
    evacuationLayer = L.layerGroup().addTo(map);
    incidentLayer = L.layerGroup();
    routeLayer = L.layerGroup();
    floodLayer = L.layerGroup();
    landslideLayer = L.layerGroup();
    accidentLayer = L.layerGroup();
    volcanicLayer = L.layerGroup().addTo(map); // Volcanic layer as background, added by default
    
    // Add evacuation center markers
    addEvacuationCenterMarkers();
    
    // Add recent incident markers
    addIncidentMarkers();
    
    addHazardZones();
    
    // Add custom controls (Zoom buttons)
    addCustomControls();

    // *** FIX: Prevent Leaflet from capturing clicks on the Map Controls container ***
    const controlsContainer = document.querySelector(".map-controls-container");
    if (controlsContainer && typeof L !== 'undefined') {
        // Disable click and scroll propagation to allow interaction with the controls
        L.DomEvent.disableClickPropagation(controlsContainer);
        L.DomEvent.disableScrollPropagation(controlsContainer);
    }

    // Initialize the burger menu and layer toggle logic
    initializeMapControls(); 
    
    AgoncilloAlert.showInAppNotification('Map Loaded', 'Interactive map is now ready', 'success');
}

function addEvacuationCenterMarkers() {
    const centers = <?php echo json_encode($evacuation_centers); ?>;
    
    centers.forEach(center => {
        if (center.latitude && center.longitude) {
            // Create custom icon for evacuation centers
            const evacuationIcon = L.divIcon({
                className: 'evacuation-marker',
                html: '<div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="bi bi-shield-check" style="font-size: 14px;"></i></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const marker = L.marker([center.latitude, center.longitude], {
                icon: evacuationIcon
            });
            
            // Create popup content
            const popupContent = `
                <div class="p-2">
                    <h6 class="mb-2">${center.name}</h6>
                    <p class="mb-1 small"><i class="bi bi-geo-alt me-1"></i>${center.address}</p>
                    <p class="mb-1 small"><i class="bi bi-building me-1"></i>${center.barangay_name}</p>
                    <div class="d-flex gap-1 mb-2">
                        <span class="badge bg-primary small">
                            <i class="bi bi-people me-1"></i>${center.capacity} capacity
                        </span>
                        <span class="badge bg-success small">
                            <i class="bi bi-check-circle me-1"></i>${center.status}
                        </span>
                    </div>
                    <button class="btn btn-sm btn-primary" onclick="getDirections(${center.latitude}, ${center.longitude})">
                        <i class="bi bi-navigation me-1"></i>Get Directions
                    </button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            evacuationLayer.addLayer(marker);
            evacuationCenterMarkers.push(marker);
        }
    });
}

function addIncidentMarkers() {
    const incidents = <?php echo json_encode($recent_incidents); ?>;
    
    incidents.forEach(incident => {
        if (incident.latitude && incident.longitude) {
            // Create custom icon for incidents
            const incidentIcon = L.divIcon({
                className: 'incident-marker',
                html: '<div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 25px; height: 25px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="bi bi-exclamation-triangle" style="font-size: 12px;"></i></div>',
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            });
            
            const marker = L.marker([incident.latitude, incident.longitude], {
                icon: incidentIcon
            });
            
            // Create popup content
            const popupContent = `
                <div class="p-2">
                    <h6 class="mb-2">Incident Report</h6>
                    <p class="mb-1 small"><strong>Type:</strong> ${incident.incident_type}</p>
                    <p class="mb-1 small"><strong>Reported by:</strong> ${incident.first_name} ${incident.last_name}</p>
                    <p class="mb-1 small"><strong>Time:</strong> ${new Date(incident.created_at).toLocaleString()}</p>
                    <p class="mb-2 small">${incident.description}</p>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            incidentLayer.addLayer(marker);
            incidentMarkers.push(marker);
        }
    });
}

function addHazardZones() {
    const hazardZones = <?php echo json_encode($hazard_zones); ?>;
    
    const sortedZones = hazardZones.sort((a, b) => {
        const layerOrder = {
            'volcanic_risk': 1,    // Bottom layer
            'flood_prone': 2,
            'landslide_prone': 3,
            'fault_line': 4        // Top layer (accident-prone)
        };
        return (layerOrder[a.zone_type] || 5) - (layerOrder[b.zone_type] || 5);
    });
    
    sortedZones.forEach(zone => {
        if (zone.coordinates) {
            let coordinates;
            try {
                coordinates = JSON.parse(zone.coordinates);
            } catch (e) {
                console.error('Invalid coordinates for zone:', zone.name);
                return;
            }
            
            // Convert coordinates to Leaflet format
            const latLngs = coordinates.map(coord => [coord.lat, coord.lng]);
            
            let fillColor, borderColor, fillOpacity, weight;
            switch (zone.zone_type) {
                case 'flood_prone':
                    fillColor = zone.risk_level === 'critical' ? '#1e40af' : 
                               zone.risk_level === 'high' ? '#3b82f6' : '#93c5fd';
                    borderColor = '#1e40af';
                    fillOpacity = 0.4;
                    weight = 2;
                    break;
                case 'landslide_prone':
                    fillColor = zone.risk_level === 'critical' ? '#92400e' : 
                               zone.risk_level === 'high' ? '#d97706' : '#fbbf24';
                    borderColor = '#92400e';
                    fillOpacity = 0.4;
                    weight = 2;
                    break;
                case 'fault_line': // Accident-prone roadway areas
                    fillColor = zone.risk_level === 'critical' ? '#dc2626' : 
                               zone.risk_level === 'high' ? '#ef4444' : '#fca5a5';
                    borderColor = '#dc2626';
                    fillOpacity = 0.6; // More visible for road safety
                    weight = 4; // Thicker lines for roadways
                    break;
                case 'volcanic_risk':
                    fillColor = zone.risk_level === 'critical' ? '#7c2d12' : 
                               zone.risk_level === 'high' ? '#ea580c' : '#fed7aa';
                    borderColor = '#7c2d12';
                    fillOpacity = 0.15; // Lower opacity so other hazards show through
                    weight = 1;
                    break;
                default:
                    fillColor = '#6b7280';
                    borderColor = '#374151';
                    fillOpacity = 0.3;
                    weight = 2;
            }
            
            let hazardLayer;
            if (zone.zone_type === 'fault_line') {
                // Create polyline for roadway accident-prone areas
                hazardLayer = L.polyline(latLngs, {
                    color: borderColor,
                    weight: weight,
                    opacity: 0.8,
                    dashArray: zone.risk_level === 'critical' ? '10, 5' : null
                });
            } else if (zone.zone_type === 'volcanic_risk') {
                const center = getPolygonCenter(latLngs);
                const radius = getPolygonRadius(latLngs, center);
                hazardLayer = L.circle(center, {
                    radius: radius,
                    fillColor: fillColor,
                    fillOpacity: fillOpacity,
                    color: borderColor,
                    weight: weight,
                    opacity: 0.8
                });
            } else {
                // Create polygon for area-based hazards
                hazardLayer = L.polygon(latLngs, {
                    fillColor: fillColor,
                    fillOpacity: fillOpacity,
                    color: borderColor,
                    weight: weight,
                    opacity: 0.8
                });
            }
            
            // Create popup content
            const zoneTypeNames = {
                'flood_prone': 'Flood Prone Area',
                'landslide_prone': 'Landslide Prone Area', 
                'fault_line': 'Accident Prone Roadway',
                'volcanic_risk': 'Volcanic Risk Area'
            };
            
            const popupContent = `
                <div class="p-2">
                    <h6 class="mb-2">${zone.name}</h6>
                    <p class="mb-1 small"><strong>Type:</strong> ${zoneTypeNames[zone.zone_type]}</p>
                    <p class="mb-1 small"><strong>Risk Level:</strong> 
                        <span class="badge bg-${zone.risk_level === 'critical' ? 'danger' : 
                                                zone.risk_level === 'high' ? 'warning' : 
                                                zone.risk_level === 'medium' ? 'info' : 'success'}">
                            ${zone.risk_level.toUpperCase()}
                        </span>
                    </p>
                    <p class="mb-2 small">${zone.description}</p>
                    <div class="alert alert-warning alert-sm mb-0">
                        <small><i class="bi bi-exclamation-triangle me-1"></i>
                        ${zone.zone_type === 'flood_prone' ? 'Avoid during heavy rains' :
                          zone.zone_type === 'landslide_prone' ? 'Exercise caution during wet weather' :
                          zone.zone_type === 'fault_line' ? 'Drive carefully - high accident area' :
                          'Monitor PHIVOLCS volcanic activity alerts'}
                        </small>
                    </div>
                </div>
            `;
            
            hazardLayer.bindPopup(popupContent);
            
            switch (zone.zone_type) {
                case 'flood_prone':
                    floodLayer.addLayer(hazardLayer);
                    break;
                case 'landslide_prone':
                    landslideLayer.addLayer(hazardLayer);
                    break;
                case 'fault_line':
                    accidentLayer.addLayer(hazardLayer);
                    break;
                case 'volcanic_risk':
                    volcanicLayer.addLayer(hazardLayer);
                    break;
            }
        }
    });
}

function getPolygonCenter(latLngs) {
    let lat = 0, lng = 0;
    latLngs.forEach(coord => {
        lat += coord[0];
        lng += coord[1];
    });
    return [lat / latLngs.length, lng / latLngs.length];
}

function getPolygonRadius(latLngs, center) {
    let maxDistance = 0;
    latLngs.forEach(coord => {
        const distance = Math.sqrt(
            Math.pow(coord[0] - center[0], 2) + Math.pow(coord[1] - center[1], 2)
        );
        maxDistance = Math.max(maxDistance, distance);
    });
    return maxDistance * 111000; // Convert to meters approximately
}

function addCustomControls() {
    // Add custom zoom controls (re-implements the standard zoom buttons removed from HTML)
    const customControl = L.control({position: 'topleft'});
    
    customControl.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'custom-controls');
        div.innerHTML = `
            <div class="btn-group-vertical" role="group">
                <button class="btn btn-light border btn-sm" onclick="map.zoomIn()" title="Zoom In">
                    <i class="bi bi-plus"></i>
                </button>
                <button class="btn btn-light border btn-sm" onclick="map.zoomOut()" title="Zoom Out">
                    <i class="bi bi-dash"></i>
                </button>
                <button class="btn btn-light border btn-sm" onclick="resetView()" title="Reset View">
                    <i class="bi bi-house"></i>
                </button>
            </div>
        `;
        return div;
    };
    
    customControl.addTo(map);
}

function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // Remove existing user location marker
                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                }
                
                // Create user location icon
                const userIcon = L.divIcon({
                    className: 'user-location-marker',
                    html: '<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"><i class="bi bi-person-fill" style="font-size: 10px;"></i></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                
                // Add user location marker
                userLocationMarker = L.marker([lat, lng], {
                    icon: userIcon
                }).addTo(map);
                
                userLocationMarker.bindPopup('<div class="p-2"><h6 class="mb-0">Your Location</h6></div>');
                
                // Center map on user location
                map.setView([lat, lng], 15);
                
                AgoncilloAlert.showInAppNotification('Location Found', 'Your current location has been marked on the map', 'success');
            },
            function(error) {
                AgoncilloAlert.showInAppNotification('Location Error', 'Could not get your current location: ' + error.message, 'error');
            }
        );
    } else {
        AgoncilloAlert.showInAppNotification('Not Supported', 'Geolocation is not supported by this browser', 'warning');
    }
}

function focusOnLocation(lat, lng) {
    if (map) {
        map.setView([lat, lng], 16);
        
        // Find and open popup for this location
        evacuationCenterMarkers.forEach(marker => {
            const markerLatLng = marker.getLatLng();
            if (Math.abs(markerLatLng.lat - lat) < 0.0001 && Math.abs(markerLatLng.lng - lng) < 0.0001) {
                marker.openPopup();
            }
        });
        
        AgoncilloAlert.showInAppNotification('Location Focus', 'Map centered on selected location', 'info');
    }
}

function getDirections(lat, lng) {
    // Just pin the location of the evacuation center on Google Maps
    const url = `https://www.google.com/maps/place/${lat},${lng}`;
    window.open(url, '_blank');
}

function addSampleRoutes() {
    // Clear existing routes
    routeLayer.clearLayers();
    
    // Add sample emergency routes (you would get these from your database)
    const routes = [
        {
            name: "Primary Evacuation Route 1",
            coordinates: [
                [13.9300, 120.9300],
                [13.9320, 120.9320],
                [13.9340, 120.9340],
                [13.9360, 120.9360]
            ],
            color: "#0d6efd"
        },
        {
            name: "Alternative Route 2",
            coordinates: [
                [13.9280, 120.9280],
                [13.9300, 120.9300],
                [13.9320, 120.9320]
            ],
            color: "#6c757d"
        }
    ];
    
    routes.forEach(route => {
        const polyline = L.polyline(route.coordinates, {
            color: route.color,
            weight: 4,
            opacity: 0.8
        });
        
        polyline.bindPopup(`<div class="p-2"><h6 class="mb-0">${route.name}</h6></div>`);
        routeLayer.addLayer(polyline);
    });
}

function callEmergency(number) {
    // FIX: Replaced banned 'confirm()' with custom notification.
    AgoncilloAlert.showInAppNotification('Call Attempted', `Attempting to call ${number}...`, 'info');
    window.location.href = `tel:${number}`;
}

const apiKey = "b94c896f1adfd27f17a915b9422e4db9"; // <-- OpenWeatherMap API key

function fetchWeather(lat, lon) {
    fetch(`https://api.openweathermap.org/data/3.0/onecall?lat=${lat}&lon=${lon}&exclude=minutely,hourly,alerts&units=metric&appid=${apiKey}`)
        .then(response => response.json())
        .then(data => {
            const current = data.current;

            document.getElementById("temperature").innerText = `${current.temp}°C`;
            document.getElementById("weather-desc").innerText = current.weather[0].description;
            document.getElementById("humidity").innerText = `${current.humidity}%`;
            document.getElementById("wind").innerText = `${current.wind_speed} km/h`;
            document.getElementById("rain").innerText = current.rain ? `${current.rain["1h"]}%` : "0%";

            // Pick icon based on condition
            const icon = current.weather[0].main.toLowerCase();
            let iconClass = "bi-cloud-sun";
            if (icon.includes("rain")) iconClass = "bi-cloud-rain";
            if (icon.includes("cloud")) iconClass = "bi-cloud";
            if (icon.includes("clear")) iconClass = "bi-sun";
            document.getElementById("weather-icon").className = `bi ${iconClass} display-4 text-warning`;
        })
        .catch(err => {
            console.error("Weather fetch failed", err);
            document.getElementById("weather-desc").innerText = "Unable to load weather.";
        });
}

// Get user location
function getLocationAndFetch() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                fetchWeather(position.coords.latitude, position.coords.longitude);
            },
            error => {
                console.error("Location error:", error);
                document.getElementById("weather-desc").innerText = "Location not available.";
            }
        );
    } else {
        document.getElementById("weather-desc").innerText = "Geolocation not supported.";
    }
}

// Run on page load + refresh every 10 minutes
getLocationAndFetch();
setInterval(getLocationAndFetch, 600000);

// NEW FUNCTION: Initializes all map controls, runs AFTER map is created.
function initializeMapControls() {
    // 1. BURGER MENU TOGGLE LOGIC
    const toggleBtn = document.getElementById("toggleMapControlsBtn");
    const toggleIcon = document.getElementById("toggleMapIcon");
    const controlsBody = document.getElementById("mapControlsBody");

    if (toggleBtn && toggleIcon && controlsBody) {
        toggleBtn.addEventListener("click", () => {
            
            // Toggle the 'collapsed' class
            controlsBody.classList.toggle("collapsed");

            // Switch icon
            if (controlsBody.classList.contains("collapsed")) {
                toggleIcon.classList.remove("bi-x-lg");
                toggleIcon.classList.add("bi-list");
            } else {
                toggleIcon.classList.remove("bi-list");
                toggleIcon.classList.add("bi-x-lg");
            }
        });
    }

    // 2. LAYER TOGGLE LOGIC (Event Listeners for Checkboxes)
    const layerControls = [
        { id: 'toggleEvacuation', layer: evacuationLayer },
        { id: 'toggleIncidents', layer: incidentLayer },
        { id: 'toggleRoutes', layer: routeLayer, callback: addSampleRoutes }, // Call addSampleRoutes on activation
        { id: 'toggleFlood', layer: floodLayer },
        { id: 'toggleLandslide', layer: landslideLayer },
        { id: 'toggleAccident', layer: accidentLayer },
        { id: 'toggleVolcanic', layer: volcanicLayer }
    ];

    layerControls.forEach(control => {
        const checkbox = document.getElementById(control.id);
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                // Since this runs after map initialization, 'map' should be defined.
                if (this.checked) {
                    if (control.callback) {
                        control.callback(); 
                    }
                    map.addLayer(control.layer);
                } else {
                    map.removeLayer(control.layer);
                }
            });
        }
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

</script>

<?php include '../includes/footer.php'; ?>
