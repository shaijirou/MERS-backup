<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection if not already included
require_once '../config/config.php';
requireEmergency();

// Ensure $pdo is defined (should be set in config.php)
if (!isset($pdo)) {
    throw new Exception('Database connection not established.');
}

// Fetch emergency personnel profile picture
$emergency_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$profile_picture = null;

try {
    if ($emergency_id) {
        $stmt = $pdo->prepare("SELECT id_document FROM users WHERE id = ?");
        $stmt->execute([$emergency_id]);
        $emergency_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emergency_data && !empty($emergency_data['id_document'])) {
            // Use absolute path for file_exists, but relative path for src attribute
            $profile_picture_filename = $emergency_data['id_document'];
            $profile_picture_path = dirname(__DIR__, 2) . "/uploads/profiles/" . $profile_picture_filename;
            if (file_exists($profile_picture_path)) {
                $profile_picture = "../uploads/profiles/" . $profile_picture_filename;
            }
        }
    }
} catch (PDOException $e) {
    // If there's an error, just use default avatar
    $profile_picture = null;
}
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid">
        <button class="btn btn-primary" id="menu-toggle">
            <i class="bi bi-list"></i>
        </button>
        <h4 style="margin-left: 8px;"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Emergency Personnel'; ?></h4>
        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                    
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>


