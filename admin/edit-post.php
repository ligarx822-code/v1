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

$post_id = (int)($_GET['id'] ?? 0);
$errors = [];
$success = '';

// Get post
$stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    redirectTo('posts.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? ''; // Don't sanitize content as it contains HTML
    $keywords = sanitize($_POST['keywords'] ?? '');
    $status = sanitize($_POST['status'] ?? 'draft');
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($content)) $errors[] = "Content is required";
    if (!in_array($status, ['draft', 'published'])) $status = 'draft';
    
    // Generate new slug if title changed
    $slug = $post['slug'];
    if ($title !== $post['title']) {
        $new_slug = generateSlug($title);
        
        // Check if new slug exists
        $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
        $stmt->execute([$new_slug, $post_id]);
        if ($stmt->rowCount() > 0) {
            $new_slug .= '-' . time();
        }
        $slug = $new_slug;
    }
    
    if (empty($errors)) {
        // Handle image upload
        $featured_image = $post['featured_image'];
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['featured_image']['type'];
            $file_size = $_FILES['featured_image']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Only JPG, PNG, and GIF images are allowed";
            } elseif ($file_size > MAX_FILE_SIZE) {
                $errors[] = "Image size must be less than 5MB";
            } else {
                $upload_dir = '../' . UPLOAD_PATH . 'posts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $new_filename = 'post_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
                    // Delete old image
                    if ($featured_image && file_exists($upload_dir . $featured_image)) {
                        unlink($upload_dir . $featured_image);
                    }
                    $featured_image = $new_filename;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }
        
        if (empty($errors)) {
            // Update post
            $stmt = $db->prepare("
                UPDATE posts 
                SET title = ?, slug = ?, content = ?, keywords = ?, featured_image = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$title, $slug, $content, $keywords, $featured_image, $status, $post_id])) {
                $success = "Post updated successfully!";
                if ($status === 'published') {
                    $success .= " <a href='../post.php?slug=" . urlencode($slug) . "' target='_blank'>View Post</a>";
                }
                
                // Update post data for form
                $post['title'] = $title;
                $post['content'] = $content;
                $post['keywords'] = $keywords;
                $post['status'] = $status;
                $post['slug'] = $slug;
                $post['featured_image'] = $featured_image;
            } else {
                $errors[] = "Failed to update post. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .editor-toolbar {
            background: var(--bg-light);
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .editor-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid var(--border-color);
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .editor-btn:hover {
            background: var(--bg-light);
        }
        .content-editor {
            border-radius: 0 0 6px 6px !important;
            border-top: none !important;
            min-height: 400px;
        }
    </style>
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo"><?php echo SITE_NAME; ?> Admin</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="posts.php">Posts</a></li>
                <li><a href="users.php">Users</a></li>
                <li><a href="security.php">Security</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="flex justify-between items-center mb-4">
            <h1>Edit Post</h1>
            <div class="flex gap-2">
                <?php if ($post['status'] === 'published'): ?>
                    <a href="../post.php?slug=<?php echo urlencode($post['slug']); ?>" target="_blank" class="btn btn-secondary">View Post</a>
                <?php endif; ?>
                <a href="posts.php" class="btn btn-secondary">← Back to Posts</a>
            </div>
        </div>

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

        <form method="POST" action="" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Main Content -->
                <div>
                    <div class="card">
                        <div class="form-group">
                            <label class="form-label">Post Title *</label>
                            <input type="text" name="title" class="form-input" 
                                   value="<?php echo htmlspecialchars($post['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Content *</label>
                            <div class="editor-toolbar">
                                <button type="button" class="editor-btn" onclick="insertText('**', '**')"><strong>B</strong></button>
                                <button type="button" class="editor-btn" onclick="insertText('*', '*')"><em>I</em></button>
                                <button type="button" class="editor-btn" onclick="insertText('# ', '')">H1</button>
                                <button type="button" class="editor-btn" onclick="insertText('## ', '')">H2</button>
                                <button type="button" class="editor-btn" onclick="insertText('### ', '')">H3</button>
                                <button type="button" class="editor-btn" onclick="insertText('[', '](url)')">Link</button>
                                <button type="button" class="editor-btn" onclick="insertText('![alt](', ')')">Image</button>
                                <button type="button" class="editor-btn" onclick="insertText('```\n', '\n```')">Code</button>
                                <button type="button" class="editor-btn" onclick="insertText('> ', '')">Quote</button>
                                <button type="button" class="editor-btn" onclick="insertText('<iframe src=\"', '\" width=\"560\" height=\"315\" frameborder=\"0\"></iframe>')">Video</button>
                            </div>
                            <textarea name="content" id="contentEditor" class="form-input form-textarea content-editor" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <div class="card mb-4">
                        <h3>Publish</h3>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input">
                                <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Update Post</button>
                    </div>

                    <div class="card mb-4">
                        <h3>Featured Image</h3>
                        <?php if ($post['featured_image']): ?>
                            <div class="mb-2">
                                <img src="../<?php echo UPLOAD_PATH; ?>posts/<?php echo $post['featured_image']; ?>" 
                                     alt="Current featured image" style="width: 100%; border-radius: 6px;">
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <input type="file" name="featured_image" class="form-input" accept="image/*">
                            <small style="color: var(--text-light);">Max size: 5MB. JPG, PNG, GIF allowed. Leave empty to keep current image.</small>
                        </div>
                    </div>

                    <div class="card">
                        <h3>SEO & Keywords</h3>
                        <div class="form-group">
                            <label class="form-label">Keywords</label>
                            <input type="text" name="keywords" class="form-input" 
                                   value="<?php echo htmlspecialchars($post['keywords']); ?>"
                                   placeholder="keyword1, keyword2, keyword3">
                            <small style="color: var(--text-light);">Separate keywords with commas.</small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function insertText(startTag, endTag) {
            const textarea = document.getElementById('contentEditor');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            const replacement = startTag + selectedText + endTag;
            
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + startTag.length, start + startTag.length + selectedText.length);
        }
    </script>
</body>
</html>
