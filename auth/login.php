<?php
try {
    require_once '../config/config.php';
    logError("Login page accessed", 'INFO');
    
    if (!safe_require('../includes/security.php')) {
        throw new Exception("Security module could not be loaded");
    }
    
    $security = new Security();
    $security->checkDDoS();
    
    $errors = [];
    $login = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logError("Login form submitted", 'INFO');
        
        try {
            $login = sanitize($_POST['login'] ?? '');
            $password = $_POST['password'] ?? '';
            $captcha = sanitize($_POST['captcha'] ?? '');
            
            logError("Login attempt for: $login", 'INFO');
            
            // Validation
            if (empty($login)) {
                $errors[] = "Username or email is required";
                logError("Login validation failed: empty login", 'WARNING');
            }
            if (empty($password)) {
                $errors[] = "Password is required";
                logError("Login validation failed: empty password", 'WARNING');
            }
            if (!$security->verifyCaptcha($captcha)) {
                $errors[] = "Invalid captcha";
                logError("Login validation failed: invalid captcha", 'WARNING');
            }
            
            if (empty($errors)) {
                $database = getDatabase();
                if (!$database) {
                    throw new Exception("Database connection failed");
                }
                
                $db = $database->getConnection();
                if (!$db) {
                    throw new Exception("Could not establish database connection");
                }
                
                // Find user by username or email
                $stmt = safeQuery($database, "
                    SELECT id, username, email, password, is_admin, is_banned, profile_image 
                    FROM users 
                    WHERE (username = ? OR email = ?) AND is_banned = 0
                ", [$login, $login]);
                
                if ($stmt && $stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    logError("User found: " . $user['username'], 'INFO');
                    
                    if (password_verify($password, $user['password'])) {
                        logError("Password verification successful for user: " . $user['username'], 'INFO');
                        
                        // Update last login
                        $update_stmt = safeQuery($database, "UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                        if (!$update_stmt) {
                            logError("Failed to update last login for user: " . $user['username'], 'WARNING');
                        }
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        $_SESSION['profile_image'] = $user['profile_image'];
                        
                        logError("User logged in successfully: " . $user['username'], 'INFO');
                        
                        // Redirect to dashboard or home
                        if ($user['is_admin']) {
                            redirectTo('../admin/dashboard.php');
                        } else {
                            redirectTo('../index.php');
                        }
                    } else {
                        $errors[] = "Invalid credentials";
                        logError("Password verification failed for user: $login", 'WARNING');
                    }
                } else {
                    $errors[] = "Invalid credentials or account is banned";
                    logError("User not found or banned: $login", 'WARNING');
                }
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred during login. Please try again.";
            logError("Login process error: " . $e->getMessage(), 'ERROR');
        }
    }
    
    // Generate captcha
    try {
        $captcha_image = $security->generateCaptcha();
    } catch (Exception $e) {
        logError("Captcha generation failed: " . $e->getMessage(), 'ERROR');
        $captcha_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }
    
} catch (Exception $e) {
    logError("Critical login page error: " . $e->getMessage(), 'CRITICAL');
    $errors[] = "System error. Please try again later.";
    $captcha_image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    if (!isset($login)) {
        $login = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 400px; margin: 2rem auto;">
            <h2 class="text-center mb-4">Login</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username or Email *</label>
                    <input type="text" name="login" class="form-input" 
                           value="<?php echo htmlspecialchars($login ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Captcha *</label>
                    <img src="<?php echo htmlspecialchars($captcha_image ?? ''); ?>" alt="Captcha" style="margin-bottom: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                    <input type="text" name="captcha" class="form-input" placeholder="Enter captcha" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p class="text-center mt-4">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </div>
    </div>
    
    <script>
        window.addEventListener('error', function(e) {
            console.log('[v0] JavaScript error:', e.error);
        });
        
        // Log page load
        console.log('[v0] Login page loaded successfully');
    </script>
</body>
</html>
