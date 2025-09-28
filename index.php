<?php
require_once 'config/config.php';

$page_title = 'Login';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'police':
            header("Location: police/dashboard.php");
            exit();
        case 'emergency':
            header("Location: emergency/dashboard.php");
            exit();
        case 'barangay':
            header("Location: barangay/dashboard.php");
            exit();
        case 'firefighter':
            header("Location: firefighter/dashboard.php");
            exit();
        default:
            header("Location: user/dashboard.php");
            exit();
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, first_name, last_name, email, password, user_type, verification_status 
                  FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            
            if (password_verify($password, $user['password'])) {
                if ($user['verification_status'] == 'verified') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    logActivity($user['id'], 'User logged in');
                    
                    switch ($user['user_type']) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            exit();
                        case 'police':
                            header("Location: police/dashboard.php");
                            exit();
                        case 'emergency':
                            header("Location: emergency/dashboard.php");
                            exit();
                        case 'barangay':
                            header("Location: barangay/dashboard.php");
                            exit();
                        case 'firefighter':
                            header("Location: firefighter/dashboard.php");
                            exit();
                        default:
                            header("Location: user/dashboard.php");
                            exit();
                    }
                } else {
                    $error_message = 'Your account is pending verification. Please wait for admin approval.';
                }
            } else {
                $error_message = 'Invalid email or password.';
            }
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row vh-100 d-flex justify-content-center align-items-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <img src="assets/img/logo.png" alt="Agoncillo Logo" class="img-fluid mb-3" style="max-height: 100px;">
                        <h2 class="fw-bold text-primary"><?php echo APP_NAME; ?></h2>
                        <p class="text-muted">Municipality of Agoncillo, Batangas</p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-envelope-fill"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary text-white"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="text-primary">Forgot password?</a>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p>Don't have an account? <a href="register.php" class="text-primary">Register here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted">
                <small>&copy; 2025 Municipality of Agoncillo. All rights reserved.</small>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
