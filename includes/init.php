<?php
require_once 'config/config.php';
require_once 'includes/security.php';

// Initialize security on every page load
initSecurity();

// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\';');

// Clean old security logs (keep 30 days)
if (random_int(1, 100) === 1) { // 1% chance to run cleanup
    try {
        $pdo->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $pdo->exec("DELETE FROM failed_logins WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    } catch (Exception $e) {
        // Silent cleanup failure
    }
}
?>
