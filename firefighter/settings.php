<?php
require_once '../config/config.php';

// Check if user is logged in and is firefighter
if (!isLoggedIn() || !isFirefighter()) {
    redirect('../index.php');
}

$page_title = 'Firefighter Settings';
$additional_css = ['assets/css/admin.css'];

// Get database connection
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $notification_email = isset($_POST['notification_email']) ? 1 : 0;
        $notification_sms = isset($_POST['notification_sms']) ? 1 : 0;
        $auto_refresh = (int)$_POST['auto_refresh'];
        $sound_alerts = isset($_POST['sound_alerts']) ? 1 : 0;
        $fire_protocol_reminders = isset($_POST['fire_protocol_reminders']) ? 1 : 0;
        
        // Update user notification preferences
        $stmt = $db->prepare("UPDATE users SET email_notifications = :email_notifications, sms_notifications = :sms_notifications WHERE id = :user_id");
        $stmt->bindParam(':email_notifications', $notification_email);
        $stmt->bindParam(':sms_notifications', $notification_sms);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $success_message = "Settings updated successfully!";
        
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

// Default settings if none exist
$settings = [
    'notification_email' => $user['email_notifications'] ?? 1,
    'notification_sms' => $user['sms_notifications'] ?? 1,
    'auto_refresh' => 30,
    'sound_alerts' => 1,
    'fire_protocol_reminders' => 1
];

include '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">

<div class="d-flex" id="wrapper">
        
    <?php include 'includes/sidebar.php'; ?>
    
     
    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
            <div class="row my-4">
                <div class="col-12">
                    <h2><i class="bi bi-gear text-danger me-2"></i>Fire Department Settings</h2>
                    <p class="text-muted">Configure your firefighter dashboard preferences and notifications</p>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <form method="POST">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Notification Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="notification_email" 
                                                   name="notification_email" <?php echo $settings['notification_email'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notification_email">
                                                <i class="bi bi-envelope me-2"></i>Email Notifications
                                            </label>
                                            <small class="form-text text-muted d-block">Receive fire incident alerts via email</small>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="notification_sms" 
                                                   name="notification_sms" <?php echo $settings['notification_sms'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notification_sms">
                                                <i class="bi bi-phone me-2"></i>SMS Notifications
                                            </label>
                                            <small class="form-text text-muted d-block">Receive fire incident alerts via SMS</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="sound_alerts" 
                                                   name="sound_alerts" <?php echo $settings['sound_alerts'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sound_alerts">
                                                <i class="bi bi-volume-up me-2"></i>Sound Alerts
                                            </label>
                                            <small class="form-text text-muted d-block">Play sound when new fire incidents arrive</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="auto_refresh" class="form-label">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Auto Refresh Interval
                                            </label>
                                            <select class="form-select" id="auto_refresh" name="auto_refresh">
                                                <option value="15" <?php echo $settings['auto_refresh'] == 15 ? 'selected' : ''; ?>>15 seconds</option>
                                                <option value="30" <?php echo $settings['auto_refresh'] == 30 ? 'selected' : ''; ?>>30 seconds</option>
                                                <option value="60" <?php echo $settings['auto_refresh'] == 60 ? 'selected' : ''; ?>>1 minute</option>
                                                <option value="300" <?php echo $settings['auto_refresh'] == 300 ? 'selected' : ''; ?>>5 minutes</option>
                                            </select>
                                            <small class="form-text text-muted">How often to refresh incident data</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-droplet me-2"></i>Fire Safety Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="fire_protocol_reminders" 
                                           name="fire_protocol_reminders" <?php echo $settings['fire_protocol_reminders'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="fire_protocol_reminders">
                                        <i class="bi bi-clipboard-check me-2"></i>Fire Protocol Reminders
                                    </label>
                                    <small class="form-text text-muted d-block">Show RECEO-VS protocol reminders on dashboard</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle me-2"></i>Fire Safety Protocols</h6>
                                    <p class="mb-2"><strong>RECEO-VS:</strong></p>
                                    <ul class="mb-0">
                                        <li><strong>R</strong>escue - Save lives first</li>
                                        <li><strong>E</strong>xposures - Protect adjacent structures</li>
                                        <li><strong>C</strong>onfinement - Contain the fire</li>
                                        <li><strong>E</strong>xtinguishment - Put out the fire</li>
                                        <li><strong>O</strong>verhaul - Ensure complete extinguishment</li>
                                        <li><strong>V</strong>entilation - Remove smoke and heat</li>
                                        <li><strong>S</strong>alvage - Minimize property damage</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-check me-1"></i> Save Settings
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
                
                 
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Settings Information</h6>
                        </div>
                        <div class="card-body">
                            <h6>Notification Settings</h6>
                            <p class="small text-muted">Configure how you want to receive fire incident notifications. Email and SMS notifications ensure you're always informed of new emergencies.</p>
                            
                            <h6>Auto Refresh</h6>
                            <p class="small text-muted">Set how frequently the dashboard updates with new incident data. Shorter intervals provide more real-time updates but may use more bandwidth.</p>
                            
                            <h6>Fire Safety Protocols</h6>
                            <p class="small text-muted">Enable protocol reminders to keep fire safety procedures visible on your dashboard for quick reference during emergencies.</p>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Emergency Contacts</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Fire Chief:</strong><br>
                                <small>(043) 123-4567</small>
                            </div>
                            <div class="mb-2">
                                <strong>Emergency Dispatch:</strong><br>
                                <small>(043) 911-FIRE</small>
                            </div>
                            <div class="mb-2">
                                <strong>MDRRMO:</strong><br>
                                <small>(043) 456-7890</small>
                            </div>
                            <div>
                                <strong>Municipal Hall:</strong><br>
                                <small>(043) 789-0123</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle sidebar
document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

// Test notification sound
function testSound() {
    if (document.getElementById('sound_alerts').checked) {
        // Create a simple beep sound
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    }
}

// Add test sound button
document.getElementById('sound_alerts').addEventListener('change', function() {
    if (this.checked) {
        setTimeout(testSound, 100);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
