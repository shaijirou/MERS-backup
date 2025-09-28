<?php
require_once '../config/config.php';
requireBarangay();
?>

<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white p-3">
        <img src="../assets/img/logo.png" alt="Logo" class="me-2" style="height: 30px;">
        <span class="fw-bold">Barangay Panel</span>
    </div>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
            <?php
            $unread_count = 0;
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = FALSE");
                $stmt->execute([$_SESSION['user_id']]);
                $unread_count = $stmt->fetchColumn();
                if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $unread_count; ?></span>
                <?php endif;
            } catch (PDOException $e) {
                // Handle error silently for now
            }
            ?>
        </a>
        <a href="incidents.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo basename($_SERVER['PHP_SELF']) == 'incidents.php' ? 'active' : ''; ?>">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Local Incidents
            <?php
            $pending_count = 0;
            try {
                $user_query = "SELECT assigned_barangay, barangay FROM users WHERE id = ?";
                $user_stmt = $pdo->prepare($user_query);
                $user_stmt->execute([$_SESSION['user_id']]);
                $user_data = $user_stmt->fetch();
                $barangay_name = $user_data['assigned_barangay'] ?: $user_data['barangay'];
                
                if ($barangay_name) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_reports ir 
                                          JOIN users u ON ir.user_id = u.id 
                                          WHERE ir.approval_status = 'approved' 
                                          AND ir.response_status IN ('notified', 'responding', 'on_scene')
                                          AND u.barangay = ?");
                    $stmt->execute([$barangay_name]);
                    $pending_count = $stmt->fetchColumn();
                }
                if ($pending_count > 0): ?>
                    <span class="badge bg-warning ms-auto"><?php echo $pending_count; ?></span>
                <?php endif;
            } catch (PDOException $e) {
                // Handle error silently for now
            }
            ?>
        </a>
        
        <a href="reports.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-text-fill me-2"></i>Reports
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle me-2"></i>Profile
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white border-0 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="bi bi-gear-fill me-2"></i>Settings
        </a>
    </div>
</div>
