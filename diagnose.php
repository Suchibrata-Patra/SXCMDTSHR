<?php
/**
 * Diagnostic Script
 * Upload this to /SXC_MDTS/ and access via browser to check paths
 */
require_once __DIR__ . '/security_handler.php';
echo "<h1>Path Diagnostic Report</h1>";

echo "<h2>Server Paths</h2>";
echo "<pre>";
echo "Current Directory (__DIR__): " . __DIR__ . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "Current Working Directory: " . getcwd() . "\n";
echo "</pre>";

echo "<h2>File_Drive Path Check</h2>";
$possiblePaths = [
    __DIR__ . '/File_Drive',
    $_SERVER['DOCUMENT_ROOT'] . '/SXC_MDTS/File_Drive',
    dirname(__FILE__) . '/File_Drive',
    '/home/u955994755/public_html/SXC_MDTS/File_Drive',
    '/files/public_html/SXC_MDTS/File_Drive',
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Path</th><th>Exists?</th><th>Readable?</th><th>Files</th></tr>";

foreach ($possiblePaths as $path) {
    $exists = is_dir($path);
    $readable = $exists && is_readable($path);
    $files = 0;
    
    if ($readable) {
        $items = scandir($path);
        $files = count($items) - 2; // Exclude . and ..
    }
    
    echo "<tr>";
    echo "<td><code>" . htmlspecialchars($path) . "</code></td>";
    echo "<td>" . ($exists ? '✅ YES' : '❌ NO') . "</td>";
    echo "<td>" . ($readable ? '✅ YES' : '❌ NO') . "</td>";
    echo "<td>" . $files . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Request Test</h2>";
echo "<pre>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Query String: " . ($_SERVER['QUERY_STRING'] ?? 'empty') . "\n";
echo "GET Parameters:\n";
print_r($_GET);
echo "</pre>";

echo "<h2>Test Backend Call</h2>";
echo "<p><a href='diagnostic.php?action=test'>Click here to test with ?action=test</a></p>";

if (isset($_GET['action'])) {
    echo "<div style='background: #efe; padding: 10px; border: 1px solid #0a0;'>";
    echo "✅ Action parameter received: <strong>" . htmlspecialchars($_GET['action']) . "</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #fee; padding: 10px; border: 1px solid #a00;'>";
    echo "❌ No action parameter received";
    echo "</div>";
}

echo "<h2>Create File_Drive Directory</h2>";
$targetPath = __DIR__ . '/File_Drive';
if (!is_dir($targetPath)) {
    echo "<p>Creating directory: <code>" . htmlspecialchars($targetPath) . "</code></p>";
    if (@mkdir($targetPath, 0755, true)) {
        echo "<div style='background: #efe; padding: 10px;'>✅ Directory created successfully!</div>";
    } else {
        echo "<div style='background: #fee; padding: 10px;'>❌ Failed to create directory. Check permissions.</div>";
    }
} else {
    echo "<div style='background: #efe; padding: 10px;'>✅ Directory already exists!</div>";
}
?>