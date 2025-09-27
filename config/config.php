<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Enhanced logging function
function logError($message, $type = 'ERROR', $file = '', $line = '') {
    $logFile = __DIR__ . '/../logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    
    $logEntry = "[$timestamp] [$type] IP: $ip | URI: $uri | Message: $message";
    if ($file && $line) {
        $logEntry .= " | File: $file:$line";
    }
    $logEntry .= " | User-Agent: " . substr($userAgent, 0, 100) . "\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also log to PHP error log
    error_log("[$type] $message");
}

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'FATAL ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'UNKNOWN ERROR';
    logError("$errorType: $errstr", $errorType, $errfile, $errline);
    
    // Don't execute PHP internal error handler
    return true;
}

// Custom exception handler
function customExceptionHandler($exception) {
    logError("UNCAUGHT EXCEPTION: " . $exception->getMessage(), 'EXCEPTION', $exception->getFile(), $exception->getLine());
    
    // Show user-friendly error page
    if (!headers_sent()) {
        http_response_code(500);
        include __DIR__ . '/../error_pages/500.html';
        exit;
    }
}

// Set custom handlers
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Start session safely with enhanced error handling
function startSessionSafely() {
    try {
        if (session_status() === PHP_SESSION_NONE) {
            // Enhanced session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
            
            logError("Session started successfully", 'INFO');
            return true;
        }
        return true;
    } catch (Exception $e) {
        logError("Session start failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

startSessionSafely();

// Site settings with environment variable support
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'My Blog');
define('SITE_URL', $_ENV['SITE_URL'] ?? 'http://localhost/blog');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Security settings
define('DDOS_REQUEST_LIMIT', 30);
define('DDOS_BAN_DURATION', 3600);

// Enhanced safe require function
function safe_require($file) {
    try {
        if (file_exists($file)) {
            require_once $file;
            logError("Successfully loaded: $file", 'INFO');
            return true;
        } else {
            logError("File not found: $file", 'WARNING');
            return false;
        }
    } catch (Exception $e) {
        logError("Failed to load $file: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Include database safely
if (!safe_require(__DIR__ . '/database.php')) {
    logError("Critical: Database configuration could not be loaded", 'CRITICAL');
}

// Enhanced sanitization function
function sanitize($data, $type = 'string') {
    try {
        if ($data === null || $data === '') {
            return '';
        }
        
        switch ($type) {
            case 'email':
                $sanitized = filter_var(trim($data), FILTER_SANITIZE_EMAIL);
                break;
            case 'url':
                $sanitized = filter_var(trim($data), FILTER_SANITIZE_URL);
                break;
            case 'int':
                $sanitized = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                break;
            case 'float':
                $sanitized = filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;
            default:
                $sanitized = htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        
        return $sanitized;
    } catch (Exception $e) {
        logError("Sanitization failed for type $type: " . $e->getMessage(), 'ERROR');
        return '';
    }
}

function generateSlug($text) {
    try {
        if (empty($text)) {
            return 'untitled-' . time();
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $slug = trim($text, '-') ?: 'untitled-' . time();
        
        logError("Generated slug: $slug from text: " . substr($text, 0, 50), 'INFO');
        return $slug;
    } catch (Exception $e) {
        logError("Slug generation failed: " . $e->getMessage(), 'ERROR');
        return 'untitled-' . time();
    }
}

function getClientIP() {
    try {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    } catch (Exception $e) {
        logError("Failed to get client IP: " . $e->getMessage(), 'ERROR');
        return '0.0.0.0';
    }
}

function isLoggedIn() {
    try {
        $loggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        if ($loggedIn) {
            logError("User " . $_SESSION['user_id'] . " is logged in", 'INFO');
        }
        return $loggedIn;
    } catch (Exception $e) {
        logError("Login check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function isAdmin() {
    try {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    } catch (Exception $e) {
        logError("Admin check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function redirectTo($url) {
    try {
        logError("Redirecting to: $url", 'INFO');
        if (!headers_sent()) {
            header("Location: $url");
            exit();
        }
        echo "<script>window.location.href='$url';</script>";
        exit();
    } catch (Exception $e) {
        logError("Redirect failed: " . $e->getMessage(), 'ERROR');
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
}

function getDatabase() {
    static $database = null;
    try {
        if ($database === null) {
            $database = new Database();
            if (!$database->getConnection()) {
                logError("Database connection failed", 'CRITICAL');
                return null;
            }
            logError("Database connection established", 'INFO');
        }
        return $database;
    } catch (Exception $e) {
        logError("Database initialization error: " . $e->getMessage(), 'CRITICAL');
        return null;
    }
}

function safeQuery($db, $sql, $params = []) {
    try {
        if (!$db) {
            logError("Database connection is null", 'ERROR');
            return false;
        }
        
        $connection = $db->getConnection();
        if (!$connection) {
            logError("Failed to get database connection", 'ERROR');
            return false;
        }
        
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            logError("Failed to prepare statement: $sql", 'ERROR');
            return false;
        }
        
        $result = $stmt->execute($params);
        if (!$result) {
            logError("Failed to execute query: $sql with params: " . json_encode($params), 'ERROR');
            return false;
        }
        
        logError("Query executed successfully: " . substr($sql, 0, 100), 'INFO');
        return $stmt;
    } catch (Exception $e) {
        logError("Query error: " . $e->getMessage() . " | SQL: $sql", 'ERROR');
        return false;
    }
}

function ensureDirectories() {
    try {
        $dirs = [
            'uploads', 
            'uploads/posts', 
            'uploads/profiles',
            'logs',
            'error_pages'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (@mkdir($dir, 0755, true)) {
                    logError("Created directory: $dir", 'INFO');
                } else {
                    logError("Failed to create directory: $dir", 'WARNING');
                }
            }
        }
        
        // Create error pages if they don't exist
        $errorPage500 = 'error_pages/500.html';
        if (!file_exists($errorPage500)) {
            $content = '<!DOCTYPE html>
<html>
<head>
    <title>Server Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Error</h1>
        <p>We apologize, but something went wrong on our server. Please try again later.</p>
        <a href="/" class="btn">Go Home</a>
    </div>
</body>
</html>';
            @file_put_contents($errorPage500, $content);
        }
        
    } catch (Exception $e) {
        logError("Failed to ensure directories: " . $e->getMessage(), 'ERROR');
    }
}

ensureDirectories();

// Enhanced validation functions
function validateEmail($email) {
    try {
        // Check if it's a valid email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Check if it's a Gmail address
        if (!preg_match('/^[a-zA-Z0-9]+@gmail\.com$/', $email)) {
            return false;
        }
        
        logError("Email validation passed: $email", 'INFO');
        return true;
    } catch (Exception $e) {
        logError("Email validation failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function validateUsername($username) {
    try {
        // Username should be 3-20 characters, alphanumeric and underscore only
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return false;
        }
        
        logError("Username validation passed: $username", 'INFO');
        return true;
    } catch (Exception $e) {
        logError("Username validation failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Log system startup
logError("System initialized successfully", 'INFO');
?>
