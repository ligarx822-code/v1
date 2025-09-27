<?php
require_once '../config/config.php';
require_once '../includes/security.php';

// Check admin access
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    redirectTo('../panel.php');
}

$security = new Security();
$security->checkDDoS();

$database = new Database();
$db = $database->getConnection();

$stats = [];

// Total users with error handling
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_banned = 0");
    $result = $stmt->fetch();
    $stats['total_users'] = $result ? (int)$result['count'] : 0;
} catch (Exception $e) {
    $stats['total_users'] = 0;
}

// Total posts with error handling
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'");
    $result = $stmt->fetch();
    $stats['total_posts'] = $result ? (int)$result['count'] : 0;
} catch (Exception $e) {
    $stats['total_posts'] = 0;
}

// Total comments with error handling
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM comments");
    $result = $stmt->fetch();
    $stats['total_comments'] = $result ? (int)$result['count'] : 0;
} catch (Exception $e) {
    $stats['total_comments'] = 0;
}

// Today's visits with error handling
try {
    $stmt = $db->prepare("SELECT visits FROM site_stats WHERE date = CURDATE()");
    $stmt->execute();
    $today_stats = $stmt->fetch();
    $stats['today_visits'] = $today_stats ? (int)$today_stats['visits'] : 0;
} catch (Exception $e) {
    $stats['today_visits'] = 0;
}

// This week's visits with error handling
try {
    $stmt = $db->query("
        SELECT SUM(visits) as total 
        FROM site_stats 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $result = $stmt->fetch();
    $stats['week_visits'] = $result && $result['total'] ? (int)$result['total'] : 0;
} catch (Exception $e) {
    $stats['week_visits'] = 0;
}

// This month's visits with error handling
try {
    $stmt = $db->query("
        SELECT SUM(visits) as total 
        FROM site_stats 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $result = $stmt->fetch();
    $stats['month_visits'] = $result && $result['total'] ? (int)$result['total'] : 0;
} catch (Exception $e) {
    $stats['month_visits'] = 0;
}

// Total visits since launch with error handling
try {
    $stmt = $db->query("SELECT SUM(visits) as total FROM site_stats");
    $result = $stmt->fetch();
    $stats['total_visits'] = $result && $result['total'] ? (int)$result['total'] : 0;
} catch (Exception $e) {
    $stats['total_visits'] = 0;
}

// Get chart data for last 30 days with error handling
try {
    $stmt = $db->query("
        SELECT date, visits, unique_visitors, page_views 
        FROM site_stats 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY date ASC
    ");
    $chart_data = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    $chart_data = [];
}

// Recent posts with error handling
try {
    $stmt = $db->query("
        SELECT p.*, u.username 
        FROM posts p 
        LEFT JOIN users u ON p.author_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $recent_posts = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    $recent_posts = [];
}

// Recent users with error handling
try {
    $stmt = $db->query("
        SELECT username, email, created_at 
        FROM users 
        WHERE is_banned = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    $recent_users = [];
}

// DDoS banned IPs with error handling
try {
    $stmt = $db->query("
        SELECT ip_address, ban_reason, banned_at, ban_expires, is_permanent 
        FROM ddos_bans 
        WHERE is_permanent = 1 OR ban_expires > NOW()
        ORDER BY banned_at DESC 
        LIMIT 10
    ");
    $banned_ips = $stmt->fetchAll() ?: [];
} catch (Exception $e) {
    $banned_ips = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Enhanced beautiful admin dashboard styling */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .admin-logo {
            font-size: 1.5rem;
            font-weight: 800;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .admin-nav-links {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 1rem;
        }
        
        .admin-nav-links a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .admin-nav-links a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateY(-2px);
        }
        
        .admin-nav-links a.active {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stat-label {
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .chart-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .data-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .data-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: var(--bg-light);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-color);
        }
        
        .data-table tr:hover {
            background: var(--bg-light);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .status-expired {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .admin-nav {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .admin-nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-grid {
                grid-template-columns: 1fr;
            }
            
            .data-card {
                padding: 1.5rem;
            }
            
            .chart-card {
                padding: 1.5rem;
            }
            
            .data-table {
                font-size: 0.9rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-container {
                padding: 0.5rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .data-table {
                font-size: 0.8rem;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <nav class="admin-nav">
            <a href="dashboard.php" class="admin-logo">⚙️ <?php echo SITE_NAME; ?> Admin</a>
            <ul class="admin-nav-links">
                <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                <li><a href="posts.php">📝 Posts</a></li>
                <li><a href="users.php">👥 Users</a></li>
                <li><a href="security.php">🔒 Security</a></li>
                <li><a href="../auth/logout.php">🚪 Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="dashboard-container">
        <h1 class="dashboard-title">📊 Admin Dashboard</h1>
        
         Enhanced Statistics Cards 
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">👥 Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_posts']); ?></div>
                <div class="stat-label">📝 Published Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_comments']); ?></div>
                <div class="stat-label">💬 Total Comments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['today_visits']); ?></div>
                <div class="stat-label">📈 Today's Visits</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['week_visits']); ?></div>
                <div class="stat-label">📅 This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['month_visits']); ?></div>
                <div class="stat-label">🗓️ This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_visits']); ?></div>
                <div class="stat-label">🌍 Total Visits</div>
            </div>
        </div>

         Enhanced Charts 
        <div class="chart-card">
            <h3 class="chart-title">📊 Visitor Statistics (Last 30 Days)</h3>
            <?php if (empty($chart_data)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📈</div>
                    <p>No visitor data available yet. Statistics will appear as users visit your site.</p>
                </div>
            <?php else: ?>
                <canvas id="visitorChart" width="400" height="200"></canvas>
            <?php endif; ?>
        </div>

        <div class="data-grid">
             Recent Posts 
            <div class="data-card">
                <h3>📝 Recent Posts</h3>
                <?php if (empty($recent_posts)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <p>No posts published yet.</p>
                        <a href="create-post.php" class="btn btn-primary" style="margin-top: 1rem;">Create First Post</a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Date</th>
                                    <th>Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_posts as $post): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($post['title'], 0, 30)); ?>...</td>
                                    <td><?php echo htmlspecialchars($post['username'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M j', strtotime($post['created_at'])); ?></td>
                                    <td><?php echo number_format($post['views']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

             Recent Users 
            <div class="data-card">
                <h3>👥 Recent Users</h3>
                <?php if (empty($recent_users)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">👥</div>
                        <p>No users registered yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo date('M j', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

         Banned IPs 
        <?php if (!empty($banned_ips)): ?>
        <div class="data-card" style="margin-top: 2rem;">
            <h3>🔒 Banned IPs (DDoS Protection)</h3>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Reason</th>
                            <th>Banned At</th>
                            <th>Expires</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banned_ips as $ban): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ban['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($ban['ban_reason']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($ban['banned_at'])); ?></td>
                            <td>
                                <?php if ($ban['is_permanent']): ?>
                                    <span class="status-badge status-active">Permanent</span>
                                <?php else: ?>
                                    <?php echo date('M j, Y H:i', strtotime($ban['ban_expires'])); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ban['is_permanent'] || strtotime($ban['ban_expires']) > time()): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-expired">Expired</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($chart_data)): ?>
    <script>
        // Enhanced Visitor Chart
        const ctx = document.getElementById('visitorChart').getContext('2d');
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        const labels = chartData.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const visits = chartData.map(item => parseInt(item.visits) || 0);
        const uniqueVisitors = chartData.map(item => parseInt(item.unique_visitors) || 0);
        const pageViews = chartData.map(item => parseInt(item.page_views) || 0);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Visits',
                        data: visits,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Unique Visitors',
                        data: uniqueVisitors,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Page Views',
                        data: pageViews,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                elements: {
                    point: {
                        radius: 4,
                        hoverRadius: 8
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
