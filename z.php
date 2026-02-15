<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['smtp_user'])) {
    die("Please log in first");
}

$userEmail = $_SESSION['smtp_user'];

echo "<h2>Deleted Items Diagnostic</h2>";
echo "<p>Session Email: <strong>$userEmail</strong></p>";

$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed");
}

// Test 1: Check if sent_emails table exists
echo "<h3>Test 1: Check sent_emails table</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'sent_emails'");
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ sent_emails table EXISTS<br>";
        
        // Check deleted sent emails
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sent_emails WHERE sender_email = ? AND is_deleted = 1");
        $stmt->execute([$userEmail]);
        $count = $stmt->fetch()['count'];
        echo "Deleted sent emails: <strong>$count</strong><br>";
    } else {
        echo "❌ sent_emails table DOES NOT EXIST<br>";
        echo "<span style='color: red;'>This is the problem! The UNION query will fail.</span><br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking sent_emails: " . $e->getMessage() . "<br>";
}

// Test 2: Check inbox_messages deleted items
echo "<h3>Test 2: Check inbox_messages deleted items</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM inbox_messages WHERE user_email = ? AND is_deleted = 1");
    $stmt->execute([$userEmail]);
    $deletedInbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Deleted inbox messages: <strong>" . count($deletedInbox) . "</strong><br>";
    
    if (count($deletedInbox) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Sender</th><th>Subject</th><th>Deleted At</th></tr>";
        foreach ($deletedInbox as $msg) {
            echo "<tr>";
            echo "<td>" . $msg['id'] . "</td>";
            echo "<td>" . htmlspecialchars($msg['sender_email']) . "</td>";
            echo "<td>" . htmlspecialchars($msg['subject']) . "</td>";
            echo "<td>" . $msg['deleted_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Try the actual UNION query from deleted_items.php
echo "<h3>Test 3: Test the UNION query</h3>";
try {
    $sql = "
        (SELECT 
            se.id,
            'sent' as email_type,
            se.sender_email,
            se.recipient_email,
            se.subject,
            se.sent_at as email_date
        FROM sent_emails se
        WHERE se.sender_email = ? AND se.is_deleted = 1)
        
        UNION ALL
        
        (SELECT 
            im.id,
            'received' as email_type,
            im.sender_email,
            im.user_email as recipient_email,
            im.subject,
            im.received_date as email_date
        FROM inbox_messages im
        WHERE im.user_email = ? AND im.is_deleted = 1)
        
        ORDER BY email_date DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userEmail, $userEmail]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ UNION query executed successfully<br>";
    echo "Total results: <strong>" . count($results) . "</strong><br>";
    
    if (count($results) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Type</th><th>From/To</th><th>Subject</th><th>Date</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['email_type'] . "</td>";
            echo "<td>" . htmlspecialchars($row['sender_email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
            echo "<td>" . $row['email_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ UNION query FAILED: " . $e->getMessage() . "<br>";
    echo "<p style='color: red; font-weight: bold;'>This is why deleted_items.php shows nothing!</p>";
    echo "<p><strong>Solution:</strong> The sent_emails table probably doesn't exist or has different column names.</p>";
}

// Test 4: Simplified query (inbox only)
echo "<h3>Test 4: Simplified query (inbox_messages only)</h3>";
try {
    $sql = "
        SELECT 
            id,
            'received' as email_type,
            sender_email,
            user_email as recipient_email,
            subject,
            body_preview,
            received_date as email_date,
            deleted_at,
            has_attachments
        FROM inbox_messages
        WHERE user_email = ? AND is_deleted = 1
        ORDER BY deleted_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userEmail]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Simplified query executed successfully<br>";
    echo "Results: <strong>" . count($results) . "</strong><br>";
    
    if (count($results) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>From</th><th>Subject</th><th>Preview</th><th>Deleted At</th></tr>";
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['sender_email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['body_preview'], 0, 50)) . "...</td>";
            echo "<td>" . $row['deleted_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p style='color: green; font-weight: bold;'>✅ This query works! Use this instead.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Recommendation:</h3>";
echo "<p>If Test 3 failed but Test 4 succeeded, you should modify deleted_items.php to only query inbox_messages (received emails), not sent_emails.</p>";
?>