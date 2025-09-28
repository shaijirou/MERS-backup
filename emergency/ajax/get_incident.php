<?php
require_once '../../config/config.php';

// Check if user is logged in and is emergency personnel
if (!isLoggedIn() || !isEmergency()) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

if (isset($_GET['id'])) {
    $incident_id = (int)$_GET['id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT ir.*, u.first_name, u.last_name, u.phone, u.email 
              FROM incident_reports ir 
              JOIN users u ON ir.user_id = u.id 
              WHERE ir.id = :incident_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':incident_id', $incident_id);
    $stmt->execute();
    $incident = $stmt->fetch();
    
    if ($incident) {
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6>Emergency Report Information</h6>
                <p><strong>Report Number:</strong> <?php echo htmlspecialchars($incident['report_number']); ?></p>
                <p><strong>Emergency Type:</strong> <?php echo htmlspecialchars($incident['incident_type']); ?></p>
                <p><strong>Urgency Level:</strong> <span class="badge bg-<?php echo getUrgencyColor($incident['urgency_level']); ?>"><?php echo ucfirst($incident['urgency_level']); ?></span></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($incident['location']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($incident['description']); ?></p>
                <p><strong>People Affected:</strong> <?php echo ucfirst($incident['people_affected']); ?></p>
                <p><strong>Injuries Reported:</strong> <span class="badge bg-<?php echo $incident['injuries'] == 'yes' ? 'danger' : 'success'; ?>"><?php echo ucfirst($incident['injuries']); ?></span></p>
            </div>
            <div class="col-md-6">
                <h6>Reporter Contact Information</h6>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($incident['first_name'] . ' ' . $incident['last_name']); ?></p>
                <p><strong>Primary Phone:</strong> <a href="tel:<?php echo htmlspecialchars($incident['phone']); ?>"><?php echo htmlspecialchars($incident['phone']); ?></a></p>
                <p><strong>Contact Number:</strong> <a href="tel:<?php echo htmlspecialchars($incident['contact_number']); ?>"><?php echo htmlspecialchars($incident['contact_number']); ?></a></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($incident['email']); ?>"><?php echo htmlspecialchars($incident['email']); ?></a></p>
                
                <h6 class="mt-3">Response Status</h6>
                <p><strong>Current Status:</strong> <span class="badge bg-<?php echo getStatusColor($incident['response_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $incident['response_status'])); ?></span></p>
                <p><strong>Reported:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['created_at'])); ?></p>
                <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($incident['updated_at'])); ?></p>
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
                                    <img src="../<?php echo htmlspecialchars($photo); ?>" class="img-fluid rounded" alt="Emergency Incident Photo">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Emergency Response Protocol:</strong> Ensure proper safety measures are in place before responding. Contact dispatch for additional support if needed.
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
    } else {
        echo '<p class="text-center">Emergency incident not found.</p>';
    }
} else {
    echo '<p class="text-center">Invalid request.</p>';
}
?>
