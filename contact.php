<?php
require_once 'config/config.php';
require_once 'includes/security.php';

$security = new Security();
$security->checkDDoS();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $captcha = sanitize($_POST['captcha'] ?? '');
    
    // Validation
    if (empty($name)) {
        $response['message'] = 'Name is required';
    } elseif (empty($email)) {
        $response['message'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format';
    } elseif (empty($message)) {
        $response['message'] = 'Message is required';
    } elseif (!$security->verifyCaptcha($captcha)) {
        $response['message'] = 'Invalid captcha';
    } else {
        // Save contact message
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO contact_messages (name, email, phone, message) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$name, $email, $phone, $message])) {
            $response['success'] = true;
            $response['message'] = 'Thank you! Your message has been sent successfully.';
        } else {
            $response['message'] = 'Failed to send message. Please try again.';
        }
    }
}

// Return JSON response for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redirect back with message for regular form submission
if ($response['success']) {
    $_SESSION['contact_message'] = $response['message'];
    $_SESSION['contact_type'] = 'success';
} else {
    $_SESSION['contact_message'] = $response['message'];
    $_SESSION['contact_type'] = 'error';
}

redirectTo('index.php#contact');
?>
