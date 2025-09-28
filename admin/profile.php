<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Admin Profile';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get admin profile data (fetch before handling POST to avoid undefined variable)
$profile_query = "SELECT * FROM users WHERE id = :user_id";
$profile_stmt = $db->prepare($profile_query);
$profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
$profile_stmt->execute();
$admin_profile = $profile_stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $email = sanitizeInput($_POST['email']);
                $phone = sanitizeInput($_POST['phone']);
                $house_number = sanitizeInput($_POST['house_number']);
                $street = sanitizeInput($_POST['street']);
                $barangay = sanitizeInput($_POST['barangay']);
                $landmark = sanitizeInput($_POST['landmark']);
                
                // Validation
                $errors = [];
                
                if (empty($first_name)) {
                    $errors[] = "First name is required";
                }
                
                if (empty($last_name)) {
                    $errors[] = "Last name is required";
                }
                
                if (empty($email)) {
                    $errors[] = "Email is required";
                }
                if (empty($phone)) {
                    $errors[] = "Phone number is required";
                }
                if (empty($house_number)) {
                    $errors[] = "House number is required";
                }
                if (empty($street)) {
                    $errors[] = "Street is required";
                }
                if (empty($barangay)) {
                    $errors[] = "Barangay is required";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format";
                }
                
                // Check if email exists for other users
                $email_check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
                $email_check_stmt = $db->prepare($email_check_query);
                $email_check_stmt->bindParam(':email', $email);
                $email_check_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $email_check_stmt->execute();
                
                if ($email_check_stmt->rowCount() > 0) {
                    $errors[] = "Email address already exists";
                }
                
                if (empty($errors)) {
                    // If super_admin, allow user_type change
                    if ($admin_profile['user_type'] === 'super_admin' && isset($_POST['user_type'])) {
                        $user_type = sanitizeInput($_POST['user_type']);
                        // Only allow valid user types
                        $allowed_types = ['super_admin', 'admin', 'user'];
                        if (in_array($user_type, $allowed_types)) {
                            $update_query = "UPDATE users SET 
                                           first_name = :first_name,
                                           last_name = :last_name,
                                           email = :email,
                                           phone = :phone,
                                           user_type = :user_type,
                                           updated_at = NOW()
                                           WHERE id = :user_id";
                            
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':first_name', $first_name);
                            $update_stmt->bindParam(':last_name', $last_name);
                            $update_stmt->bindParam(':email', $email);
                            $update_stmt->bindParam(':phone', $phone);
                            $update_stmt->bindParam(':user_type', $user_type);
                            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                            
                            if ($update_stmt->execute()) {
                                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                                $success_message = "Profile updated successfully!";
                                logActivity($_SESSION['user_id'], 'UPDATE_PROFILE', 'users', $_SESSION['user_id']);
                            } else {
                                $error_message = "Error updating profile.";
                            }
                        } else {
                            $error_message = "Invalid user type selected.";
                        }
                    } else {
                        // Not super_admin or user_type not set, update without changing user_type
                        $update_query = "UPDATE users SET 
                                       first_name = :first_name,
                                       last_name = :last_name,
                                       email = :email,
                                       phone = :phone,
                                       updated_at = NOW()
                                       WHERE id = :user_id";
                        
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':first_name', $first_name);
                        $update_stmt->bindParam(':last_name', $last_name);
                        $update_stmt->bindParam(':email', $email);
                        $update_stmt->bindParam(':phone', $phone);
                        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        
                        if ($update_stmt->execute()) {
                            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                            $success_message = "Profile updated successfully!";
                            logActivity($_SESSION['user_id'], 'UPDATE_PROFILE', 'users', $_SESSION['user_id']);
                        } else {
                            $error_message = "Error updating profile.";
                        }
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
                break;

            case 'upload_picture':
                if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] == 0) {
                    $file = $_FILES['id_document'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    // Validation
                    $errors = [];
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        $errors[] = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.";
                    }
                    
                    if ($file['size'] > $max_size) {
                        $errors[] = "File size too large. Maximum size is 5MB.";
                    }
                    
                    // Check if file is actually an image
                    $image_info = getimagesize($file['tmp_name']);
                    if ($image_info === false) {
                        $errors[] = "File is not a valid image.";
                    }
                    
                    if (empty($errors)) {
                        // Generate unique filename
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        // Get current profile picture to delete old one
                        $current_pic_query = "SELECT id_document FROM users WHERE id = :user_id";
                        $current_pic_stmt = $db->prepare($current_pic_query);
                        $current_pic_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        $current_pic_stmt->execute();
                        $current_pic = $current_pic_stmt->fetchColumn();
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Update database
                            $update_query = "UPDATE users SET id_document = :id_document, updated_at = NOW() WHERE id = :user_id";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':id_document', $new_filename);
                            $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                            
                            if ($update_stmt->execute()) {
                                // Delete old profile picture if it exists
                                if ($current_pic && file_exists($upload_dir . $current_pic)) {
                                    unlink($upload_dir . $current_pic);
                                }
                                
                                $success_message = "Profile picture updated successfully!";
                                logActivity($_SESSION['user_id'], 'UPDATE_id_document', 'users', $_SESSION['user_id']);
                            } else {
                                $error_message = "Error updating profile picture in database.";
                                // Delete uploaded file if database update failed
                                if (file_exists($upload_path)) {
                                    unlink($upload_path);
                                }
                            }
                        } else {
                            $error_message = "Error uploading file.";
                        }
                    } else {
                        $error_message = implode('<br>', $errors);
                    }
                } else {
                    $error_message = "Please select a file to upload.";
                }
                break;

            case 'remove_picture':
                // Get current profile picture
                $current_pic_query = "SELECT id_document FROM users WHERE id = :user_id";
                $current_pic_stmt = $db->prepare($current_pic_query);
                $current_pic_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $current_pic_stmt->execute();
                $current_pic = $current_pic_stmt->fetchColumn();
                
                if ($current_pic) {
                    // Update database
                    $update_query = "UPDATE users SET id_document = NULL, updated_at = NOW() WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        // Delete file
                        if (file_exists($upload_dir . $current_pic)) {
                            unlink($upload_dir . $current_pic);
                        }
                        
                        $success_message = "Profile picture removed successfully!";
                        logActivity($_SESSION['user_id'], 'REMOVE_id_document', 'users', $_SESSION['user_id']);
                    } else {
                        $error_message = "Error removing profile picture.";
                    }
                } else {
                    $error_message = "No profile picture to remove.";
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validation
                $errors = [];
                
                if (empty($current_password)) {
                    $errors[] = "Current password is required";
                }
                
                if (empty($new_password)) {
                    $errors[] = "New password is required";
                } elseif (strlen($new_password) < 6) {
                    $errors[] = "New password must be at least 6 characters long";
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = "New passwords do not match";
                }
                
                if (empty($errors)) {
                    // Verify current password
                    $password_query = "SELECT password FROM users WHERE id = :user_id";
                    $password_stmt = $db->prepare($password_query);
                    $password_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $password_stmt->execute();
                    $user_data = $password_stmt->fetch();
                    
                    if (password_verify($current_password, $user_data['password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        $update_query = "UPDATE users SET 
                                       password = :password,
                                       updated_at = NOW()
                                       WHERE id = :user_id";
                        
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':password', $hashed_password);
                        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Password changed successfully!";
                            logActivity($_SESSION['user_id'], 'CHANGE_PASSWORD', 'users', $_SESSION['user_id']);
                        } else {
                            $error_message = "Error changing password.";
                        }
                    } else {
                        $error_message = "Current password is incorrect";
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
                break;
        }
    }
}

// Get admin profile data
$profile_query = "SELECT * FROM users WHERE id = :user_id";
$profile_stmt = $db->prepare($profile_query);
$profile_stmt->bindParam(':user_id', $_SESSION['user_id']);
$profile_stmt->execute();
$admin_profile = $profile_stmt->fetch();

// Get admin activity statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN action = 'LOGIN' THEN 1 END) as total_logins,
                COUNT(CASE WHEN action LIKE '%USER%' THEN 1 END) as user_actions,
                COUNT(CASE WHEN action LIKE '%ALERT%' THEN 1 END) as alert_actions,
                COUNT(CASE WHEN action LIKE '%INCIDENT%' THEN 1 END) as incident_actions,
                MAX(created_at) as last_activity
                FROM system_logs 
                WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $_SESSION['user_id']);
$stats_stmt->execute();
$admin_stats = $stats_stmt->fetch();

// Get recent activity
$activity_query = "SELECT * FROM system_logs 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT 10";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
$activity_stmt->execute();
$recent_activities = $activity_stmt->fetchAll();

include '../includes/header.php';
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
<style>
.profile-picture-container {
    position: relative;
    display: inline-block;
}

.profile-picture {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e9ecef;
    transition: all 0.3s ease;
}

.profile-picture:hover {
    border-color: #0d6efd;
}

.profile-picture-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    cursor: pointer;
}

.profile-picture-container:hover .profile-picture-overlay {
    opacity: 1;
}

.default-avatar {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    border: 4px solid #e9ecef;
    transition: all 0.3s ease;
}

.default-avatar:hover {
    border-color: #0d6efd;
}

.file-upload-btn {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.file-upload-btn input[type=file] {
    position: absolute;
    left: -9999px;
}

.picture-actions {
    margin-top: 1rem;
}

.picture-actions .btn {
    margin: 0.25rem;
}
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
                <h1 class="h3 mb-0">Admin Profile</h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <!-- Profile Card -->
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <div class="profile-picture-container">
                                    <?php if ($admin_profile['id_document'] && file_exists($upload_dir . $admin_profile['id_document'])): ?>
                                        <img src="<?php echo $upload_dir . $admin_profile['id_document']; ?>" 
                                             alt="Profile Picture" class="profile-picture" id="currentProfilePicture">
                                        <div class="profile-picture-overlay" onclick="document.getElementById('profilePictureInput').click()">
                                            <i class="bi bi-camera text-white fs-4"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="default-avatar" onclick="document.getElementById('profilePictureInput').click()">
                                            <i class="bi bi-person"></i>
                                            <div class="profile-picture-overlay">
                                                <i class="bi bi-camera text-white fs-4"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Profile Picture Actions -->
                                <div class="picture-actions">
                                    <form method="POST" enctype="multipart/form-data" style="display: inline;">
                                        <input type="hidden" name="action" value="upload_picture">
                                        <div class="file-upload-btn">
                                            <input type="file" id="profilePictureInput" name="id_document" 
                                                   accept="image/*" onchange="previewImage(this); this.form.submit();">
                                        </div>
                                    </form>
                                    
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="document.getElementById('profilePictureInput').click()">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </button>
                                    
                                    <?php if ($admin_profile['id_document']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="remove_picture">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to remove your profile picture?')">
                                                <i class="bi bi-trash me-1"></i>Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($admin_profile['first_name'] . ' ' . $admin_profile['last_name']); ?></h4>
                            <p class="text-muted"><?php echo ucfirst($admin_profile['user_type']); ?> Administrator</p>
                            <p class="text-muted">
                                <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($admin_profile['email']); ?>
                            </p>
                            <?php if (!empty($admin_profile['phone'])): ?>
                                <p class="text-muted">
                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($admin_profile['phone']); ?>
                                </p>
                            <?php endif; ?>
                            <p class="text-muted">
                                <i class="bi bi-calendar me-1"></i>Member since <?php echo date('F Y', strtotime($admin_profile['created_at'])); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Activity Statistics -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart me-2"></i>Activity Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center g-3">
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h4 class="text-primary mb-1"><?php echo $admin_stats['total_logins'] ?? 0; ?></h4>
                                        <small class="text-muted">Total Logins</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h4 class="text-success mb-1"><?php echo $admin_stats['user_actions'] ?? 0; ?></h4>
                                        <small class="text-muted">User Actions</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h4 class="text-warning mb-1"><?php echo $admin_stats['alert_actions'] ?? 0; ?></h4>
                                        <small class="text-muted">Alert Actions</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <h4 class="text-info mb-1"><?php echo $admin_stats['incident_actions'] ?? 0; ?></h4>
                                        <small class="text-muted">Incident Actions</small>
                                    </div>
                                </div>
                            </div>
                            <?php if ($admin_stats['last_activity']): ?>
                                <hr>
                                <p class="text-muted text-center mb-0">
                                    <small>Last Activity: <?php echo formatDateTime($admin_stats['last_activity']); ?></small>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <!-- Profile Settings -->
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                        <i class="bi bi-person me-1"></i>Profile Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                        <i class="bi bi-lock me-1"></i>Change Password
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                        <i class="bi bi-clock-history me-1"></i>Recent Activity
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="profileTabsContent">
                                <!-- Profile Information Tab -->
                                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="first_name" class="form-label">First Name *</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($admin_profile['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="last_name" class="form-label">Last Name *</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($admin_profile['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($admin_profile['email']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number *</label>
                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($admin_profile['phone']); ?>">
                                        </div>

                                         <div class="mb-3">
                            <div class="col-md-4">
    <label for="house_number" class="form-label">House No. *</label>
    <input type="text" class="form-control" id="house_number" name="house_number" 
           value="<?php echo htmlspecialchars($admin_profile['house_number'] ?? '', ENT_QUOTES); ?>" required>
</div>

                            <div class="col-md-8">
    <label for="street" class="form-label">Street *</label>
    <input type="text" class="form-control" id="street" name="street" 
           value="<?php echo htmlspecialchars($admin_profile['street'] ?? '', ENT_QUOTES); ?>" 
           required>
</div>


                       <div class="mb-3">
    <label for="barangay" class="form-label">Barangay *</label>
    <select class="form-select" id="barangay" name="barangays" required>
        <option value="" disabled <?php echo empty($admin_profile['barangays']) ? 'selected' : ''; ?>>
            Select your barangay
        </option>
        <?php foreach ($barangays as $barangay): ?>
            <option value="<?php echo htmlspecialchars($barangay['name'], ENT_QUOTES); ?>"
                <?php echo ($admin_profile['barangays'] ?? '') === $barangay['name'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($barangay['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


                        
                       <div class="mb-3">
    <label for="landmark" class="form-label">Landmark (Optional)</label>
    <input type="text" class="form-control" id="landmark" name="landmark" 
           value="<?php echo htmlspecialchars($admin_profile['landmark'] ?? '', ENT_QUOTES); ?>">
</div>

                                        
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <?php if ($admin_profile['user_type'] === 'super_admin'): ?>
                                                <select class="form-select" name="user_type" id="user_type">
                                                    <option value="super_admin" <?php if ($admin_profile['user_type'] === 'super_admin') echo 'selected'; ?>>Super Admin</option>
                                                    <option value="admin" <?php if ($admin_profile['user_type'] === 'admin') echo 'selected'; ?>>Admin</option>
                                                    <option value="user" <?php if ($admin_profile['user_type'] === 'user') echo 'selected'; ?>>User</option>
                                                </select>
                                                <small class="form-text text-muted">You can change your role as Super Admin.</small>
                                            <?php else: ?>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo ucfirst($admin_profile['user_type']); ?>" readonly>
                                                <small class="form-text text-muted">Contact a Super Admin to change your role.</small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Update Profile
                                        </button>
                                    </form>
                                </div>

                                <!-- Change Password Tab -->
                                <div class="tab-pane fade" id="password" role="tabpanel">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password *</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password *</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-key me-1"></i>Change Password
                                        </button>
                                    </form>
                                </div>

                                <!-- Recent Activity Tab -->
                                <div class="tab-pane fade" id="activity" role="tabpanel">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Description</th>
                                                    <th>Date/Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_activities as $activity): ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                            $action_icon = '';
                                                            $action_class = '';
                                                            switch (strtolower($activity['action'])) {
                                                                case 'login': 
                                                                    $action_icon = 'bi-box-arrow-in-right'; 
                                                                    $action_class = 'text-success'; 
                                                                    break;
                                                                case 'logout': 
                                                                    $action_icon = 'bi-box-arrow-right'; 
                                                                    $action_class = 'text-warning'; 
                                                                    break;
                                                                case 'create_user': 
                                                                    $action_icon = 'bi-person-plus'; 
                                                                    $action_class = 'text-primary'; 
                                                                    break;
                                                                case 'update_user': 
                                                                case 'update_profile': 
                                                                    $action_icon = 'bi-person-gear'; 
                                                                    $action_class = 'text-info'; 
                                                                    break;
                                                                case 'update_id_document': 
                                                                    $action_icon = 'bi-image'; 
                                                                    $action_class = 'text-info'; 
                                                                    break;
                                                                case 'remove_id_document': 
                                                                    $action_icon = 'bi-image-fill'; 
                                                                    $action_class = 'text-warning'; 
                                                                    break;
                                                                case 'delete_user': 
                                                                    $action_icon = 'bi-person-dash'; 
                                                                    $action_class = 'text-danger'; 
                                                                    break;
                                                                case 'create_alert': 
                                                                    $action_icon = 'bi-bell'; 
                                                                    $action_class = 'text-warning'; 
                                                                    break;
                                                                case 'update_alert': 
                                                                    $action_icon = 'bi-bell-fill'; 
                                                                    $action_class = 'text-info'; 
                                                                    break;
                                                                case 'delete_alert': 
                                                                    $action_icon = 'bi-bell-slash'; 
                                                                    $action_class = 'text-danger'; 
                                                                    break;
                                                                case 'change_password': 
                                                                    $action_icon = 'bi-key'; 
                                                                    $action_class = 'text-warning'; 
                                                                    break;
                                                                default: 
                                                                    $action_icon = 'bi-gear'; 
                                                                    $action_class = 'text-secondary'; 
                                                                    break;
                                                            }
                                                            ?>
                                                            <i class="bi <?php echo $action_icon; ?> <?php echo $action_class; ?> me-2"></i>
                                                            <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?></td>
                                                        <td><?php echo formatDateTime($activity['created_at']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recent_activities)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">No recent activity found</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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

// Image preview function
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const currentPicture = document.getElementById('currentProfilePicture');
            if (currentPicture) {
                currentPicture.src = e.target.result;
            } else {
                // Replace default avatar with image preview
                const container = document.querySelector('.profile-picture-container');
                container.innerHTML = `
                    <img src="${e.target.result}" alt="Profile Picture Preview" class="profile-picture" id="currentProfilePicture">
                    <div class="profile-picture-overlay" onclick="document.getElementById('profilePictureInput').click()">
                        <i class="bi bi-camera text-white fs-4"></i>
                    </div>
                `;
            }
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// File size validation
document.getElementById('profilePictureInput').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('File size too large. Maximum size is 5MB.');
            this.value = '';
            return;
        }
        
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
            this.value = '';
            return;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
