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
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
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
                <small>&copy; 2023 Municipality of Agoncillo. All rights reserved.</small>
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
