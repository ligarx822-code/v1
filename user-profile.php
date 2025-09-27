<?php
require_once 'config/config.php';
require_once 'includes/security.php';

$security = new Security();
$security->checkDDoS();

$database = new Database();
$db = $database->getConnection();

// Get user ID from URL
$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) {
    redirectTo('index.php');
}

// Get user data
$stmt = $db->prepare("
    SELECT id, username, email, profile_image, created_at, last_login,
           (SELECT COUNT(*) FROM posts WHERE author_id = users.id AND status = 'published') as post_count,
           (SELECT COUNT(*) FROM comments WHERE user_id = users.id) as comment_count
    FROM users 
    WHERE id = ? AND is_banned = 0
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit;
}

// Get user's posts
$stmt = $db->prepare("
    SELECT id, title, slug, created_at, views, featured_image 
    FROM posts 
    WHERE author_id = ? AND status = 'published' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_posts = $stmt->fetchAll();

// Get user's recent comments
$stmt = $db->prepare("
    SELECT c.content, c.created_at, p.title as post_title, p.slug as post_slug, p.id as post_id
    FROM comments c 
    LEFT JOIN posts p ON c.post_id = p.id 
    WHERE c.user_id = ? AND p.status = 'published'
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$user_comments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?>'s Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="chat.php">Chat</a></li>
                    <li><a href="auth/profile.php">Profile</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="auth/login.php">Login</a></li>
                    <li><a href="auth/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="main-content">
            <!-- User Profile -->
            <div class="card mb-4">
                <div class="flex items-center gap-4">
                    <img src="<?php echo UPLOAD_PATH; ?>profiles/<?php echo $user['profile_image']; ?>" 
                         alt="<?php echo htmlspecialchars($user['username']); ?>" 
                         style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;"
                         onerror="this.src='assets/default-avatar.jpg'">
                    <div>
                        <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($user['username']); ?></h1>
                        <div style="color: var(--text-light); margin-bottom: 1rem;">
                            <p>Member since <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                            <?php if ($user['last_login']): ?>
                                <p>Last seen <?php echo date('M j, Y', strtotime($user['last_login'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-4">
                            <div class="text-center">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo number_format($user['post_count']); ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-light);">Posts</div>
                            </div>
                            <div class="text-center">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo number_format($user['comment_count']); ?>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--text-light);">Comments</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User's Posts -->
            <div class="card mb-4">
                <h3>Recent Posts</h3>
                <?php if (empty($user_posts)): ?>
                    <p>No posts yet.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($user_posts as $post): ?>
                            <div style="display: flex; gap: 1rem; padding: 1rem; background: var(--bg-light); border-radius: 6px;">
                                <?php if ($post['featured_image']): ?>
                                    <img src="<?php echo UPLOAD_PATH; ?>posts/<?php echo $post['featured_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                         style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;">
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <h4 style="margin-bottom: 0.5rem;">
                                        <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" 
                                           style="text-decoration: none; color: var(--text-color);">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </a>
                                    </h4>
                                    <div style="font-size: 0.875rem; color: var(--text-light);">
                                        <?php echo date('M j, Y', strtotime($post['created_at'])); ?> • 
                                        <?php echo number_format($post['views']); ?> views
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($user['post_count'] > 10): ?>
                        <div class="mt-4 text-center">
                            <a href="user-posts.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary">View All Posts</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- User's Recent Comments -->
            <div class="card">
                <h3>Recent Comments</h3>
                <?php if (empty($user_comments)): ?>
                    <p>No comments yet.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 1rem;">
                        <?php foreach ($user_comments as $comment): ?>
                            <div style="padding: 1rem; background: var(--bg-light); border-radius: 6px;">
                                <div style="font-size: 0.875rem; color: var(--text-light); margin-bottom: 0.5rem;">
                                    Commented on 
                                    <a href="post.php?slug=<?php echo urlencode($comment['post_slug']); ?>" 
                                       style="color: var(--primary-color); text-decoration: none;">
                                        <?php echo htmlspecialchars($comment['post_title']); ?>
                                    </a>
                                    • <?php echo date('M j, Y', strtotime($comment['created_at'])); ?>
                                </div>
                                <div>
                                    <?php echo nl2br(htmlspecialchars(substr($comment['content'], 0, 200))); ?>
                                    <?php if (strlen($comment['content']) > 200): ?>...<?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="widget">
                <h3 class="widget-title">Contact</h3>
                <?php if (isLoggedIn() && $_SESSION['user_id'] != $user['id']): ?>
                    <p>Want to get in touch with <?php echo htmlspecialchars($user['username']); ?>?</p>
                    <a href="chat.php" class="btn btn-primary" style="width: 100%;">Send Message</a>
                <?php elseif (isLoggedIn() && $_SESSION['user_id'] == $user['id']): ?>
                    <p>This is your profile page.</p>
                    <a href="auth/profile.php" class="btn btn-primary" style="width: 100%;">Edit Profile</a>
                <?php else: ?>
                    <p>Please <a href="auth/login.php">login</a> to contact this user.</p>
                <?php endif; ?>
            </div>

            <div class="widget">
                <h3 class="widget-title">Activity</h3>
                <div style="display: grid; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>Posts:</span>
                        <strong><?php echo number_format($user['post_count']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Comments:</span>
                        <strong><?php echo number_format($user['comment_count']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Joined:</span>
                        <strong><?php echo date('M Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
