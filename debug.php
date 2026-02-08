<?php
// Debug script to test label retrieval
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the db_config
require_once 'db_config.php';

// Test with your email
$userEmail = 'info.official@holidayseva.com';

echo "<h2>Debug: Testing Label Retrieval</h2>";
echo "<hr>";

// Test 1: Check database connection
echo "<h3>Test 1: Database Connection</h3>";
$pdo = getDatabaseConnection();
if ($pdo) {
    echo "✓ Database connected successfully<br>";
} else {
    echo "✗ Database connection failed<br>";
    die();
}

// Test 2: Check if user exists
echo "<h3>Test 2: User Check</h3>";
$userId = getUserId($pdo, $userEmail);
if ($userId) {
    echo "✓ User found with ID: $userId<br>";
} else {
    echo "✗ User not found for email: $userEmail<br>";
}

// Test 3: Check labels table directly
echo "<h3>Test 3: Direct Labels Query</h3>";
$stmt = $pdo->prepare("SELECT * FROM labels WHERE user_email = ?");
$stmt->execute([$userEmail]);
$directLabels = $stmt->fetchAll();
echo "Labels found: " . count($directLabels) . "<br>";
echo "<pre>";
print_r($directLabels);
echo "</pre>";

// Test 4: Test getLabelCounts function
echo "<h3>Test 4: getLabelCounts() Function</h3>";
$sidebarLabels = getLabelCounts($userEmail);
echo "Labels from function: " . count($sidebarLabels) . "<br>";
echo "<pre>";
print_r($sidebarLabels);
echo "</pre>";

// Test 5: Check the exact query being run
echo "<h3>Test 5: Manual Query with Debug</h3>";
$sql = "SELECT 
            l.id, 
            l.label_name, 
            l.label_color,
            l.created_at,
            COUNT(uea.email_id) as count
        FROM labels l
        LEFT JOIN user_email_access uea ON l.id = uea.label_id 
            AND uea.user_id = :user_id
            AND uea.is_deleted = 0
        WHERE l.user_email = :user_email OR l.user_email IS NULL
        GROUP BY l.id, l.label_name, l.label_color, l.created_at
        ORDER BY l.label_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':user_id' => $userId,
    ':user_email' => $userEmail
]);
$manualResults = $stmt->fetchAll();
echo "Manual query results: " . count($manualResults) . "<br>";
echo "<pre>";
print_r($manualResults);
echo "</pre>";

// Test 6: Check if there are any labels with NULL user_email
echo "<h3>Test 6: Check for NULL user_email labels</h3>";
$stmt = $pdo->prepare("SELECT * FROM labels WHERE user_email IS NULL");
$stmt->execute();
$nullLabels = $stmt->fetchAll();
echo "Labels with NULL user_email: " . count($nullLabels) . "<br>";
echo "<pre>";
print_r($nullLabels);
echo "</pre>";

?>