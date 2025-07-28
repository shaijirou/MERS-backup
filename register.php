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
    $address = sanitizeInput($_POST['address']);
    $barangay = sanitizeInput($_POST['barangay']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || 
        empty($address) || empty($barangay) || empty($password) || empty($confirm_password)) {
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
            }
            
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (first_name, last_name, email, phone, password, address, barangay, id_document, selfie_photo) 
                      VALUES (:first_name, :last_name, :email, :phone, :password, :address, :barangay, :id_document, :selfie_photo)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':barangay', $barangay);
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
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter your first name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter your last name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Mobile Number</label>
                            <div class="input-group">
                                <span class="input-group-text">+63</span>
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="9XX XXX XXXX" required>
                            </div>
                            <div class="form-text">We'll send a verification code to this number</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Complete Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter your complete address in Agoncillo, Batangas" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="barangay" class="form-label">Barangay</label>
                            <select class="form-select" id="barangay" name="barangay" required>
                                <option value="" selected disabled>Select your barangay</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['name']; ?>"><?php echo $barangay['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ID Verification</label>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="id_document" name="id_document" accept="image/*" required>
                                <label class="input-group-text" for="id_document">Upload</label>
                            </div>
                            <div class="form-text">Please upload a valid ID or Barangay Certificate as proof of residency</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Selfie Verification</label>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="selfie_photo" name="selfie_photo" accept="image/*" required>
                                <label class="input-group-text" for="selfie_photo">Upload</label>
                            </div>
                            <div class="form-text">Please upload a clear selfie for identity verification</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
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

<?php include 'includes/footer.php'; ?>
