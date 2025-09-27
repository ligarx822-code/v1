<?php
// CSRF Token Management Class
class CSRFToken {
    private static $tokenName = 'csrf_token';
    private static $tokenLength = 32;
    
    /**
     * Generate a new CSRF token
     */
    public static function generate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::$tokenLength));
        $_SESSION[self::$tokenName] = $token;
        $_SESSION[self::$tokenName . '_time'] = time();
        
        return $token;
    }
    
    /**
     * Get current CSRF token (generate if doesn't exist)
     */
    public static function get() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$tokenName]) || self::isExpired()) {
            return self::generate();
        }
        
        return $_SESSION[self::$tokenName];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verify($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::$tokenName]) || self::isExpired()) {
            return false;
        }
        
        return hash_equals($_SESSION[self::$tokenName], $token);
    }

    /**
     * Validate CSRF token (alias for verify method)
     */
    public static function validate($token = null) {
        if ($token === null) {
            $token = $_POST[self::$tokenName] ?? $_GET[self::$tokenName] ?? '';
        }
        return self::verify($token);
    }
    
    /**
     * Check if token is expired (1 hour)
     */
    private static function isExpired() {
        if (!isset($_SESSION[self::$tokenName . '_time'])) {
            return true;
        }
        
        return (time() - $_SESSION[self::$tokenName . '_time']) > 3600;
    }
    
    /**
     * Generate HTML input field
     */
    public static function field() {
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . self::get() . '">';
    }
    
    /**
     * Validate POST request
     */
    public static function validatePost() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[self::$tokenName] ?? '';
            if (!self::verify($token)) {
                http_response_code(403);
                die('CSRF token validation failed. Please refresh the page and try again.');
            }
        }
    }
    
    /**
     * Clear token
     */
    public static function clear() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION[self::$tokenName]);
        unset($_SESSION[self::$tokenName . '_time']);
    }
}

// Helper functions for backward compatibility
function csrf_token() {
    return CSRFToken::get();
}

function csrf_field() {
    return CSRFToken::field();
}

function verify_csrf() {
    CSRFToken::validatePost();
}
?>
