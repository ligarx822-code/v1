<?php
// Enhanced Security functions and DDoS protection
require_once 'csrf.php'; // Include CSRF token class

class Security {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function checkDDoS() {
        $ip = $this->getClientIP();
        
        // Check if IP is already banned
        $stmt = $this->db->prepare("
            SELECT * FROM ddos_bans 
            WHERE ip_address = ? AND 
            (is_permanent = 1 OR ban_expires > NOW())
        ");
        $stmt->execute([$ip]);
        
        if ($stmt->rowCount() > 0) {
            $ban = $stmt->fetch();
            $this->logSecurityEvent($ip, 'BLOCKED_ACCESS', 'Attempted access while banned: ' . $ban['ban_reason']);
            $this->blockAccess("Your IP has been banned due to suspicious activity.");
        }
        
        // Track current request with enhanced logging
        $this->trackRequest($ip);
        
        // Check request frequency with sliding window
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as request_count 
            FROM request_tracking 
            WHERE ip_address = ? AND last_request > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$ip]);
        
        $row = $stmt->fetch();
        if ($row['request_count'] > DDOS_REQUEST_LIMIT) {
            $this->banIP($ip, 'DDoS Attack - ' . $row['request_count'] . ' requests in 1 minute');
            $this->logSecurityEvent($ip, 'DDOS_DETECTED', 'Banned for ' . $row['request_count'] . ' requests');
            $this->blockAccess("Too many requests. You have been temporarily banned.");
        }
        
        // Check for suspicious patterns
        $this->checkSuspiciousActivity($ip);
    }
    
    private function checkSuspiciousActivity($ip) {
        // Check for rapid POST requests
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as post_count 
            FROM request_tracking 
            WHERE ip_address = ? AND request_method = 'POST' 
            AND last_request > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        $stmt->execute([$ip]);
        
        $row = $stmt->fetch();
        if ($row['post_count'] > 10) {
            $this->logSecurityEvent($ip, 'SUSPICIOUS_POST', $row['post_count'] . ' POST requests in 30 seconds');
            $this->banIP($ip, 'Suspicious POST activity - ' . $row['post_count'] . ' requests');
            $this->blockAccess("Suspicious activity detected. Access denied.");
        }
    }
    
    private function trackRequest($ip) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $stmt = $this->db->prepare("
            INSERT INTO request_tracking (ip_address, request_count, last_request, request_method, user_agent, request_uri) 
            VALUES (?, 1, NOW(), ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            request_count = request_count + 1, 
            last_request = NOW(),
            request_method = VALUES(request_method),
            user_agent = VALUES(user_agent),
            request_uri = VALUES(request_uri)
        ");
        $stmt->execute([$ip, $method, substr($userAgent, 0, 255), substr($uri, 0, 255)]);
        
        // Clean old tracking data
        $this->db->exec("DELETE FROM request_tracking WHERE last_request < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    }
    
    private function banIP($ip, $reason) {
        $banExpires = date('Y-m-d H:i:s', time() + DDOS_BAN_DURATION);
        $stmt = $this->db->prepare("
            INSERT INTO ddos_bans (ip_address, ban_reason, ban_expires, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            ban_reason = VALUES(ban_reason),
            ban_expires = VALUES(ban_expires),
            ban_count = ban_count + 1
        ");
        $stmt->execute([$ip, $reason, $banExpires]);
        
        $this->logSecurityEvent($ip, 'IP_BANNED', $reason);
    }
    
    private function logSecurityEvent($ip, $event_type, $description) {
        $stmt = $this->db->prepare("
            INSERT INTO security_logs (ip_address, event_type, description, user_agent, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt->execute([$ip, $event_type, $description, substr($userAgent, 0, 255)]);
    }
    
    private function blockAccess($message) {
        http_response_code(429);
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    text-align: center; 
                    padding: 50px 20px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                }
                .container {
                    background: rgba(255,255,255,0.1);
                    padding: 40px;
                    border-radius: 20px;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                }
                .error { color: #ffcdd2; font-size: 18px; margin-top: 20px; }
                h1 { font-size: 2.5rem; margin-bottom: 20px; }
                .icon { font-size: 4rem; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='icon'>🛡️</div>
                <h1>Access Denied</h1>
                <p class='error'>$message</p>
                <p>If you believe this is an error, please contact the administrator.</p>
            </div>
        </body>
        </html>
        ");
    }
    
    public function generateCaptcha() {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $captcha = '';
        for ($i = 0; $i < 5; $i++) {
            $captcha .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $_SESSION['captcha'] = $captcha;
        $_SESSION['captcha_time'] = time();
        return $this->createCaptchaImage($captcha);
    }
    
    private function createCaptchaImage($text) {
        $width = 150;
        $height = 50;
        $image = imagecreate($width, $height);
        
        // Random colors
        $bg_color = imagecolorallocate($image, random_int(240, 255), random_int(240, 255), random_int(240, 255));
        $text_color = imagecolorallocate($image, random_int(0, 100), random_int(0, 100), random_int(0, 100));
        $line_color = imagecolorallocate($image, random_int(150, 200), random_int(150, 200), random_int(150, 200));
        
        // Add noise lines
        for ($i = 0; $i < 8; $i++) {
            imageline($image, random_int(0, $width), random_int(0, $height), 
                     random_int(0, $width), random_int(0, $height), $line_color);
        }
        
        // Add noise dots
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $line_color);
        }
        
        // Add text with slight rotation
        $font_size = 5;
        $x = 20;
        for ($i = 0; $i < strlen($text); $i++) {
            $char_color = imagecolorallocate($image, random_int(0, 100), random_int(0, 100), random_int(0, 100));
            imagestring($image, $font_size, $x + ($i * 20), random_int(10, 20), $text[$i], $char_color);
        }
        
        // Output image
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
    
    public function verifyCaptcha($input) {
        if (!isset($_SESSION['captcha']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check if CAPTCHA is expired (5 minutes)
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha'], $_SESSION['captcha_time']);
            return false;
        }
        
        $valid = strtoupper($input) === $_SESSION['captcha'];
        
        // Clear CAPTCHA after verification attempt
        unset($_SESSION['captcha'], $_SESSION['captcha_time']);
        
        return $valid;
    }
    
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'error' => 'File too large'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes) || 
            !isset($allowedMimes[$extension]) || 
            $mimeType !== $allowedMimes[$extension]) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        return ['success' => true, 'extension' => $extension, 'mime' => $mimeType];
    }
    
    private function getClientIP() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

function initSecurity() {
    $security = new Security();
    $security->checkDDoS();
}
?>
