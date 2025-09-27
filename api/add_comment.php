<?php
session_start();
require_once '../config/config.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to add comments']);
    exit;
}

$postId = intval($_POST['post_id'] ?? 0);
$comment = sanitize($_POST['comment'] ?? '');
$userId = $_SESSION['user_id'];

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
    exit;
}

try {
    $database = getDatabase();
    $db = $database->getConnection();
    
    // Check if post exists
    $stmt = $db->prepare("SELECT id FROM posts WHERE id = ? AND status = 'published'");
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Add comment
    $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$postId, $userId, $comment])) {
        echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>