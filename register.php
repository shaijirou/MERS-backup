<?php
require_once 'config/config.php';

$page_title = 'Register';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

$error_message = '';
$success_message = '';

// Get barangays for dropdown
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, name FROM barangays ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$barangays = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $house_number = sanitizeInput($_POST['house_number']);
    $street = sanitizeInput($_POST['street']);
    $barangay = sanitizeInput($_POST['barangay']);
    $landmark = sanitizeInput($_POST['landmark']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || 
        empty($house_number) || empty($street) || empty($barangay) || empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Check if email or phone already exists
        $query = "SELECT id FROM users WHERE email = :email OR phone = :phone";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error_message = 'Email or phone number already registered.';
        } else {
            // Handle file uploads
            $id_document = '';
            $selfie_photo = '';
            
            if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0) {
                $upload_dir = 'uploads/documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
                $new_filename = 'id_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['id_document']['tmp_name'], $upload_path)) {
                    $id_document = $upload_path;
                }
            }
            
            if (isset($_FILES['selfie_photo']) && $_FILES['selfie_photo']['error'] == 0) {
                $upload_dir = 'uploads/selfies/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['selfie_photo']['name'], PATHINFO_EXTENSION));
                $new_filename = 'selfie_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['selfie_photo']['tmp_name'], $upload_path)) {
                    $selfie_photo = $upload_path;
                }
            } elseif (isset($_POST['captured_selfie']) && !empty($_POST['captured_selfie'])) {
                // Handle base64 captured selfie
                $upload_dir = 'uploads/selfies/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $base64_data = $_POST['captured_selfie'];
                $base64_data = str_replace('data:image/png;base64,', '', $base64_data);
                $base64_data = str_replace(' ', '+', $base64_data);
                $image_data = base64_decode($base64_data);
                
                $new_filename = 'selfie_captured_' . uniqid() . '.png';
                $upload_path = $upload_dir . $new_filename;
                
                if (file_put_contents($upload_path, $image_data)) {
                    $selfie_photo = $upload_path;
                }
            }
            
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (first_name, last_name, email, phone, password, house_number, street,  barangay, landmark, id_document, selfie_photo) 
                      VALUES (:first_name, :last_name, :email, :phone, :password, :house_number, :street, :barangay, :landmark, :id_document, :selfie_photo)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':house_number', $house_number);
            $stmt->bindParam(':street', $street);
            $stmt->bindParam(':barangay', $barangay);
            $stmt->bindParam(':landmark', $landmark);
            $stmt->bindParam(':id_document', $id_document);
            $stmt->bindParam(':selfie_photo', $selfie_photo);
            
            if ($stmt->execute()) {
                $success_message = 'Registration successful! Your account is pending verification. You will receive a notification once approved.';
                logActivity($db->lastInsertId(), 'User registered');
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row min-vh-100 d-flex justify-content-center align-items-center py-5">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/img/logo.png" alt="Agoncillo Logo" class="img-fluid mb-3" style="max-height: 80px;">
                        <h2 class="fw-bold text-primary">Create an Account</h2>
                        <p class="text-muted">Register to receive disaster alerts and emergency notifications</p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $success_message; ?>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST" enctype="multipart/form-data" id="registrationForm">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter your first name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter your last name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Mobile Number *</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="9XX XXX XXXX" required>
                            </div>
                            <div class="form-text">We'll send a verification code to this number</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="house_number" class="form-label">House No. *</label>
                                <input type="text" class="form-control" id="house_number" name="house_number" placeholder="e.g., 123" required>
                            </div>
                            <div class="col-md-8">
                                <label for="street" class="form-label">Street *</label>
                                <input type="text" class="form-control" id="street" name="street" placeholder="e.g., Rizal St." required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="barangay" class="form-label">Barangay *</label>
                            <select class="form-select" id="barangay" name="barangay" required>
                                <option value="" selected disabled>Select your barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['name']; ?>"><?php echo $barangay['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="landmark" class="form-label">Landmark (Optional)</label>
                            <input type="text" class="form-control" id="landmark" name="landmark" placeholder="e.g., Near the market">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ID Verification *</label>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="id_document" name="id_document" accept="image/*" required>
                                <label class="input-group-text" for="id_document">Upload</label>
                            </div>
                            <div class="form-text">Please upload a valid ID or Barangay Certificate as proof of residency</div>
                        </div>
                        
                        <!-- Enhanced selfie verification with camera capture -->
                        <div class="mb-3">
                            <label class="form-label">Selfie Verification *</label>
                            <div class="card border-primary">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="card-title">Take Photo with Camera</h6>
                                            <div id="camera-section">
                                                <video id="video" width="100%" height="200" autoplay style="border-radius: 8px; background: #f8f9fa;"></video>
                                                <div class="text-center mt-2">
                                                    <button type="button" class="btn btn-primary" id="start-camera">
                                                        <i class="fas fa-camera me-2"></i>Start Camera
                                                    </button>
                                                    <button type="button" class="btn btn-success" id="capture-photo" style="display: none;">
                                                        <i class="fas fa-camera-retro me-2"></i>Capture Photo
                                                    </button>
                                                    <button type="button" class="btn btn-secondary" id="retake-photo" style="display: none;">
                                                        <i class="fas fa-redo me-2"></i>Retake
                                                    </button>
                                                </div>
                                                <canvas id="canvas" style="display: none;"></canvas>
                                                <div id="captured-preview" style="display: none;" class="mt-3">
                                                    <h6>Captured Photo:</h6>
                                                    <img id="captured-image" src="/placeholder.svg" alt="Captured Selfie" class="img-fluid rounded" style="max-height: 200px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div>
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Selfie Guidelines:</strong>
                                                <ul class="mb-0 mt-2">
                                                    <li>Face should be clearly visible</li>
                                                    <li>Good lighting conditions</li>
                                                    <li>Look directly at the camera</li>
                                                    <li>Remove sunglasses or hat</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="captured_selfie" name="captured_selfie">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input class="form-check-input" type="checkbox" id="terms_check" required>
                            <label class="form-check-label" for="terms_check">
                                I agree to the <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Register</button>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <p>Already have an account? <a href="index.php" class="text-primary">Login here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted">
                <small>&copy; 2025 Municipality of Agoncillo. All rights reserved.</small>
            </div>
        </div>
    </div>
</div>

<!-- Added Terms of Service Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold mb-3">Municipal Emergency Response System (MERS) - Terms of Service</h6>
                
                <p><strong>Last Updated: October 2025</strong></p>
                
                <h6 class="fw-bold mt-4">1. Acceptance of Terms</h6>
                <p>By registering and using the Municipal Emergency Response System (MERS), you agree to comply with these Terms of Service. If you do not agree to these terms, please do not use this system.</p>
                
                <h6 class="fw-bold mt-4">2. Purpose of the System</h6>
                <p>MERS is designed to provide emergency alerts, disaster notifications, and critical information to residents of Agoncillo, Batangas. The system aims to enhance public safety and emergency response coordination.</p>
                
                <h6 class="fw-bold mt-4">3. User Responsibilities</h6>
                <ul>
                    <li>You are responsible for maintaining the confidentiality of your account credentials</li>
                    <li>You agree to provide accurate and truthful information during registration</li>
                    <li>You will not use this system for any unlawful or harmful purposes</li>
                    <li>You agree not to share your account with other individuals</li>
                    <li>You will not attempt to access unauthorized areas of the system</li>
                </ul>
                
                <h6 class="fw-bold mt-4">4. Alert Notifications</h6>
                <p>By registering, you consent to receive emergency alerts and notifications via SMS, email, and in-app messages. You may manage your notification preferences in your account settings.</p>
                
                <h6 class="fw-bold mt-4">5. Accuracy of Information</h6>
                <p>While we strive to provide accurate and timely emergency information, MERS does not guarantee the accuracy, completeness, or timeliness of all alerts. Users should verify critical information through official channels.</p>
                
                <h6 class="fw-bold mt-4">6. Limitation of Liability</h6>
                <p>The Municipality of Agoncillo and MERS administrators shall not be liable for any direct, indirect, incidental, or consequential damages arising from the use or inability to use this system.</p>
                
                <h6 class="fw-bold mt-4">7. Termination of Access</h6>
                <p>We reserve the right to suspend or terminate user accounts that violate these terms or engage in prohibited activities.</p>
                
                <h6 class="fw-bold mt-4">8. Changes to Terms</h6>
                <p>We may update these Terms of Service at any time. Continued use of the system constitutes acceptance of updated terms.</p>
                
                <h6 class="fw-bold mt-4">9. Contact Information</h6>
                <p>For questions regarding these terms, please contact the Municipality of Agoncillo Emergency Management Office.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Added Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold mb-3">Municipal Emergency Response System (MERS) - Privacy Policy</h6>
                
                <p><strong>Last Updated: October 2025</strong></p>
                
                <h6 class="fw-bold mt-4">1. Introduction</h6>
                <p>The Municipality of Agoncillo is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard your personal information through the Municipal Emergency Response System (MERS).</p>
                
                <h6 class="fw-bold mt-4">2. Information We Collect</h6>
                <p>We collect the following information during registration:</p>
                <ul>
                    <li>Personal Information: First name, last name, email address, phone number</li>
                    <li>Address Information: House number, street, barangay, and landmark</li>
                    <li>Identification Documents: Valid ID or Barangay Certificate for verification</li>
                    <li>Biometric Data: Selfie photo for identity verification purposes</li>
                    <li>Account Security: Encrypted password for account access</li>
                </ul>
                
                <h6 class="fw-bold mt-4">3. How We Use Your Information</h6>
                <p>Your information is used for:</p>
                <ul>
                    <li>Sending emergency alerts and disaster notifications</li>
                    <li>Verifying your identity and account authenticity</li>
                    <li>Locating you during emergency situations</li>
                    <li>Improving emergency response coordination</li>
                    <li>Communicating important updates and system announcements</li>
                    <li>Complying with legal and regulatory requirements</li>
                </ul>
                
                <h6 class="fw-bold mt-4">4. Data Security</h6>
                <p>We implement industry-standard security measures to protect your personal information, including encryption, secure servers, and access controls. However, no system is completely secure, and we cannot guarantee absolute security.</p>
                
                <h6 class="fw-bold mt-4">5. Data Sharing</h6>
                <p>Your information may be shared with:</p>
                <ul>
                    <li>Emergency response personnel during disaster situations</li>
                    <li>Local government units for emergency coordination</li>
                    <li>Authorized third-party service providers</li>
                </ul>
                <p>We do not sell or rent your personal information to third parties.</p>
                
                <h6 class="fw-bold mt-4">6. Data Retention</h6>
                <p>Your personal information is retained as long as your account is active. Upon account deletion, your data will be securely deleted within 30 days, except where required by law.</p>
                
                <h6 class="fw-bold mt-4">7. Your Rights</h6>
                <p>You have the right to:</p>
                <ul>
                    <li>Access your personal information</li>
                    <li>Request correction of inaccurate data</li>
                    <li>Request deletion of your account and data</li>
                    <li>Opt-out of non-emergency communications</li>
                </ul>
                
                <h6 class="fw-bold mt-4">8. Cookies and Tracking</h6>
                <p>MERS may use cookies and similar technologies to enhance user experience and system functionality. You can control cookie settings through your browser.</p>
                
                <h6 class="fw-bold mt-4">9. Changes to Privacy Policy</h6>
                <p>We may update this Privacy Policy periodically. We will notify users of significant changes via email or system announcement.</p>
                
                <h6 class="fw-bold mt-4">10. Contact Us</h6>
                <p>For privacy concerns or data requests, please contact the Municipality of Agoncillo Data Protection Officer or Emergency Management Office.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Added camera functionality JavaScript -->
<script>
let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let capturedImage = document.getElementById('captured-image');
let capturedPreview = document.getElementById('captured-preview');
let capturedSelfieInput = document.getElementById('captured_selfie');
let stream = null;

document.getElementById('start-camera').addEventListener('click', async function() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user' // Front camera for selfies
            } 
        });
        video.srcObject = stream;
        
        document.getElementById('start-camera').style.display = 'none';
        document.getElementById('capture-photo').style.display = 'inline-block';
        
        // Disable file upload when camera is active
        document.getElementById('selfie_photo').disabled = true;
    } catch (err) {
        alert('Error accessing camera: ' + err.message);
        console.error('Error accessing camera:', err);
    }
});

document.getElementById('capture-photo').addEventListener('click', function() {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    let context = canvas.getContext('2d');
    context.drawImage(video, 0, 0);
    
    // Convert to base64
    let dataURL = canvas.toDataURL('image/png');
    capturedSelfieInput.value = dataURL;
    
    // Show preview
    capturedImage.src = dataURL;
    capturedPreview.style.display = 'block';
    
    // Update buttons
    document.getElementById('capture-photo').style.display = 'none';
    document.getElementById('retake-photo').style.display = 'inline-block';
    
    // Stop camera
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    video.srcObject = null;
});

document.getElementById('retake-photo').addEventListener('click', function() {
    // Reset everything
    capturedPreview.style.display = 'none';
    capturedSelfieInput.value = '';
    
    document.getElementById('retake-photo').style.display = 'none';
    document.getElementById('start-camera').style.display = 'inline-block';
    
    // Re-enable file upload
    document.getElementById('selfie_photo').disabled = false;
});

// Disable camera when file is selected
document.getElementById('selfie_photo').addEventListener('change', function() {
    if (this.files.length > 0) {
        // Stop camera if running
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        video.srcObject = null;
        
        // Reset camera UI
        document.getElementById('start-camera').style.display = 'inline-block';
        document.getElementById('capture-photo').style.display = 'none';
        document.getElementById('retake-photo').style.display = 'none';
        capturedPreview.style.display = 'none';
        capturedSelfieInput.value = '';
    }
});

// Form validation
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    let fileUpload = document.getElementById('selfie_photo').files.length > 0;
    let cameraCapture = capturedSelfieInput.value !== '';
    
    if (!fileUpload && !cameraCapture) {
        e.preventDefault();
        alert('Please either upload a selfie photo or capture one using the camera.');
        return false;
    }
});

// Clean up camera stream when page unloads
window.addEventListener('beforeunload', function() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});
</script>
<script>
document.querySelectorAll('.password-input').forEach(input => {
    input.addEventListener('focus', () => {
        input.style.borderColor = 'red';
    });
    input.addEventListener('blur', () => {
        input.style.borderColor = '';
    });
});
</script>
<?php include 'includes/footer.php'; ?>
