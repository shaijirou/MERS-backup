<?php
require_once '../config/config.php';
// requireAdmin();

$page_title = 'User Management';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Handle user actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    switch ($action) {
        case 'verify':
            $query = "UPDATE users SET verification_status = 'verified', verified_by = :admin_id WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "User verified successfully.";
                logActivity($_SESSION['user_id'], 'User verified', 'users', $user_id);
            }
            break;
            
        case 'unverify':
            $query = "UPDATE users SET verification_status = 'pending', verified_by = NULL WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "User verification removed.";
                logActivity($_SESSION['user_id'], 'User unverified', 'users', $user_id);
            }
            break;
            
        case 'activate':
            $query = "UPDATE users SET status = 'active' WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "User activated successfully.";
                logActivity($_SESSION['user_id'], 'User activated', 'users', $user_id);
            }
            break;
            
        case 'deactivate':
            $query = "UPDATE users SET status = 'inactive' WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "User deactivated successfully.";
                logActivity($_SESSION['user_id'], 'User deactivated', 'users', $user_id);
            }
            break;
            
        case 'delete':
            $query = "DELETE FROM users WHERE id = :user_id AND user_type != 'admin'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            if ($stmt->execute()) {
                $success_message = "User deleted successfully.";
                logActivity($_SESSION['user_id'], 'User deleted', 'users', $user_id);
            }
            break;
    }
}


// Pagination and filtering
$page = $_GET['page'] ?? 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_verified = $_GET['verified'] ?? '';
$filter_barangay = $_GET['barangay'] ?? '';

// Build WHERE clause
$where_conditions = ["u.user_type = 'resident'"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter_status) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_verified !== '') {
    $where_conditions[] = "u.verification_status = :verified";
    $params[':verified'] = $filter_verified;
}

if ($filter_barangay) {
    $where_conditions[] = "u.barangay = :barangay";
    $params[':barangay'] = $filter_barangay;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get users
$query = "SELECT u.*, 
                 admin.first_name AS verified_by_name, 
                 admin.last_name AS verified_by_lastname,
                 (SELECT COUNT(*) FROM incident_reports WHERE user_id = u.id) AS report_count
          FROM users u
          LEFT JOIN users AS admin ON u.verified_by = admin.id
          WHERE $where_clause
          ORDER BY u.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get barangays for filter
$barangay_query = "SELECT DISTINCT barangay FROM users WHERE user_type = 'resident' AND barangay IS NOT NULL ORDER BY barangay";
$barangay_stmt = $db->prepare($barangay_query);
$barangay_stmt->execute();
$barangays = $barangay_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_users,
                    -- SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users
                FROM users WHERE user_type = 'resident'";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

include '../includes/header.php';
?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
<div class="d-flex" id="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div id="page-content-wrapper">
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid px-4">
        
            <div class="d-flex justify-content-between align-items-center py-3">
                <h1 class="h3 mb-0">User Management</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus"></i> Add New User
                    </button>
                    <button class="btn btn-success" onclick="exportUsers()">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>

           
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>


            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['total_users']; ?></h4>
                                    <p class="mb-0">Total Users</p>
                                </div>
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['verified_users']; ?></h4>
                                    <p class="mb-0">Verified Users</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
               
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['new_users']; ?></h4>
                                    <p class="mb-0">New This Month</p>
                                </div>
                                <i class="bi bi-person-plus fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, or phone">
                        </div>
                       
                        <div class="col-md-2">
                            <label class="form-label">Verified</label>
                            <select class="form-select" name="verified">
                                <option value="">All</option>
                                <option value="verified" <?php echo $filter_verified === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="pending" <?php echo $filter_verified === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay']; ?>" <?php echo $filter_barangay == $barangay['barangay'] ? 'selected' : ''; ?>>
                                        <?php echo $barangay['barangay']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Users (<?php echo $total_records; ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Verified</th>
                                    <th>Reports</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                          
                                            <div>
                                                <div class="fw-bold"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                                                <small class="text-muted"><?php echo $user['email']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo $user['phone']; ?></div>
                                        
                                    </td>
                                    <td>
                                        <div><?php echo $user['barangay']; ?></div>
                                    <?php if (!empty($user['house_number']) || !empty($user['street']) || !empty($user['landmark'])): ?>
                                    <small class="text-muted">
                                    <?php 
                                    $fullAddress = $user['house_number'] . ' ' . $user['street'];
                                    if (!empty($user['landmark'])) {
                                    $fullAddress .= ' (Near ' . $user['landmark'] . ')';
                                    }
                                     echo substr($fullAddress, 0, 30) . (strlen($fullAddress) > 30 ? '...' : '');
                                    ?>
                                    </small>
                                    <?php endif; ?>

                                    </td>
                                  
                                    <td>
                                        <?php if ($user['verification_status'] === 'verified'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Verified
                                            </span>
                                            <?php if ($user['verified_by_name']): ?>
                                                <small class="d-block text-muted">
                                                    by <?php echo $user['verified_by_name'] . ' ' . $user['verified_by_lastname']; ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $user['report_count']; ?></span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($user['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($user['verification_status'] === 'pending'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="performAction('verify', <?php echo $user['id']; ?>)">
                                                                <i class="bi bi-check-circle text-success"></i> Verify User
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="performAction('unverify', <?php echo $user['id']; ?>)">
                                                                <i class="bi bi-x-circle text-warning"></i> Remove Verification
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                   
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="performAction('delete', <?php echo $user['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Delete User
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
          
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
 
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="addUserForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" name="first_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" name="last_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email *</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone *</label>
              <input type="tel" class="form-control" name="phone" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Barangay *</label>
              <select class="form-select" name="barangay" required>
                <option value="">Select Barangay</option>
                <option value="Adia">Adia</option>
                <option value="Bagong Sikat">Bagong Sikat</option>
                <option value="Balangon">Balangon</option>
                <option value="Bangin">Bangin</option>
                <option value="Banyaga">Banyaga</option>
                <option value="Barigon">Barigon</option>
                <option value="Bilibinwang">Bilibinwang</option>
                <option value="Coral na Munti">Coral na Munti</option>
                <option value="Guitna">Guitna</option>
                <option value="Mabini">Mabini</option>
                <option value="Pamiga">Pamiga</option>
                <option value="Panhulan">Panhulan</option>
                <option value="Pansipit">Pansipit</option>
                <option value="Poblacion">Poblacion</option>
                <option value="Pook">Pook</option>
                <option value="San Jacinto">San Jacinto</option>
                <option value="San Teodoro">San Teodoro</option>
                <option value="Santa Cruz">Santa Cruz</option>
                <option value="Santo Tomas">Santo Tomas</option>
                <option value="Subic Ilaya">Subic Ilaya</option>
                <option value="Subic Ibaba">Subic Ibaba</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">House Number *</label>
              <input type="text" class="form-control" name="house_number" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Street *</label>
              <input type="text" class="form-control" name="street" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Landmark</label>
              <input type="text" class="form-control" name="landmark" placeholder="e.g. Near the market">
            </div>
            <div class="col-md-6">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm Password *</label>
              <input type="password" class="form-control" name="confirm_password" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                 Content will be loaded via AJAX 
            </div>
        </div>
    </div>
</div>

<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="user_id" id="actionUserId">
</form>

 
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});

function performAction(action, userId) {
    let confirmMessage = '';
    
    switch(action) {
        case 'verify':
            confirmMessage = 'Are you sure you want to verify this user?';
            break;
        case 'unverify':
            confirmMessage = 'Are you sure you want to remove verification from this user?';
            break;
        case 'activate':
            confirmMessage = 'Are you sure you want to activate this user?';
            break;
        case 'deactivate':
            confirmMessage = 'Are you sure you want to deactivate this user?';
            break;
        case 'delete':
            confirmMessage = 'Are you sure you want to delete this user? This action cannot be undone.';
            break;
    }
    
    if (confirm(confirmMessage)) {
        document.getElementById('actionType').value = action;
        document.getElementById('actionUserId').value = userId;
        document.getElementById('actionForm').submit();
    }
}

function viewUser(userId) {
    // Load user details via AJAX
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('userDetailsContent').innerHTML = data;
            new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user details');
        });
}

function exportUsers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export_users.php?' + params.toString();
}

$(document).ready(function() {
    // Form validation
    $('#addUserForm').on('submit', function(e) {
        e.preventDefault();
        
        const password = $('input[name="password"]').val();
        const confirmPassword = $('input[name="confirm_password"]').val();
        
        if (password !== confirmPassword) {
            Swal.fire({
                title: "Error",
                text: "Passwords do not match",
                icon: "error"
            });
            return false;
        }
        
        if (password.length < 6) {
            Swal.fire({
                title: "Error", 
                text: "Password must be at least 6 characters long",
                icon: "error"
            });
            return false;
        }
        
        $.ajax({
            url: "add_user.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success"
                    }).then(() => {
                        $("#addUserForm")[0].reset();
                        $("#addUserModal").modal("hide");
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: "Error",
                        html: response.message,
                        icon: "error"
                    });
                    if (response.field) {
                        $(`[name='${response.field}']`).focus();
                    }
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: "Error",
                    text: "An error occurred while processing your request.",
                    icon: "error"
                });
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
