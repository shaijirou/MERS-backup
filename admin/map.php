<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Emergency Response Map';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Get evacuation centers with coordinates
$centers_query = "SELECT * FROM evacuation_centers WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY name";
$centers_stmt = $db->prepare($centers_query);
$centers_stmt->execute();
$centers_result = $centers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent incidents with location data
$incidents_query = "SELECT ir.*, u.first_name, u.last_name, u.barangay 
                   FROM incident_reports ir 
                   LEFT JOIN users u ON ir.user_id = u.id 
                   WHERE ir.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   ORDER BY ir.created_at DESC 
                   LIMIT 50";
$incidents_stmt = $db->prepare($incidents_query);
$incidents_stmt->execute();
$incidents_result = $incidents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active alerts
$alerts_query = "SELECT * FROM alerts WHERE status = 'active' ORDER BY created_at DESC";
$alerts_stmt = $db->prepare($alerts_query);
$alerts_stmt->execute();
$alerts_result = $alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Barangay coordinates (approximate center points for Agoncillo, Batangas)
$barangay_coords = [
    'Adia' => ['lat' => 13.9234, 'lng' => 120.9123],
    'Bagong Sikat' => ['lat' => 13.9345, 'lng' => 120.9234],
    'Balangon' => ['lat' => 13.9456, 'lng' => 120.9345],
    'Banyaga' => ['lat' => 13.9567, 'lng' => 120.9456],
    'Bilibinwang' => ['lat' => 13.9678, 'lng' => 120.9567],
    'Coral na Munti' => ['lat' => 13.9789, 'lng' => 120.9678],
    'Guitna' => ['lat' => 13.9890, 'lng' => 120.9789],
    'Mabacong' => ['lat' => 13.9901, 'lng' => 120.9890],
    'Panhulan' => ['lat' => 14.0012, 'lng' => 120.9901],
    'Poblacion' => ['lat' => 14.0123, 'lng' => 121.0012],
    'Pook' => ['lat' => 14.0234, 'lng' => 121.0123],
    'Pulang Bato' => ['lat' => 14.0345, 'lng' => 121.0234],
    'San Jacinto' => ['lat' => 14.0456, 'lng' => 121.0345],
    'San Teodoro' => ['lat' => 14.0567, 'lng' => 121.0456],
    'Santa Rosa' => ['lat' => 14.0678, 'lng' => 121.0567],
    'Santo Tomas' => ['lat' => 14.0789, 'lng' => 121.0678],
    'Subic Ilaya' => ['lat' => 14.0890, 'lng' => 121.0789],
    'Subic Ibaba' => ['lat' => 14.0901, 'lng' => 121.0890]
];

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
#map {
    height: 600px;
    width: 100%;
    border-radius: 8px;
}
.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: white;
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.legend {
    background: white;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 15px;
}
.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}
.legend-icon {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    border-radius: 50%;
}
.incident-marker { background-color: #dc3545; }
.evacuation-marker { background-color: #28a745; }
.alert-marker { background-color: #ffc107; }
</style>

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
                <h1 class="h3 mb-0">Emergency Response Map</h1>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="refreshMap()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-outline-secondary" onclick="toggleFullscreen()">
                        <i class="bi bi-arrows-fullscreen"></i> Fullscreen
                    </button>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-9">
                    <div class="card">
                        <div class="card-body p-0 position-relative">
                            <div class="map-controls">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="showIncidents" checked>
                                    <label class="form-check-label" for="showIncidents">
                                        <small>Show Incidents</small>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="showEvacuation" checked>
                                    <label class="form-check-label" for="showEvacuation">
                                        <small>Show Evacuation Centers</small>
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="showAlerts" checked>
                                    <label class="form-check-label" for="showAlerts">
                                        <small>Show Alert Areas</small>
                                    </label>
                                </div>
                            </div>
                            <div id="map"></div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <!-- Legend -->
                    <div class="legend">
                        <h6><i class="bi bi-geo-alt"></i> Map Legend</h6>
                        <div class="legend-item">
                            <div class="legend-icon incident-marker"></div>
                            <small>Incident Reports</small>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon evacuation-marker"></div>
                            <small>Evacuation Centers</small>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon alert-marker"></div>
                            <small>Alert Areas</small>
                        </div>
                    </div>

                    <!-- Recent Incidents -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Recent Incidents
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($incidents_result as $incident): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo ucfirst($incident['incident_type']); ?></h6>
                                            <small><?php echo date('M j', strtotime($incident['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars(substr($incident['description'], 0, 60)) . '...'; ?></p>
                                        <small><?php echo htmlspecialchars($incident['barangay']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Active Alerts -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-bell"></i> Active Alerts
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($alerts_result as $alert): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($alert['title']); ?></h6>
                                            <span class="badge bg-<?php echo $alert['severity_level'] == 'critical' ? 'danger' : ($alert['severity_level'] == 'high' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($alert['severity_level']); ?>
                                            </span>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars(substr($alert['message'], 0, 60)) . '...'; ?></p>
                                        <small><?php echo htmlspecialchars($alert['affected_barangays']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize map centered on Agoncillo, Batangas
var map = L.map('map').setView([13.934542301563013, 120.92846530878772], 15);

// Add OpenStreetMap tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Layer groups for different marker types
var incidentLayer = L.layerGroup().addTo(map);
var evacuationLayer = L.layerGroup().addTo(map);
var alertLayer = L.layerGroup().addTo(map);

// Barangay coordinates
var barangayCoords = <?php echo json_encode($barangay_coords); ?>;

// Evacuation Centers Data
var evacuationCenters = <?php echo json_encode($centers_result); ?>;

// Incidents Data
var incidents = <?php echo json_encode($incidents_result); ?>;

// Alerts Data
var alerts = <?php echo json_encode($alerts_result); ?>;

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
            <h6><i class="bi bi-house"></i> ${center.name}</h6>
            <p><strong>Address:</strong> ${center.address}, ${center.barangay}</p>
            <p><strong>Capacity:</strong> ${center.current_occupancy}/${center.capacity} (${occupancyRate.toFixed(1)}%)</p>
            <p><strong>Status:</strong> <span class="badge bg-${center.status === 'active' ? 'success' : 'secondary'}">${center.status}</span></p>
            <p><strong>Contact:</strong> ${center.contact_person}<br>${center.contact_number}</p>
        </div>
    `;
    
    marker.bindPopup(popupContent);
    evacuationLayer.addLayer(marker);
});

// Add incident markers
incidents.forEach(function(incident) {
    if (incident.barangay && barangayCoords[incident.barangay]) {
        var coords = barangayCoords[incident.barangay];
        var markerColor = getSeverityColor(incident.severity);
        
        // Add small random offset to avoid overlapping markers
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
                <h6><i class="bi bi-exclamation-triangle"></i> ${incident.incident_type.charAt(0).toUpperCase() + incident.incident_type.slice(1)} Incident</h6>
                <p><strong>Location:</strong> ${incident.location}, ${incident.barangay}</p>
                <p><strong>Description:</strong> ${incident.description.substring(0, 100)}...</p>
                <p><strong>Severity:</strong> <span class="badge bg-${getSeverityBadgeClass(incident.severity)}">${incident.severity}</span></p>
                <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeClass(incident.status)}">${incident.status.replace('_', ' ')}</span></p>
                <p><strong>Reported by:</strong> ${incident.first_name} ${incident.last_name}</p>
                <p><strong>Date:</strong> ${new Date(incident.created_at).toLocaleDateString()}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        incidentLayer.addLayer(marker);
    }
});

// Add alert area markers
alerts.forEach(function(alert) {
    if (alert.affected_barangays && barangayCoords[alert.affected_barangays]) {
        var coords = barangayCoords[alert.affected_barangays];
        var markerColor = getSeverityColor(alert.severity_level);
        
        var marker = L.circle([coords.lat, coords.lng], {
            radius: 1000, // 1km radius
            fillColor: markerColor,
            color: markerColor,
            weight: 2,
            opacity: 0.6,
            fillOpacity: 0.2
        });
        
        var popupContent = `
            <div class="popup-content">
                <h6><i class="bi bi-bell"></i> ${alert.title}</h6>
                <p><strong>Type:</strong> ${alert.alert_type.charAt(0).toUpperCase() + alert.alert_type.slice(1)}</p>
                <p><strong>Message:</strong> ${alert.message}</p>
                <p><strong>Severity:</strong> <span class="badge bg-${getSeverityBadgeClass(alert.severity_level)}">${alert.severity_level}</span></p>
                <p><strong>Area:</strong> ${alert.affected_barangays}</p>
                <p><strong>Issued:</strong> ${new Date(alert.created_at).toLocaleDateString()}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        alertLayer.addLayer(marker);
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

// Toggle layer visibility
document.getElementById('showIncidents').addEventListener('change', function() {
    if (this.checked) {
        map.addLayer(incidentLayer);
    } else {
        map.removeLayer(incidentLayer);
    }
});

document.getElementById('showEvacuation').addEventListener('change', function() {
    if (this.checked) {
        map.addLayer(evacuationLayer);
    } else {
        map.removeLayer(evacuationLayer);
    }
});

document.getElementById('showAlerts').addEventListener('change', function() {
    if (this.checked) {
        map.addLayer(alertLayer);
    } else {
        map.removeLayer(alertLayer);
    }
});

// Refresh map function
function refreshMap() {
    location.reload();
}

// Toggle fullscreen
function toggleFullscreen() {
    var mapContainer = document.getElementById('map');
    if (mapContainer.requestFullscreen) {
        mapContainer.requestFullscreen();
    } else if (mapContainer.webkitRequestFullscreen) {
        mapContainer.webkitRequestFullscreen();
    } else if (mapContainer.msRequestFullscreen) {
        mapContainer.msRequestFullscreen();
    }
}
</script>

<?php include '../includes/footer.php'; ?>
