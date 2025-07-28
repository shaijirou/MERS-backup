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
                    <p class="text-muted mb-0">Interactive map showing evacuation centers and emergency routes in Agoncillo</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#legendModal">
                        <i class="bi bi-info-circle me-1"></i>Legend
                    </button>
                    <button class="btn btn-primary" onclick="getCurrentLocation()">
                        <i class="bi bi-geo-alt-fill me-1"></i>My Location
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
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" onclick="toggleLayer('evacuation')">
                            <i class="bi bi-shield-check me-1"></i>Evacuation Centers
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleLayer('incidents')">
                            <i class="bi bi-exclamation-triangle me-1"></i>Recent Incidents
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleLayer('routes')">
                            <i class="bi bi-signpost-2 me-1"></i>Emergency Routes
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="map-container" style="height: 500px; background: #f8f9fa; position: relative;">
                        <!-- Interactive Map will be loaded here -->
                        <div id="map-placeholder" class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading map...</span>
                                </div>
                                <p class="text-muted">Loading interactive map...</p>
                            </div>
                        </div>
                        
                        <!-- Map Controls -->
                        <div class="position-absolute top-0 start-0 m-3">
                            <div class="btn-group-vertical" role="group">
                                <button class="btn btn-light border" onclick="zoomIn()" title="Zoom In">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <button class="btn btn-light border" onclick="zoomOut()" title="Zoom Out">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <button class="btn btn-light border" onclick="resetView()" title="Reset View">
                                    <i class="bi bi-house"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Evacuation Centers List -->
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
                                <button class="btn btn-sm btn-outline-primary" onclick="getDirections(<?php echo $center['latitude']; ?>, <?php echo $center['longitude']; ?>)">
                                    <i class="bi bi-navigation"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Emergency Contacts -->
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

            <!-- Weather Information -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cloud-sun me-2 text-warning"></i>Current Weather
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
                    <hr>
                    <div class="alert alert-warning alert-sm mb-0" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <small>Flood risk level: <strong>Medium</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Legend Modal -->
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
let layersControl;
let evacuationLayer, incidentLayer, routeLayer;

// Agoncillo coordinates (approximate center)
const AGONCILLO_CENTER = [13.9333, 120.9333];

// Initialize map on page load
document.addEventListener('DOMContentLoaded', function() {
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
    mapContainer.innerHTML = '<div id="leaflet-map" style="height: 500px; width: 100%;"></div>';
    
    // Initialize Leaflet map
    map = L.map('leaflet-map').setView(AGONCILLO_CENTER, 13);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Create layer groups
    evacuationLayer = L.layerGroup().addTo(map);
    incidentLayer = L.layerGroup();
    routeLayer = L.layerGroup();
    
    // Add evacuation center markers
    addEvacuationCenterMarkers();
    
    // Add recent incident markers
    addIncidentMarkers();
    
    // Add custom controls
    addCustomControls();
    
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

function addCustomControls() {
    // Add custom zoom controls (Leaflet has default ones, but we can customize)
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
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                const url = `https://www.google.com/maps/dir/${userLat},${userLng}/${lat},${lng}`;
                window.open(url, '_blank');
            },
            function(error) {
                const url = `https://www.google.com/maps/place/${lat},${lng}`;
                window.open(url, '_blank');
            }
        );
    } else {
        const url = `https://www.google.com/maps/place/${lat},${lng}`;
        window.open(url, '_blank');
    }
}

function toggleLayer(layerType) {
    const buttons = document.querySelectorAll('.btn-group .btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    event.target.classList.add('active');
    
    if (!map) return;
    
    // Toggle layers based on type
    switch(layerType) {
        case 'evacuation':
            if (map.hasLayer(evacuationLayer)) {
                map.removeLayer(evacuationLayer);
            } else {
                map.addLayer(evacuationLayer);
            }
            break;
        case 'incidents':
            if (map.hasLayer(incidentLayer)) {
                map.removeLayer(incidentLayer);
            } else {
                map.addLayer(incidentLayer);
            }
            break;
        case 'routes':
            if (map.hasLayer(routeLayer)) {
                map.removeLayer(routeLayer);
            } else {
                map.addLayer(routeLayer);
                // Add some sample routes
                addSampleRoutes();
            }
            break;
    }
    
    AgoncilloAlert.showInAppNotification('Layer Toggle', `${layerType} layer toggled`, 'info');
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

function resetView() {
    if (map) {
        map.setView(AGONCILLO_CENTER, 13);
        AgoncilloAlert.showInAppNotification('View Reset', 'Map view has been reset', 'info');
    }
}

function callEmergency(number) {
    if (confirm(`Call ${number}?`)) {
        window.location.href = `tel:${number}`;
    }
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
