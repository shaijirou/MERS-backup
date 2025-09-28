<?php
require_once '../../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

if (isset($_GET['id'])) {
    $incident_id = (int)$_GET['id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email, u.address, u.barangay 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.id = :incident_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':incident_id', $incident_id);
    $stmt->execute();
    $incident = $stmt->fetch();
    
    if ($incident) {
        $is_fire_related = isFireRelated($incident['incident_type']);
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6><?php echo $is_fire_related ? 'Fire' : ''; ?> Incident Information</h6>
                <?php if ($is_fire_related): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-fire me-2"></i><strong>FIRE EMERGENCY</strong>
                    </div>
                <?php endif; ?>
                <p><strong>Report Number:</strong> <?php echo htmlspecialchars($incident['report_number']); ?></p>
                <p><strong>Incident Type:</strong> 
                    <?php if ($is_fire_related): ?>
                        <i class="fas fa-fire text-danger me-1"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($incident['incident_type']); ?>
                </p>
                <p><strong>Urgency Level:</strong> <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?>"><?php echo ucfirst($incident['urgency_level']); ?></span></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($incident['location']); ?></p>
                <p><strong>Barangay:</strong> <?php echo htmlspecialchars($incident['barangay']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($incident['description']); ?></p>
                <p><strong>People Affected:</strong> <?php echo ucfirst($incident['people_affected']); ?></p>
                <p><strong>Injuries:</strong> <span class="badge bg-<?php echo $incident['injuries'] == 'yes' ? 'danger' : 'success'; ?>"><?php echo ucfirst($incident['injuries']); ?></span></p>
            </div>
            <div class="col-md-6">
                <h6>Reporter Contact Information</h6>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></p>
                <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($incident['phone']); ?>"><?php echo htmlspecialchars($incident['phone']); ?></a></p>
                <p><strong>Contact Number:</strong> <a href="tel:<?php echo htmlspecialchars($incident['contact_number']); ?>"><?php echo htmlspecialchars($incident['contact_number']); ?></a></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($incident['email']); ?>"><?php echo htmlspecialchars($incident['email']); ?></a></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($incident['address']); ?></p>
                
                <h6 class="mt-3">Fire Department Response</h6>
                <p><strong>Current Status:</strong> <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>"><?php echo getFireStatus($incident['response_status']); ?></span></p>
                <p><strong>Reported:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['created_at'])); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['updated_at'])); ?></p>
                
                <?php if ($is_fire_related): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Fire Safety Reminder:</strong><br>
                        <small>Ensure water supply is adequate. Check for exposures. Establish command post upwind.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($incident['photos']): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Incident Photos</h6>
                    <?php 
                    $photos = json_decode($incident['photos'], true);
                    if ($photos && is_array($photos)):
                    ?>
                        <div class="row">
                            <?php foreach ($photos as $photo): ?>
                                <div class="col-md-4 mb-2">
                                    <img src="../<?php echo htmlspecialchars($photo); ?>" class="img-fluid rounded" alt="Fire Incident Photo">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-fire-extinguisher me-2"></i>
                    <strong>Fire Department Protocol:</strong> Follow RECEO-VS procedures. Ensure crew safety at all times. Coordinate with other emergency services as needed.
                </div>
            </div>
        </div>
        <?php
        
        function getUrgencyColor($urgency) {
            switch ($urgency) {
                case 'low': return 'success';
                case 'medium': return 'warning';
                case 'high': return 'danger';
                case 'critical': return 'dark';
                default: return 'secondary';
            }
        }
        
        function getStatusColor($status) {
            switch ($status) {
                case 'notified': return 'info';
                case 'responding': return 'warning';
                case 'on_scene': return 'danger';
                case 'resolved': return 'success';
                default: return 'secondary';
            }
        }
        
        function getFireStatus($status) {
            switch ($status) {
                case 'notified': return 'Notified';
                case 'responding': return 'En Route';
                case 'on_scene': return 'Fighting Fire';
                case 'resolved': return 'Fire Out';
                default: return ucfirst(str_replace('_', ' ', $status));
            }
        }
        
        function isFireRelated($incident_type) {
            $fire_keywords = ['fire', 'burn', 'explosion', 'smoke'];
            foreach ($fire_keywords as $keyword) {
                if (stripos($incident_type, $keyword) !== false) {
                    return true;
                }
            }
            return false;
        }
    } else {
        echo '<p class="text-center">Fire incident not found.</p>';
    }
} else {
    echo '<p class="text-center">Invalid request.</p>';
}
?>
