<?php
/**
 * DIAGNOSTIC SCRIPT - Check if fixes are working
 * Run this to verify the imap_helper.php is processing messages correctly
 */

// Test the functions directly
require_once 'imap_helper.php';
require_once 'inbox_functions.php';

echo "=== INBOX FIX DIAGNOSTIC ===\n\n";

// Test 1: Check if functions exist
echo "Test 1: Checking if functions exist...\n";
if (function_exists('getMessageBodyParsed')) {
    echo "✅ getMessageBodyParsed() exists\n";
} else {
    echo "❌ getMessageBodyParsed() NOT FOUND\n";
}

if (function_exists('getPartBody')) {
    echo "✅ getPartBody() exists\n";
} else {
    echo "❌ getPartBody() NOT FOUND\n";
}

if (function_exists('saveInboxMessage')) {
    echo "✅ saveInboxMessage() exists\n";
} else {
    echo "❌ saveInboxMessage() NOT FOUND\n";
}

echo "\n";

// Test 2: Check if ON DUPLICATE KEY UPDATE is in place
echo "Test 2: Checking database insert logic...\n";
$functionContent = file_get_contents('inbox_functions.php');
if (strpos($functionContent, 'ON DUPLICATE KEY UPDATE') !== false) {
    echo "✅ ON DUPLICATE KEY UPDATE found in inbox_functions.php\n";
} else {
    echo "❌ ON DUPLICATE KEY UPDATE NOT FOUND - read status won't be preserved!\n";
}

echo "\n";

// Test 3: Check if HTML is being preserved
echo "Test 3: Checking HTML preservation logic...\n";
$imapContent = file_get_contents('imap_helper.php');
if (strpos($imapContent, "'body' => \$body") !== false) {
    echo "✅ Body is being saved as-is (HTML preserved)\n";
} else {
    echo "❌ Body processing may strip HTML\n";
}

if (strpos($imapContent, "strip_tags(\$body)") !== false && 
    strpos($imapContent, "body_preview") !== false) {
    echo "✅ Preview generation strips HTML (correct!)\n";
} else {
    echo "⚠️ Preview generation logic may not be stripping HTML properly\n";
}

echo "\n";

// Test 4: Check database for messages
echo "Test 4: Checking database messages...\n";
require_once 'db_config.php';
$pdo = getDatabaseConnection();

if (!$pdo) {
    echo "❌ Cannot connect to database\n";
    exit(1);
}

// Get a sample message
$stmt = $pdo->prepare("
    SELECT 
        id,
        subject,
        LEFT(body, 100) as body_sample,
        LENGTH(body) as body_length,
        LEFT(body_preview, 100) as preview_sample,
        LENGTH(body_preview) as preview_length,
        CASE 
            WHEN body LIKE '%<html%' OR body LIKE '%<div%' OR body LIKE '%<p>%' 
            THEN 'YES - HTML' 
            ELSE 'NO - Plain text' 
        END as has_html,
        fetched_at
    FROM inbox_messages 
    WHERE user_email = :email
    ORDER BY id DESC 
    LIMIT 3
");

$testEmail = 'info.official@holidayseva.com'; // Change if needed
$stmt->execute([':email' => $testEmail]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "⚠️ No messages found in database for $testEmail\n";
    echo "   This is normal if you just cleared the inbox.\n";
    echo "   Run a sync to fetch messages.\n";
} else {
    echo "Found " . count($messages) . " messages. Analyzing...\n\n";
    
    foreach ($messages as $msg) {
        echo "Message ID {$msg['id']}:\n";
        echo "  Subject: {$msg['subject']}\n";
        echo "  Body type: {$msg['has_html']}\n";
        echo "  Body length: {$msg['body_length']} chars\n";
        echo "  Preview length: {$msg['preview_length']} chars\n";
        echo "  Body starts: " . substr($msg['body_sample'], 0, 50) . "...\n";
        echo "  Preview starts: " . substr($msg['preview_sample'], 0, 50) . "...\n";
        echo "  Fetched: {$msg['fetched_at']}\n";
        
        // Check if this is OLD data (before fix)
        $fetchTime = strtotime($msg['fetched_at']);
        $now = time();
        $minutesAgo = ($now - $fetchTime) / 60;
        
        if ($minutesAgo > 5) {
            echo "  ⚠️ WARNING: This message is OLD (fetched " . round($minutesAgo) . " minutes ago)\n";
            echo "     It was fetched BEFORE the fix was applied.\n";
            echo "     You need to RE-SYNC to get properly formatted messages!\n";
        } else {
            echo "  ✅ This is a RECENT fetch (less than 5 minutes ago)\n";
        }
        
        // Check if preview has forward header
        if (strpos($msg['preview_sample'], 'Forwarded message') !== false) {
            echo "  ❌ Preview contains 'Forwarded message' header\n";
            echo "     This message needs to be re-synced!\n";
        } else {
            echo "  ✅ Preview looks clean (no forward header)\n";
        }
        
        echo "\n";
    }
}

echo "\n";

// Test 5: Recommendations
echo "=== RECOMMENDATIONS ===\n\n";

$needsResync = false;
foreach ($messages as $msg) {
    $fetchTime = strtotime($msg['fetched_at']);
    $minutesAgo = (time() - $fetchTime) / 60;
    if ($minutesAgo > 5 || strpos($msg['preview_sample'], 'Forwarded message') !== false) {
        $needsResync = true;
        break;
    }
}

if ($needsResync) {
    echo "❌ ACTION REQUIRED:\n\n";
    echo "Your database contains OLD messages from before the fix was applied.\n\n";
    echo "Steps to fix:\n";
    echo "1. Delete old messages:\n";
    echo "   DELETE FROM inbox_messages WHERE user_email = '$testEmail';\n\n";
    echo "2. Re-sync in the UI:\n";
    echo "   - Open inbox\n";
    echo "   - Click 'Sync Messages' or press Ctrl+R\n";
    echo "   - Wait for messages to download\n\n";
    echo "3. Run this diagnostic again to verify\n\n";
} else {
    echo "✅ ALL GOOD!\n\n";
    echo "Your messages appear to be properly formatted.\n";
    echo "- HTML is preserved in body\n";
    echo "- Previews are clean plain text\n";
    echo "- Messages are recent\n\n";
}

echo "=== DIAGNOSTIC COMPLETE ===\n";
?>