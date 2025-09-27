<?php
// Database Auto Starter - Creates all required tables automatically
// Run this file to set up your complete blog database

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$db_name = 'stacknro_blog';
$username = 'stacknro_blog';
$password = 'admin-2025';
$charset = 'utf8mb4';

echo "🚀 Starting Database Auto Setup...\n";
echo "=====================================\n";

// All SQL statements embedded in PHP
$allSqlStatements = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        profile_image VARCHAR(255) DEFAULT 'default-avatar.jpg',
        is_admin TINYINT(1) DEFAULT 0,
        is_banned TINYINT(1) DEFAULT 0,
        email_verified TINYINT(1) DEFAULT 0,
        verification_code VARCHAR(6),
        verification_expires TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_created_at (created_at)
    )",
    
    // Blog posts table
    "CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        content TEXT NOT NULL,
        keywords VARCHAR(500),
        featured_image VARCHAR(255),
        author_id INT,
        status ENUM('draft', 'published') DEFAULT 'draft',
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
        FULLTEXT(title, content, keywords),
        INDEX idx_status (status),
        INDEX idx_author (author_id),
        INDEX idx_created_at (created_at),
        INDEX idx_slug (slug)
    )",
    
    // Post likes table
    "CREATE TABLE IF NOT EXISTS post_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_like (post_id, user_id),
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id)
    )",
    
    // Post views table
    "CREATE TABLE IF NOT EXISTS post_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_post_user (post_id, user_id),
        INDEX idx_post_ip (post_id, ip_address),
        INDEX idx_created_at (created_at)
    )",
    
    // Comments table
    "CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    )",
    
    // Chat messages table
    "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    )",
    
    // Site statistics table
    "CREATE TABLE IF NOT EXISTS site_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE UNIQUE NOT NULL,
        visits INT DEFAULT 0,
        unique_visitors INT DEFAULT 0,
        page_views INT DEFAULT 0,
        INDEX idx_date (date)
    )",
    
    // Contact messages table
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at)
    )",
    
    // DDoS protection table
    "CREATE TABLE IF NOT EXISTS ddos_bans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL UNIQUE,
        ban_reason VARCHAR(255) DEFAULT 'DDoS Attack',
        banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ban_expires TIMESTAMP NULL,
        is_permanent TINYINT(1) DEFAULT 0,
        ban_count INT DEFAULT 1,
        INDEX idx_ip (ip_address),
        INDEX idx_expires (ban_expires)
    )",
    
    // Request tracking table
    "CREATE TABLE IF NOT EXISTS request_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        request_count INT DEFAULT 1,
        last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        request_method VARCHAR(10) DEFAULT 'GET',
        user_agent TEXT,
        request_uri VARCHAR(255),
        INDEX idx_ip_time (ip_address, last_request)
    )",
    
    // Email verification table
    "CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(6) NOT NULL,
        type ENUM('registration', 'password_reset') DEFAULT 'registration',
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_code (email, code),
        INDEX idx_expires (expires_at),
        INDEX idx_type (type)
    )",
    
    // Security logs table
    "CREATE TABLE IF NOT EXISTS security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        description TEXT,
        user_agent TEXT,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, created_at),
        INDEX idx_event_type (event_type),
        INDEX idx_created_at (created_at)
    )",
    
    // Failed login attempts table
    "CREATE TABLE IF NOT EXISTS failed_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(100),
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_agent TEXT,
        INDEX idx_ip_time (ip_address, attempt_time),
        INDEX idx_username (username)
    )",
    
    // Session security table
    "CREATE TABLE IF NOT EXISTS secure_sessions (
        session_id VARCHAR(128) PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent_hash VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_last_activity (last_activity)
    )"
];

// Insert statements
$insertStatements = [
    // Admin user
    "INSERT INTO users (username, email, password, is_admin, email_verified, verification_code) VALUES 
    ('admin', 'admin-sunatullo@gmail.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, NULL)
    ON DUPLICATE KEY UPDATE 
        email = 'admin-sunatullo@gmail.com',
        is_admin = 1,
        email_verified = 1,
        verification_code = NULL",
    
    // Sample posts
    "INSERT INTO posts (title, slug, content, keywords, author_id, status) VALUES 
    ('🚀 Complete Web Development Guide 2025', 'complete-web-development-guide-2025', 
    '<div style=\"text-align: center; margin-bottom: 2rem;\">
        <img src=\"https://images.pexels.com/photos/11035380/pexels-photo-11035380.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1\" alt=\"Web Development\" style=\"width: 100%; max-width: 800px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);\">
    </div>

    <h2 style=\"color: #667eea; margin-bottom: 1.5rem;\">🎯 Introduction</h2>
    <p style=\"font-size: 1.1rem; line-height: 1.8; color: #334155;\">Welcome to the most comprehensive web development guide for 2025! This tutorial will take you from beginner to advanced level, covering all the essential technologies and best practices.</p>

    <h3 style=\"color: #764ba2; margin: 2rem 0 1rem;\">📚 What You will Learn</h3>
    <ul style=\"font-size: 1.05rem; line-height: 1.7; color: #475569;\">
        <li>Modern HTML5 and CSS3 techniques</li>
        <li>JavaScript ES6+ features and frameworks</li>
        <li>Responsive design principles</li>
        <li>Backend development with PHP/Node.js</li>
        <li>Database design and optimization</li>
    </ul>

    <h3 style=\"color: #764ba2; margin: 2rem 0 1rem;\">💻 Code Example</h3>
    <pre style=\"background: linear-gradient(145deg, #1e293b, #334155); color: #e2e8f0; padding: 24px; border-radius: 12px; overflow-x: auto; margin: 2rem 0; box-shadow: 0 8px 25px rgba(0,0,0,0.2);\"><code>// Modern JavaScript Example
const fetchUserData = async (userId) => {
    try {
        const response = await fetch(`/api/users/${userId}`);
        const userData = await response.json();
        
        return {
            success: true,
            data: userData
        };
    } catch (error) {
        console.error(\"Error fetching user:\", error);
        return {
            success: false,
            error: error.message
        };
    }
};

// Usage
fetchUserData(123).then(result => {
    if (result.success) {
        console.log(\"User data:\", result.data);
    }
});</code></pre>

    <h3 style=\"color: #764ba2; margin: 2rem 0 1rem;\">🎥 Tutorial Video</h3>
    <div style=\"position: relative; width: 100%; height: 0; padding-bottom: 56.25%; margin: 2rem 0; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.1);\">
        <iframe src=\"https://www.youtube.com/embed/dQw4w9WgXcQ\" 
                style=\"position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;\" 
                allowfullscreen></iframe>
    </div>', 
    'web development, programming, javascript, html, css, tutorial, 2025, guide, coding, frontend, backend', 1, 'published')
    ON DUPLICATE KEY UPDATE title=title",
    
    // Sample chat messages
    "INSERT INTO chat_messages (user_id, message) VALUES 
    (1, 'Welcome to our amazing chat system! 👋'),
    (1, 'Feel free to start conversations here and connect with other users.'),
    (1, 'This chat supports real-time messaging with infinite scroll - try it out!')
    ON DUPLICATE KEY UPDATE message=message"
];

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "✅ Connected to MySQL server\n";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '$db_name' created/verified\n";
    
    // Connect to the specific database
    $pdo->exec("USE `$db_name`");
    echo "✅ Using database '$db_name'\n";
    
    echo "📊 Found " . count($allSqlStatements) . " table creation statements\n";
    echo "=====================================\n";
    
    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;
    
    // Execute table creation statements
    foreach ($allSqlStatements as $statement) {
        try {
            $pdo->exec($statement);
            $successCount++;
            
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
            } else {
                echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
            }
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                $skipCount++;
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "ℹ️  Table already exists: {$matches[1]}\n";
                }
            } else {
                $errorCount++;
                echo "⚠️  Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "=====================================\n";
    echo "📊 Executing insert statements...\n";
    
    // Execute insert statements
    foreach ($insertStatements as $statement) {
        try {
            $pdo->exec($statement);
            $successCount++;
            
            if (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✅ Inserted data into: {$matches[1]}\n";
            }
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $skipCount++;
                echo "ℹ️  Skipped duplicate entry\n";
            } else {
                $errorCount++;
                echo "⚠️  Insert error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "=====================================\n";
    echo "🎉 Database setup completed!\n";
    echo "✅ Successful operations: $successCount\n";
    echo "ℹ️  Skipped (already exists): $skipCount\n";
    echo "⚠️  Errors: $errorCount\n";
    
    // Verify tables were created
    echo "\n🔍 Verifying database structure...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'users', 'posts', 'post_likes', 'post_views', 'comments', 
        'chat_messages', 'site_stats', 'contact_messages', 'ddos_bans', 
        'request_tracking', 'email_verifications', 'security_logs', 
        'failed_logins', 'secure_sessions'
    ];
    
    $missingTables = array_diff($expectedTables, $tables);
    
    if (empty($missingTables)) {
        echo "✅ All required tables created successfully!\n";
    } else {
        echo "❌ Missing tables: " . implode(', ', $missingTables) . "\n";
    }
    
    echo "\n📊 Database Statistics:\n";
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "   📋 $table: $count records\n";
        } catch (Exception $e) {
            echo "   ❌ $table: Error reading\n";
        }
    }
    
    // Test admin user
    echo "\n👤 Checking admin user...\n";
    $adminCheck = $pdo->query("SELECT username, email, is_admin FROM users WHERE is_admin = 1 LIMIT 1")->fetch();
    if ($adminCheck) {
        echo "✅ Admin user found: {$adminCheck['username']} ({$adminCheck['email']})\n";
        echo "🔑 Admin login: admin-blog / admin2025\n";
    } else {
        echo "⚠️  No admin user found\n";
    }
    
    echo "\n🎯 Setup complete! Your blog system is ready to use.\n";
    echo "🌐 You can now access:\n";
    echo "   - Main site: index.php\n";
    echo "   - Admin panel: panel.php\n";
    echo "   - Chat system: chat.php\n";
    
} catch (Exception $e) {
    echo "❌ Critical Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>