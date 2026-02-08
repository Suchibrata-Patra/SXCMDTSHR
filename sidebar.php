<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a test session email if not set
if (!isset($_SESSION['smtp_user'])) {
    $_SESSION['smtp_user'] = 'info.official@holidayseva.com';
    echo "<div style='background: yellow; padding: 10px; margin: 10px;'>SESSION SET: " . $_SESSION['smtp_user'] . "</div>";
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

echo "<div style='background: lightblue; padding: 10px; margin: 10px;'>";
echo "Current User Email: " . htmlspecialchars($userEmail) . "<br>";
echo "Current Page: " . htmlspecialchars($current_page);
echo "</div>";

require_once 'db_config.php';

echo "<div style='background: lightgreen; padding: 10px; margin: 10px;'>";
echo "About to call getLabelCounts()...<br>";
$sidebarLabels = getLabelCounts($userEmail);
echo "Labels retrieved: " . count($sidebarLabels) . "<br>";
echo "<pre>";
print_r($sidebarLabels);
echo "</pre>";
echo "</div>";

$unlabeledCount = getUnlabeledEmailCount($userEmail);
echo "<div style='background: lightyellow; padding: 10px; margin: 10px;'>";
echo "Unlabeled Count: " . $unlabeledCount;
echo "</div>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Test</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <h1>Testing Sidebar Labels</h1>
    
    <div style="background: #f0f0f0; padding: 20px; margin: 20px;">
        <h2>Label Items (from foreach loop):</h2>
        <?php if (empty($sidebarLabels)): ?>
            <p style="color: red; font-weight: bold;">NO LABELS FOUND!</p>
        <?php else: ?>
            <?php foreach ($sidebarLabels as $label): ?>
                <div style="border: 1px solid #ccc; padding: 10px; margin: 5px; background: white;">
                    <strong>ID:</strong> <?= $label['id'] ?><br>
                    <strong>Name:</strong> <?= htmlspecialchars($label['label_name']) ?><br>
                    <strong>Color:</strong> <span style="background: <?= htmlspecialchars($label['label_color']) ?>; padding: 5px 10px; color: white;"><?= htmlspecialchars($label['label_color']) ?></span><br>
                    <strong>Count:</strong> <?= isset($label['count']) ? $label['count'] : 'NOT SET' ?><br>
                    <strong>Created:</strong> <?= $label['created_at'] ?? 'NULL' ?><br>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr>
    
    <h2>Now showing the actual sidebar HTML:</h2>
    
    <style>
        /* Include your sidebar styles here */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 15px;
            text-decoration: none;
            color: #1c1c1e;
            font-size: 13px;
            font-weight: 400;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid #ddd;
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .label-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .label-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-count {
            font-size: 11px;
            font-weight: 600;
            color: #8E8E93;
            background: #F2F2F7;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }
    </style>

    <div style="max-width: 300px; background: #fbfbfd; padding: 20px;">
        <h3>Labels Section:</h3>
        
        <?php if (empty($sidebarLabels)): ?>
            <p style="color: red;">No labels to display</p>
        <?php else: ?>
            <!-- Label Items -->
            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" 
                         style="background-color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span class="label-name"><?= htmlspecialchars($label['label_name']) ?></span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>