<?php
/**
 * Minimal CSV Preview - No dependencies
 */

// Enable error reporting to see what's wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any errors
ob_start();

try {
    // Start session
    session_start();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Check if user is logged in (minimal check)
    if (!isset($_SESSION['smtp_user'])) {
        throw new Exception('Not logged in. Please login first.');
    }
    
    // Get action
    $action = $_GET['action'] ?? '';
    
    if ($action !== 'preview') {
        throw new Exception('Invalid action');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('No CSV file uploaded');
    }
    
    $csvFile = $_FILES['csv_file'];
    
    // Check for upload errors
    if ($csvFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error code: ' . $csvFile['error']);
    }
    
    // Check file exists
    if (!file_exists($csvFile['tmp_name'])) {
        throw new Exception('Uploaded file not found');
    }
    
    // Check file extension
    $ext = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        throw new Exception('File must be a CSV file');
    }
    
    // Open CSV file
    $handle = fopen($csvFile['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Could not open CSV file');
    }
    
    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception('CSV file is empty');
    }
    
    // Clean headers (remove BOM)
    $header = array_map(function($h) {
        $h = str_replace("\xEF\xBB\xBF", '', $h);
        return trim($h);
    }, $header);
    
    // Check required columns
    $required = [
        'mail_id', 'receiver_name', 'Mail_Subject', 'Article_Title',
        'Personalised_message', 'closing_wish', 'Name', 'Designation',
        'Additional_information', 'Attachments'
    ];
    
    $missing = [];
    foreach ($required as $col) {
        if (!in_array($col, $header)) {
            $missing[] = $col;
        }
    }
    
    if (!empty($missing)) {
        fclose($handle);
        throw new Exception('Missing columns: ' . implode(', ', $missing));
    }
    
    // Create column map
    $colMap = array_flip($header);
    
    // Read preview rows
    $preview = [];
    $total = 0;
    $max = 10;
    
    while (($row = fgetcsv($handle)) !== false) {
        $total++;
        
        if (count($preview) < $max) {
            $preview[] = [
                'row_number' => $total,
                'mail_id' => $row[$colMap['mail_id']] ?? '',
                'receiver_name' => $row[$colMap['receiver_name']] ?? '',
                'subject' => $row[$colMap['Mail_Subject']] ?? '',
                'article_title' => $row[$colMap['Article_Title']] ?? '',
                'message_preview' => substr($row[$colMap['Personalised_message']] ?? '', 0, 100) . '...',
                'closing_wish' => $row[$colMap['closing_wish']] ?? '',
                'sender_name' => $row[$colMap['Name']] ?? '',
                'sender_designation' => $row[$colMap['Designation']] ?? ''
            ];
        }
    }
    
    fclose($handle);
    
    // Clear output buffer
    ob_end_clean();
    
    // Return success
    echo json_encode([
        'success' => true,
        'preview_rows' => $preview,
        'total_rows' => $total,
        'headers' => $header,
        'message' => "Found $total email(s) in CSV file. Preview showing first " . count($preview) . " rows."
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    ob_end_clean();
    
    // Return error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>