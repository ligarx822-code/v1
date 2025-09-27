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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? ''; // Don't sanitize content as it contains HTML
    $keywords = sanitize($_POST['keywords'] ?? '');
    $status = sanitize($_POST['status'] ?? 'draft');
    
    // Validation
    if (empty($title)) $errors[] = "Title is required";
    if (empty($content)) $errors[] = "Content is required";
    if (!in_array($status, ['draft', 'published'])) $status = 'draft';
    
    // Generate slug
    $slug = generateSlug($title);
    
    // Check if slug exists
    if (!empty($slug)) {
        $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->rowCount() > 0) {
            $slug .= '-' . time();
        }
    }
    
    if (empty($errors)) {
        // Handle image upload
        $featured_image = null;
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
                    $featured_image = $new_filename;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }
        
        if (empty($errors)) {
            // Create post
            $stmt = $db->prepare("
                INSERT INTO posts (title, slug, content, keywords, featured_image, author_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$title, $slug, $content, $keywords, $featured_image, $_SESSION['user_id'], $status])) {
                $success = "Post created successfully!";
                if ($status === 'published') {
                    $success .= " <a href='../post.php?slug=" . urlencode($slug) . "' target='_blank'>View Post</a>";
                }
                // Clear form
                $title = $content = $keywords = '';
            } else {
                $errors[] = "Failed to create post. Please try again.";
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
    <title>Create Post - <?php echo SITE_NAME; ?></title>
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
            <h1>Create New Post</h1>
            <a href="posts.php" class="btn btn-secondary">← Back to Posts</a>
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
                                   value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
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
                            <textarea name="content" id="contentEditor" class="form-input form-textarea content-editor" 
                                      placeholder="Write your post content here... You can use HTML tags and the toolbar buttons above." required><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                            <small style="color: var(--text-light);">
                                You can use HTML tags, embed YouTube videos with iframe, add images, code blocks, and more.
                            </small>
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
                                <option value="draft" <?php echo ($status ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo ($status ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Create Post</button>
                    </div>

                    <div class="card mb-4">
                        <h3>Featured Image</h3>
                        <div class="form-group">
                            <input type="file" name="featured_image" class="form-input" accept="image/*">
                            <small style="color: var(--text-light);">Max size: 5MB. JPG, PNG, GIF allowed.</small>
                        </div>
                    </div>

                    <div class="card">
                        <h3>SEO & Keywords</h3>
                        <div class="form-group">
                            <label class="form-label">Keywords</label>
                            <input type="text" name="keywords" class="form-input" 
                                   value="<?php echo htmlspecialchars($keywords ?? ''); ?>"
                                   placeholder="keyword1, keyword2, keyword3">
                            <small style="color: var(--text-light);">Separate keywords with commas. Used for search and related posts.</small>
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

        // Auto-save draft every 30 seconds
        let autoSaveTimer;
        const contentEditor = document.getElementById('contentEditor');
        
        function autoSave() {
            const formData = new FormData();
            formData.append('title', document.querySelector('input[name="title"]').value);
            formData.append('content', contentEditor.value);
            formData.append('keywords', document.querySelector('input[name="keywords"]').value);
            formData.append('status', 'draft');
            formData.append('auto_save', '1');
            
            // Only auto-save if there's content
            if (formData.get('title') || formData.get('content')) {
                console.log('Auto-saving draft...');
                // You could implement auto-save functionality here
            }
        }

        contentEditor.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSave, 30000);
        });
    </script>
</body>
</html>
