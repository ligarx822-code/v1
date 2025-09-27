<?php
session_start();
require_once 'config/config.php';
require_once 'includes/security.php';
require_once 'includes/csrf.php';

try {
    $security = new Security();
    $security->checkDDoS();
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    $is_logged_in = isset($_SESSION['user_id']);
    
    // Get database connection
    $database = getDatabase();
    if (!$database) {
        throw new Exception("Database connection failed");
    }
    $db = $database->getConnection();
    
    // Get user info if logged in
    $user_avatar = 'assets/default-avatar.jpg';
    if ($is_logged_in) {
        $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $user_avatar = $user['profile_image'] ?: 'assets/default-avatar.jpg';
    }
    
    // Handle message sending (only for logged users)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $is_logged_in) {
        if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
            throw new Exception('CSRF token validation failed');
        }
        
        $message = trim($_POST['message']);
        if (!empty($message) && strlen($message) <= 1000) {
            $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            
            $stmt = $db->prepare("INSERT INTO chat_messages (user_id, message, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $message]);
            
            // Regenerate CSRF token after use
            CSRFToken::generate();
            
            // Redirect to prevent resubmission
            header('Location: chat.php');
            exit;
        }
    }
    
    // Handle AJAX requests for loading messages
    if (isset($_GET['load_messages'])) {
        $offset = (int)($_GET['offset'] ?? 0);
        $limit = 100;
        
        $stmt = $db->prepare("
            SELECT cm.*, u.username, u.profile_image, 
                   DATE_FORMAT(cm.created_at, '%H:%i') as time,
                   DATE_FORMAT(cm.created_at, '%Y-%m-%d') as date
            FROM chat_messages cm 
            JOIN users u ON cm.user_id = u.id 
            ORDER BY cm.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $messages = array_reverse($stmt->fetchAll());
        
        header('Content-Type: application/json');
        echo json_encode($messages);
        exit;
    }
    
    // Get initial messages (latest 100)
    $stmt = $db->prepare("
        SELECT cm.*, u.username, u.profile_image, 
               DATE_FORMAT(cm.created_at, '%H:%i') as time,
               DATE_FORMAT(cm.created_at, '%Y-%m-%d') as date
        FROM chat_messages cm 
        JOIN users u ON cm.user_id = u.id 
        ORDER BY cm.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $messages = array_reverse($stmt->fetchAll());
    
    // Get total message count for infinite scroll
    $stmt = $db->query("SELECT COUNT(*) as total FROM chat_messages");
    $total_messages = $stmt->fetch()['total'];
    
    // Get online users (active in last 5 minutes)
    $stmt = $db->prepare("
        SELECT DISTINCT u.username, u.profile_image 
        FROM users u 
        JOIN chat_messages cm ON u.id = cm.user_id 
        WHERE cm.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.username
    ");
    $stmt->execute();
    $online_users = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Chat error: " . $e->getMessage());
    $messages = [];
    $online_users = [];
    $total_messages = 0;
    $error_message = "Chat system is temporarily unavailable. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 Chat - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">🏠 Home</a></li>
                <?php if ($is_logged_in): ?>
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
    
    <div class="chat-container">
        <div class="chat-main">
            <div class="chat-header">
                <h2>💬 Live Chat</h2>
                <p><?php echo $is_logged_in ? "Welcome, " . htmlspecialchars($username) . "!" : "Welcome to our chat room!"; ?></p>
            </div>
            
            <?php if (!$is_logged_in): ?>
                <div class="alert alert-info" style="margin: 1rem;">
                    <p>You are viewing the chat as a guest. <a href="auth/login.php">Login</a> or <a href="auth/register.php">Register</a> to participate in the conversation!</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="margin: 1rem;">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="chat-messages" id="chatMessages">
                <div id="loadMoreBtn" class="loading-messages" style="display: none;">
                    <button onclick="loadMoreMessages()" class="btn btn-secondary">Load More Messages</button>
                </div>
                
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; color: #64748b; padding: 40px;">
                        <div style="font-size: 3em; margin-bottom: 16px;">💬</div>
                        <h3>No messages yet</h3>
                        <p>Be the first to start the conversation!</p>
                    </div>
                <?php else: ?>
                    <div id="messagesContainer">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($is_logged_in && $msg['user_id'] == $user_id) ? 'own' : ''; ?>">
                                <img src="<?php echo htmlspecialchars($msg['profile_image'] ?: 'assets/default-avatar.jpg'); ?>" 
                                     alt="Avatar" class="message-avatar">
                                <div class="message-content">
                                    <div class="message-header">
                                        <span class="message-username"><?php echo htmlspecialchars($msg['username']); ?></span>
                                        <span class="message-time"><?php echo $msg['time']; ?></span>
                                    </div>
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="chat-input">
                <?php if ($is_logged_in): ?>
                    <form method="POST" class="input-group">
                        <?php echo CSRFToken::field(); ?>
                        <textarea name="message" class="message-input" placeholder="Type your message..." 
                                  maxlength="1000" required></textarea>
                        <button type="submit" name="send_message" class="send-btn">📤 Send</button>
                    </form>
                <?php else: ?>
                    <div class="input-group">
                        <textarea class="message-input" placeholder="Please login to send messages..." disabled></textarea>
                        <button class="send-btn" disabled>🔑 Login Required</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="online-users">
            <h3>
                <span class="online-indicator"></span>
                Online Users
            </h3>
            <?php if (empty($online_users)): ?>
                <p style="color: #64748b; text-align: center; padding: 20px;">No active users</p>
            <?php else: ?>
                <?php foreach ($online_users as $user): ?>
                    <div class="user-item">
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?: 'assets/default-avatar.jpg'); ?>" 
                             alt="Avatar" class="user-avatar">
                        <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        let currentOffset = <?php echo count($messages); ?>;
        let totalMessages = <?php echo $total_messages; ?>;
        let isLoading = false;
        
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
        
        // Auto-scroll to bottom
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Show load more button if there are more messages
        if (currentOffset < totalMessages) {
            document.getElementById('loadMoreBtn').style.display = 'block';
        }
        
        // Load more messages function
        function loadMoreMessages() {
            if (isLoading) return;
            
            isLoading = true;
            const loadBtn = document.querySelector('#loadMoreBtn button');
            loadBtn.innerHTML = 'Loading...';
            loadBtn.disabled = true;
            
            fetch(`chat.php?load_messages=1&offset=${currentOffset}`)
                .then(response => response.json())
                .then(messages => {
                    if (messages.length > 0) {
                        const messagesContainer = document.getElementById('messagesContainer');
                        const scrollHeight = chatMessages.scrollHeight;
                        
                        // Add new messages at the top
                        messages.forEach(msg => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = `message ${msg.user_id == <?php echo $user_id ?? 0; ?> ? 'own' : ''}`;
                            messageDiv.innerHTML = `
                                <img src="${msg.profile_image || 'assets/default-avatar.jpg'}" alt="Avatar" class="message-avatar">
                                <div class="message-content">
                                    <div class="message-header">
                                        <span class="message-username">${msg.username}</span>
                                        <span class="message-time">${msg.time}</span>
                                    </div>
                                    <div class="message-text">${msg.message.replace(/\n/g, '<br>')}</div>
                                </div>
                            `;
                            messagesContainer.insertBefore(messageDiv, messagesContainer.firstChild);
                        });
                        
                        currentOffset += messages.length;
                        
                        // Maintain scroll position
                        chatMessages.scrollTop = chatMessages.scrollHeight - scrollHeight;
                        
                        // Hide load more button if no more messages
                        if (currentOffset >= totalMessages) {
                            document.getElementById('loadMoreBtn').style.display = 'none';
                        }
                    } else {
                        document.getElementById('loadMoreBtn').style.display = 'none';
                    }
                    
                    isLoading = false;
                    loadBtn.innerHTML = 'Load More Messages';
                    loadBtn.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    isLoading = false;
                    loadBtn.innerHTML = 'Load More Messages';
                    loadBtn.disabled = false;
                });
        }
        
        // Auto-resize textarea
        const textarea = document.querySelector('.message-input:not([disabled])');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Send message with Enter key
            textarea.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });
        }
        
        // Auto-refresh messages every 30 seconds
        setInterval(function() {
            if (!isLoading) {
                location.reload();
            }
        }, 30000);
        
        console.log('[v0] Chat page loaded successfully');
    </script>
</body>
</html>