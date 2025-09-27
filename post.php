<?php
require_once 'config/config.php';
require_once 'includes/security.php';
require_once 'includes/stats.php';

$security = new Security();
$security->checkDDoS();

$database = new Database();
$db = $database->getConnection();

// Get post by slug or ID
$slug = sanitize($_GET['slug'] ?? '');
$post_id = (int)($_GET['id'] ?? 0);

if ($slug) {
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.profile_image 
        FROM posts p 
        LEFT JOIN users u ON p.author_id = u.id 
        WHERE p.slug = ? AND p.status = 'published'
    ");
    $stmt->execute([$slug]);
} else {
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.profile_image 
        FROM posts p 
        LEFT JOIN users u ON p.author_id = u.id 
        WHERE p.id = ? AND p.status = 'published'
    ");
    $stmt->execute([$post_id]);
}

$post = $stmt->fetch();

if (!$post) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit;
}

// Track post view
$stats = new StatsTracker();
$stats->trackPostView($post['id']);

// Get comments
$stmt = $db->prepare("
    SELECT c.*, u.username, u.profile_image 
    FROM comments c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$post['id']]);
$comments = $stmt->fetchAll();

// Handle comment submission
$comment_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $comment_content = sanitize($_POST['comment'] ?? '');
    
    if (!empty($comment_content)) {
        $stmt = $db->prepare("
            INSERT INTO comments (post_id, user_id, content) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$post['id'], $_SESSION['user_id'], $comment_content])) {
            $comment_message = "Comment added successfully!";
            // Refresh comments
            $stmt = $db->prepare("
                SELECT c.*, u.username, u.profile_image 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.post_id = ? 
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$post['id']]);
            $comments = $stmt->fetchAll();
        } else {
            $comment_message = "Failed to add comment.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .post-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 40px;
        }
        
        .post-article {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .post-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 0;
        }
        
        .post-content-wrapper {
            padding: 40px;
        }
        
        .post-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        
        .post-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
            padding: 20px;
            background: linear-gradient(145deg, #f8fafc, #e2e8f0);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .author-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .author-info h4 {
            margin: 0;
            font-size: 1.2em;
            font-weight: 700;
            color: #334155;
        }
        
        .author-info p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 0.95em;
        }
        
        .post-keywords {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
        }
        
        .keyword-tag {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }
        
        .keyword-tag:hover {
            transform: translateY(-2px);
        }
        
        .post-content {
            line-height: 1.8;
            font-size: 1.15em;
            color: #334155;
        }
        
        .post-content img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .post-content iframe {
            max-width: 100%;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .post-content pre {
            background: linear-gradient(145deg, #1e293b, #334155);
            color: #e2e8f0;
            padding: 24px;
            border-radius: 12px;
            overflow-x: auto;
            margin: 2rem 0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .post-content code {
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
            color: #667eea;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .post-content blockquote {
            border-left: 4px solid #667eea;
            padding: 20px 24px;
            margin: 2rem 0;
            background: linear-gradient(145deg, #f8fafc, #f1f5f9);
            border-radius: 0 12px 12px 0;
            font-style: italic;
            color: #475569;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .comments-section {
            margin-top: 40px;
        }
        
        .comments-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .comments-header h3 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 700;
        }
        
        .comment-form {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .comment-form h4 {
            margin-bottom: 20px;
            color: #667eea;
            font-size: 1.4em;
            font-weight: 700;
        }
        
        .comment-textarea {
            width: 100%;
            min-height: 120px;
            padding: 20px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            font-size: 1.05em;
            font-family: inherit;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            resize: vertical;
        }
        
        .comment-textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .comment-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            margin-top: 16px;
        }
        
        .comment-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }
        
        .comment {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            display: flex;
            gap: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .comment:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .comment-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            flex-shrink: 0;
        }
        
        .comment-content {
            flex: 1;
        }
        
        .comment-author {
            font-weight: 700;
            font-size: 1.1em;
            color: #667eea;
            margin-bottom: 4px;
        }
        
        .comment-author a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s ease;
        }
        
        .comment-author a:hover {
            color: #764ba2;
        }
        
        .comment-date {
            color: #64748b;
            font-size: 0.9em;
            margin-bottom: 12px;
        }
        
        .comment-text {
            line-height: 1.6;
            color: #334155;
            font-size: 1.05em;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .widget {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .widget-title {
            color: #667eea;
            font-size: 1.3em;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .author-widget {
            text-align: center;
        }
        
        .author-widget-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            margin: 0 auto 16px;
        }
        
        .related-post {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            background: linear-gradient(145deg, #f8fafc, #f1f5f9);
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .related-post:hover {
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
            transform: translateX(4px);
        }
        
        .related-post a {
            text-decoration: none;
            color: #334155;
            font-weight: 600;
        }
        
        .related-post-views {
            color: #64748b;
            font-size: 0.9em;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .post-container {
                grid-template-columns: 1fr;
                padding: 12px;
                gap: 24px;
            }
            
            .post-content-wrapper {
                padding: 24px;
            }
            
            .post-title {
                font-size: 2rem;
            }
            
            .comment {
                flex-direction: column;
                gap: 16px;
            }
            
            .comment-avatar {
                width: 48px;
                height: 48px;
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="post-container">
        <div class="main-content">
            <!-- Post Content -->
            <article class="post-article">
                <?php if ($post['featured_image']): ?>
                    <img src="<?php echo UPLOAD_PATH; ?>posts/<?php echo $post['featured_image']; ?>" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
                <?php endif; ?>
                
                <div class="post-content-wrapper">
                    <h1 class="post-title">
                        <?php echo htmlspecialchars($post['title']); ?>
                    </h1>
                    
                    <div class="post-meta">
                        <img src="<?php echo UPLOAD_PATH; ?>profiles/<?php echo $post['profile_image']; ?>" 
                             alt="Author" class="author-avatar"
                             onerror="this.src='assets/default-avatar.jpg'">
                        <div class="author-info">
                            <h4>
                                <a href="user-profile.php?id=<?php echo $post['author_id']; ?>" 
                                   style="text-decoration: none; color: inherit;">
                                    <?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?>
                                </a>
                            </h4>
                            <p>
                                <?php echo date('M j, Y \a\t g:i A', strtotime($post['created_at'])); ?> • 
                                <?php echo number_format($post['views']); ?> views
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($post['keywords']): ?>
                        <div class="post-keywords">
                            <?php foreach (explode(',', $post['keywords']) as $keyword): ?>
                                <span class="keyword-tag">
                                    <?php echo htmlspecialchars(trim($keyword)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="post-content">
                        <?php echo $post['content']; // Content is stored as HTML ?>
                    </div>
                    
                    <div class="post-actions">
                        <a href="index.php" class="btn btn-secondary">← Back to Blog</a>
                        <div>
                            <button onclick="window.print()" class="btn btn-secondary">Print</button>
                            <button onclick="navigator.share ? navigator.share({title: '<?php echo addslashes($post['title']); ?>', url: window.location.href}) : alert('Share: ' + window.location.href)" class="btn btn-primary">Share</button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Comments Section -->
            <div class="comments-section">
                <div class="comments-header">
                    <h3>Comments (<?php echo count($comments); ?>)</h3>
                </div>
                
                <?php if ($comment_message): ?>
                    <div class="alert alert-success">
                        <p><?php echo $comment_message; ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                    <form method="POST" class="comment-form">
                        <h4>Add a Comment</h4>
                        <textarea name="comment" class="comment-textarea" 
                                  placeholder="Share your thoughts..." required></textarea>
                        <button type="submit" class="comment-submit">Post Comment</button>
                    </form>
                <?php else: ?>
                    <div class="comment-form" style="text-align: center;">
                        <p style="font-size: 1.2em; margin-bottom: 20px;">Join the conversation!</p>
                        <a href="auth/login.php" class="btn btn-primary" style="margin-right: 12px;">Login</a>
                        <a href="auth/register.php" class="btn btn-secondary">Register</a>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($comments)): ?>
                    <div class="comment-form" style="text-align: center;">
                        <p style="font-size: 1.1em; color: #64748b;">No comments yet. Be the first to share your thoughts!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <a href="user-profile.php?id=<?php echo $comment['user_id']; ?>">
                                <img src="<?php echo UPLOAD_PATH; ?>profiles/<?php echo $comment['profile_image']; ?>" 
                                     alt="<?php echo htmlspecialchars($comment['username']); ?>" class="comment-avatar"
                                     onerror="this.src='assets/default-avatar.jpg'">
                            </a>
                            <div class="comment-content">
                                <div class="comment-author">
                                    <a href="user-profile.php?id=<?php echo $comment['user_id']; ?>">
                                        <?php echo htmlspecialchars($comment['username']); ?>
                                    </a>
                                </div>
                                <div class="comment-date">
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                </div>
                                <div class="comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Author Info -->
            <div class="widget author-widget">
                <h3 class="widget-title">About the Author</h3>
                <img src="<?php echo UPLOAD_PATH; ?>profiles/<?php echo $post['profile_image']; ?>" 
                     alt="<?php echo htmlspecialchars($post['username']); ?>" 
                     class="author-widget-avatar"
                     onerror="this.src='assets/default-avatar.jpg'">
                <h4 style="margin-bottom: 16px; color: #334155;">
                    <?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?>
                </h4>
                <a href="user-profile.php?id=<?php echo $post['author_id']; ?>" class="btn btn-primary" style="width: 100%;">View Profile</a>
            </div>

            <!-- Related Posts -->
            <div class="widget">
                <h3 class="widget-title">Related Posts</h3>
                <?php
                // Get related posts based on keywords
                $related_posts = [];
                if ($post['keywords']) {
                    $keywords = explode(',', $post['keywords']);
                    $keyword_conditions = [];
                    $keyword_params = [];
                    
                    foreach ($keywords as $keyword) {
                        $keyword_conditions[] = "keywords LIKE ?";
                        $keyword_params[] = "%" . trim($keyword) . "%";
                    }
                    
                    if (!empty($keyword_conditions)) {
                        $sql = "
                            SELECT id, title, views, slug
                            FROM posts 
                            WHERE status = 'published' AND id != ? AND (" . implode(' OR ', $keyword_conditions) . ")
                            ORDER BY views DESC 
                            LIMIT 5
                        ";
                        $stmt = $db->prepare($sql);
                        $stmt->execute(array_merge([$post['id']], $keyword_params));
                        $related_posts = $stmt->fetchAll();
                    }
                }
                ?>
                
                <?php if (empty($related_posts)): ?>
                    <p style="text-align: center; color: #64748b;">No related posts found.</p>
                <?php else: ?>
                    <?php foreach ($related_posts as $related): ?>
                        <div class="related-post">
                            <a href="post.php?slug=<?php echo urlencode($related['slug']); ?>">
                                <?php echo htmlspecialchars(substr($related['title'], 0, 60)); ?><?php echo strlen($related['title']) > 60 ? '...' : ''; ?>
                            </a>
                            <div class="related-post-views">
                                <?php echo number_format($related['views']); ?> views
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
