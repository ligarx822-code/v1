<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/csrf.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php?error=admin_required');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

// Get admin user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Get system statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $stmt->fetch()['count'];

// Total posts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM posts");
$stats['posts'] = $stmt->fetch()['count'];

// Total comments
$stmt = $pdo->query("SELECT COUNT(*) as count FROM comments");
$stats['comments'] = $stmt->fetch()['count'];

// Total chat messages
$stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages");
$stats['messages'] = $stmt->fetch()['count'];

// Recent activity
$stmt = $pdo->query("
    SELECT 'user' as type, username as title, created_at, 'New user registered' as description
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'post' as type, title, created_at, 'New post published' as description
    FROM posts 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'comment' as type, CONCAT('Comment on post ID: ', post_id) as title, created_at, 'New comment added' as description
    FROM comments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
$recent_activity = $stmt->fetchAll();

// Security logs
$stmt = $pdo->query("
    SELECT * FROM security_logs 
    ORDER BY created_at DESC 
    LIMIT 5
");
$security_logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Blog System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: calc(100vh - 80px);
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        
        .admin-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .admin-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.2em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .stat-card:hover::before {
            left: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 3em;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 1.1em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .admin-section {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .section-title {
            color: #667eea;
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .activity-item:hover {
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }
        
        .activity-desc {
            color: #64748b;
            font-size: 0.9em;
        }
        
        .activity-time {
            color: #94a3b8;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 30px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }
        
        .security-log {
            background: linear-gradient(145deg, #fef2f2, #fecaca);
            border: 1px solid #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        
        .security-log.info {
            background: linear-gradient(145deg, #eff6ff, #dbeafe);
            border-color: #93c5fd;
        }
        
        .security-log.warning {
            background: linear-gradient(145deg, #fffbeb, #fed7aa);
            border-color: #fdba74;
        }
        
        .log-time {
            color: #64748b;
            font-size: 0.8em;
            float: right;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 12px;
            }
            
            .admin-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 16px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .admin-header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <p>Welcome back, <?php echo htmlspecialchars($admin['username']); ?>! Manage your blog system from here.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo number_format($stats['users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo number_format($stats['posts']); ?></div>
                <div class="stat-label">Total Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?php echo number_format($stats['comments']); ?></div>
                <div class="stat-label">Comments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💭</div>
                <div class="stat-number"><?php echo number_format($stats['messages']); ?></div>
                <div class="stat-label">Chat Messages</div>
            </div>
        </div>
        
        <div class="admin-grid">
            <div class="admin-section">
                <h3 class="section-title">Recent Activity</h3>
                <?php if (empty($recent_activity)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No recent activity</p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                switch($activity['type']) {
                                    case 'user': echo '👤'; break;
                                    case 'post': echo '📝'; break;
                                    case 'comment': echo '💬'; break;
                                    default: echo '📋'; break;
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                <div class="activity-desc"><?php echo htmlspecialchars($activity['description']); ?></div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="admin-section">
                <h3 class="section-title">Security Logs</h3>
                <?php if (empty($security_logs)): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No security events</p>
                <?php else: ?>
                    <?php foreach ($security_logs as $log): ?>
                        <div class="security-log <?php echo strtolower($log['event_type']); ?>">
                            <span class="log-time"><?php echo date('M j, H:i', strtotime($log['created_at'])); ?></span>
                            <strong><?php echo htmlspecialchars($log['event_type']); ?></strong><br>
                            <?php echo htmlspecialchars($log['description']); ?>
                            <br><small>IP: <?php echo htmlspecialchars($log['ip_address']); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-section">
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="posts.php" class="action-btn">
                    <span>📝</span> Manage Posts
                </a>
                <a href="users.php" class="action-btn">
                    <span>👥</span> Manage Users
                </a>
                <a href="create-post.php" class="action-btn">
                    <span>➕</span> Create Post
                </a>
                <a href="security.php" class="action-btn">
                    <span>🛡️</span> Security Settings
                </a>
                <a href="../chat.php" class="action-btn">
                    <span>💭</span> View Chat
                </a>
                <a href="dashboard.php" class="action-btn">
                    <span>📊</span> Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
