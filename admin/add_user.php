<?php
require_once '../config/config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$response = ['success' => false, 'message' => '', 'field' => null];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) {
        $errors[] = "Email is required";
        $response['field'] = "email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
        $response['field'] = "email";
    }
    if (empty($phone)) {
        $errors[] = "Phone number is required";
        $response['field'] = "phone";
    }
    if (empty($house_number) || empty($street)) {
        $errors[] = "House number and street are required";
        $response['field'] = "house_number";
    }
    if (empty($barangay)) {
        $errors[] = "Barangay is required";
        $response['field'] = "barangay";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
        $response['field'] = "password";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
        $response['field'] = "password";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
        $response['field'] = "confirm_password";
    }

    // Email check
    $email_check_stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $email_check_stmt->bindParam(':email', $email);
    $email_check_stmt->execute();
    if ($email_check_stmt->rowCount() > 0) {
        $errors[] = "Email address already exists";
        $response['field'] = "email";
    }

    // Phone check
    $phone_check_stmt = $db->prepare("SELECT id FROM users WHERE phone = :phone");
    $phone_check_stmt->bindParam(':phone', $phone);
    $phone_check_stmt->execute();
    if ($phone_check_stmt->rowCount() > 0) {
        $errors[] = "Phone number already exists";
        $response['field'] = "phone";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $insert_query = "INSERT INTO users 
            (first_name, last_name, email, phone, house_number, street, barangay, landmark, password, user_type, created_at) 
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

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'User created successfully!';
            $response['field'] = null;
        } else {
            $response['message'] = 'Error creating user.';
        }
    } else {
        $response['message'] = implode("<br>", $errors);
    }
} else {
    $response['message'] = 'Invalid request method';
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>
