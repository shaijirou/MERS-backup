<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_admin'])) {
        // Add new admin
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $department = sanitizeInput($_POST['department']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = sanitizeInput($_POST['role']);
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error_message = "All required fields must be filled.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Check if email already exists
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            // Check if phone already exists
            $check_phone_query = "SELECT id FROM users WHERE phone = :phone";
            $check_phone_stmt = $db->prepare($check_phone_query);
            $check_phone_stmt->bindParam(':phone', $phone);
            $check_phone_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Email address already exists.";
            } elseif (!empty($phone) && $check_phone_stmt->rowCount() > 0) {
                $error_message = "Phone number already exists.";
            } else {
                // Insert new admin
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (first_name, last_name, email, phone, department, password, user_type, phone_verified, verification_status) 
                     VALUES (:first_name, :last_name, :email, :phone, :department, :password, :user_type, 1, 'verified')";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':department', $department);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':user_type', $role);
                
                if ($stmt->execute()) {
                    $new_admin_id = $db->lastInsertId();
                    logActivity($_SESSION['user_id'], 'CREATE_ADMIN', 'users', $new_admin_id, null, [
                    'name' => $first_name . ' ' . $last_name,
                    'email' => $email,
                    'role' => $role,
                    'department' => $department
                    ]);
                    $success_message = "New admin account created successfully.";
                } else {
                    $error_message = "Error creating admin account.";
                }
            }
        }
    } elseif (isset($_POST['update_settings'])) {
        // Update system settings
        $settings = [
            'app_name' => sanitizeInput($_POST['app_name']),
            'contact_email' => sanitizeInput($_POST['contact_email']),
            'contact_phone' => sanitizeInput($_POST['contact_phone']),
            'emergency_hotline' => sanitizeInput($_POST['emergency_hotline']),
            'office_address' => sanitizeInput($_POST['office_address'])
        ];

        foreach ($settings as $key => $value) {
            $update_query = "UPDATE settings SET setting_value = :value WHERE setting_key = :key";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':value', $value);
            $update_stmt->bindParam(':key', $key);
            $update_stmt->execute();
        }

        $success_message = "System settings updated successfully.";
        logActivity($_SESSION['user_id'], 'UPDATE_SETTINGS', 'settings', null, null, $settings);
        
       
    } elseif (isset($_POST['delete_admin'])) {
        $admin_id = (int)$_POST['admin_id'];
        
        // Prevent deleting own account
        if ($admin_id == $_SESSION['user_id']) {
            $error_message = "You cannot delete your own account.";
        } else {
            // Get admin details before deletion
            $get_admin_query = "SELECT first_name, last_name, email FROM users WHERE id = :id AND user_type IN ('admin', 'super_admin', 'police', 'barangay', 'emergency', 'firefighter')";
            $get_admin_stmt = $db->prepare($get_admin_query);
            $get_admin_stmt->bindParam(':id', $admin_id);
            $get_admin_stmt->execute();
            $admin_details = $get_admin_stmt->fetch();
            
            if ($admin_details) {
            // Delete related records in system_logs (and other tables if necessary)
            $delete_logs_query = "DELETE FROM system_logs WHERE user_id = :id";
            $delete_logs_stmt = $db->prepare($delete_logs_query);
            $delete_logs_stmt->bindParam(':id', $admin_id);
            $delete_logs_stmt->execute();
            $delete_query = "DELETE FROM users WHERE id = :id AND user_type IN ('admin', 'super_admin', 'police', 'barangay', 'emergency', 'firefighter')";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $admin_id);
            
            if ($delete_stmt->execute()) {
                logActivity($_SESSION['user_id'], 'DELETE_ADMIN', 'users', $admin_id, $admin_details, null);
                $success_message =  "Account deleted successfully.";
            } else {
                $error_message = "Error deleting admin account. There may be related records in other tables that must be deleted first.";
            }
            } else {
            $error_message = "Admin account not found.";
            }
        }
    }
}

// Get all admin accounts
$admin_query = "SELECT id, first_name, last_name, email, phone,id_document, user_type, created_at, 
                       (SELECT COUNT(*) FROM system_logs WHERE user_id = users.id) as activity_count
                FROM users 
                WHERE user_type != 'resident' 
                ORDER BY created_at DESC";
$admin_stmt = $db->prepare($admin_query);
$admin_stmt->execute();
$admins = $admin_stmt->fetchAll();


// Get current user info
$current_user_query = "SELECT first_name, last_name, email, user_type FROM users WHERE id = :id";
$current_user_stmt = $db->prepare($current_user_query);
$current_user_stmt->bindParam(':id', $_SESSION['user_id']);
$current_user_stmt->execute();
$current_user = $current_user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .settings-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .admin-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #007bff, #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .role-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-gear-fill me-2"></i>System Settings</h2>
                </div>

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

                <div class="row">
                    <!-- Admin Management -->
                    <div class="col-lg-8">
                        <div class="card settings-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Admin Accounts</h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                    <i class="bi bi-plus-lg me-1"></i>Add Admin
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Admin</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Activity</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                       <div class="admin-avatar me-3">
                                                            <?php if (!empty($admin['id_document']) && file_exists(__DIR__ . '/../uploads/profiles/' . $admin['id_document'])) : ?>
                                                                <img src="../uploads/profiles/<?php echo htmlspecialchars($admin['id_document']); ?>" 
                                                                    class="rounded-circle" width="40" height="40" alt="Admin">
                                                            <?php else : ?>
                                                                <?php echo strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1)); ?>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div>
                                                            <div class="fw-semibold"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></div>
                                                            <?php if ($admin['phone']): ?>
                                                                <small class="text-muted"><?php echo htmlspecialchars($admin['phone']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                <td>
                                                    <span class="badge role-badge 
                                                        <?php
                                                            switch ($admin['user_type']) {
                                                                case 'super_admin': echo 'bg-danger'; break;
                                                                case 'barangay': echo 'bg-success'; break;
                                                                case 'police': echo 'bg-dark'; break;
                                                                case 'firefighter': echo 'bg-warning text-dark'; break;
                                                                case 'emergency': echo 'bg-info text-dark'; break;
                                                                default: echo 'bg-primary';
                                                            }
                                                        ?>">
                                                        <?php
                                                            switch ($admin['user_type']) {
                                                                case 'super_admin': echo 'Super Admin'; break;
                                                                case 'barangay': echo 'Barangay'; break;
                                                                case 'police': echo 'Police'; break;
                                                                case 'firefighter': echo 'Fire'; break;
                                                                case 'emergency': echo 'Emergency'; break;
                                                                default: echo 'Admin';
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $admin['activity_count']; ?> actions</span>
                                                </td>
                                                <td>
                                                    <small><?php echo formatDateTime($admin['created_at']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                onclick="confirmDeleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Current User</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="col-lg-4">
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>System Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php
                                    // Fetch settings from database
                                    $settings_query = "SELECT setting_key, setting_value FROM settings";
                                    $settings_stmt = $db->prepare($settings_query);
                                    $settings_stmt->execute();
                                    $settings_data = [];
                                    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $settings_data[$row['setting_key']] = $row['setting_value'];
                                    }
                                    ?>
                                    <div class="mb-3">
                                        <label for="app_name" class="form-label">Application Name</label>
                                        <input type="text" class="form-control" id="app_name" name="app_name"
                                               value="<?php echo htmlspecialchars($settings_data['app_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="contact_email" class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" id="contact_email" name="contact_email"
                                               value="<?php echo htmlspecialchars($settings_data['contact_email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="contact_phone" class="form-label">Contact Phone</label>
                                        <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                                               value="<?php echo htmlspecialchars($settings_data['contact_phone'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="emergency_hotline" class="form-label">Emergency Hotline</label>
                                        <input type="text" class="form-control" id="emergency_hotline" name="emergency_hotline"
                                               value="<?php echo htmlspecialchars($settings_data['emergency_hotline'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="office_address" class="form-label">Office Address</label>
                                        <textarea class="form-control" id="office_address" name="office_address" rows="3"><?php echo htmlspecialchars($settings_data['office_address'] ?? ''); ?></textarea>
                                    </div>
                                    </div>
                                    <button type="submit" name="update_settings" class="btn btn-primary w-100">
                                        <i class="bi bi-check-lg me-1"></i>Update Settings
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Current User Info -->
                        <div class="card settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Your Account</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="admin-avatar me-3">
                                        <?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($current_user['email']); ?></small>
                                    </div>
                                </div>
                                <span class="badge <?php echo $current_user['user_type'] === 'super_admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                    <?php echo $current_user['user_type'] === 'super_admin' ? 'Super Admin' : 'Admin'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department" name="department" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <?php if ($current_user['user_type'] === 'super_admin'): ?>
                                    <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                                 <option value="barangay">Barangay</option>
                                  <option value="police">Police</option>
                                  <option value="emergency">Emergency</option>
                                  <option value="firefighter">Firefighter</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>The new admin will be able to access the admin panel immediately after creation.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_admin" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Create Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Admin Form (Hidden) -->
    <form id="deleteAdminForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_admin" value="1">
        <input type="hidden" name="admin_id" id="deleteAdminId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});
        function confirmDeleteAdmin(adminId, adminName) {
            if (confirm(`Are you sure you want to delete the admin account for "${adminName}"? This action cannot be undone.`)) {
                document.getElementById('deleteAdminId').value = adminId;
                document.getElementById('deleteAdminForm').submit();
            }
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
