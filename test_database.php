<?php
// test_database.php - Test database connection and sent_emails table
session_start();
require 'config.php';

// Set hardcoded credentials for testing
function testGetDatabaseConnection() {
    $host = "localhost";
    $dbname = "u955994755_SXC_MDTS";
    $username = "u955994755_DB_supremacy";
    $password = "sxccal.edu#MDTS@2026";
    
    echo "<h3>Testing Database Connection...</h3>";
    echo "Host: $host<br>";
    echo "Database: $dbname<br>";
    echo "Username: $username<br>";
    echo "Password: " . str_repeat('*', strlen($password)) . "<br><br>";
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        echo "✅ <strong style='color: green;'>Database connection successful!</strong><br><br>";
        return $pdo;
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Database connection failed!</strong><br>";
        echo "Error: " . $e->getMessage() . "<br><br>";
        return null;
    }
}

// Test table existence
function testTableExists($pdo) {
    echo "<h3>Checking if sent_emails table exists...</h3>";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'sent_emails'");
        $result = $stmt->fetch();
        
        if ($result) {
            echo "✅ <strong style='color: green;'>Table 'sent_emails' exists!</strong><br><br>";
            return true;
        } else {
            echo "❌ <strong style='color: red;'>Table 'sent_emails' does NOT exist!</strong><br>";
            echo "Please run the create_sent_emails_table.sql file.<br><br>";
            return false;
        }
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Error checking table:</strong> " . $e->getMessage() . "<br><br>";
        return false;
    }
}

// Test table structure
function testTableStructure($pdo) {
    echo "<h3>Table Structure:</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE sent_emails");
        $columns = $stmt->fetchAll();
        
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . $col['Field'] . "</strong></td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $col['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br><br>";
        
        return true;
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Error fetching table structure:</strong> " . $e->getMessage() . "<br><br>";
        return false;
    }
}

// Test insert
function testInsert($pdo) {
    echo "<h3>Testing INSERT operation...</h3>";
    
    $testData = [
        'sender_email' => 'test@sxccal.edu',
        'recipient_email' => 'recipient@example.com',
        'cc_list' => 'cc1@example.com, cc2@example.com',
        'bcc_list' => '',
        'subject' => 'Test Email - ' . date('Y-m-d H:i:s'),
        'article_title' => 'Test Article Title',
        'message_body' => '<p>This is a <strong>test email</strong> message body.</p>',
        'attachment_names' => 'test.pdf, document.docx'
    ];
    
    try {
        $sql = "INSERT INTO sent_emails 
                (sender_email, recipient_email, cc_list, bcc_list, subject, 
                 article_title, message_body, attachment_names, sent_at) 
                VALUES 
                (:sender_email, :recipient_email, :cc_list, :bcc_list, :subject, 
                 :article_title, :message_body, :attachment_names, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($testData);
        
        if ($success) {
            $lastId = $pdo->lastInsertId();
            echo "✅ <strong style='color: green;'>Test record inserted successfully!</strong><br>";
            echo "Inserted ID: $lastId<br><br>";
            return $lastId;
        } else {
            echo "❌ <strong style='color: red;'>Insert failed!</strong><br><br>";
            return false;
        }
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Insert error:</strong> " . $e->getMessage() . "<br><br>";
        return false;
    }
}

// Test select
function testSelect($pdo, $insertedId = null) {
    echo "<h3>Testing SELECT operation...</h3>";
    
    try {
        if ($insertedId) {
            $stmt = $pdo->prepare("SELECT * FROM sent_emails WHERE id = :id");
            $stmt->execute(['id' => $insertedId]);
            echo "Fetching record with ID: $insertedId<br>";
        } else {
            $stmt = $pdo->query("SELECT * FROM sent_emails ORDER BY sent_at DESC LIMIT 5");
            echo "Fetching last 5 records:<br>";
        }
        
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            echo "⚠️ <strong style='color: orange;'>No records found in database.</strong><br><br>";
            return false;
        }
        
        echo "✅ <strong style='color: green;'>Found " . count($results) . " record(s)</strong><br><br>";
        
        foreach ($results as $row) {
            echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>ID:</strong> " . $row['id'] . "<br>";
            echo "<strong>From:</strong> " . htmlspecialchars($row['sender_email']) . "<br>";
            echo "<strong>To:</strong> " . htmlspecialchars($row['recipient_email']) . "<br>";
            echo "<strong>Subject:</strong> " . htmlspecialchars($row['subject']) . "<br>";
            echo "<strong>Sent At:</strong> " . $row['sent_at'] . "<br>";
            
            if (!empty($row['cc_list'])) {
                echo "<strong>CC:</strong> " . htmlspecialchars($row['cc_list']) . "<br>";
            }
            if (!empty($row['article_title'])) {
                echo "<strong>Article:</strong> " . htmlspecialchars($row['article_title']) . "<br>";
            }
            if (!empty($row['attachment_names'])) {
                echo "<strong>Attachments:</strong> " . htmlspecialchars($row['attachment_names']) . "<br>";
            }
            echo "</div>";
        }
        echo "<br>";
        
        return true;
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Select error:</strong> " . $e->getMessage() . "<br><br>";
        return false;
    }
}

// Test count
function testCount($pdo) {
    echo "<h3>Testing record count...</h3>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM sent_emails");
        $result = $stmt->fetch();
        
        echo "✅ <strong style='color: green;'>Total records in database:</strong> " . $result['total'] . "<br><br>";
        return true;
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Count error:</strong> " . $e->getMessage() . "<br><br>";
        return false;
    }
}

// Clean up test record
function cleanupTestRecord($pdo, $insertedId) {
    if (!$insertedId) return;
    
    echo "<h3>Cleaning up test record...</h3>";
    
    try {
        $stmt = $pdo->prepare("DELETE FROM sent_emails WHERE id = :id");
        $stmt->execute(['id' => $insertedId]);
        echo "✅ <strong style='color: green;'>Test record deleted (ID: $insertedId)</strong><br><br>";
    } catch (PDOException $e) {
        echo "❌ <strong style='color: red;'>Cleanup error:</strong> " . $e->getMessage() . "<br><br>";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test - SXC MDTS</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0973dc;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        h3 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 15px;
            border-left: 4px solid #0973dc;
            padding-left: 15px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0973dc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #006bb3;
        }
        table {
            width: 100%;
            margin: 10px 0;
        }
        th {
            background: #0973dc;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            padding: 8px;
        }
        .summary {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
            border-left: 4px solid #0973dc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 SXC MDTS - Database Connection Test</h1>
        
        <?php
        // Run all tests
        $pdo = testGetDatabaseConnection();
        
        if ($pdo) {
            $tableExists = testTableExists($pdo);
            
            if ($tableExists) {
                testTableStructure($pdo);
                testCount($pdo);
                
                echo "<hr style='margin: 30px 0;'>";
                
                $insertedId = testInsert($pdo);
                
                if ($insertedId) {
                    testSelect($pdo, $insertedId);
                    cleanupTestRecord($pdo, $insertedId);
                } else {
                    testSelect($pdo);
                }
                
                echo "<div class='summary'>";
                echo "<h3 style='margin-top: 0;'>✅ Summary</h3>";
                echo "<strong>Database connection is working perfectly!</strong><br>";
                echo "The sent_emails table is properly configured and functional.<br>";
                echo "Your email tracking system should work correctly.<br><br>";
                echo "<strong>Next steps:</strong><br>";
                echo "1. Try sending a test email<br>";
                echo "2. Check if it appears in sent_history.php<br>";
                echo "3. If issues persist, check your PHP error log<br>";
                echo "</div>";
            } else {
                echo "<div class='summary' style='background: #fff3e0; border-color: #f57c00;'>";
                echo "<h3 style='margin-top: 0;'>⚠️ Action Required</h3>";
                echo "The database connection works, but the <strong>sent_emails</strong> table doesn't exist.<br><br>";
                echo "<strong>Please run this SQL command:</strong><br>";
                echo "<textarea style='width: 100%; height: 200px; margin: 10px 0; padding: 10px; font-family: monospace;' readonly>";
                echo "CREATE TABLE IF NOT EXISTS `sent_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_email` VARCHAR(255) NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `cc_list` TEXT,
  `bcc_list` TEXT,
  `subject` VARCHAR(500) NOT NULL,
  `article_title` VARCHAR(500),
  `message_body` LONGTEXT NOT NULL,
  `attachment_names` TEXT,
  `sent_at` DATETIME NOT NULL,
  INDEX `idx_sender` (`sender_email`),
  INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                echo "</textarea>";
                echo "</div>";
            }
        } else {
            echo "<div class='summary' style='background: #ffebee; border-color: #c62828;'>";
            echo "<h3 style='margin-top: 0;'>❌ Database Connection Failed</h3>";
            echo "<strong>Please check:</strong><br>";
            echo "1. Database credentials are correct in db_config.php<br>";
            echo "2. MySQL server is running<br>";
            echo "3. Database exists<br>";
            echo "4. User has proper permissions<br>";
            echo "</div>";
        }
        ?>
        
        <a href="index.php" class="btn">← Back to Composer</a>
        <a href="sent_history.php" class="btn">View Sent History</a>
    </div>
</body>
</html>