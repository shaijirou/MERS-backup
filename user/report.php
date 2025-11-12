<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'Report Incident';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $incident_type = sanitizeInput($_POST['incident_type']);
    $location = sanitizeInput($_POST['location']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $description = sanitizeInput($_POST['description']);
    $people_affected = sanitizeInput($_POST['people_affected']);
    $injuries = sanitizeInput($_POST['injuries']);
    $contact_number = sanitizeInput($_POST['contact_number']);
    
    $photos = [];
    if (isset($_FILES['photos'])) {
        $upload_dir = '../uploads/incidents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
            if ($_FILES['photos']['error'][$i] == 0) {
                $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                $new_filename = 'incident_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $upload_path)) {
                    $photos[] = $upload_path;
                }
            }
        }
    }
    
    if (empty($incident_type) || empty($location) || empty($description) || empty($contact_number) || empty($photos)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Generate report number
        $report_number = generateReportNumber();
        
        $query = "INSERT INTO incident_reports (report_number, user_id, incident_type, location, latitude, longitude, description, people_affected, injuries, photos, contact_number) 
                  VALUES (:report_number, :user_id, :incident_type, :location, :latitude, :longitude, :description, :people_affected, :injuries, :photos, :contact_number)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':report_number', $report_number);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':incident_type', $incident_type);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':people_affected', $people_affected);
        $stmt->bindParam(':injuries', $injuries);
        $photos_json = json_encode($photos);
        $stmt->bindParam(':photos', $photos_json);
        $stmt->bindParam(':contact_number', $contact_number);
        
        if ($stmt->execute()) {
            $success_message = 'Incident report submitted successfully! Report Number: ' . $report_number;
            logActivity($_SESSION['user_id'], 'Incident report submitted', 'incident_reports', $db->lastInsertId());
        } else {
            $error_message = 'Failed to submit incident report. Please try again.';
        }
    }
}

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
                    <a class="nav-link" href="map.php"><i class="bi bi-map-fill me-1"></i> Evacuation Map</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <!-- Added My Reports link to navbar -->
                <li class="nav-item">
                    <a class="nav-link" href="my-reports.php"><i class="bi bi-file-earmark-text-fill me-1"></i> My Reports</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                         <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $_SESSION['user_name']; ?></span>
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
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="card-title mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Report an Incident</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
                            <div class="mt-3">
                                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                                <a href="report.php" class="btn btn-outline-primary">Report Another Incident</a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="incident_type" class="form-label">Incident Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="incident_type" name="incident_type" required>
                                    <option value="" selected disabled>Select incident type</option>
                                    <option value="flood">Flood</option>
                                    <option value="fire">Fire</option>
                                    <option value="landslide">Landslide</option>
                                    <option value="earthquake">Earthquake</option>
                                    <option value="road_accident">Road Accident</option>
                                    <option value="medical_emergency">Medical Emergency</option>
                                    <option value="power_outage">Power Outage</option>
                                    <option value="water_supply_issue">Water Supply Issue</option>
                                    <option value="fallen_tree">Fallen Tree</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="location" name="location" rows="2" placeholder="Provide detailed location (street, barangay, landmarks)" required></textarea>
                            <div class="form-text">Be as specific as possible to help emergency responders locate the incident</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="latitude" class="form-label">Latitude (Optional)</label>
                                <input type="number" step="any" class="form-control" id="latitude" name="latitude" placeholder="e.g., 13.9094">
                            </div>
                            <div class="col-md-6">
                                <label for="longitude" class="form-label">Longitude (Optional)</label>
                                <input type="number" step="any" class="form-control" id="longitude" name="longitude" placeholder="e.g., 120.9200">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="getLocation()">
                                <i class="bi bi-geo-alt-fill me-1"></i>Get My Current Location
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe what happened, current situation, and any immediate dangers" required></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="people_affected" class="form-label">People Affected</label>
                                <select class="form-select" id="people_affected" name="people_affected">
                                    <option value="none">None</option>
                                    <option value="few">Few (1-5 people)</option>
                                    <option value="several">Several (6-20 people)</option>
                                    <option value="many">Many (20+ people)</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="injuries" class="form-label">Are there any injuries?</label>
                                <select class="form-select" id="injuries" name="injuries">
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="photos" class="form-label">Photos <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="photos" name="photos[]" accept="image/*" required>
                            <div class="form-text">Upload photos of the incident to help emergency responders assess the situation</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="contact_number" name="contact_number" placeholder="+63 9XX XXX XXXX" required>
                            <div class="form-text">Emergency responders may need to contact you for additional information</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Important:</strong> If this is a life-threatening emergency, please call emergency services immediately at <strong>911</strong> or contact MDRRMO Agoncillo at <strong>+63 912 345 6789</strong> before submitting this report.
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-send-fill me-2"></i>Submit Incident Report
                            </button>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            alert('Location captured successfully!');
        }, function(error) {
            alert('Error getting location: ' + error.message);
        });
    } else {
        alert('Geolocation is not supported by this browser.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
