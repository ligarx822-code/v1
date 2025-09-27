<?php
session_start();
require_once 'config/config.php';
require_once 'includes/security.php';

$security = new Security();
$security->checkDDoS();

$error = '';
$success = '';

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Static admin credentials
    if ($username === 'admin-blog' && $password === 'admin2025') {
        // Create admin session
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['user_id'] = 1; // Set admin user ID
        $_SESSION['username'] = 'admin';
        $_SESSION['is_admin'] = true;
        
        // Log admin login
        logError("Admin panel access by: $username", 'INFO');
        
        header('Location: admin/dashboard.php');
        exit;
    } else {
        $error = 'Invalid admin credentials.';
        logError("Failed admin login attempt: $username", 'WARNING');
    }
}

// Check if already logged in as admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: admin/dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Beautiful admin panel styling */
        body {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }
        
        .admin-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 2;
            animation: slideInUp 0.8s ease;
        }
        
        .admin-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .admin-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            font-weight: bold;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .admin-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        
        .admin-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        .admin-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1.25rem 1.5rem;
            border: 2px solid var(--border);
            border-radius: 16px;
            font-size: 1rem;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
            outline: none;
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
        }
        
        .form-input.with-icon {
            padding-left: 3rem;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            padding: 1.25rem 2rem;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-admin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-admin:hover::before {
            left: 100%;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.4);
        }
        
        .btn-admin:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: slideInDown 0.5s ease;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .admin-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
        }
        
        .admin-footer a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .admin-footer a:hover {
            color: #6366f1;
        }
        
        .security-info {
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: center;
        }
        
        /* Animations */
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
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .admin-container {
                padding: 2rem;
                margin: 1rem;
                border-radius: 20px;
            }
            
            .admin-title {
                font-size: 1.75rem;
            }
            
            .admin-logo {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }
            
            .admin-container {
                padding: 1.5rem;
                border-radius: 16px;
            }
            
            .admin-title {
                font-size: 1.5rem;
            }
            
            .form-input {
                padding: 1rem 1.25rem;
            }
            
            .btn-admin {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="admin-logo">⚙️</div>
            <h1 class="admin-title">Admin Panel</h1>
            <p class="admin-subtitle">Secure access to <?php echo SITE_NAME; ?> administration</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                🚨 <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="username" class="form-label">👤 Admin Username</label>
                <div style="position: relative;">
                    <span class="input-icon">👤</span>
                    <input type="text" id="username" name="username" class="form-input with-icon" 
                           placeholder="Enter admin username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">🔒 Admin Password</label>
                <div style="position: relative;">
                    <span class="input-icon">🔑</span>
                    <input type="password" id="password" name="password" class="form-input with-icon" 
                           placeholder="Enter admin password" required>
                </div>
            </div>
            
            <button type="submit" class="btn-admin">
                🚀 Access Admin Panel
            </button>
        </form>
        
        <div class="security-info">
            🔐 This is a secure admin area. All login attempts are monitored and logged.
            <br><small>Username: <strong>admin-blog</strong> | Password: <strong>admin2025</strong></small>
        </div>
        
        <div class="admin-footer">
            <a href="index.php">← Back to Website</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input
            const firstInput = document.querySelector('.form-input');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Form submission animation
            const form = document.querySelector('.admin-form');
            const submitBtn = document.querySelector('.btn-admin');
            
            form.addEventListener('submit', function() {
                submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Authenticating...';
                submitBtn.disabled = true;
            });
            
            // Add floating animation to inputs on focus
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Add spin animation for loading
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>