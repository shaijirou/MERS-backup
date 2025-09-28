<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'My Profile';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Get user information
$query = "SELECT u.*, b.name as barangay_name FROM users u 
          LEFT JOIN barangays b ON u.barangay = b.name 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Get barangays for dropdown
$query = "SELECT * FROM barangays ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$barangays = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $house_number = sanitizeInput($_POST['house_number']);
        $street = sanitizeInput($_POST['street']);
        $barangay = sanitizeInput($_POST['barangay']);
         $landmark = sanitizeInput($_POST['landmark']);
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($house_number) || empty($street) || empty($barangay)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Check if email already exists for other users
            $query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'Email address is already in use by another account.';
            } else {
                // Handle profile picture upload
                $selfie_photo = $user['selfie_photo'];
                if (isset($_FILES['selfie_photo']) && $_FILES['selfie_photo']['error'] == 0) {
                    $upload_dir = '../uploads/profiles/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['selfie_photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['selfie_photo']['tmp_name'], $upload_path)) {
                            // Delete old profile picture if exists
                            if ($selfie_photo && file_exists('../' . $selfie_photo)) {
                                unlink('../' . $selfie_photo);
                            }
                            $selfie_photo = str_replace('../', '', $upload_path);
                        }
                    }
                }
                
                // Update user information
                $query = "UPDATE users SET 
                          first_name = :first_name,
                          last_name = :last_name,
                          email = :email,
                          phone = :phone,
                          house_number = :house_number,
                          street = :street,
                          barangay = :barangay,
                          landmark = :landmark,
                          selfie_photo = :selfie_photo,
                          updated_at = NOW()
                          WHERE id = :user_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':house_number', $house_number);
                $stmt->bindParam(':street', $street);
                $stmt->bindParam(':barangay', $barangay);
                $stmt->bindParam(':landmark', $landmark);
                $stmt->bindParam(':selfie_photo', $selfie_photo);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = 'Profile updated successfully!';
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    
                    // Refresh user data
                    $query = "SELECT u.*, b.name as barangay_name FROM users u 
                              LEFT JOIN barangays b ON u.barangay = b.name 
                              WHERE u.id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $stmt->execute();
                    $user = $stmt->fetch();
                    
                    logActivity($_SESSION['user_id'], 'Profile updated', 'users', $_SESSION['user_id']);
                } else {
                    $error_message = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error_message = 'New password must be at least 8 characters long.';
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = 'Password changed successfully!';
                    logActivity($_SESSION['user_id'], 'Password changed', 'users', $_SESSION['user_id']);
                } else {
                    $error_message = 'Failed to change password. Please try again.';
                }
            } else {
                $error_message = 'Current password is incorrect.';
            }
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
                    <a class="nav-link" href="report.php"><i class="bi bi-exclamation-triangle-fill me-1"></i> Report Incident</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item active" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h2>
            <p class="text-muted mb-0">Manage your account information and preferences</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-fill me-2"></i>Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-4">
                            <div class="col-12 text-center">
                                <div class="position-relative d-inline-block">
                                    <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" 
                                         alt="Profile Picture" class="rounded-circle border" 
                                         width="120" height="120" style="object-fit: cover;" id="profilePreview">
                                    <button type="button" class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                                            onclick="document.getElementById('selfie_photo').click()">
                                        <i class="bi bi-camera"></i>
                                    </button>
                                </div>
                                <input type="file" class="d-none" id="selfie_photo" name="selfie_photo" 
                                       accept="image/*" onchange="previewImage(this)">
                                <p class="text-muted mt-2 mb-0">Click the camera icon to change your profile picture</p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                <select class="form-select" id="barangay" name="barangay" required>
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['name']; ?>" 
                                            <?php echo $user['barangay'] == $barangay['name'] ? 'selected' : ''; ?>>
                                        <?php echo $barangay['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
    <label for="house_number" class="form-label">House Number</label>
    <input type="text" class="form-control" id="house_number" name="house_number" 
           value="<?php echo htmlspecialchars($user['house_number']); ?>" 
           placeholder="Enter house number">
</div>

<div class="col-md-4">
    <label for="street" class="form-label">Street</label>
    <input type="text" class="form-control" id="street" name="street" 
           value="<?php echo htmlspecialchars($user['street']); ?>" 
           placeholder="Enter street">
</div>

<div class="col-md-4">
    <label for="landmark" class="form-label">Landmark</label>
    <input type="text" class="form-control" id="landmark" name="landmark" 
           value="<?php echo htmlspecialchars($user['landmark']); ?>" 
           placeholder="e.g. Near plaza, school">
</div>

                        </div>

                        

                        <hr>
                        

                        <div class="d-flex justify-content-end">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Account Security -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-lock me-2"></i>Account Security
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="8" required>
                            <div class="form-text">Minimum 8 characters</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   minlength="8" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning w-100">
                            <i class="bi bi-key me-1"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Statistics -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Account Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Member Since</span>
                        <strong><?php echo date('M Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                    
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Account Status</span>
                        <span class="badge bg-success">Active</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="report.php" class="btn btn-outline-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>Report Emergency
                        </a>
                        <a href="alerts.php" class="btn btn-outline-primary">
                            <i class="bi bi-bell me-1"></i>View Alerts
                        </a>
                        <a href="map.php" class="btn btn-outline-info">
                            <i class="bi bi-map me-1"></i>Evacuation Map
                        </a>
                        <a href="contacts.php" class="btn btn-outline-secondary">
                            <i class="bi bi-telephone me-1"></i>Emergency Contacts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
