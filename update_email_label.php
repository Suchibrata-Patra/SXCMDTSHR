<?php
// update_email_label.php - AJAX handler for updating email labels
session_start();
require 'config.php';
require 'db_config.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$userEmail = $_SESSION['smtp_user'];
$emailId = intval($_POST['email_id'] ?? 0);
$labelId = $_POST['label_id'] ?? null;

// Convert empty string to null
if ($labelId === '' || $labelId === 'null') {
    $labelId = null;
} else {
    $labelId = intval($labelId);
}

if (!$emailId) {
    echo json_encode(['success' => false, 'message' => 'Invalid email ID']);
    exit();
}

// Update the label
$result = updateEmailLabel($emailId, $userEmail, $labelId);

if ($result) {
    echo json_encode([
        'success' => true, 
        'message' => $labelId ? 'Label updated successfully' : 'Label removed successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update label']);
}
?>