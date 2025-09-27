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
$stmt = $db->prepare("SELECT username FROM users WHERE id = ? AND is_banned = 0");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total posts count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'published'");
$stmt->execute([$user_id]);
$total_posts = $stmt->fetch()['total'];
$total_pages = ceil($total_posts / $per_page);

// Get user's posts
$stmt = $db->prepare("
    SELECT id, title, slug, content, created_at, views, featured_image, keywords
    FROM posts 
    WHERE author_id = ? AND status = 'published' 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?>'s Posts - <?php echo SITE_NAME; ?></title>
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
            <div class="flex justify-between items-center mb-4">
                <h1>Posts by <?php echo htmlspecialchars($user['username']); ?></h1>
                <a href="user-profile.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">← Back to Profile</a>
            </div>

            <?php if (empty($posts)): ?>
                <div class="card text-center">
                    <h3>No posts found</h3>
                    <p><?php echo htmlspecialchars($user['username']); ?> hasn't published any posts yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post-card card">
                        <?php if ($post['featured_image']): ?>
                            <img src="<?php echo UPLOAD_PATH; ?>posts/<?php echo $post['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
                        <?php endif; ?>
                        
                        <h2 class="post-title">
                            <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" style="text-decoration: none; color: inherit;">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </h2>
                        
                        <div class="post-meta">
                            <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                            <span>•</span>
                            <span><?php echo number_format($post['views']); ?> views</span>
                        </div>
                        
                        <div class="post-excerpt">
                            <?php 
                            $excerpt = strip_tags($post['content']);
                            echo htmlspecialchars(substr($excerpt, 0, 200)) . (strlen($excerpt) > 200 ? '...' : '');
                            ?>
                        </div>
                        
                        <?php if ($post['keywords']): ?>
                            <div class="mt-2">
                                <?php foreach (explode(',', $post['keywords']) as $keyword): ?>
                                    <span style="background: var(--bg-light); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; margin-right: 0.5rem;">
                                        <?php echo htmlspecialchars(trim($keyword)); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-primary">Read More</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-between items-center mt-4">
                <div>
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                    (<?php echo number_format($total_posts); ?> posts)
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $user_id; ?>&page=<?php echo $page - 1; ?>" 
                           class="btn btn-secondary">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?id=<?php echo $user_id; ?>&page=<?php echo $i; ?>" 
                           class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $user_id; ?>&page=<?php echo $page + 1; ?>" 
                           class="btn btn-secondary">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="widget">
                <h3 class="widget-title">About <?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Total posts: <strong><?php echo number_format($total_posts); ?></strong></p>
                <a href="user-profile.php?id=<?php echo $user_id; ?>" class="btn btn-primary" style="width: 100%;">View Profile</a>
            </div>
        </div>
    </div>
</body>
</html>
