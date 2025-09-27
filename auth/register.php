<?php
require_once '../config/config.php';
require_once '../includes/security.php';
require_once '../includes/email-verification.php';
require_once '../includes/csrf.php';

$security = new Security();
$security->checkDDoS();

$errors = [];
$success = '';
$step = $_GET['step'] ?? 'register';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'register') {
        if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
            $errors[] = "Security token validation failed. Please try again.";
        } else {
            $username = sanitize($_POST['username'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $captcha = sanitize($_POST['captcha'] ?? '');
            
            // Validation
            if (empty($username)) $errors[] = "Username is required";
            if (empty($email)) $errors[] = "Email is required";
            if (empty($password)) $errors[] = "Password is required";
            if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
            if ($password !== $confirm_password) $errors[] = "Passwords do not match";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
            if (!$security->verifyCaptcha($captcha)) $errors[] = "Invalid captcha";
            
            if (empty($errors)) {
                $database = getDatabase();
                $db = $database->getConnection();
                
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Username or email already exists";
                } else {
                    $emailVerification = new EmailVerification($db);
                    $result = $emailVerification->sendVerificationEmail($email, 'registration');
                    
                    if ($result['success']) {
                        // Store registration data in session temporarily
                        $_SESSION['pending_registration'] = [
                            'username' => $username,
                            'email' => $email,
                            'phone' => $phone,
                            'password' => password_hash($password, PASSWORD_DEFAULT)
                        ];
                        
                        header('Location: register.php?step=verify');
                        exit;
                    } else {
                        $errors[] = $result['message'];
                    }
                }
            }
        }
    } elseif ($step === 'verify') {
        if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
            $errors[] = "Security token validation failed. Please try again.";
        } else {
            $verification_code = sanitize($_POST['verification_code'] ?? '');
            
            if (empty($verification_code)) {
                $errors[] = "Verification code is required";
            } else {
                $pending = $_SESSION['pending_registration'] ?? null;
                if (!$pending) {
                    $errors[] = "Registration session expired. Please start over.";
                } else {
                    $database = getDatabase();
                    $db = $database->getConnection();
                    $emailVerification = new EmailVerification($db);
                    
                    $result = $emailVerification->verifyCode($pending['email'], $verification_code, 'registration');
                    
                    if ($result['success']) {
                        // Create user account
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, phone, password, email_verified) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        
                        if ($stmt->execute([$pending['username'], $pending['email'], $pending['phone'], $pending['password']])) {
                            unset($_SESSION['pending_registration']);
                            $success = "Registration successful! Your email has been verified. Please login.";
                            $step = 'complete';
                        } else {
                            $errors[] = "Registration failed. Please try again.";
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
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* Enhanced registration form styling */
        .register-container {
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
        
        .verification-info {
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            border: 1px solid var(--primary-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .verification-code-input {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5rem;
            padding: 1.5rem;
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
        <div class="register-container">
             Added step indicator 
            <div class="step-indicator">
                <div class="step <?php echo $step === 'register' ? 'active' : ($step === 'verify' || $step === 'complete' ? 'completed' : 'inactive'); ?>">1</div>
                <div class="step <?php echo $step === 'verify' ? 'active' : ($step === 'complete' ? 'completed' : 'inactive'); ?>">2</div>
                <div class="step <?php echo $step === 'complete' ? 'active' : 'inactive'; ?>">3</div>
            </div>
            
            <?php if ($step === 'register'): ?>
                <h2 class="text-center mb-4">📝 Create Account</h2>
                
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
                        <label class="form-label">👤 Username *</label>
                        <input type="text" name="username" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📧 Gmail Address *</label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="yourname@gmail.com" required>
                        <small style="color: var(--text-light);">Only @gmail.com addresses are accepted</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">📱 Phone</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 Password *</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🔒 Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required>
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
                        📧 Send Verification Code
                    </button>
                </form>
                
            <?php elseif ($step === 'verify'): ?>
                <h2 class="text-center mb-4">📧 Verify Your Email</h2>
                
                <div class="verification-info">
                    <h3>✅ Verification Code Sent!</h3>
                    <p>We've sent a 6-digit verification code to:</p>
                    <strong><?php echo htmlspecialchars($_SESSION['pending_registration']['email'] ?? ''); ?></strong>
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
                        <label class="form-label">🔢 Enter Verification Code *</label>
                        <input type="text" name="verification_code" class="form-input verification-code-input" 
                               placeholder="000000" maxlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        ✅ Verify & Complete Registration
                    </button>
                </form>
                
                <p class="text-center mt-4">
                    Didn't receive the code? <a href="register.php">Start over</a>
                </p>
                
            <?php elseif ($step === 'complete'): ?>
                <div class="text-center">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🎉</div>
                    <h2 class="mb-4">Registration Complete!</h2>
                    
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
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </div>
    </div>
    
    <script>
        // Auto-format verification code input
        const codeInput = document.querySelector('.verification-code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    </script>
</body>
</html>
