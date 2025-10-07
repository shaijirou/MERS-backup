<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection if not already included
require_once '../config/config.php';
requirePolice();

// Ensure $pdo is defined (should be set in config.php)
if (!isset($pdo)) {
    throw new Exception('Database connection not established.');
}

// Fetch police personnel profile picture
$police_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$profile_picture = null;

try {
    if ($police_id) {
        $stmt = $pdo->prepare("SELECT id_document FROM users WHERE id = ?");
        $stmt->execute([$police_id]);
        $police_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($police_data && !empty($police_data['id_document'])) {
            // Use absolute path for file_exists, but relative path for src attribute
            $profile_picture_filename = $police_data['id_document'];
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
        <h4 style="margin-left: 8px;"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Police Personnel'; ?></h4>
        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                    <img src="<?php echo htmlspecialchars($profile_picture ?: '../assets/img/user-avatar.jpg'); ?>" 
                        class="rounded-circle me-1" width="28" height="28" alt="User">
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
