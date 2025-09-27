<?php
require_once '../config/config.php';
require_once '../includes/security.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    redirectTo('../panel.php');
}

$security = new Security();
$security->checkDDoS();

$database = new Database();
$db = $database->getConnection();

$message = '';

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_id = (int)($_POST['post_id'] ?? 0);
    
    switch ($action) {
        case 'delete':
            $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
            if ($stmt->execute([$post_id])) {
                $message = "Post deleted successfully.";
            }
            break;
            
        case 'publish':
            $stmt = $db->prepare("UPDATE posts SET status = 'published' WHERE id = ?");
            if ($stmt->execute([$post_id])) {
                $message = "Post published successfully.";
            }
            break;
            
        case 'draft':
            $stmt = $db->prepare("UPDATE posts SET status = 'draft' WHERE id = ?");
            if ($stmt->execute([$post_id])) {
                $message = "Post moved to draft.";
            }
            break;
    }
}

// Get posts with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? 'all');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM posts $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_posts = $stmt->fetch()['total'];
$total_pages = ceil($total_posts / $per_page);

// Get posts
$sql = "
    SELECT p.*, u.username 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    $where_clause 
    ORDER BY p.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?> Admin</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="posts.php" class="active">Posts</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="security.php">Security</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="flex justify-between items-center mb-4">
            <h1>Post Management</h1>
            <a href="create-post.php" class="btn btn-primary">Create New Post</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <form method="GET" class="flex gap-2" style="flex-wrap: wrap;">
                <input type="text" name="search" class="form-input" placeholder="Search posts..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                
                <select name="status" class="form-input" style="width: auto;">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Posts</option>
                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="posts.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Posts Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars(substr($post['title'], 0, 50)); ?><?php echo strlen($post['title']) > 50 ? '...' : ''; ?></strong>
                                    <?php if ($post['featured_image']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">📷 Has image</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?></td>
                            <td>
                                <?php if ($post['status'] === 'published'): ?>
                                    <span style="color: var(--success-color); font-weight: bold;">Published</span>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($post['views']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($post['created_at'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($post['updated_at'])); ?></td>
                            <td>
                                <div class="flex gap-1" style="flex-wrap: wrap;">
                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Edit</a>
                                    
                                    <?php if ($post['status'] === 'draft'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="publish">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn btn-success" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Publish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="draft">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Draft</button>
                                        </form>
                                        
                                        <a href="../post.php?id=<?php echo $post['id']; ?>" target="_blank" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">View</a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" 
                                                onclick="return confirm('Delete this post permanently?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-4">
            <div>
                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_posts)); ?> 
                of <?php echo number_format($total_posts); ?> posts
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-secondary">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="btn btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
