<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $house_number = trim($_POST['house_number'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // $verification_status = $_POST['verification_status'] ?? '';
    // $verification_status = ($verification_status === 'on' || $verification_status === 'verified') ? 'verified' : 'unverified';

    // Validation
    $errors = [];

    if (empty($first_name)) {
        $errors[] = "First name is required";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }

    if (empty($house_number) || empty($street)) {
        $errors[] = "House number and street is required";
    }

    if (empty($barangay)) {
        $errors[] = "Barangay is required";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Check if email already exists
    $email_check_query = "SELECT id FROM users WHERE email = :email";
    $email_check_stmt = $db->prepare($email_check_query);
    $email_check_stmt->bindParam(':email', $email);
    $email_check_stmt->execute();
    if ($email_check_stmt->rowCount() > 0) {
        $errors[] = "Email address already exists";
    }

    // Check if phone already exists
    $phone_check_query = "SELECT id FROM users WHERE phone = :phone";
    $phone_check_stmt = $db->prepare($phone_check_query);
    $phone_check_stmt->bindParam(':phone', $phone);
    $phone_check_stmt->execute();
    if ($phone_check_stmt->rowCount() > 0) {
        $errors[] = "Phone number already exists";
    }

    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $insert_query = "INSERT INTO users (first_name, last_name, email, phone, house_number, street, barangay, landmark, password, user_type, created_at) 
                        VALUES (:first_name, :last_name, :email, :phone, :house_number, :street, :barangay, :landmark, :password, 'resident', NOW())";

        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':house_number', $house_number);
        $stmt->bindParam(':street', $street);
        $stmt->bindParam(':barangay', $barangay);
        $stmt->bindParam(':landmark', $landmark);
        $stmt->bindParam(':password', $hashed_password);
        // $stmt->bindParam(':verification_status', $verification_status);
        
    

        if ($stmt->execute()) {
            $user_id = $db->lastInsertId();
            
            // Log admin activity
            logActivity($_SESSION['user_id'], 'User created', 'users', $user_id, null, [
                'name' => $first_name . ' ' . $last_name,
                'email' => $email
            ]);

            $response['success'] = true;
            $response['message'] = 'User created successfully!';
            $response['user_id'] = $user_id;
        } else {
            $response['message'] = 'Error creating user.';
        }
    } else {
        $response['message'] = implode('<br>', $errors);
    }
} else {
    $response['message'] = 'Invalid request method';
}

// Return JSON response for AJAX requests
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Redirect back to users page with message
if ($response['success']) {
    header('Location: users.php?success=' . urlencode($response['message']));
} else {
    header('Location: users.php?error=' . urlencode($response['message']));
}
exit();
?>
