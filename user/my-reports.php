<?php
require_once '../config/config.php';
requireLogin();

$page_title = 'My Reports';
$additional_css = ['assets/css/user.css'];

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch();

// Get user's incident reports with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$count_query = "SELECT COUNT(*) as total FROM incident_reports WHERE user_id = :user_id";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':user_id', $_SESSION['user_id']);
$count_stmt->execute();
$count_result = $count_stmt->fetch();
$total_reports = $count_result['total'];
$total_pages = ceil($total_reports / $per_page);

// Get reports with pagination
$query = "SELECT * FROM incident_reports 
          WHERE user_id = :user_id 
          ORDER BY created_at DESC 
          LIMIT :offset, :limit";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll();

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
                <!-- Added My Reports link -->
                <li class="nav-item">
                    <a class="nav-link active" href="my-reports.php"><i class="bi bi-file-earmark-text-fill me-1"></i> My Reports</a>
                </li>
                <!-- Fixed dropdown menu with proper attributes and structure -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="../<?php echo $user['selfie_photo'] ?: 'assets/img/user-avatar.jpg'; ?>" class="rounded-circle me-1" width="28" height="28" alt="User">
                        <span><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
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
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="bi bi-file-earmark-text-fill me-2 text-primary"></i>My Reports</h2>
                    <p class="text-muted mb-0">View and track all your incident reports</p>
                </div>
                <a href="report.php" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i> Report New Incident</a>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Your Incident Reports (<?php echo $total_reports; ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($reports)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Report #</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Date Reported</th>
                                    <th>Approval Status</th>
                                    <th>Response Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($report['report_number']); ?></strong></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $report['incident_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['location']); ?></td>
                                    <td><?php echo formatDateTime($report['created_at']); ?></td>
                                    <td>
                                        <?php
                                        $approval_status = $report['approval_status'] ?: 'pending';
                                        $approval_class = $approval_status === 'approved' ? 'bg-success' :
                                                        ($approval_status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                                        ?>
                                        <span class="badge <?php echo $approval_class; ?>">
                                            <?php echo ucfirst($approval_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $response_status = $report['response_status'] ?: 'pending';
                                        $response_class = $response_status === 'resolved' ? 'bg-success' :
                                                        ($response_status === 'on_scene' ? 'bg-info text-dark' :
                                                        ($response_status === 'responding' ? 'bg-warning text-dark' :
                                                        ($response_status === 'notified' ? 'bg-secondary' : 'bg-warning text-dark')));
                                        ?>
                                        <span class="badge <?php echo $response_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $response_status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Changed to button that triggers modal instead of direct link -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#incidentModal"
                                                onclick="loadIncidentDetails(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No reports yet</h5>
                        <p class="text-muted mb-3">You haven't submitted any incident reports yet.</p>
                        <a href="report.php" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i> Report an Incident</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Previous Button -->
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                    </li>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active = $i == $page ? 'active' : '';
                        echo "<li class='page-item $active'><a class='page-link' href='?page=$i'>$i</a></li>";
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo "<li class='page-item'><a class='page-link' href='?page=$total_pages'>$total_pages</a></li>";
                    }
                    ?>

                    <!-- Next Button -->
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Incident Details Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1" aria-labelledby="incidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incidentModalLabel">Incident Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="incidentDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> -->

<script>
function loadIncidentDetails(report) {
    const content = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Report Number</h6>
                <p><strong>${escapeHtml(report.report_number)}</strong></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Date Reported</h6>
                <p><strong>${formatDateTime(report.created_at)}</strong></p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Incident Type</h6>
                <p><strong>${escapeHtml(report.incident_type.toUpperCase().replace(/_/g, ' '))}</strong></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Location</h6>
                <p><strong>${escapeHtml(report.location)}</strong></p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Approval Status</h6>
                <p>
                    <span class="badge ${
                        report.approval_status === 'approved' ? 'bg-success' :
                        report.approval_status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'
                    }">
                        ${report.approval_status ? report.approval_status.toUpperCase() : 'PENDING'}
                    </span>
                </p>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Response Status</h6>
                <p>
                    <span class="badge ${
                        report.response_status === 'resolved' ? 'bg-success' :
                        report.response_status === 'on_scene' ? 'bg-info text-dark' :
                        report.response_status === 'responding' ? 'bg-warning text-dark' :
                        report.response_status === 'notified' ? 'bg-secondary' : 'bg-warning text-dark'
                    }">
                        ${report.response_status ? report.response_status.toUpperCase().replace(/_/g, ' ') : 'PENDING'}
                    </span>
                </p>
            </div>
        </div>

        <hr>
        
        <div class="mb-3">
            <h6 class="text-muted">Description</h6>
            <div style="pre-wrap; word-wrap: break-word; overflow-wrap: break-word;">
            <p>${escapeHtml(report.description)}</p>
            </div>
        </div>

        ${report.resolution_notes ? `
        <div class="mb-3">
            <h6 class="text-muted">Resolution Notes</h6>
            <p>${escapeHtml(report.resolution_notes)}</p>
        </div>
        ` : ''}

        ${report.photo ? `
        <div class="mb-3">
            <h6 class="text-muted">Incident Photo</h6>
            <img src="../${escapeHtml(report.photo)}" class="img-fluid rounded" alt="Incident photo" style="max-height: 300px;">
        </div>
        ` : ''}
    `;

    document.getElementById('incidentDetailsContent').innerHTML = content;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<?php include '../includes/footer.php'; ?>
