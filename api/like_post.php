<?php
session_start();
require_once '../config/config.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to like posts']);
    exit;
}

$postId = intval($_POST['post_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit;
}

try {
    $database = getDatabase();
    $db = $database->getConnection();
    
    // Check if post exists
    $stmt = $db->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Check if user already liked this post
    $stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $existingLike = $stmt->fetch();
    
    if ($existingLike) {
        // Unlike the post
        $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $action = 'unliked';
    } else {
        // Like the post
        $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$postId, $userId]);
        $action = 'liked';
    }
    
    // Get updated like count
    $stmt = $db->prepare("SELECT COUNT(*) as likes_count FROM post_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $result = $stmt->fetch();
    $likes_count = $result['likes_count'];
    
    echo json_encode([
        'success' => true, 
        'action' => $action,
        'likes_count' => $likes_count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>