<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;
    private $connectionAttempts = 0;
    private $maxAttempts = 3;
    
    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'stacknro_blog';
        $this->username = $_ENV['DB_USER'] ?? 'stacknro_blog';
        $this->password = $_ENV['DB_PASS'] ?? 'admin-2025';
        
        logError("Database constructor called with host: {$this->host}, db: {$this->db_name}", 'INFO');
        $this->connect();
    }
    
    private function connect() {
        $this->connectionAttempts++;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10,
                PDO::ATTR_PERSISTENT => false
            ];
            
            logError("Attempting database connection (attempt {$this->connectionAttempts})", 'INFO');
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Test the connection
            $this->conn->query("SELECT 1");
            
            logError("Database connection successful", 'INFO');
            
        } catch(PDOException $e) {
            $errorMsg = "Database connection failed (attempt {$this->connectionAttempts}): " . $e->getMessage();
            logError($errorMsg, 'ERROR');
            
            $this->conn = null;
            
            // Try to reconnect if we haven't exceeded max attempts
            if ($this->connectionAttempts < $this->maxAttempts) {
                logError("Retrying database connection in 2 seconds...", 'INFO');
                sleep(2);
                return $this->connect();
            } else {
                logError("Max database connection attempts reached. Connection failed permanently.", 'CRITICAL');
            }
        } catch (Exception $e) {
            logError("Unexpected database error: " . $e->getMessage(), 'CRITICAL');
            $this->conn = null;
        }
    }
    
    public function getConnection() {
        try {
            // Check if connection is still alive
            if ($this->conn === null) {
                logError("Connection is null, attempting to reconnect", 'WARNING');
                $this->connectionAttempts = 0; // Reset attempts for reconnection
                $this->connect();
            } else {
                // Test if connection is still active
                try {
                    $this->conn->query("SELECT 1");
                } catch (PDOException $e) {
                    logError("Connection lost, attempting to reconnect: " . $e->getMessage(), 'WARNING');
                    $this->conn = null;
                    $this->connectionAttempts = 0;
                    $this->connect();
                }
            }
            
            return $this->conn;
        } catch (Exception $e) {
            logError("Failed to get database connection: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    public function query($sql, $params = []) {
        try {
            $connection = $this->getConnection();
            if (!$connection) {
                logError("Cannot execute query - no database connection", 'ERROR');
                return false;
            }
            
            logError("Executing query: " . substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : ''), 'INFO');
            
            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                logError("Failed to prepare statement: $sql", 'ERROR');
                return false;
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                logError("Query execution failed: " . implode(' - ', $errorInfo), 'ERROR');
                return false;
            }
            
            logError("Query executed successfully", 'INFO');
            return $stmt;
            
        } catch (PDOException $e) {
            logError("PDO Query error: " . $e->getMessage() . " | SQL: " . substr($sql, 0, 200), 'ERROR');
            return false;
        } catch (Exception $e) {
            logError("General query error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if (!$stmt) {
                logError("Fetch failed - query returned false", 'ERROR');
                return false;
            }
            
            $result = $stmt->fetch();
            logError("Fetch completed, result: " . ($result ? 'found' : 'not found'), 'INFO');
            return $result;
            
        } catch (Exception $e) {
            logError("Fetch error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            if (!$stmt) {
                logError("FetchAll failed - query returned false", 'ERROR');
                return [];
            }
            
            $results = $stmt->fetchAll();
            $count = is_array($results) ? count($results) : 0;
            logError("FetchAll completed, found $count records", 'INFO');
            return $results ?: [];
            
        } catch (Exception $e) {
            logError("FetchAll error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    public function lastInsertId() {
        try {
            $connection = $this->getConnection();
            if (!$connection) {
                return false;
            }
            
            $id = $connection->lastInsertId();
            logError("Last insert ID: $id", 'INFO');
            return $id;
            
        } catch (Exception $e) {
            logError("Failed to get last insert ID: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function beginTransaction() {
        try {
            $connection = $this->getConnection();
            if (!$connection) {
                return false;
            }
            
            $result = $connection->beginTransaction();
            logError("Transaction started", 'INFO');
            return $result;
            
        } catch (Exception $e) {
            logError("Failed to start transaction: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function commit() {
        try {
            $connection = $this->getConnection();
            if (!$connection) {
                return false;
            }
            
            $result = $connection->commit();
            logError("Transaction committed", 'INFO');
            return $result;
            
        } catch (Exception $e) {
            logError("Failed to commit transaction: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function rollback() {
        try {
            $connection = $this->getConnection();
            if (!$connection) {
                return false;
            }
            
            $result = $connection->rollback();
            logError("Transaction rolled back", 'INFO');
            return $result;
            
        } catch (Exception $e) {
            logError("Failed to rollback transaction: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public function isConnected() {
        try {
            if ($this->conn === null) {
                return false;
            }
            
            $this->conn->query("SELECT 1");
            return true;
            
        } catch (Exception $e) {
            logError("Connection test failed: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }
}
?>
