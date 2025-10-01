<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid incident ID</div>';
    exit;
}

$incident_id = $_GET['id'];

// Get incident details with reporter and reviewer information
$query = "SELECT ir.*, 
                 u.first_name as reporter_first_name, u.last_name as reporter_last_name, 
                 u.email as reporter_email, u.phone as reporter_phone, u.barangay as reporter_barangay,
                 CONCAT_WS(' ', u.house_number, u.street, u.landmark, u.barangay) AS reporter_address,
                 admin.first_name as reviewed_by_name, admin.last_name as reviewed_by_lastname
          FROM incident_reports ir 
          LEFT JOIN users u ON ir.user_id = u.id 
          LEFT JOIN users admin ON ir.reviewed_by = admin.id
          WHERE ir.id = :incident_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':incident_id', $incident_id, PDO::PARAM_INT);
$stmt->execute();
$incident = $stmt->fetch();

if (!$incident) {
    echo '<div class="alert alert-danger">Incident not found</div>';
    exit;
}

// Format status badge
$status_class = '';
switch ($incident['status']) {
    case 'pending': $status_class = 'bg-warning'; break;
    case 'in_progress': $status_class = 'bg-info'; break;
    case 'resolved': $status_class = 'bg-success'; break;
    case 'closed': $status_class = 'bg-secondary'; break;
}

// Format urgency badge
$urgency = $incident['urgency_level'] ?? '';
$urgency_class = '';
switch ($urgency) {
    case 'low': $urgency_class = 'bg-success'; break;
    case 'medium': $urgency_class = 'bg-warning'; break;
    case 'high': $urgency_class = 'bg-orange'; break;
    case 'critical': $urgency_class = 'bg-danger'; break;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-muted mb-3">Incident Information</h6>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Photo Evidence:</label>
            <div>
                <?php 
                $photos = [];
                if (!empty($incident['photos'])) {
                    $decoded_photos = json_decode($incident['photos'], true);
                    if (is_array($decoded_photos)) {
                        $photos = $decoded_photos;
                    }
                }
                
                if (!empty($photos)): ?>
                    <div class="row g-2">
                        <?php foreach ($photos as $index => $photo): ?>
                            <?php 
                            $photo_path = '';
                            if (file_exists('../uploads/incidents/' . $photo)) {
                                $photo_path = '../uploads/incidents/' . $photo;
                            } elseif (file_exists('uploads/incidents/' . $photo)) {
                                $photo_path = 'uploads/incidents/' . $photo;
                            } elseif (file_exists($photo)) {
                                $photo_path = $photo;
                            }
                            ?>
                            <?php if ($photo_path): ?>
                                <div class="col-md-6">
                                    <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                                         alt="Incident Photo <?php echo $index + 1; ?>" 
                                         class="img-fluid rounded border" 
                                         style="max-height: 150px; width: 100%; object-fit: cover; cursor: pointer;"
                                         onclick="showImageModal('<?php echo htmlspecialchars($photo_path); ?>')">
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Click any photo to view full size</small>
                <?php else: ?>
                    <div class="border rounded p-3 text-center bg-light" style="height: 150px; display: flex; align-items: center; justify-content: center;">
                        <div>
                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                            <br>
                            <small class="text-muted">No photos available</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Incident Type:</label>
            <div>
                <span class="badge bg-secondary"><?php echo ucfirst($incident['incident_type']); ?></span>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Description:</label>
            <div class="border rounded p-2 bg-light">
                <?php echo nl2br(htmlspecialchars($incident['description'])); ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Location:</label>
            <div><?php echo htmlspecialchars($incident['location']); ?></div>
        </div>
        
        <?php if ($incident['latitude'] && $incident['longitude']): ?>
        <div class="mb-3">
            <label class="form-label fw-bold">Coordinates:</label>
            <div>
                <small class="text-muted">
                    Lat: <?php echo $incident['latitude']; ?>, 
                    Lng: <?php echo $incident['longitude']; ?>
                </small>
                <br>
                <a href="https://maps.google.com/?q=<?php echo $incident['latitude']; ?>,<?php echo $incident['longitude']; ?>" 
                   target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                    <i class="bi bi-geo-alt"></i> View on Map
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Severity Level:</label>
            <div>
                <?php if ($urgency): ?>
                    <span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($urgency); ?></span>
                <?php else: ?>
                    <span class="text-muted">Not specified</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Status:</label>
            <div>
                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?></span>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-muted mb-3">Reporter Information</h6>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Name:</label>
            <div><?php echo htmlspecialchars($incident['reporter_first_name'] . ' ' . $incident['reporter_last_name']); ?></div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Email:</label>
            <div>
                <a href="mailto:<?php echo htmlspecialchars($incident['reporter_email']); ?>">
                    <?php echo htmlspecialchars($incident['reporter_email']); ?>
                </a>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Phone:</label>
            <div>
                <?php if ($incident['reporter_phone']): ?>
                    <a href="tel:<?php echo htmlspecialchars($incident['reporter_phone']); ?>">
                        <?php echo htmlspecialchars($incident['reporter_phone']); ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">Not provided</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Barangay:</label>
            <div><?php echo htmlspecialchars($incident['reporter_barangay']); ?></div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Address:</label>
            <div>
                <?php echo $incident['reporter_address'] ? htmlspecialchars($incident['reporter_address']) : '<span class="text-muted">Not provided</span>'; ?>
            </div>
        </div>
        
        <hr>
        
        <h6 class="text-muted mb-3">Administrative Information</h6>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Reported At:</label>
            <div>
                <?php echo date('F j, Y g:i A', strtotime($incident['created_at'])); ?>
                <br>
                <small class="text-muted"><?php echo timeAgo($incident['created_at']); ?></small>
            </div>
        </div>
        
        <?php if ($incident['reviewed_by']): ?>
        <div class="mb-3">
            <label class="form-label fw-bold">Reviewed By:</label>
            <div><?php echo htmlspecialchars($incident['reviewed_by_name'] . ' ' . $incident['reviewed_by_lastname']); ?></div>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Reviewed At:</label>
            <div>
                <?php echo date('F j, Y g:i A', strtotime($incident['reviewed_at'])); ?>
                <br>
                <small class="text-muted"><?php echo timeAgo($incident['reviewed_at']); ?></small>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($incident['resolution_notes']): ?>
        <div class="mb-3">
            <label class="form-label fw-bold">Admin Notes:</label>
            <div class="border rounded p-2 bg-light">
                <?php echo nl2br(htmlspecialchars($incident['resolution_notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Last Updated:</label>
            <div>
                <?php echo date('F j, Y g:i A', strtotime($incident['updated_at'])); ?>
                <br>
                <small class="text-muted"><?php echo timeAgo($incident['updated_at']); ?></small>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 pt-3 border-top">
    <div class="d-flex gap-2">
        <button class="btn btn-warning" onclick="updateIncident(<?php echo $incident['id']; ?>, '<?php echo $incident['status']; ?>', '<?php echo htmlspecialchars($incident['resolution_notes']); ?>')">
            <i class="bi bi-pencil"></i> Update Status
        </button>
        <?php if ($incident['latitude'] && $incident['longitude']): ?>
        <a href="https://maps.google.com/?q=<?php echo $incident['latitude']; ?>,<?php echo $incident['longitude']; ?>" 
           target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-geo-alt"></i> View Location
        </a>
        <?php endif; ?>
    </div>
</div>
