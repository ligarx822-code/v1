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

// Handle IP ban actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'ban_ip') {
        $ip_address = sanitize($_POST['ip_address'] ?? '');
        $reason = sanitize($_POST['reason'] ?? 'Manual ban by admin');
        $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
        
        if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
            $ban_expires = $is_permanent ? null : date('Y-m-d H:i:s', time() + DDOS_BAN_DURATION);
            
            $stmt = $db->prepare("
                INSERT INTO ddos_bans (ip_address, ban_reason, ban_expires, is_permanent) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                ban_reason = VALUES(ban_reason),
                ban_expires = VALUES(ban_expires),
                is_permanent = VALUES(is_permanent),
                banned_at = NOW()
            ");
            
            if ($stmt->execute([$ip_address, $reason, $ban_expires, $is_permanent])) {
                $message = "IP address banned successfully.";
            } else {
                $message = "Failed to ban IP address.";
            }
        } else {
            $message = "Invalid IP address format.";
        }
    } elseif ($action === 'unban_ip') {
        $ban_id = (int)($_POST['ban_id'] ?? 0);
        
        $stmt = $db->prepare("DELETE FROM ddos_bans WHERE id = ?");
        if ($stmt->execute([$ban_id])) {
            $message = "IP address unbanned successfully.";
        }
    }
}

// Get banned IPs
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = sanitize($_GET['search'] ?? '');
$filter = sanitize($_GET['filter'] ?? 'all');

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "ip_address LIKE ?";
    $params[] = "%$search%";
}

switch ($filter) {
    case 'active':
        $where_conditions[] = "(is_permanent = 1 OR ban_expires > NOW())";
        break;
    case 'expired':
        $where_conditions[] = "(is_permanent = 0 AND ban_expires <= NOW())";
        break;
    case 'permanent':
        $where_conditions[] = "is_permanent = 1";
        break;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM ddos_bans $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_bans = $stmt->fetch()['total'];
$total_pages = ceil($total_bans / $per_page);

// Get banned IPs
$sql = "
    SELECT * FROM ddos_bans 
    $where_clause 
    ORDER BY banned_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$banned_ips = $stmt->fetchAll();

// Get security statistics
$stats = [];

// Active bans
$stmt = $db->query("SELECT COUNT(*) as count FROM ddos_bans WHERE is_permanent = 1 OR ban_expires > NOW()");
$stats['active_bans'] = $stmt->fetch()['count'];

// Total bans today
$stmt = $db->query("SELECT COUNT(*) as count FROM ddos_bans WHERE DATE(banned_at) = CURDATE()");
$stats['today_bans'] = $stmt->fetch()['count'];

// Total requests tracked today
$stmt = $db->query("SELECT SUM(request_count) as total FROM request_tracking WHERE DATE(last_request) = CURDATE()");
$stats['today_requests'] = $stmt->fetch()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?> Admin</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="posts.php">Posts</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="security.php" class="active">Security</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <h1>Security Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <!-- Security Statistics -->
        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['active_bans']); ?></div>
                <div class="stat-label">Active IP Bans</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['today_bans']); ?></div>
                <div class="stat-label">Bans Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['today_requests']); ?></div>
                <div class="stat-label">Requests Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo DDOS_REQUEST_LIMIT; ?></div>
                <div class="stat-label">Request Limit/Min</div>
            </div>
        </div>

        <!-- Manual IP Ban -->
        <div class="card mb-4">
            <h3>Ban IP Address</h3>
            <form method="POST" class="flex gap-2" style="flex-wrap: wrap; align-items: end;">
                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                    <label class="form-label">IP Address</label>
                    <input type="text" name="ip_address" class="form-input" placeholder="192.168.1.1" required>
                </div>
                
                <div class="form-group" style="flex: 2; min-width: 250px; margin-bottom: 0;">
                    <label class="form-label">Reason</label>
                    <input type="text" name="reason" class="form-input" placeholder="Manual ban by admin" value="Manual ban by admin">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        <input type="checkbox" name="is_permanent" style="margin-right: 0.5rem;">
                        Permanent Ban
                    </label>
                </div>
                
                <input type="hidden" name="action" value="ban_ip">
                <button type="submit" class="btn btn-danger">Ban IP</button>
            </form>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <form method="GET" class="flex gap-2" style="flex-wrap: wrap;">
                <input type="text" name="search" class="form-input" placeholder="Search IP addresses..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                
                <select name="filter" class="form-input" style="width: auto;">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Bans</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="expired" <?php echo $filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="permanent" <?php echo $filter === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="security.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Banned IPs Table -->
        <div class="card">
            <h3>Banned IP Addresses</h3>
            <?php if (empty($banned_ips)): ?>
                <p>No banned IP addresses found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Banned At</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banned_ips as $ban): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($ban['ip_address']); ?></code></td>
                                <td><?php echo htmlspecialchars($ban['ban_reason']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($ban['banned_at'])); ?></td>
                                <td>
                                    <?php if ($ban['is_permanent']): ?>
                                        <span style="color: var(--error-color); font-weight: bold;">Permanent</span>
                                    <?php else: ?>
                                        <?php echo date('M j, Y H:i', strtotime($ban['ban_expires'])); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ban['is_permanent'] || strtotime($ban['ban_expires']) > time()): ?>
                                        <span style="color: var(--error-color); font-weight: bold;">Active</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="unban_ip">
                                        <input type="hidden" name="ban_id" value="<?php echo $ban['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" 
                                                onclick="return confirm('Remove this IP ban?')">Unban</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-4">
            <div>
                Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total_bans)); ?> 
                of <?php echo number_format($total_bans); ?> bans
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
