<?php
require_once '../config/config.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    redirectTo('login.php');
}

$security = new Security();
$security->checkDDoS();

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    // Check if changing password
    if (!empty($new_password)) {
        if (empty($current_password)) $errors[] = "Current password is required to change password";
        if (strlen($new_password) < 6) $errors[] = "New password must be at least 6 characters";
        if ($new_password !== $confirm_password) $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        // Check if username/email already exists for other users
        $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists";
        } else {
            // Verify current password if changing password
            if (!empty($new_password)) {
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    $errors[] = "Current password is incorrect";
                }
            }
            
            if (empty($errors)) {
                // Update profile
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET username = ?, email = ?, phone = ?, password = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $phone, $hashed_password, $_SESSION['user_id']]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET username = ?, email = ?, phone = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $phone, $_SESSION['user_id']]);
                }
                
                // Update session
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                
                $success = "Profile updated successfully!";
            }
        }
    }
    
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_image']['type'];
        $file_size = $_FILES['profile_image']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($file_size > MAX_FILE_SIZE) {
            $errors[] = "Image size must be less than 5MB";
        } else {
            $upload_dir = '../' . UPLOAD_PATH . 'profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$new_filename, $_SESSION['user_id']]);
                
                // Update session
                $_SESSION['profile_image'] = $new_filename;
                
                $success = "Profile image updated successfully!";
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
}

// Get current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="../chat.php">Chat</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 600px; margin: 2rem auto;">
            <h2 class="text-center mb-4">My Profile</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="text-center mb-4">
                <img src="../<?php echo UPLOAD_PATH; ?>profiles/<?php echo $user['profile_image']; ?>" 
                     alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;"
                     onerror="this.src='../assets/default-avatar.jpg'">
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Profile Image</label>
                    <input type="file" name="profile_image" class="form-input" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-input" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
                
                <hr style="margin: 2rem 0;">
                <h3>Change Password</h3>
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Profile</button>
            </form>
        </div>
    </div>
</body>
</html>
