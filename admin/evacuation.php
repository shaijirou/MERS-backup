<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Evacuation Centers';
$additional_css = ['assets/css/admin.css'];

$database = new Database();
$db = $database->getConnection();

// Handle evacuation center actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_center':
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $barangay_id = (int)($_POST['barangay_id'] ?? 0); // Use barangay_id instead of barangay
            $capacity = (int)$_POST['capacity'] ?? 0;
            $contact_person = $_POST['contact_person'] ?? '';
            $contact_number = $_POST['contact_number'] ?? '';
            $facilities = $_POST['facilities'] ?? '';
            $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            
            $query = "INSERT INTO evacuation_centers (name, address, barangay_id, capacity, contact_person, contact_number, facilities, latitude, longitude, created_at) 
                     VALUES (:name, :address, :barangay_id, :capacity, :contact_person, :contact_number, :facilities, :latitude, :longitude, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':barangay_id', $barangay_id); // Bind barangay_id
            $stmt->bindParam(':capacity', $capacity);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':facilities', $facilities);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);
            
            if ($stmt->execute()) {
                $success_message = "Evacuation center added successfully!";
                logActivity($_SESSION['user_id'], 'Evacuation center added', 'evacuation_centers', $db->lastInsertId());
            } else {
                $error_message = "Error adding evacuation center.";
            }
            break;
            
        case 'update_center':
            $center_id = $_POST['center_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $barangay_id = (int)($_POST['barangay_id'] ?? 0); // Use barangay_id instead of barangay
            $capacity = (int)$_POST['capacity'] ?? 0;
            $contact_person = $_POST['contact_person'] ?? '';
            $contact_number = $_POST['contact_number'] ?? '';
            $facilities = $_POST['facilities'] ?? '';
            $status = $_POST['status'] ?? '';
            $current_occupancy = (int)$_POST['current_occupancy'] ?? 0;
            $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
            $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
            
            $query = "UPDATE evacuation_centers SET 
                     name = :name,
                     address = :address,
                     barangay_id = :barangay_id,
                     capacity = :capacity,
                     contact_person = :contact_person,
                     contact_number = :contact_number,
                     facilities = :facilities,
                     status = :status,
                     current_occupancy = :current_occupancy,
                     latitude = :latitude,
                     longitude = :longitude,
                     updated_at = NOW()
                     WHERE id = :center_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':barangay_id', $barangay_id); // Bind barangay_id
            $stmt->bindParam(':capacity', $capacity);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':facilities', $facilities);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':current_occupancy', $current_occupancy);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);
            $stmt->bindParam(':center_id', $center_id);
            
            if ($stmt->execute()) {
                $success_message = "Evacuation center updated successfully!";
                logActivity($_SESSION['user_id'], 'Evacuation center updated', 'evacuation_centers', $center_id);
            } else {
                $error_message = "Error updating evacuation center.";
            }
            break;
            
        case 'delete_center':
            $center_id = $_POST['center_id'] ?? '';
            
            $query = "DELETE FROM evacuation_centers WHERE id = :center_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':center_id', $center_id);
            
            if ($stmt->execute()) {
                $success_message = "Evacuation center deleted successfully!";
                logActivity($_SESSION['user_id'], 'Evacuation center deleted', 'evacuation_centers', $center_id);
            } else {
                $error_message = "Error deleting evacuation center.";
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ec.status = :status";
    $params[':status'] = $status_filter;
}
if ($barangay_filter) {
    $where_conditions[] = "b.name = :barangay";
    $params[':barangay'] = $barangay_filter;
}
if ($search) {
    $where_conditions[] = "(ec.name LIKE :search OR ec.address LIKE :search OR ec.contact_person LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$count_query = "SELECT COUNT(*) as total FROM evacuation_centers ec 
                LEFT JOIN barangays b ON ec.barangay_id = b.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT ec.*, b.name as barangay_name FROM evacuation_centers ec
          LEFT JOIN barangays b ON ec.barangay_id = b.id
          $where_clause 
          ORDER BY ec.name ASC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$centers = $stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_centers,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_centers,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_centers,
                SUM(capacity) as total_capacity,
                SUM(current_occupancy) as total_occupancy
                FROM evacuation_centers";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

$barangays_query = "SELECT id, name FROM barangays ORDER BY name ASC";
$barangays_stmt = $db->prepare($barangays_query);
$barangays_stmt->execute();
$barangays = $barangays_stmt->fetchAll();

include '../includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/admin.css" rel="stylesheet">
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
                <h1 class="h3 mb-0">Evacuation Centers</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCenterModal">
                        <i class="bi bi-plus-lg"></i> Add Center
                    </button>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['total_centers']; ?></h4>
                                    <p class="mb-0">Total Centers</p>
                                </div>
                                <i class="bi bi-house fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo $stats['active_centers']; ?></h4>
                                    <p class="mb-0">Active Centers</p>
                                </div>
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['total_capacity']); ?></h4>
                                    <p class="mb-0">Total Capacity</p>
                                </div>
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?php echo number_format($stats['total_occupancy']); ?></h4>
                                    <p class="mb-0">Current Occupancy</p>
                                </div>
                                <i class="bi bi-person-check fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Search centers..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="maintenance" <?php echo $status_filter == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barangay</label>
                            <select class="form-select" name="barangay">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['name']; ?>" <?php echo $barangay_filter == $barangay['name'] ? 'selected' : ''; ?>>
                                        <?php echo $barangay['name']; ?>
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

            <!-- Evacuation Centers Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Evacuation Centers (<?php echo $total_records; ?> total)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Center Name</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Occupancy</th>
                                    <th>Status</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($centers as $center): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($center['name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($center['facilities']); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($center['address']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($center['barangay_name']); ?></small>  <!-- Display barangay_name from JOIN -->
                                    </td>
                                    <td><?php echo number_format($center['capacity']); ?></td>
                                    <td>
                                        <?php 
                                        $occupancy_percentage = $center['capacity'] > 0 ? ($center['current_occupancy'] / $center['capacity']) * 100 : 0;
                                        $progress_class = '';
                                        if ($occupancy_percentage >= 90) $progress_class = 'bg-danger';
                                        elseif ($occupancy_percentage >= 50) $progress_class = 'bg-warning';
                                        else $progress_class = 'bg-success';
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" 
                                                 style="width: <?php echo $occupancy_percentage; ?>%" 
                                                 aria-valuenow="<?php echo $occupancy_percentage; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $center['current_occupancy']; ?>/<?php echo $center['capacity']; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch ($center['status']) {
                                            case 'active': $status_class = 'bg-success'; break;
                                            case 'inactive': $status_class = 'bg-secondary'; break;
                                            case 'maintenance': $status_class = 'bg-warning'; break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($center['status']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($center['contact_person']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($center['contact_number']); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewCenter(<?php echo $center['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editCenter(<?php echo $center['id']; ?>)">
                                                            <i class="bi bi-pencil text-warning"></i> Edit Center
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteCenter(<?php echo $center['id']; ?>)">
                                                            <i class="bi bi-trash"></i> Delete Center
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
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php
                            // Copy $_GET and remove 'page' for clean query building
                            $query_params = $_GET;
                            ?>
                            <?php if ($page > 1): ?>
                                <?php $query_params['page'] = $page - 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php $query_params['page'] = $i; ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <?php $query_params['page'] = $page + 1; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Next</a>
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

<!-- Add Center Modal -->
<div class="modal fade" id="addCenterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Evacuation Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_center">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Center Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="barangay" class="form-label">Barangay *</label>
                                <select class="form-select" id="barangay" name="barangay_id" required>  <!-- Changed name to barangay_id -->
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>  <!-- Use id and name from database -->
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label for="address" class="form-label">Address *</label>
                        <input type="address" class="form-control" id="address" name="address"  required>
                     </div>
                    </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="contact_number" class="form-label">Contact Number *</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="facilities" class="form-label">Facilities</label>
                                <input type="text" class="form-control" id="facilities" name="facilities" placeholder="e.g., Kitchen, Medical, Restrooms">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" class="form-control" id="latitude" name="latitude" step="any" placeholder="e.g., 14.1234">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                            <div class="mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" class="form-control" id="longitude" name="longitude" step="any" placeholder="e.g., 121.1234">
                            </div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Center</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Center Modal -->
<div class="modal fade" id="editCenterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Evacuation Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_center">
                    <input type="hidden" name="center_id" id="edit_center_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Center Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="edit_barangay" class="form-label">Barangay *</label>
                                <select class="form-select" id="edit_barangay" name="barangay_id" required>  <!-- Changed name to barangay_id -->
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                        <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>  <!-- Use id and name from database -->
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                            <label for="edit_address" class="form-label">Address *</label>
                            <input type="address" class="form-control" id="edit_address" name="address" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="edit_capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="edit_capacity" name="capacity" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                    <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_current_occupancy" class="form-label">Current Occupancy</label>
                                <input type="number" class="form-control" id="edit_current_occupancy" name="current_occupancy" min="0">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_contact_person" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="edit_contact_number" class="form-label">Contact Number *</label>
                                <input type="text" class="form-control" id="edit_contact_number" name="contact_number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                            <label for="edit_facilities" class="form-label">Facilities</label>
                            <input type="text" class="form-control" id="edit_facilities" name="facilities" placeholder="e.g., Kitchen, Medical, Restrooms">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label for="edit_latitude" class="form-label">Latitude</label>
                                <input type="number" class="form-control" id="edit_latitude" name="latitude" step="any">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_longitude" class="form-label">Longitude</label>
                                <input type="number" class="form-control" id="edit_longitude" name="longitude" step="any">
                            </div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Center</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Center Modal -->
<div class="modal fade" id="viewCenterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Evacuation Center Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Center Name</label>
                            <p class="form-control-plaintext" id="view_name"></p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <p class="form-control-plaintext">
                                <span id="view_status_badge" class="badge"></span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <p class="form-control-plaintext" id="view_address"></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Capacity</label>
                            <p class="form-control-plaintext" id="view_capacity"></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Occupancy</label>
                            <p class="form-control-plaintext" id="view_current_occupancy"></p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Occupancy Rate</label>
                            <div class="progress mt-2" style="height: 25px;">
                                <div id="view_progress_bar" class="progress-bar" role="progressbar" style="width: 0%">
                                    <span id="view_occupancy_text">0%</span>
                                </div>
                            </div>
                        </div>
                </div>
                </div>
            
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Person</label>
                            <p class="form-control-plaintext" id="view_contact_person"></p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Contact Number</label>
                            <p class="form-control-plaintext" id="view_contact_number"></p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                    <div class="mb-3">
                    <label class="form-label fw-bold">Facilities</label>
                    <p class="form-control-plaintext" id="view_facilities"></p>
                </div>
                </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Latitude</label>
                            <p class="form-control-plaintext" id="view_latitude"></p>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Longitude</label>
                            <p class="form-control-plaintext" id="view_longitude"></p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Created At</label>
                            <p class="form-control-plaintext" id="view_created_at"></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Last Updated</label>
                            <p class="form-control-plaintext" id="view_updated_at"></p>
                        </div>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editCenterFromView()">
                    <i class="bi bi-pencil"></i> Edit Center
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="center_id" id="actionCenterId">
</form>

<script>
    document.getElementById("menu-toggle").addEventListener("click", function(e) {
    e.preventDefault();
    document.getElementById("wrapper").classList.toggle("toggled");
});
function editCenter(centerId) {
    // Fetch center details and populate edit modal
    fetch(`get_center_details.php?id=${centerId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_center_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_address').value = data.address;
            document.getElementById('edit_barangay').value = data.barangay_id; // Use barangay_id for select value
            document.getElementById('edit_capacity').value = data.capacity;
            document.getElementById('edit_current_occupancy').value = data.current_occupancy;
            document.getElementById('edit_contact_person').value = data.contact_person;
            document.getElementById('edit_contact_number').value = data.contact_number;
            document.getElementById('edit_facilities').value = data.facilities || '';
            document.getElementById('edit_status').value = data.status;
            document.getElementById('edit_latitude').value = data.latitude;
            document.getElementById('edit_longitude').value = data.longitude;
            
            new bootstrap.Modal(document.getElementById('editCenterModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading center details');
        });
}

function deleteCenter(centerId) {
    if (confirm('Are you sure you want to delete this evacuation center? This action cannot be undone.')) {
        document.getElementById('actionType').value = 'delete_center';
        document.getElementById('actionCenterId').value = centerId;
        document.getElementById('actionForm').submit();
    }
}

function viewCenter(centerId) {
    fetch(`get_center_details.php?id=${centerId}`)
        .then(response => response.json())
        .then(data => {
            // Populate view modal with center details
            document.getElementById('view_name').textContent = data.name;
            // document.getElementById('view_barangay').textContent = data.barangay_name; // Use barangay_name instead of barangay
            document.getElementById('view_address').textContent = data.address;
            document.getElementById('view_capacity').textContent = Number(data.capacity).toLocaleString();
            document.getElementById('view_current_occupancy').textContent = Number(data.current_occupancy).toLocaleString();
            document.getElementById('view_contact_person').textContent = data.contact_person;
            document.getElementById('view_contact_number').textContent = data.contact_number;
            document.getElementById('view_facilities').textContent = data.facilities && data.facilities.trim() !== '' ? data.facilities : 'Not specified';
            document.getElementById('view_latitude').textContent = data.latitude || 'Not specified';
            document.getElementById('view_longitude').textContent = data.longitude || 'Not specified';
            
            // Format dates
            const createdDate = new Date(data.created_at);
            const updatedDate = data.updated_at ? new Date(data.updated_at) : null;
            document.getElementById('view_created_at').textContent = createdDate.toLocaleDateString() + ' ' + createdDate.toLocaleTimeString();
            document.getElementById('view_updated_at').textContent = updatedDate ? 
                updatedDate.toLocaleDateString() + ' ' + updatedDate.toLocaleTimeString() : 'Never';
            
            // Set status badge
            const statusBadge = document.getElementById('view_status_badge');
            statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
            statusBadge.className = 'badge ';
            switch (data.status) {
                case 'active': statusBadge.className += 'bg-success'; break;
                case 'inactive': statusBadge.className += 'bg-secondary'; break;
                case 'maintenance': statusBadge.className += 'bg-warning'; break;
            }
            
            // Calculate and display occupancy rate
            const occupancyPercentage = data.capacity > 0 ? (data.current_occupancy / data.capacity) * 100 : 0;
            const progressBar = document.getElementById('view_progress_bar');
            const occupancyText = document.getElementById('view_occupancy_text');
            
            progressBar.style.width = occupancyPercentage + '%';
            progressBar.setAttribute('aria-valuenow', occupancyPercentage);
            occupancyText.textContent = Math.round(occupancyPercentage) + '%';
            
            // Set progress bar color based on occupancy
            progressBar.className = 'progress-bar ';
            if (occupancyPercentage >= 90) progressBar.className += 'bg-danger';
            else if (occupancyPercentage >= 50) progressBar.className += 'bg-warning';
            else progressBar.className += 'bg-success';
            
            // Store center ID for potential edit action
            window.currentViewCenterId = data.id;
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('viewCenterModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading center details');
        });
}

function editCenterFromView() {
    // Close view modal and open edit modal
    bootstrap.Modal.getInstance(document.getElementById('viewCenterModal')).hide();
    
    // Wait for view modal to close, then open edit modal
    setTimeout(() => {
        editCenter(window.currentViewCenterId);
    }, 300);
}
</script>

<?php include '../includes/footer.php'; ?>
