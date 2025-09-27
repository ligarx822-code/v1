<?php
require_once '../config/config.php';
require_once '../includes/security.php';
require_once '../includes/email-verification.php';
require_once '../includes/csrf.php';

$security = new Security();
$security->checkDDoS();

$errors = [];
$success = '';
$step = $_GET['step'] ?? 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
            $errors[] = "Security token validation failed. Please try again.";
        } else {
            $email = sanitize($_POST['email'] ?? '');
            $captcha = sanitize($_POST['captcha'] ?? '');
            
            if (empty($email)) {
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            } elseif (!$security->verifyCaptcha($captcha)) {
                $errors[] = "Invalid captcha";
            } else {
                $database = getDatabase();
                $db = $database->getConnection();
                
                // Check if email exists
                $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $emailVerification = new EmailVerification($db);
                    $result = $emailVerification->sendVerificationEmail($email, 'password_reset');
                    
                    if ($result['success']) {
                        $_SESSION['reset_email'] = $email;
                        header('Location: forgot-password.php?step=verify');
                        exit;
                    } else {
                        $errors[] = $result['message'];
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $_SESSION['reset_email'] = $email;
                    header('Location: forgot-password.php?step=verify');
                    exit;
                }
            }
        }
    } elseif ($step === 'verify') {
        if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
            $errors[] = "Security token validation failed. Please try again.";
        } else {
            $verification_code = sanitize($_POST['verification_code'] ?? '');
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($verification_code)) {
                $errors[] = "Verification code is required";
            } elseif (empty($new_password)) {
                $errors[] = "New password is required";
            } elseif (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            } else {
                $email = $_SESSION['reset_email'] ?? '';
                if (!$email) {
                    $errors[] = "Reset session expired. Please start over.";
                } else {
                    $database = getDatabase();
                    $db = $database->getConnection();
                    $emailVerification = new EmailVerification($db);
                    
                    $result = $emailVerification->verifyCode($email, $verification_code, 'password_reset');
                    
                    if ($result['success']) {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
                        
                        if ($stmt->execute([$hashed_password, $email])) {
                            unset($_SESSION['reset_email']);
                            $success = "Password reset successful! You can now login with your new password.";
                            $step = 'complete';
                        } else {
                            $errors[] = "Failed to update password. Please try again.";
                        }
                    } else {
                        $errors[] = $result['message'];
                    }
                }
            }
        }
    }
}

$captcha_image = $security->generateCaptcha();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .reset-container {
            max-width: 500px;
            margin: 2rem auto;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: var(--gradient-primary);
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed {
            background: var(--success-color);
            color: white;
        }
        
        .step.inactive {
            background: var(--border-color);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="../index.php">🏠 Home</a></li>
                <li><a href="login.php">🔑 Login</a></li>
                <li><a href="register.php">📝 Register</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="reset-container">
            <div class="step-indicator">
                <div class="step <?php echo $step === 'request' ? 'active' : ($step === 'verify' || $step === 'complete' ? 'completed' : 'inactive'); ?>">1</div>
                <div class="step <?php echo $step === 'verify' ? 'active' : ($step === 'complete' ? 'completed' : 'inactive'); ?>">2</div>
                <div class="step <?php echo $step === 'complete' ? 'active' : 'inactive'; ?>">3</div>
            </div>
            
            <?php if ($step === 'request'): ?>
                <h2 class="text-center mb-4">🔑 Reset Password</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo CSRFToken::field(); ?>
                    <div class="form-group">
                        <label class="form-label">📧 Email Address *</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="Enter your email address" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 Security Verification *</label>
                        <img src="<?php echo $captcha_image; ?>" alt="Captcha" 
                             style="margin-bottom: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;"
                             onclick="this.src='../captcha.php?'+Math.random()">
                        <input type="text" name="captcha" class="form-input" placeholder="Enter captcha" required>
                        <small style="color: var(--text-light);">Click image to refresh</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        📧 Send Reset Code
                    </button>
                </form>
                
            <?php elseif ($step === 'verify'): ?>
                <h2 class="text-center mb-4">🔢 Enter Reset Code</h2>
                
                <div class="verification-info">
                    <h3>✅ Reset Code Sent!</h3>
                    <p>We've sent a 6-digit reset code to:</p>
                    <strong><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></strong>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">The code will expire in 5 minutes.</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo CSRFToken::field(); ?>
                    <div class="form-group">
                        <label class="form-label">🔢 Verification Code *</label>
                        <input type="text" name="verification_code" class="form-input verification-code-input" 
                               placeholder="000000" maxlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 New Password *</label>
                        <input type="password" name="new_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        ✅ Reset Password
                    </button>
                </form>
                
            <?php elseif ($step === 'complete'): ?>
                <div class="text-center">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🎉</div>
                    <h2 class="mb-4">Password Reset Complete!</h2>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <p><?php echo $success; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="login.php" class="btn btn-primary" style="font-size: 1.1rem;">
                        🔑 Login Now
                    </a>
                </div>
            <?php endif; ?>
            
            <p class="text-center mt-4">
                Remember your password? <a href="login.php">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
