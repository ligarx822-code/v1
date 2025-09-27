<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="auth/login.php">Login</a></li>
                <li><a href="auth/register.php">Register</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <div class="card text-center" style="margin: 4rem auto; max-width: 600px;">
            <h1 style="font-size: 4rem; color: var(--primary-color); margin-bottom: 1rem;">404</h1>
            <h2 style="margin-bottom: 1rem;">Page Not Found</h2>
            <p style="margin-bottom: 2rem; color: var(--text-light);">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <a href="index.php" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</body>
</html>
