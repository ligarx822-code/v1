<?php
// Database setup script to create all tables
require_once '../config/config.php';

try {
    echo "Starting database setup...\n";
    
    $database = getDatabase();
    if (!$database) {
        throw new Exception("Could not connect to database");
    }
    
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception("Could not get database connection");
    }
    
    // Read and execute the complete schema
    $schema = file_get_contents('../database/complete_schema.sql');
    if (!$schema) {
        throw new Exception("Could not read schema file");
    }
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $db->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "Database setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    logError("Database setup error: " . $e->getMessage(), 'ERROR');
}
?>
