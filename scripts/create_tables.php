<?php
// Auto table creation script
require_once '../config/config.php';

try {
    echo "🚀 Starting database setup...\n";
    
    $database = getDatabase();
    if (!$database) {
        throw new Exception("❌ Could not connect to database");
    }
    
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception("❌ Could not get database connection");
    }
    
    // Read and execute the complete schema
    $schema = file_get_contents('../database/all.sql');
    if (!$schema) {
        throw new Exception("❌ Could not read schema file");
    }
    
    echo "📖 Reading SQL schema file...\n";
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(--|\/\*)/', $statement)) {
            try {
                $db->exec($statement);
                $successCount++;
                
                // Extract table name for better logging
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Created table: {$matches[1]}\n";
                } elseif (preg_match('/CREATE TRIGGER.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Created trigger: {$matches[1]}\n";
                } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                    echo "✅ Inserted data into: {$matches[1]}\n";
                } else {
                    echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
                }
                
            } catch (PDOException $e) {
                $errorCount++;
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "ℹ️  Skipped (already exists): " . substr($statement, 0, 50) . "...\n";
                } else {
                    echo "⚠️  Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "\n🎉 Database setup completed!\n";
    echo "✅ Successful operations: $successCount\n";
    echo "⚠️  Warnings/Skipped: $errorCount\n";
    
    // Verify tables were created
    echo "\n🔍 Verifying tables...\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'users', 'posts', 'post_likes', 'post_views', 'comments', 
        'chat_messages', 'site_stats', 'contact_messages', 'ddos_bans', 
        'request_tracking', 'email_verifications', 'security_logs', 
        'failed_logins', 'secure_sessions'
    ];
    
    $missingTables = array_diff($expectedTables, $tables);
    
    if (empty($missingTables)) {
        echo "✅ All required tables created successfully!\n";
    } else {
        echo "❌ Missing tables: " . implode(', ', $missingTables) . "\n";
    }
    
    echo "\n📊 Database Statistics:\n";
    foreach ($tables as $table) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "   📋 $table: $count records\n";
        } catch (Exception $e) {
            echo "   ❌ $table: Error reading\n";
        }
    }
    
    echo "\n🎯 Setup complete! Your blog system is ready to use.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    logError("Database setup error: " . $e->getMessage(), 'ERROR');
    exit(1);
}
?>