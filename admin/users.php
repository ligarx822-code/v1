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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id > 0 && $user_id != $_SESSION['user_id']) { // Can't modify own account
        switch ($action) {
            case 'ban':
                $stmt = $db->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User banned successfully.";
                }
                break;
                
            case 'unban':
                $stmt = $db->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User unbanned successfully.";
                }
                break;
                
            case 'delete':
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User deleted successfully.";
                }
                break;
                
            case 'make_admin':
                $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "User promoted to admin.";
                }
                break;
                
            case 'remove_admin':
                $stmt = $db->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "Admin privileges removed.";
                }
                break;
        }
    }
}

// Get users with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = sanitize($_GET['search'] ?? '');
$filter = sanitize($_GET['filter'] ?? 'all');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($filter) {
    case 'banned':
        $where_conditions[] = "is_banned = 1";
        break;
    case 'admin':
        $where_conditions[] = "is_admin = 1";
        break;
    case 'active':
        $where_conditions[] = "is_banned = 0";
        break;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$sql = "
    SELECT id, username, email, phone, is_admin, is_banned, created_at, last_login, profile_image,
           (SELECT COUNT(*) FROM posts WHERE author_id = users.id) as post_count,
           (SELECT COUNT(*) FROM comments WHERE user_id = users.id) as comment_count
    FROM users 
    $where_clause 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?> Admin</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="posts.php">Posts</a></li>
                <li><a href="users.php" class="active">Users</a></li>
                <li><a href="security.php">Security</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="flex justify-between items-center mb-4">
            <h1>User Management</h1>
            <div class="flex gap-2">
                <span>Total: <?php echo number_format($total_users); ?> users</span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <form method="GET" class="flex gap-2" style="flex-wrap: wrap;">
                <input type="text" name="search" class="form-input" placeholder="Search users..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                
                <select name="filter" class="form-input" style="width: auto;">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="banned" <?php echo $filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                    <option value="admin" <?php echo $filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="users.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Posts</th>
                            <th>Comments</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-2">
                                    <img src="../<?php echo UPLOAD_PATH; ?>profiles/<?php echo $user['profile_image']; ?>" 
                                         alt="Avatar" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;"
                                         onerror="this.src='../assets/default-avatar.jpg'">
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['is_admin']): ?>
                                            <span style="color: var(--accent-color); font-size: 0.75rem;">(Admin)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo number_format($user['post_count']); ?></td>
                            <td><?php echo number_format($user['comment_count']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?php echo date('M j, Y', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_banned']): ?>
                                    <span style="color: var(--error-color);">Banned</span>
                                <?php else: ?>
                                    <span style="color: var(--success-color);">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="flex gap-1" style="flex-wrap: wrap;">
                                        <?php if ($user['is_banned']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="unban">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-success" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Unban</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="ban">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" 
                                                        onclick="return confirm('Ban this user?')">Ban</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['is_admin']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_admin">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Remove Admin</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="make_admin">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Make Admin</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" 
                                                    onclick="return confirm('Delete this user permanently?')">Delete</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-light);">You</span>
                                <?php endif; ?>
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
                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_users)); ?> 
                of <?php echo number_format($total_users); ?> users
            </div>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                       class="btn btn-secondary">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                       class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" 
                       class="btn btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
