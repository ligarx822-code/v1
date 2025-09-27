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
    $per_page = 9; // 3x3 grid
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
            SELECT p.*, u.username, u.profile_image,
                   (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
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
        <!-- Enhanced Hero Section -->
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

        <!-- Enhanced Search Section -->
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
            <!-- Enhanced Posts Section -->
            <div class="posts-grid">
                <?php if (empty($posts)): ?>
                    <div class="no-content" style="grid-column: 1 / -1;">
                        <h3>📝 No posts found</h3>
                        <p>There are no published posts yet. Check back later for amazing content!</p>
                        <?php if (isLoggedIn() && isAdmin()): ?>
                            <a href="admin/create-post.php" class="btn btn-primary">Create First Post</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="post-card">
                            <?php 
                            $image_path = '';
                            if (!empty($post['featured_image']) && file_exists(UPLOAD_PATH . 'posts/' . $post['featured_image'])) {
                                $image_path = UPLOAD_PATH . 'posts/' . $post['featured_image'];
                            } else {
                                $image_path = 'uploads/posts/default.png';
                            }
                            ?>
                            <img src="<?php echo $image_path; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                 class="post-image"
                                 onclick="window.location.href='post.php?slug=<?php echo urlencode($post['slug']); ?>'">
                            
                            <div class="post-content">
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
                                    echo htmlspecialchars(substr($excerpt, 0, 120)) . (strlen($excerpt) > 120 ? '...' : '');
                                    ?>
                                </div>
                                
                                <?php if (!empty($post['keywords'])): ?>
                                    <div class="post-keywords">
                                        <?php foreach (array_slice(explode(',', $post['keywords']), 0, 3) as $keyword): ?>
                                            <?php $keyword = trim($keyword); ?>
                                            <?php if (!empty($keyword)): ?>
                                                <span class="keyword-tag">
                                                    <?php echo htmlspecialchars($keyword); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="post-actions">
                                <button class="like-btn" onclick="toggleLike(<?php echo $post['id']; ?>, this)" 
                                        data-post-id="<?php echo $post['id']; ?>">
                                    <span class="like-icon">❤️</span>
                                    <span class="like-count"><?php echo number_format($post['likes_count'] ?? 0); ?></span>
                                </button>
                                
                                <button class="comment-btn" onclick="openCommentModal(<?php echo $post['id']; ?>)">
                                    <span>💬</span>
                                    <span><?php echo number_format($post['comments_count'] ?? 0); ?></span>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Enhanced Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination" style="grid-column: 1 / -1;">
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

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Popular Posts -->
                <div class="widget">
                    <h3 class="widget-title">🔥 Trending Posts</h3>
                    <?php if (empty($popular_posts)): ?>
                        <p>No posts yet.</p>
                    <?php else: ?>
                        <?php foreach ($popular_posts as $popular): ?>
                            <div class="mb-2">
                                <a href="post.php?id=<?php echo (int)$popular['id']; ?>" style="text-decoration: none; color: var(--text-primary);">
                                    <strong><?php echo htmlspecialchars(substr($popular['title'], 0, 50)); ?>...</strong>
                                </a>
                                <div style="font-size: 0.875rem; color: var(--text-light);">
                                    👁️ <?php echo number_format($popular['views'] ?? 0); ?> views
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Posts -->
                <div class="widget">
                    <h3 class="widget-title">📅 Recent Posts</h3>
                    <?php if (empty($recent_posts)): ?>
                        <p>No posts yet.</p>
                    <?php else: ?>
                        <?php foreach ($recent_posts as $recent): ?>
                            <div class="mb-2">
                                <a href="post.php?id=<?php echo (int)$recent['id']; ?>" style="text-decoration: none; color: var(--text-primary);">
                                    <strong><?php echo htmlspecialchars(substr($recent['title'], 0, 50)); ?>...</strong>
                                </a>
                                <div style="font-size: 0.875rem; color: var(--text-light);">
                                    📅 <?php echo date('M j, Y', strtotime($recent['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- About -->
                <div class="widget">
                    <h3 class="widget-title">ℹ️ About</h3>
                    <p>Welcome to our blog! Here you'll find amazing content about various topics. Join our community and share your thoughts!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Comment Modal -->
    <div class="comment-modal" id="commentModal">
        <div class="comment-modal-content">
            <div class="comment-modal-header">
                <h3>💬 Comments</h3>
                <button class="comment-modal-close" onclick="closeCommentModal()">×</button>
            </div>
            <div id="commentModalBody">
                <!-- Comments will be loaded here -->
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
        
        // Like functionality
        async function toggleLike(postId, button) {
            <?php if (!isLoggedIn()): ?>
                alert('Please login to like posts');
                window.location.href = 'auth/login.php';
                return;
            <?php endif; ?>
            
            try {
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('csrf_token', '<?php echo csrf_token(); ?>');
                
                const response = await fetch('api/like_post.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const likeCount = button.querySelector('.like-count');
                    const likeIcon = button.querySelector('.like-icon');
                    
                    if (result.action === 'liked') {
                        button.classList.add('liked');
                        likeIcon.textContent = '❤️';
                    } else {
                        button.classList.remove('liked');
                        likeIcon.textContent = '🤍';
                    }
                    
                    likeCount.textContent = result.likes_count;
                } else {
                    alert(result.message || 'Error occurred');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error occurred');
            }
        }
        
        // Comment modal functionality
        function openCommentModal(postId) {
            const modal = document.getElementById('commentModal');
            const modalBody = document.getElementById('commentModalBody');
            
            modalBody.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading comments...</div>';
            modal.classList.add('active');
            
            // Load comments via AJAX
            fetch(`post.php?id=${postId}&ajax=comments`)
                .then(response => response.text())
                .then(html => {
                    modalBody.innerHTML = html;
                })
                .catch(error => {
                    modalBody.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--error);">Error loading comments</div>';
                });
        }
        
        function closeCommentModal() {
            const modal = document.getElementById('commentModal');
            modal.classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('commentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCommentModal();
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
                
                // Lazy loading for images
                const images = document.querySelectorAll('img[data-src]');
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                images.forEach(img => imageObserver.observe(img));
                
            } catch (error) {
                console.log('JavaScript error:', error);
            }
        });
    </script>
</body>
</html>