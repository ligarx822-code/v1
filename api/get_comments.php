<?php
session_start();
require_once '../config/config.php';
require_once '../includes/security.php';

$postId = intval($_GET['post_id'] ?? 0);

if ($postId <= 0) {
    echo '<div style="text-align: center; padding: 2rem; color: var(--error);">Invalid post ID</div>';
    exit;
}

try {
    $database = getDatabase();
    $db = $database->getConnection();
    
    // Get comments
    $stmt = $db->prepare("
        SELECT c.*, u.username, u.profile_image 
        FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? 
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();
    
    if (empty($comments)) {
        echo '<div style="text-align: center; padding: 2rem; color: var(--text-muted);">No comments yet. Be the first to comment!</div>';
    } else {
        foreach ($comments as $comment) {
            $avatar = 'assets/default-avatar.jpg';
            if (!empty($comment['profile_image'])) {
                $avatar_path = '../' . UPLOAD_PATH . 'profiles/' . $comment['profile_image'];
                if (file_exists($avatar_path)) {
                    $avatar = '../' . UPLOAD_PATH . 'profiles/' . $comment['profile_image'];
                }
            }
            
            echo '<div class="comment" style="display: flex; gap: 1rem; margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 12px;">';
            echo '<img src="' . htmlspecialchars($avatar) . '" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary);">';
            echo '<div style="flex: 1;">';
            echo '<div style="font-weight: 700; color: var(--primary); margin-bottom: 0.25rem;">' . htmlspecialchars($comment['username']) . '</div>';
            echo '<div style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 0.5rem;">' . date('M j, Y \a\t g:i A', strtotime($comment['created_at'])) . '</div>';
            echo '<div style="color: var(--text-secondary); line-height: 1.5;">' . nl2br(htmlspecialchars($comment['content'])) . '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    // Add comment form if user is logged in
    if (isLoggedIn()) {
        echo '<div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border-light);">';
        echo '<h4 style="margin-bottom: 1rem; color: var(--primary);">Add a Comment</h4>';
        echo '<form onsubmit="submitComment(event, ' . $postId . ')">';
        echo '<textarea id="commentText" placeholder="Share your thoughts..." style="width: 100%; min-height: 100px; padding: 1rem; border: 2px solid var(--border); border-radius: 8px; font-family: inherit; resize: vertical;" required></textarea>';
        echo '<button type="submit" style="margin-top: 1rem; background: var(--gradient-primary); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; cursor: pointer;">Post Comment</button>';
        echo '</form>';
        echo '</div>';
    } else {
        echo '<div style="text-align: center; margin-top: 2rem; padding: 2rem; background: var(--bg-secondary); border-radius: 12px;">';
        echo '<p style="margin-bottom: 1rem;">Please login to add comments</p>';
        echo '<a href="../auth/login.php" style="background: var(--gradient-primary); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">Login</a>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div style="text-align: center; padding: 2rem; color: var(--error);">Error loading comments</div>';
}
?>

<script>
async function submitComment(event, postId) {
    event.preventDefault();
    
    const commentText = document.getElementById('commentText').value.trim();
    if (!commentText) return;
    
    try {
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('comment', commentText);
        
        const response = await fetch('../api/add_comment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload comments
            openCommentModal(postId);
        } else {
            alert(result.message || 'Error adding comment');
        }
    } catch (error) {
        alert('Network error occurred');
    }
}
</script>