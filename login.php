<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        switch ($user['user_type']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'police':
                header('Location: police/dashboard.php');
                break;
            case 'emergency':
                header('Location: emergency/dashboard.php');
                break;
            case 'barangay':
                header('Location: barangay/dashboard.php');
                break;
            case 'firefighter':
                header('Location: firefighter/dashboard.php');
                break;
            default:
                header('Location: index.php');
                break;
        }
        exit();
    } else {
        $error = "Invalid credentials or account not active";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MERS</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3c72 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-family);
        }
        
        .login-container {
            background: var(--card-background);
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--police-color), var(--emergency-color), var(--barangay-color), var(--firefighter-color));
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .login-header .icon {
            font-size: 3.5rem;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
        }
        
        .login-header h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            font-size: 1.8rem;
        }
        
        .login-header p {
            color: var(--text-secondary);
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: var(--status-rejected);
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--status-rejected);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--input-background);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn-login {
            background: var(--primary-color);
            color: white;
            width: 100%;
            padding: var(--spacing-md);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
        }
        
        .btn-login:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .user-types {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .user-types h4 {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: var(--spacing-md);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .user-type-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-sm);
        }
        
        .user-type-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-sm);
            background: var(--background-secondary);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .user-type-item i {
            font-size: 1rem;
        }
        
        .user-type-item.admin i { color: var(--primary-color); }
        .user-type-item.police i { color: var(--police-color); }
        .user-type-item.emergency i { color: var(--emergency-color); }
        .user-type-item.barangay i { color: var(--barangay-color); }
        .user-type-item.firefighter i { color: var(--firefighter-color); }
        
        .back-link {
            text-align: center;
            margin-top: var(--spacing-lg);
        }
        
        .back-link a {
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: var(--primary-color);
        }
        
        @media (max-width: 480px) {
            .login-container {
                margin: var(--spacing-md);
                padding: var(--spacing-lg);
            }
            
            .user-type-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>System Login</h2>
            <p>Municipal Emergency Response System</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username" class="form-label">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="user-types">
            <h4>Authorized User Types</h4>
            <div class="user-type-grid">
                <div class="user-type-item admin">
                    <i class="fas fa-user-cog"></i>
                    <span>Administrator</span>
                </div>
                <div class="user-type-item police">
                    <i class="fas fa-shield-alt"></i>
                    <span>Police</span>
                </div>
                <div class="user-type-item emergency">
                    <i class="fas fa-ambulance"></i>
                    <span>Emergency</span>
                </div>
                <div class="user-type-item barangay">
                    <i class="fas fa-home"></i>
                    <span>Barangay</span>
                </div>
                <div class="user-type-item firefighter">
                    <i class="fas fa-fire-extinguisher"></i>
                    <span>Fire Fighter</span>
                </div>
            </div>
        </div>
        
        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Main Site
            </a>
        </div>
    </div>
</body>
</html>
