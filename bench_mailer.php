<?php
session_start();
require_once 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

// Load user settings
$settingsFile = 'settings.json';
$settings = [];

if (file_exists($settingsFile)) {
    $jsonContent = file_get_contents($settingsFile);
    $allSettings = json_decode($jsonContent, true) ?? [];
    $settings = $allSettings[$_SESSION['smtp_user']] ?? [];
}

$settings = array_merge([
    'display_name' => "St. Xavier's College",
    'signature' => '',
    'default_subject_prefix' => ''
], $settings);

$_SESSION['user_settings'] = $settings;

// Get database connection
$pdo = getDatabaseConnection();
$userId = getUserId($pdo, $_SESSION['smtp_user']);

// Get pending count in queue
$pendingCount = 0;
$processingCount = 0;
$completedCount = 0;
$failedCount = 0;

try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM bulk_mail_queue WHERE user_id = ? GROUP BY status");
    $stmt->execute([$userId]);
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statusCounts as $row) {
        switch ($row['status']) {
            case 'pending':
                $pendingCount = $row['count'];
                break;
            case 'processing':
                $processingCount = $row['count'];
                break;
            case 'completed':
                $completedCount = $row['count'];
                break;
            case 'failed':
                $failedCount = $row['count'];
                break;
        }
    }
} catch (Exception $e) {
    error_log("Error getting queue counts: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Mailer — SXC MDTS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
            --warning-orange: #FF9500;
            --error-red: #FF3B30;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--apple-bg);
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 40px;
        }

        /* ========== HEADER ========== */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 24px 40px;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 4px;
        }

        .header-left p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* ========== STATUS CARDS ========== */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .status-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .status-icon.pending {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning-orange);
        }

        .status-icon.processing {
            background: rgba(0, 122, 255, 0.1);
            color: var(--apple-blue);
        }

        .status-icon.completed {
            background: rgba(52, 199, 89, 0.1);
            color: var(--success-green);
        }

        .status-icon.failed {
            background: rgba(255, 59, 48, 0.1);
            color: var(--error-red);
        }

        .status-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--apple-gray);
        }

        .status-value {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
        }

        /* ========== UPLOAD SECTION ========== */
        .upload-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title .material-icons {
            color: var(--apple-blue);
        }

        /* File Upload Area */
        .upload-container {
            position: relative;
        }

        .file-drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: var(--apple-bg);
            transition: all 0.3s;
            cursor: pointer;
        }

        .file-drop-zone:hover,
        .file-drop-zone.drag-over {
            border-color: var(--apple-blue);
            background: rgba(0, 122, 255, 0.05);
        }

        .file-drop-zone.has-file {
            border-color: var(--success-green);
            background: rgba(52, 199, 89, 0.05);
        }

        .upload-icon {
            font-size: 48px;
            color: var(--apple-blue);
            margin-bottom: 16px;
        }

        .upload-text h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .upload-text p {
            font-size: 14px;
            color: var(--apple-gray);
            margin-bottom: 16px;
        }

        .browse-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--apple-blue);
            color: white;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .browse-btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        #csvFileInput {
            display: none;
        }

        /* File Info Display */
        .file-info {
            display: none;
            margin-top: 20px;
            padding: 16px;
            background: var(--apple-bg);
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .file-info.show {
            display: block;
        }

        .file-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .file-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .file-info-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .file-info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .remove-file-btn {
            margin-top: 12px;
            padding: 8px 16px;
            background: var(--error-red);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .remove-file-btn:hover {
            background: #cc2f26;
        }

        /* Attachment Upload */
        .attachment-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        .attachment-drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: var(--apple-bg);
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .attachment-drop-zone:hover {
            border-color: var(--apple-blue);
            background: rgba(0, 122, 255, 0.05);
        }

        .attachment-list {
            display: none;
        }

        .attachment-list.show {
            display: block;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--apple-bg);
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
        }

        .attachment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            background: var(--apple-blue);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .attachment-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 2px;
        }

        .attachment-details p {
            font-size: 12px;
            color: var(--apple-gray);
        }

        .remove-attachment-btn {
            padding: 6px 12px;
            background: var(--error-red);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .remove-attachment-btn:hover {
            background: #cc2f26;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn-primary:disabled {
            background: var(--apple-gray);
            cursor: not-allowed;
            opacity: 0.5;
        }

        .btn-secondary {
            background: white;
            color: var(--apple-blue);
            border: 2px solid var(--apple-blue);
        }

        .btn-secondary:hover {
            background: rgba(0, 122, 255, 0.05);
        }

        /* ========== PROGRESS SECTION ========== */
        .progress-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            display: none;
        }

        .progress-section.show {
            display: block;
        }

        .progress-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .progress-stats {
            display: flex;
            gap: 30px;
        }

        .progress-stat {
            text-align: center;
        }

        .progress-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
        }

        .progress-stat-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-bar-container {
            background: var(--apple-bg);
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--apple-blue), var(--success-green));
            width: 0%;
            transition: width 0.3s ease;
        }

        .current-email-info {
            padding: 16px;
            background: var(--apple-bg);
            border-radius: 10px;
            border-left: 4px solid var(--apple-blue);
            margin-bottom: 20px;
        }

        .current-email-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .current-email-info p {
            font-size: 13px;
            color: var(--apple-gray);
        }

        /* Email Log */
        .email-log {
            max-height: 400px;
            overflow-y: auto;
        }

        .log-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .log-status.success {
            background: var(--success-green);
        }

        .log-status.error {
            background: var(--error-red);
        }

        .log-status.processing {
            background: var(--apple-blue);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .log-content {
            flex: 1;
        }

        .log-email {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .log-time {
            font-size: 12px;
            color: var(--apple-gray);
        }

        .log-message {
            font-size: 12px;
            color: var(--error-red);
            margin-top: 4px;
        }

        /* ========== ALERTS ========== */
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert.info {
            background: rgba(0, 122, 255, 0.1);
            color: var(--apple-blue);
            border: 1px solid rgba(0, 122, 255, 0.2);
        }

        .alert.success {
            background: rgba(52, 199, 89, 0.1);
            color: var(--success-green);
            border: 1px solid rgba(52, 199, 89, 0.2);
        }

        .alert.error {
            background: rgba(255, 59, 48, 0.1);
            color: var(--error-red);
            border: 1px solid rgba(255, 59, 48, 0.2);
        }

        .alert .material-icons {
            font-size: 20px;
        }

        /* Loading Spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="app-container" style="display: flex; width: 100%;">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1>Bulk Email Sender</h1>
                        <p>Send personalized emails to multiple recipients from CSV</p>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <!-- Status Cards -->
                <div class="status-grid">
                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon pending">
                                <span class="material-icons">schedule</span>
                            </div>
                            <span class="status-label">Pending</span>
                        </div>
                        <div class="status-value" id="pendingCount"><?= $pendingCount ?></div>
                    </div>

                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon processing">
                                <span class="material-icons">sync</span>
                            </div>
                            <span class="status-label">Processing</span>
                        </div>
                        <div class="status-value" id="processingCount"><?= $processingCount ?></div>
                    </div>

                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon completed">
                                <span class="material-icons">check_circle</span>
                            </div>
                            <span class="status-label">Completed</span>
                        </div>
                        <div class="status-value" id="completedCount"><?= $completedCount ?></div>
                    </div>

                    <div class="status-card">
                        <div class="status-card-header">
                            <div class="status-icon failed">
                                <span class="material-icons">error</span>
                            </div>
                            <span class="status-label">Failed</span>
                        </div>
                        <div class="status-value" id="failedCount"><?= $failedCount ?></div>
                    </div>
                </div>

                <!-- Upload Section -->
                <div class="upload-section">
                    <h2 class="section-title">
                        <span class="material-icons">upload_file</span>
                        Upload CSV File
                    </h2>

                    <div class="alert info">
                        <span class="material-icons">info</span>
                        <span>CSV must have columns: mail_id, receiver_name, Mail_Subject, Article_Title, Personalised_message, closing_wish, Name, Designation, Additional_information, Attachments</span>
                    </div>

                    <div class="upload-container">
                        <div class="file-drop-zone" id="fileDropZone">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <h3>Drop CSV file here or click to browse</h3>
                                <p>Maximum file size: 10MB</p>
                                <button type="button" class="browse-btn" onclick="document.getElementById('csvFileInput').click()">
                                    <span class="material-icons">folder_open</span>
                                    Browse Files
                                </button>
                            </div>
                            <input type="file" id="csvFileInput" accept=".csv" />
                        </div>

                        <div class="file-info" id="fileInfo">
                            <div class="file-info-grid">
                                <div class="file-info-item">
                                    <span class="file-info-label">File Name</span>
                                    <span class="file-info-value" id="fileName">-</span>
                                </div>
                                <div class="file-info-item">
                                    <span class="file-info-label">File Size</span>
                                    <span class="file-info-value" id="fileSize">-</span>
                                </div>
                                <div class="file-info-item">
                                    <span class="file-info-label">Total Recipients</span>
                                    <span class="file-info-value" id="recipientCount">-</span>
                                </div>
                            </div>
                            <button type="button" class="remove-file-btn" onclick="removeCSVFile()">
                                <i class="fas fa-trash"></i> Remove File
                            </button>
                        </div>
                    </div>

                    <!-- Attachment Section -->
                    <div class="attachment-section">
                        <h3 class="section-title">
                            <span class="material-icons">attach_file</span>
                            Common Attachment (Optional)
                        </h3>
                        <p style="font-size: 13px; color: var(--apple-gray); margin-bottom: 16px;">
                            Upload one file that will be attached to all emails. Individual attachments from CSV are not yet supported.
                        </p>

                        <div class="attachment-drop-zone" id="attachmentDropZone">
                            <div class="upload-icon" style="font-size: 36px;">
                                <i class="fas fa-paperclip"></i>
                            </div>
                            <div class="upload-text">
                                <h3 style="font-size: 14px;">Drop attachment here or click to browse</h3>
                                <p style="font-size: 12px;">Supported: PDF, DOC, DOCX, XLS, XLSX, images (Max 20MB)</p>
                                <button type="button" class="browse-btn" onclick="document.getElementById('attachmentInput').click()">
                                    <span class="material-icons">attach_file</span>
                                    Browse Files
                                </button>
                            </div>
                            <input type="file" id="attachmentInput" style="display: none;" />
                        </div>

                        <div class="attachment-list" id="attachmentList">
                            <!-- Attachment items will be added here -->
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" onclick="clearQueue()">
                            <span class="material-icons">delete_sweep</span>
                            Clear Queue
                        </button>
                        <button type="button" class="btn btn-primary" id="sendBtn" disabled onclick="startSending()">
                            <span class="material-icons">send</span>
                            Start Sending
                        </button>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="progress-section" id="progressSection">
                    <h2 class="section-title">
                        <span class="material-icons">trending_up</span>
                        Sending Progress
                    </h2>

                    <div class="progress-header">
                        <div class="progress-stats">
                            <div class="progress-stat">
                                <div class="progress-stat-value" id="sentCount">0</div>
                                <div class="progress-stat-label">Sent</div>
                            </div>
                            <div class="progress-stat">
                                <div class="progress-stat-value" id="remainingCount">0</div>
                                <div class="progress-stat-label">Remaining</div>
                            </div>
                            <div class="progress-stat">
                                <div class="progress-stat-value" id="progressPercent">0%</div>
                                <div class="progress-stat-label">Progress</div>
                            </div>
                        </div>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>

                    <div class="current-email-info" id="currentEmailInfo" style="display: none;">
                        <h4>Currently Sending</h4>
                        <p id="currentEmailText">-</p>
                    </div>

                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Email Log</h3>
                    <div class="email-log" id="emailLog">
                        <!-- Log items will be added here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let csvFile = null;
        let csvData = [];
        let attachmentId = null;
        let isSending = false;
        let sendingInterval = null;

        // File drop zone handling
        const fileDropZone = document.getElementById('fileDropZone');
        const csvFileInput = document.getElementById('csvFileInput');
        const fileInfo = document.getElementById('fileInfo');
        const sendBtn = document.getElementById('sendBtn');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop zone when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, () => {
                fileDropZone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileDropZone.addEventListener(eventName, () => {
                fileDropZone.classList.remove('drag-over');
            }, false);
        });

        // Handle dropped files
        fileDropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleCSVFile(files[0]);
            }
        });

        // Handle file selection
        csvFileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleCSVFile(e.target.files[0]);
            }
        });

        function handleCSVFile(file) {
            if (!file.name.endsWith('.csv')) {
                alert('Please upload a CSV file');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit');
                return;
            }

            csvFile = file;
            fileDropZone.classList.add('has-file');
            fileInfo.classList.add('show');
            
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatBytes(file.size);

            // Parse CSV to count recipients
            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                const lines = text.trim().split('\n');
                csvData = lines.slice(1).filter(line => line.trim()); // Skip header
                
                document.getElementById('recipientCount').textContent = csvData.length;
                sendBtn.disabled = csvData.length === 0;
            };
            reader.readAsText(file);
        }

        function removeCSVFile() {
            csvFile = null;
            csvData = [];
            csvFileInput.value = '';
            fileDropZone.classList.remove('has-file');
            fileInfo.classList.remove('show');
            sendBtn.disabled = true;
        }

        // Attachment handling
        const attachmentDropZone = document.getElementById('attachmentDropZone');
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentList = document.getElementById('attachmentList');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            attachmentDropZone.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            attachmentDropZone.addEventListener(eventName, () => {
                attachmentDropZone.style.borderColor = 'var(--apple-blue)';
                attachmentDropZone.style.background = 'rgba(0, 122, 255, 0.05)';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            attachmentDropZone.addEventListener(eventName, () => {
                attachmentDropZone.style.borderColor = 'var(--border)';
                attachmentDropZone.style.background = 'var(--apple-bg)';
            }, false);
        });

        attachmentDropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                uploadAttachment(files[0]);
            }
        });

        attachmentInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                uploadAttachment(e.target.files[0]);
            }
        });

        function uploadAttachment(file) {
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_handler.php', true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        attachmentId = response.id;
                        displayAttachment(response);
                    } else {
                        alert('Upload failed: ' + response.error);
                    }
                }
            };

            xhr.send(formData);
        }

        function displayAttachment(attachment) {
            attachmentDropZone.style.display = 'none';
            attachmentList.classList.add('show');
            
            const ext = attachment.extension.toUpperCase();
            const icon = getFileIcon(ext);
            
            attachmentList.innerHTML = `
                <div class="attachment-item">
                    <div class="attachment-info">
                        <div class="attachment-icon">
                            <i class="${icon}"></i>
                        </div>
                        <div class="attachment-details">
                            <h4>${attachment.original_name}</h4>
                            <p>${attachment.formatted_size} • ${ext}</p>
                        </div>
                    </div>
                    <button class="remove-attachment-btn" onclick="removeAttachment()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
        }

        function removeAttachment() {
            attachmentId = null;
            attachmentDropZone.style.display = 'block';
            attachmentList.classList.remove('show');
            attachmentList.innerHTML = '';
            attachmentInput.value = '';
        }

        function getFileIcon(extension) {
            const icons = {
                'PDF': 'fas fa-file-pdf',
                'DOC': 'fas fa-file-word',
                'DOCX': 'fas fa-file-word',
                'XLS': 'fas fa-file-excel',
                'XLSX': 'fas fa-file-excel',
                'PPT': 'fas fa-file-powerpoint',
                'PPTX': 'fas fa-file-powerpoint',
                'JPG': 'fas fa-file-image',
                'JPEG': 'fas fa-file-image',
                'PNG': 'fas fa-file-image',
                'GIF': 'fas fa-file-image',
                'ZIP': 'fas fa-file-archive',
                'RAR': 'fas fa-file-archive'
            };
            return icons[extension] || 'fas fa-file';
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Start sending process
        async function startSending() {
            if (!csvFile || csvData.length === 0) {
                alert('Please upload a CSV file first');
                return;
            }

            if (isSending) {
                alert('Already sending emails');
                return;
            }

            if (!confirm(`Start sending ${csvData.length} emails?`)) {
                return;
            }

            // Upload CSV and populate queue
            const formData = new FormData();
            formData.append('csv_file', csvFile);
            if (attachmentId) {
                formData.append('attachment_id', attachmentId);
            }

            try {
                const response = await fetch('process_bulk_mail.php?action=upload', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('progressSection').classList.add('show');
                    isSending = true;
                    sendBtn.disabled = true;
                    
                    // Start processing
                    processQueue();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to start sending');
            }
        }

        // Process queue
        async function processQueue() {
            if (!isSending) return;

            try {
                const response = await fetch('process_bulk_mail.php?action=process', {
                    method: 'POST'
                });

                const result = await response.json();

                if (result.success) {
                    updateProgress(result);

                    if (result.has_more) {
                        // Continue processing after a short delay
                        setTimeout(processQueue, 1000);
                    } else {
                        // All done
                        finishSending();
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                addLog('System Error', 'Failed to process queue', 'error');
            }
        }

        function updateProgress(data) {
            // Update counts
            document.getElementById('pendingCount').textContent = data.pending || 0;
            document.getElementById('processingCount').textContent = data.processing || 0;
            document.getElementById('completedCount').textContent = data.completed || 0;
            document.getElementById('failedCount').textContent = data.failed || 0;

            // Update progress stats
            const total = data.total || 1;
            const sent = data.completed || 0;
            const remaining = total - sent - (data.failed || 0);
            const percent = Math.round((sent / total) * 100);

            document.getElementById('sentCount').textContent = sent;
            document.getElementById('remainingCount').textContent = remaining;
            document.getElementById('progressPercent').textContent = percent + '%';
            document.getElementById('progressBar').style.width = percent + '%';

            // Update current email
            if (data.current_email) {
                document.getElementById('currentEmailInfo').style.display = 'block';
                document.getElementById('currentEmailText').textContent = 
                    `Sending to: ${data.current_email.recipient} - ${data.current_email.subject}`;
            }

            // Add to log
            if (data.last_result) {
                const result = data.last_result;
                addLog(
                    result.recipient,
                    result.status === 'completed' ? 'Sent successfully' : result.error_message,
                    result.status === 'completed' ? 'success' : 'error'
                );
            }
        }

        function addLog(email, message, status) {
            const emailLog = document.getElementById('emailLog');
            const logItem = document.createElement('div');
            logItem.className = 'log-item';
            
            const now = new Date();
            const timeStr = now.toLocaleTimeString();
            
            logItem.innerHTML = `
                <div class="log-status ${status}"></div>
                <div class="log-content">
                    <div class="log-email">${email}</div>
                    <div class="log-time">${timeStr}</div>
                    ${status === 'error' ? `<div class="log-message">${message}</div>` : ''}
                </div>
            `;
            
            emailLog.insertBefore(logItem, emailLog.firstChild);
        }

        function finishSending() {
            isSending = false;
            document.getElementById('currentEmailInfo').style.display = 'none';
            alert('All emails have been processed!');
            
            // Reload counts
            location.reload();
        }

        async function clearQueue() {
            if (!confirm('Clear all pending emails from the queue?')) {
                return;
            }

            try {
                const response = await fetch('process_bulk_mail.php?action=clear', {
                    method: 'POST'
                });

                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error clearing queue');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to clear queue');
            }
        }

        // Auto-refresh status every 5 seconds if not sending
        setInterval(() => {
            if (!isSending) {
                fetch('process_bulk_mail.php?action=status')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('pendingCount').textContent = data.pending || 0;
                            document.getElementById('processingCount').textContent = data.processing || 0;
                            document.getElementById('completedCount').textContent = data.completed || 0;
                            document.getElementById('failedCount').textContent = data.failed || 0;
                        }
                    });
            }
        }, 5000);
    </script>
</body>
</html>