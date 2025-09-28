<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white p-3">
        <img src="../assets/img/logo.png" alt="Logo" class="me-2" style="height: 30px;">
        <span class="fw-bold">Admin Panel</span>
    </div>
    <?php
    // Assume $_SESSION['user_role'] contains the role, e.g., 'admin' or 'super_admin'
    $user_role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

    ?>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>
        <?php if (trim(strtolower($user_role)) === 'super_admin'): ?>
            <a href="alerts.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
                <i class="bi bi-bell-fill me-2"></i>Alert Management
            </a>
        <?php endif; ?>
        <a href="incidents.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Incident Reports
        </a>
        <?php if (trim(strtolower($user_role)) === 'super_admin'): ?>
            <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
                <i class="bi bi-people-fill me-2"></i>User Management
            </a>
        <?php endif; ?>
        <a href="evacuation.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-house-fill me-2"></i>Evacuation Centers
        </a>
        <a href="map.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-map-fill me-2"></i>GIS Mapping
        </a>
        <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-file-earmark-text-fill me-2"></i>Reports
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white border-0">
            <i class="bi bi-gear-fill me-2"></i>Settings
        </a>
    </div>
</div>
