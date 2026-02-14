<?php
/**
 * csv_upload_mapper.php - Enhanced CSV Upload Handler with Column Mapping
 * 
 * Features:
 * - Flexible column mapping with drag-and-drop interface
 * - Auto-detection of column mappings
 * - Handles any CSV structure
 * - Persistent mapping storage
 * - Comprehensive error handling
 * - Preview before processing
 */
require_once __DIR__ . '/security_handler.php';
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login first']);
    exit();
}

require_once 'db_config.php';

header('Content-Type: application/json');

// Define expected fields for bulk mailing
define('EXPECTED_FIELDS', [
    'mail_id' => [
        'label' => 'Email Address',
        'required' => true,
        'description' => 'Recipient email address',
        'aliases' => ['email', 'email_address', 'recipient_email', 'to', 'recipient', 'mail', 'e-mail']
    ],
    'receiver_name' => [
        'label' => 'Receiver Name',
        'required' => true,
        'description' => 'Name of the recipient',
        'aliases' => ['name', 'recipient_name', 'receiver', 'to_name', 'contact_name', 'full_name', 'recipient']
    ],
    'Mail_Subject' => [
        'label' => 'Email Subject',
        'required' => true,
        'description' => 'Subject line of the email',
        'aliases' => ['subject', 'email_subject', 'title', 'mail_title']
    ],
    'Article_Title' => [
        'label' => 'Article Title',
        'required' => true,
        'description' => 'Title of the article/content',
        'aliases' => ['article', 'content_title', 'heading', 'article_name', 'topic']
    ],
    'Personalised_message' => [
        'label' => 'Personalized Message',
        'required' => true,
        'description' => 'Main message content',
        'aliases' => ['message', 'content', 'body', 'email_body', 'message_content', 'personal_message', 'text']
    ],
    'closing_wish' => [
        'label' => 'Closing Wish',
        'required' => false,
        'description' => 'Closing greeting (e.g., "Best regards")',
        'aliases' => ['closing', 'regards', 'sign_off', 'closing_line', 'goodbye', 'ending']
    ],
    'Name' => [
        'label' => 'Sender Name',
        'required' => false,
        'description' => 'Name of the sender',
        'aliases' => ['sender_name', 'from_name', 'sender', 'your_name', 'signature_name']
    ],
    'Designation' => [
        'label' => 'Sender Designation',
        'required' => false,
        'description' => 'Title/designation of sender',
        'aliases' => ['title', 'position', 'job_title', 'role', 'sender_designation', 'sender_title']
    ],
    'Additional_information' => [
        'label' => 'Additional Information',
        'required' => false,
        'description' => 'Extra information or footer text',
        'aliases' => ['additional_info', 'extra_info', 'notes', 'footer', 'ps', 'additional', 'extra']
    ],
    'Attachments' => [
        'label' => 'Attachments',
        'required' => false,
        'description' => 'Attachment filenames (comma-separated)',
        'aliases' => ['attachment', 'files', 'attached_files', 'file', 'attachments_list']
    ]
]);

/**
 * Main router
 */
$action = $_GET['action'] ?? 'analyze';

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    $userEmail = $_SESSION['smtp_user'];
    $userId = getUserId($pdo, $userEmail);
    
    if (!$userId) {
        $userId = createUserIfNotExists($pdo, $userEmail, null);
        if (!$userId) {
            throw new Exception("Could not create user in database");
        }
    }
    
    switch ($action) {
        case 'analyze':
            analyzeCSV($pdo, $userId);
            break;
            
        case 'save_mapping':
            saveColumnMapping($pdo, $userId);
            break;
            
        case 'get_saved_mapping':
            getSavedMapping($pdo, $userId);
            break;
            
        case 'process_with_mapping':
            processCSVWithMapping($pdo, $userId);
            break;
            
        case 'get_expected_fields':
            getExpectedFields();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("CSV Mapper Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Analyze uploaded CSV and suggest column mappings
 */
function analyzeCSV($pdo, $userId) {
    try {
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded');
        }
        
        $csvFile = $_FILES['csv_file'];
        
        // Validate upload
        if ($csvFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $csvFile['error']);
        }
        
        // Validate file extension
        $fileExt = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }
        
        // Read CSV
        $handle = fopen($csvFile['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }
        
        // Read header
        $header = fgetcsv($handle);
        if (!$header || empty($header)) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row');
        }
        
        // Clean headers (remove BOM and whitespace)
        $header = array_map(function($h) {
            $h = str_replace("\xEF\xBB\xBF", '', $h); // Remove UTF-8 BOM
            return trim($h);
        }, $header);
        
        // Read preview rows (first 5 rows)
        $previewRows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
            $previewRows[] = $row;
            $rowCount++;
        }
        
        // Count total rows
        $totalRows = $rowCount;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        fclose($handle);
        
        // Auto-detect column mappings
        $suggestedMapping = autoDetectMapping($header);
        
        // Check for saved user mappings
        $savedMapping = getUserSavedMapping($pdo, $userId);
        
        // Analyze column data types
        $columnAnalysis = analyzeColumns($header, $previewRows);
        
        echo json_encode([
            'success' => true,
            'filename' => $csvFile['name'],
            'csv_columns' => $header,
            'preview_rows' => $previewRows,
            'total_rows' => $totalRows,
            'suggested_mapping' => $suggestedMapping,
            'saved_mapping' => $savedMapping,
            'column_analysis' => $columnAnalysis,
            'expected_fields' => EXPECTED_FIELDS
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Auto-detect column mappings using fuzzy matching
 */
function autoDetectMapping($csvColumns) {
    $mapping = [];
    $usedColumns = [];
    
    foreach (EXPECTED_FIELDS as $expectedField => $config) {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($csvColumns as $csvColumn) {
            if (in_array($csvColumn, $usedColumns)) {
                continue; // Skip already mapped columns
            }
            
            $score = calculateSimilarity($csvColumn, $expectedField, $config['aliases']);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $csvColumn;
            }
        }
        
        // Only map if confidence is high enough (>50%)
        if ($bestMatch && $bestScore > 0.5) {
            $mapping[$expectedField] = $bestMatch;
            $usedColumns[] = $bestMatch;
        }
    }
    
    return $mapping;
}

/**
 * Calculate similarity score between CSV column and expected field
 */
function calculateSimilarity($csvColumn, $expectedField, $aliases) {
    $csvColumnLower = strtolower(trim($csvColumn));
    $expectedFieldLower = strtolower($expectedField);
    
    // Exact match
    if ($csvColumnLower === $expectedFieldLower) {
        return 1.0;
    }
    
    // Check aliases
    foreach ($aliases as $alias) {
        if ($csvColumnLower === strtolower($alias)) {
            return 0.95;
        }
        
        // Partial match in alias
        if (strpos($csvColumnLower, strtolower($alias)) !== false || 
            strpos(strtolower($alias), $csvColumnLower) !== false) {
            return 0.8;
        }
    }
    
    // Levenshtein distance for fuzzy matching
    $distance = levenshtein($csvColumnLower, $expectedFieldLower);
    $maxLength = max(strlen($csvColumnLower), strlen($expectedFieldLower));
    
    if ($maxLength > 0) {
        $similarity = 1 - ($distance / $maxLength);
        return max(0, $similarity);
    }
    
    return 0;
}

/**
 * Analyze column data to detect types and quality
 */
function analyzeColumns($headers, $rows) {
    $analysis = [];
    
    foreach ($headers as $index => $header) {
        $values = array_column($rows, $index);
        $nonEmptyValues = array_filter($values, function($v) {
            return !empty(trim($v));
        });
        
        $analysis[$header] = [
            'sample_values' => array_slice($values, 0, 3),
            'empty_count' => count($values) - count($nonEmptyValues),
            'fill_rate' => count($values) > 0 ? round((count($nonEmptyValues) / count($values)) * 100, 1) : 0,
            'likely_email' => isLikelyEmail($values),
            'avg_length' => calculateAverageLength($nonEmptyValues)
        ];
    }
    
    return $analysis;
}

/**
 * Check if column contains email addresses
 */
function isLikelyEmail($values) {
    $emailCount = 0;
    $checkedCount = 0;
    
    foreach (array_slice($values, 0, 10) as $value) {
        if (empty(trim($value))) continue;
        
        $checkedCount++;
        if (filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            $emailCount++;
        }
    }
    
    return $checkedCount > 0 && ($emailCount / $checkedCount) > 0.8;
}

/**
 * Calculate average length of values
 */
function calculateAverageLength($values) {
    if (empty($values)) return 0;
    
    $totalLength = array_sum(array_map('strlen', $values));
    return round($totalLength / count($values), 1);
}

/**
 * Save user's column mapping preference
 */
function saveColumnMapping($pdo, $userId) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['mapping']) || !is_array($input['mapping'])) {
            throw new Exception('Invalid mapping data');
        }
        
        $mappingJson = json_encode($input['mapping']);
        $mappingName = $input['mapping_name'] ?? 'Default Mapping';
        
        // Create table if it doesn't exist
        createMappingTable($pdo);
        
        // Save or update mapping
        $stmt = $pdo->prepare("
            INSERT INTO csv_column_mappings 
            (user_id, mapping_name, column_mapping, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                column_mapping = VALUES(column_mapping),
                updated_at = NOW()
        ");
        
        $stmt->execute([$userId, $mappingName, $mappingJson]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Column mapping saved successfully'
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Get user's saved mapping
 */
function getSavedMapping($pdo, $userId) {
    try {
        $mapping = getUserSavedMapping($pdo, $userId);
        
        echo json_encode([
            'success' => true,
            'mapping' => $mapping
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Fetch user's saved mapping from database
 */
function getUserSavedMapping($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT column_mapping 
            FROM csv_column_mappings 
            WHERE user_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return json_decode($result['column_mapping'], true);
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error fetching saved mapping: " . $e->getMessage());
        return null;
    }
}

/**
 * Process CSV with user-confirmed mapping
 */
function processCSVWithMapping($pdo, $userId) {
    try {
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded');
        }
        
        $input = json_decode($_POST['mapping'] ?? '{}', true);
        
        if (!isset($input['column_mapping']) || !is_array($input['column_mapping'])) {
            throw new Exception('No column mapping provided');
        }
        
        $columnMapping = $input['column_mapping'];
        
        // Validate required fields are mapped
        $missingRequired = [];
        foreach (EXPECTED_FIELDS as $field => $config) {
            if ($config['required'] && (!isset($columnMapping[$field]) || empty($columnMapping[$field]))) {
                $missingRequired[] = $config['label'];
            }
        }
        
        if (!empty($missingRequired)) {
            throw new Exception('Required fields not mapped: ' . implode(', ', $missingRequired));
        }
        
        $csvFile = $_FILES['csv_file'];
        
        // Read CSV
        $handle = fopen($csvFile['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file');
        }
        
        // Read and clean header
        $csvHeaders = fgetcsv($handle);
        $csvHeaders = array_map(function($h) {
            return trim(str_replace("\xEF\xBB\xBF", '', $h));
        }, $csvHeaders);
        
        // Create reverse mapping (CSV column -> Expected field)
        $reverseMapping = array_flip($columnMapping);
        
        // Get column indices
        $columnIndices = [];
        foreach ($csvHeaders as $index => $header) {
            if (isset($reverseMapping[$header])) {
                $columnIndices[$reverseMapping[$header]] = $index;
            }
        }
        
        // Process rows and add to queue
        $batchUuid = generateUuid();
        $processedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            try {
                // Extract data using mapping
                $emailData = [];
                foreach (EXPECTED_FIELDS as $field => $config) {
                    if (isset($columnIndices[$field])) {
                        $emailData[$field] = trim($row[$columnIndices[$field]] ?? '');
                    } else {
                        $emailData[$field] = ''; // Use empty string for unmapped fields
                    }
                }
                
                // Validate required fields have values
                if (empty($emailData['mail_id']) || !filter_var($emailData['mail_id'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email address at row $rowNumber");
                }
                
                if (empty($emailData['receiver_name'])) {
                    throw new Exception("Missing receiver name at row $rowNumber");
                }
                
                if (empty($emailData['Mail_Subject'])) {
                    throw new Exception("Missing subject at row $rowNumber");
                }
                
                // Add to bulk mail queue
                addToBulkQueue($pdo, $userId, $batchUuid, $emailData);
                $processedCount++;
                
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage()
                ];
                
                // Stop if too many errors
                if ($errorCount > 10) {
                    $errors[] = [
                        'row' => 'multiple',
                        'error' => 'Too many errors. Processing stopped.'
                    ];
                    break;
                }
            }
        }
        
        fclose($handle);
        
        echo json_encode([
            'success' => true,
            'batch_uuid' => $batchUuid,
            'processed_count' => $processedCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'message' => "$processedCount emails added to queue successfully"
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Add email to bulk mail queue
 */
function addToBulkQueue($pdo, $userId, $batchUuid, $data) {
    // Handle attachments if specified
    $attachmentId = null;
    if (!empty($data['Attachments'])) {
        $attachmentId = findAttachmentByFilename($pdo, $userId, $data['Attachments']);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO bulk_mail_queue (
            user_id, batch_uuid, recipient_email, recipient_name,
            subject, article_title, message_content, closing_wish,
            sender_name, sender_designation, additional_info,
            attachment_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $userId,
        $batchUuid,
        $data['mail_id'],
        $data['receiver_name'],
        $data['Mail_Subject'],
        $data['Article_Title'],
        $data['Personalised_message'],
        $data['closing_wish'],
        $data['Name'],
        $data['Designation'],
        $data['Additional_information'],
        $attachmentId
    ]);
}

/**
 * Find attachment by filename
 */
function findAttachmentByFilename($pdo, $userId, $filename) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.id 
            FROM attachments a
            INNER JOIN user_attachment_access uaa ON a.id = uaa.attachment_id
            WHERE uaa.user_id = ? AND a.original_filename = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, trim($filename)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
        
    } catch (Exception $e) {
        error_log("Error finding attachment: " . $e->getMessage());
        return null;
    }
}

/**
 * Get expected fields configuration
 */
function getExpectedFields() {
    echo json_encode([
        'success' => true,
        'expected_fields' => EXPECTED_FIELDS
    ]);
}

/**
 * Create mapping table if it doesn't exist
 */
function createMappingTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `csv_column_mappings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `mapping_name` VARCHAR(255) NOT NULL DEFAULT 'Default Mapping',
            `column_mapping` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_mapping` (`user_id`, `mapping_name`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
    } catch (Exception $e) {
        error_log("Error creating mapping table: " . $e->getMessage());
        // Don't throw - table might already exist
    }
}

/**
 * Generate UUID v4
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>