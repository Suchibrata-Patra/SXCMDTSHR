<?php
session_start();
require_once 'db_config.php';

echo "<h2>Debug Information for Deleted Emails</h2>";

// Check session
echo "<h3>1. Session Information:</h3>";
echo "Session smtp_user: <strong>" . ($_SESSION['smtp_user'] ?? 'NOT SET') . "</strong><br>";

// Check database
echo "<h3>2. Database Deleted Emails:</h3>";
$pdo = getDatabaseConnection();
if ($pdo) {
    $stmt = $pdo->query("
        SELECT 
            id, 
            user_email, 
            sender_email, 
            subject, 
            is_deleted, 
            deleted_at 
        FROM inbox_messages 
        WHERE is_deleted = 1
        ORDER BY deleted_at DESC
    ");
    
    $deletedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total deleted emails in database: <strong>" . count($deletedEmails) . "</strong><br><br>";
    
    if (count($deletedEmails) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User Email</th><th>Sender</th><th>Subject</th><th>Deleted At</th></tr>";
        foreach ($deletedEmails as $email) {
            echo "<tr>";
            echo "<td>" . $email['id'] . "</td>";
            echo "<td>" . htmlspecialchars($email['user_email']) . "</td>";
            echo "<td>" . htmlspecialchars($email['sender_email']) . "</td>";
            echo "<td>" . htmlspecialchars($email['subject']) . "</td>";
            echo "<td>" . $email['deleted_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check if session email matches
    echo "<h3>3. Email Match Check:</h3>";
    if (isset($_SESSION['smtp_user'])) {
        $sessionEmail = $_SESSION['smtp_user'];
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM inbox_messages 
            WHERE user_email = :email AND is_deleted = 1
        ");
        $stmt->execute([':email' => $sessionEmail]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Deleted emails for session user '<strong>$sessionEmail</strong>': <strong>" . $result['count'] . "</strong><br>";
        
        if ($result['count'] == 0) {
            echo "<br><span style='color: red; font-weight: bold;'>⚠️ PROBLEM FOUND: No deleted emails match your session email!</span><br>";
            echo "Your session email is: <strong>$sessionEmail</strong><br>";
            echo "But deleted emails are for: <strong>info.official@holidayseva.com</strong><br>";
            echo "<br><strong>Solution:</strong> Make sure you're logged in with the email: info.official@holidayseva.com";
        }
    } else {
        echo "<span style='color: red;'>Session smtp_user is not set!</span>";
    }
}

?>