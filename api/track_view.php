<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Only track views for logged-in users
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$postId = intval($_POST['post_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

try {
    // Check if user already viewed this post
    $stmt = $db->prepare("SELECT id FROM post_views WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    
    if (!$stmt->fetch()) {
        // Add new view record
        $stmt = $db->prepare("INSERT INTO post_views (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$postId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'View tracked']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Already viewed']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error occurred']);
}
?>
