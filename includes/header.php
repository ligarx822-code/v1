<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="index.php">
                <h2>Blog System</h2>
            </a>
        </div>
        
        <div class="nav-menu" id="navMenu">
            <a href="index.php" class="nav-link">Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="chat.php" class="nav-link">Chat</a>
                <a href="user-profile.php?id=<?php echo $_SESSION['user_id']; ?>" class="nav-link">Profile</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin/dashboard.php" class="nav-link admin-link">Admin</a>
                <?php endif; ?>
                <a href="auth/logout.php" class="nav-link logout-btn">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="nav-link">Login</a>
                <a href="auth/register.php" class="nav-link">Register</a>
            <?php endif; ?>
            <a href="contact.php" class="nav-link">Contact</a>
        </div>
        
        <div class="nav-toggle" id="navToggle">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</nav>

<script>
// Mobile menu toggle
const navToggle = document.getElementById('navToggle');
const navMenu = document.getElementById('navMenu');

navToggle.addEventListener('click', () => {
    navMenu.classList.toggle('active');
    navToggle.classList.toggle('active');
});

// Close menu when clicking outside
document.addEventListener('click', (e) => {
    if (!navToggle.contains(e.target) && !navMenu.contains(e.target)) {
        navMenu.classList.remove('active');
        navToggle.classList.remove('active');
    }
});
</script>

<style>
.navbar {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    padding: 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    height: 70px;
}

.nav-brand a {
    color: white;
    text-decoration: none;
}

.nav-brand h2 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.nav-menu {
    display: flex;
    gap: 30px;
    align-items: center;
}

.nav-link {
    color: white;
    text-decoration: none;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-2px);
}

.admin-link {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
}

.logout-btn {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.logout-btn:hover {
    background: rgba(255,255,255,0.2);
}

.nav-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 4px;
}

.nav-toggle span {
    width: 25px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.nav-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

.nav-toggle.active span:nth-child(2) {
    opacity: 0;
}

.nav-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

@media (max-width: 768px) {
    .nav-menu {
        position: fixed;
        top: 70px;
        left: -100%;
        width: 100%;
        height: calc(100vh - 70px);
        background: var(--primary);
        flex-direction: column;
        justify-content: flex-start;
        padding-top: 50px;
        transition: left 0.3s ease;
    }
    
    .nav-menu.active {
        left: 0;
    }
    
    .nav-toggle {
        display: flex;
    }
    
    .nav-link {
        font-size: 1.2rem;
        padding: 15px 30px;
        width: 80%;
        text-align: center;
    }
}
</style>
