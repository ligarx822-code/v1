<?php
try {
    require_once 'config/config.php';
    
    $includes = ['includes/security.php', 'includes/stats.php'];
    foreach ($includes as $include) {
        if (file_exists($include)) {
            require_once $include;
        }
    }
    
    $security = null;
    $stats = null;
    
    if (class_exists('Security')) {
        try {
            $security = new Security();
            $security->checkDDoS();
        } catch (Exception $e) {
            error_log("Security check error: " . $e->getMessage());
        }
    }
    
    if (class_exists('StatsTracker')) {
        try {
            $stats = new StatsTracker();
            $stats->trackVisit();
        } catch (Exception $e) {
            error_log("Stats tracking error: " . $e->getMessage());
        }
    }
    
    $database = getDatabase();
    $db = $database ? $database->getConnection() : null;
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 6;
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = ["status = 'published'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(title LIKE ? OR content LIKE ? OR keywords LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    $total_posts = 0;
    try {
        $count_sql = "SELECT COUNT(*) as total FROM posts $where_clause";
        $stmt = $db->prepare($count_sql);
        if ($stmt && $stmt->execute($params)) {
            $result = $stmt->fetch();
            $total_posts = $result ? (int)$result['total'] : 0;
        }
    } catch (Exception $e) {
        error_log("Count query error: " . $e->getMessage());
    }
    
    $total_pages = $total_posts > 0 ? ceil($total_posts / $per_page) : 1;
    
    $posts = [];
    try {
        $sql = "
            SELECT p.*, u.username, u.profile_image 
            FROM posts p 
            LEFT JOIN users u ON p.author_id = u.id 
            $where_clause 
            ORDER BY p.created_at DESC 
            LIMIT $per_page OFFSET $offset
        ";
        $stmt = $db->prepare($sql);
        if ($stmt && $stmt->execute($params)) {
            $posts = $stmt->fetchAll() ?: [];
        }
    } catch (Exception $e) {
        error_log("Posts query error: " . $e->getMessage());
    }
    
    $popular_posts = [];
    $recent_posts = [];
    $user_count = 0;
    $total_views = 0;
    
    try {
        $stmt = $db->query("SELECT id, title, views FROM posts WHERE status = 'published' ORDER BY views DESC LIMIT 5");
        $popular_posts = $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        error_log("Popular posts query error: " . $e->getMessage());
    }
    
    try {
        $stmt = $db->query("SELECT id, title, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT 5");
        $recent_posts = $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        error_log("Recent posts query error: " . $e->getMessage());
    }
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt ? $stmt->fetch() : false;
        $user_count = $result ? (int)$result['count'] : 0;
    } catch (Exception $e) {
        error_log("User count query error: " . $e->getMessage());
    }
    
    try {
        $stmt = $db->query("SELECT SUM(views) as total FROM posts");
        $result = $stmt ? $stmt->fetch() : false;
        $total_views = $result && $result['total'] ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Total views query error: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Critical error in index.php: " . $e->getMessage());
    $posts = [];
    $popular_posts = [];
    $recent_posts = [];
    $total_posts = 0;
    $total_pages = 1;
    $user_count = 0;
    $total_views = 0;
    $search = '';
    $page = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo defined('SITE_NAME') ? SITE_NAME : 'My Blog'; ?> - Discover Amazing Content</title>
    <meta name="description" content="Join our community and discover amazing blog posts, engage in discussions, and share your thoughts.">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Enhanced hero section with animated background */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-white);
            text-align: center;
            padding: 4rem 2rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 3rem;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #fff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: slideInDown 1s ease;
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            animation: slideInUp 1s ease 0.3s both;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2rem;
            animation: fadeIn 1s ease 0.6s both;
        }
        
        .hero-stat {
            text-align: center;
        }
        
        .hero-stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        
        .hero-stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        /* Enhanced search section with better styling */
        .search-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
            margin-bottom: 3rem;
            position: relative;
        }
        
        .search-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 20px 20px 0 0;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: stretch;
            margin-bottom: 1rem;
        }
        
        .search-input {
            flex: 1;
            padding: 1.25rem 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            font-size: 1.1rem;
            background: var(--bg-light);
            transition: var(--transition-normal);
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }
        
        .search-results-info {
            color: var(--text-light);
            font-style: italic;
            animation: slideInLeft 0.5s ease;
        }
        
        /* Enhanced post cards with better hover effects */
        .posts-grid {
            display: grid;
            gap: 2rem;
        }
        
        .post-card {
            background: var(--gradient-card);
            border-radius: 20px;
            padding: 0;
            overflow: hidden;
            transition: var(--transition-normal);
            border: 1px solid var(--border-light);
            position: relative;
        }
        
        .post-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: var(--transition-normal);
        }
        
        .post-card:hover::before {
            transform: scaleX(1);
        }
        
        .post-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        
        .post-card-content {
            padding: 2rem;
        }
        
        .post-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: var(--transition-normal);
        }
        
        .post-card:hover .post-image {
            transform: scale(1.05);
        }
        
        .post-title a {
            text-decoration: none;
            color: inherit;
            transition: var(--transition-normal);
        }
        
        .post-title a:hover {
            color: var(--primary-color);
        }
        
        .post-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .author-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        .post-keywords {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .keyword-tag {
            background: var(--gradient-primary);
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition-normal);
        }
        
        .keyword-tag:hover {
            transform: scale(1.05);
        }
        
        /* Enhanced pagination with better styling */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 3rem;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        
        .pagination-info {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination-buttons .btn {
            min-width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        /* Enhanced contact section */
        .contact-section {
            background: var(--bg-light);
            padding: 4rem 0;
            margin-top: 4rem;
            position: relative;
        }
        
        .contact-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gradient-primary);
        }
        
        .contact-card {
            max-width: 700px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-light);
            position: relative;
        }
        
        .contact-title {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .captcha-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .captcha-image {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition-normal);
        }
        
        .captcha-image:hover {
            border-color: var(--primary-color);
            transform: scale(1.02);
        }
        
        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Enhanced responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .pagination {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .contact-card {
                padding: 2rem;
                margin: 0 1rem;
            }
            
            .captcha-container {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        /* Enhanced error handling styles */
        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 1px solid #f87171;
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
            text-align: center;
        }
        
        .no-content {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-light);
            margin: 2rem 0;
        }
        
        .no-content h3 {
            color: var(--text-color);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .no-content p {
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="index.php" class="logo"><?php echo defined('SITE_NAME') ? SITE_NAME : 'My Blog'; ?></a>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">🏠 Home</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="chat.php">💬 Chat</a></li>
                    <li><a href="auth/profile.php">👤 Profile</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/dashboard.php">⚙️ Admin</a></li>
                    <?php endif; ?>
                    <li><a href="auth/logout.php">🚪 Logout</a></li>
                <?php else: ?>
                    <li><a href="auth/login.php">🔑 Login</a></li>
                    <li><a href="auth/register.php">📝 Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <div class="container">
         Enhanced Hero Section 
        <div class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Welcome to <?php echo defined('SITE_NAME') ? SITE_NAME : 'My Blog'; ?></h1>
                <p class="hero-subtitle">Discover amazing content, engage with our community, and share your thoughts</p>
                
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?php echo number_format($total_posts); ?></span>
                        <span class="hero-stat-label">Posts Published</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?php echo number_format($user_count); ?></span>
                        <span class="hero-stat-label">Community Members</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?php echo number_format($total_views); ?></span>
                        <span class="hero-stat-label">Total Views</span>
                    </div>
                </div>
            </div>
        </div>

         Enhanced Search Section 
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="🔍 Search posts by title, content, or keywords..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
            <?php if ($search): ?>
                <p class="search-results-info">
                    Found <?php echo number_format($total_posts); ?> posts for "<?php echo htmlspecialchars($search); ?>"
                </p>
            <?php endif; ?>
        </div>

        <div class="main-content">
             Enhanced Posts Section 
            <div class="posts-grid">
                <?php if (empty($posts)): ?>
                    <div class="no-content">
                        <h3>📝 No posts found</h3>
                        <p>There are no published posts yet. Check back later for amazing content!</p>
                        <?php if (isLoggedIn() && isAdmin()): ?>
                            <a href="admin/create-post.php" class="btn btn-primary">Create First Post</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="post-card">
                            <?php if (!empty($post['featured_image']) && file_exists(UPLOAD_PATH . 'posts/' . $post['featured_image'])): ?>
                                <img src="<?php echo UPLOAD_PATH; ?>posts/<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($post['title']); ?>" class="post-image">
                            <?php endif; ?>
                            
                            <div class="post-card-content">
                                <h2 class="post-title">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h2>
                                
                                <div class="post-meta">
                                    <?php 
                                    $profile_image = !empty($post['profile_image']) && file_exists(UPLOAD_PATH . 'profiles/' . $post['profile_image']) 
                                        ? UPLOAD_PATH . 'profiles/' . $post['profile_image'] 
                                        : 'assets/default-avatar.jpg';
                                    ?>
                                    <img src="<?php echo $profile_image; ?>" alt="Author" class="author-avatar">
                                    <span>By <strong><?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?></strong></span>
                                    <span>•</span>
                                    <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                    <span>•</span>
                                    <span>👁️ <?php echo number_format($post['views'] ?? 0); ?></span>
                                </div>
                                
                                <div class="post-excerpt">
                                    <?php 
                                    $content = $post['content'] ?? '';
                                    $excerpt = strip_tags($content);
                                    echo htmlspecialchars(substr($excerpt, 0, 180)) . (strlen($excerpt) > 180 ? '...' : '');
                                    ?>
                                </div>
                                
                                <?php if (!empty($post['keywords'])): ?>
                                    <div class="post-keywords">
                                        <?php foreach (explode(',', $post['keywords']) as $keyword): ?>
                                            <?php $keyword = trim($keyword); ?>
                                            <?php if (!empty($keyword)): ?>
                                                <span class="keyword-tag">
                                                    <?php echo htmlspecialchars($keyword); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 1.5rem;">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-primary">
                                        Read More →
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

                 Enhanced Pagination 
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                        (<?php echo number_format($total_posts); ?> posts)
                    </div>
                    <div class="pagination-buttons">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" 
                               class="btn btn-secondary">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                               class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" 
                               class="btn btn-secondary">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

             Sidebar 
            <div class="sidebar">
                 Popular Posts 
                <div class="widget">
                    <h3 class="widget-title">Popular Posts</h3>
                    <?php if (empty($popular_posts)): ?>
                        <p>No posts yet.</p>
                    <?php else: ?>
                        <?php foreach ($popular_posts as $popular): ?>
                            <div class="mb-2">
                                <a href="post.php?id=<?php echo (int)$popular['id']; ?>" style="text-decoration: none; color: var(--text-color);">
                                    <strong><?php echo htmlspecialchars(substr($popular['title'], 0, 50)); ?>...</strong>
                                </a>
                                <div style="font-size: 0.875rem; color: var(--text-light);">
                                    <?php echo number_format($popular['views'] ?? 0); ?> views
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                 Recent Posts 
                <div class="widget">
                    <h3 class="widget-title">Recent Posts</h3>
                    <?php if (empty($recent_posts)): ?>
                        <p>No posts yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_posts as $recent): ?>
                            <div class="mb-2">
                                <a href="post.php?id=<?php echo (int)$recent['id']; ?>" style="text-decoration: none; color: var(--text-color);">
                                    <strong><?php echo htmlspecialchars(substr($recent['title'], 0, 50)); ?>...</strong>
                                </a>
                                <div style="font-size: 0.875rem; color: var(--text-light);">
                                    <?php echo date('M j, Y', strtotime($recent['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                 About 
                <div class="widget">
                    <h3 class="widget-title">About</h3>
                    <p>Welcome to our blog! Here you'll find amazing content about various topics. Join our community and share your thoughts!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Contact Section -->
    <div class="contact-section">
        <div class="container">
            <div class="contact-card">
                <h3 class="contact-title">💬 Get In Touch</h3>
                <form id="contactForm" method="POST" action="contact.php">
                    <div class="form-group">
                        <label class="form-label">👤 Full Name *</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📧 Email Address *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📱 Phone Number</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">💭 Your Message *</label>
                        <textarea name="message" class="form-input form-textarea" 
                                  placeholder="Tell us what's on your mind..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 Security Verification *</label>
                        <div class="captcha-container">
                            <img id="captchaImage" src="captcha.php" alt="Captcha" class="captcha-image" 
                                 onclick="this.src='captcha.php?'+Math.random()">
                            <div style="flex: 1;">
                                <input type="text" name="captcha" class="form-input" 
                                       placeholder="Enter the code shown above" required>
                                <small style="color: var(--text-light); display: block; margin-top: 0.5rem;">
                                    Click image to refresh
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem;">
                        🚀 Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }
        
        // Close mobile nav when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.querySelector('.nav');
            const navLinks = document.getElementById('navLinks');
            if (!nav.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Smooth scroll for anchor links
                document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function (e) {
                        e.preventDefault();
                        const target = document.querySelector(this.getAttribute('href'));
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                });
                
                // Auto-refresh captcha every 2 minutes
                setInterval(function() {
                    const captcha = document.getElementById('captchaImage');
                    if (captcha) {
                        captcha.src = 'captcha.php?' + Math.random();
                    }
                }, 120000);
                
                // Form validation feedback
                const form = document.getElementById('contactForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const btn = form.querySelector('button[type="submit"]');
                        if (btn) {
                            btn.innerHTML = '<span class="loading"></span> Sending...';
                            btn.disabled = true;
                        }
                    });
                }
            } catch (error) {
                console.log('JavaScript error:', error);
            }
        });
    </script>
</body>
</html>
