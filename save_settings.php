<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit();
}

$settingsFile = 'settings.json';

// Load
$allSettings = [];
if (file_exists($settingsFile)) {
    $json = file_get_contents($settingsFile);
    $allSettings = json_decode($json, true);
    if (!is_array($allSettings)) {
        $allSettings = [];
    }
}

// Build user settings
$user = $_SESSION['smtp_user'];

$allSettings[$user] = [
    "display_name" => $_POST['display_name'] ?? "",
    "signature" => $_POST['signature'] ?? "",
    "default_subject_prefix" => $_POST['default_subject_prefix'] ?? "",
    "cc_yourself" => isset($_POST['cc_yourself']),
    "email_preview" => isset($_POST['email_preview']),
    "smtp_host" => $_POST['smtp_host'] ?? "smtp.gmail.com",
    "smtp_port" => $_POST['smtp_port'] ?? "587",
    "theme" => $_POST['theme'] ?? "light",
    "auto_save_drafts" => isset($_POST['auto_save_drafts'])
];

// Save
if (file_put_contents($settingsFile, json_encode($allSettings, JSON_PRETTY_PRINT))) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Write failed"]);
}
?>
