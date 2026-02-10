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

        .file-drop-zone i {
            font-size: 48px;
            color: var(--apple-gray);
            margin-bottom: 16px;
        }

        .file-drop-zone h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .file-drop-zone p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        .file-input {
            display: none;
        }

        /* CSV Preview Section */
        .preview-section {
            display: none;
            margin-top: 30px;
        }

        .preview-section.show {
            display: block;
        }

        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .preview-info {
            font-size: 14px;
            color: var(--apple-gray);
        }

        .preview-table-container {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            max-height: 400px;
            overflow-y: auto;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
        }

        .preview-table th {
            background: var(--apple-bg);
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .preview-table td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }

        .preview-table tbody tr:hover {
            background: rgba(0, 122, 255, 0.05);
        }

        .row-number {
            color: var(--apple-gray);
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: var(--apple-gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #6e6e73;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Attachment Section */
        .attachment-section {
            margin-top: 30px;
        }

        .attachment-drop-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: var(--apple-bg);
            cursor: pointer;
            transition: all 0.3s;
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
            padding: 16px;
            background: var(--apple-bg);
            border-radius: 10px;
            margin-top: 10px;
        }

        .attachment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--apple-blue);
        }

        .attachment-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .attachment-details p {
            font-size: 12px;
            color: var(--apple-gray);
        }

        .remove-attachment-btn {
            padding: 8px 16px;
            background: var(--error-red);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        /* Progress Section */
        .progress-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: none;
        }

        .progress-section.show {
            display: block;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .progress-stat {
            text-align: center;
        }

        .progress-stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
        }

        .progress-stat-label {
            font-size: 13px;
            color: var(--apple-gray);
            margin-top: 4px;
        }

        .progress-bar-container {
            background: var(--apple-bg);
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--apple-blue), var(--success-green));
            width: 0%;
            transition: width 0.3s;
        }

        .progress-percent {
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--apple-gray);
        }

        .current-email-info {
            background: var(--apple-bg);
            padding: 16px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }

        .current-email-info.show {
            display: block;
        }

        .current-email-text {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* Email Log */
        .email-log-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
        }

        .email-log {
            max-height: 400px;
            overflow-y: auto;
        }

        .log-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-top: 4px;
        }

        .log-status.success {
            background: var(--success-green);
        }

        .log-status.error {
            background: var(--error-red);
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
            margin-top: 4px;
        }

        .log-message {
            font-size: 13px;
            color: var(--error-red);
            margin-top: 4px;
        }

        /* Queue List Section */
        .queue-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .queue-table-container {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-top: 20px;
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
        }

        .queue-table th {
            background: var(--apple-bg);
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .queue-table td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }

        .queue-table tbody tr:hover {
            background: rgba(0, 122, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning-orange);
        }

        .status-badge.processing {
            background: rgba(0, 122, 255, 0.1);
            color: var(--apple-blue);
        }

        .status-badge.completed {
            background: rgba(52, 199, 89, 0.1);
            color: var(--success-green);
        }

        .status-badge.failed {
            background: rgba(255, 59, 48, 0.1);
            color: var(--error-red);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Bulk Mailer</h1>
                    <p>Send emails to multiple recipients from CSV</p>
                </div>
            </div>
        </div>

        <div class="content-area">
            <!-- Status Cards -->
            <div class="status-grid">
                <div class="status-card">
                    <div class="status-card-header">
                        <div class="status-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="status-label">Pending</div>
                    </div>
                    <div class="status-value" id="pendingCount"><?= $pendingCount ?></div>
                </div>

                <div class="status-card">
                    <div class="status-card-header">
                        <div class="status-icon processing">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="status-label">Processing</div>
                    </div>
                    <div class="status-value" id="processingCount"><?= $processingCount ?></div>
                </div>

                <div class="status-card">
                    <div class="status-card-header">
                        <div class="status-icon completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="status-label">Completed</div>
                    </div>
                    <div class="status-value" id="completedCount"><?= $completedCount ?></div>
                </div>

                <div class="status-card">
                    <div class="status-card-header">
                        <div class="status-icon failed">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="status-label">Failed</div>
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

                <div class="upload-container">
                    <div class="file-drop-zone" id="csvDropZone" onclick="document.getElementById('csvFileInput').click()">
                        <i class="fas fa-file-csv"></i>
                        <h3>Drop CSV file here or click to browse</h3>
                        <p>CSV must contain required columns: mail_id, receiver_name, Mail_Subject, Article_Title, etc.</p>
                    </div>
                    <input type="file" id="csvFileInput" class="file-input" accept=".csv" onchange="handleCSVSelect(this.files[0])">
                </div>

                <!-- CSV Preview -->
                <div class="preview-section" id="previewSection">
                    <div class="preview-header">
                        <h3 class="section-title">CSV Preview</h3>
                        <div class="preview-info" id="previewInfo"></div>
                    </div>
                    
                    <div class="preview-table-container">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Subject</th>
                                    <th>Article Title</th>
                                    <th>Message Preview</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody"></tbody>
                        </table>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" id="confirmUploadBtn" onclick="confirmAndUpload()">
                            <i class="fas fa-check"></i> Confirm & Upload
                        </button>
                        <button class="btn btn-secondary" onclick="cancelPreview()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>

                <!-- Attachment Section -->
                <div class="attachment-section">
                    <h3 class="section-title">
                        <span class="material-icons">attach_file</span>
                        Common Attachment (Optional)
                    </h3>
                    
                    <div class="attachment-drop-zone" id="attachmentDropZone" onclick="document.getElementById('attachmentInput').click()">
                        <i class="fas fa-paperclip"></i>
                        <p>Click to add a common attachment for all emails</p>
                    </div>
                    <input type="file" id="attachmentInput" class="file-input" onchange="handleAttachmentSelect(this.files[0])">
                    
                    <div class="attachment-list" id="attachmentList"></div>
                </div>

                <div class="action-buttons" id="uploadActions" style="display: none;">
                    <button class="btn btn-primary" id="sendBtn" onclick="startSending()">
                        <i class="fas fa-paper-plane"></i> Start Sending
                    </button>
                    <button class="btn btn-secondary" onclick="clearQueue()">
                        <i class="fas fa-trash"></i> Clear Queue
                    </button>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="progress-section" id="progressSection">
                <h2 class="section-title">
                    <span class="material-icons">trending_up</span>
                    Sending Progress
                </h2>

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

                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>

                <div class="current-email-info" id="currentEmailInfo">
                    <div class="current-email-text" id="currentEmailText"></div>
                </div>
            </div>

            <!-- Queue List -->
            <div class="queue-section">
                <h2 class="section-title">
                    <span class="material-icons">list</span>
                    Email Queue
                </h2>

                <div class="queue-table-container">
                    <table class="queue-table">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="queueTableBody">
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--apple-gray);">No emails in queue</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Email Log -->
            <div class="email-log-section">
                <h2 class="section-title">
                    <span class="material-icons">history</span>
                    Activity Log
                </h2>
                <div class="email-log" id="emailLog">
                    <div class="log-item">
                        <div class="log-status" style="background: var(--apple-gray);"></div>
                        <div class="log-content">
                            <div class="log-email">No activity yet</div>
                            <div class="log-time">Upload a CSV file to begin</div>
                        </div>
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

        const csvDropZone = document.getElementById('csvDropZone');
        const csvFileInput = document.getElementById('csvFileInput');
        const previewSection = document.getElementById('previewSection');
        const previewTableBody = document.getElementById('previewTableBody');
        const previewInfo = document.getElementById('previewInfo');
        const attachmentDropZone = document.getElementById('attachmentDropZone');
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentList = document.getElementById('attachmentList');
        const sendBtn = document.getElementById('sendBtn');
        const uploadActions = document.getElementById('uploadActions');

        // CSV drag and drop
        csvDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            csvDropZone.classList.add('drag-over');
        });

        csvDropZone.addEventListener('dragleave', () => {
            csvDropZone.classList.remove('drag-over');
        });

        csvDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            csvDropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file && file.name.endsWith('.csv')) {
                handleCSVSelect(file);
            } else {
                alert('Please drop a CSV file');
            }
        });

        // Handle CSV file selection - Preview first
        async function handleCSVSelect(file) {
            if (!file) return;

            csvFile = file;
            csvData = [];

            // Show loading
            previewTableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;">Loading preview...</td></tr>';
            previewSection.classList.add('show');

            // Send to server for preview
            const formData = new FormData();
            formData.append('csv_file', file);

            try {
                const response = await fetch('process_bulk_mail.php?action=preview', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    csvData = result.preview_rows;
                    displayPreview(result);
                } else {
                    alert('Error: ' + result.error);
                    previewSection.classList.remove('show');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to preview CSV file');
                previewSection.classList.remove('show');
            }
        }

        function displayPreview(result) {
            previewInfo.textContent = result.message;
            
            previewTableBody.innerHTML = '';
            
            result.preview_rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="row-number">${row.row_number}</td>
                    <td>${row.mail_id}</td>
                    <td>${row.receiver_name}</td>
                    <td>${row.subject}</td>
                    <td>${row.article_title}</td>
                    <td>${row.message_preview}</td>
                `;
                previewTableBody.appendChild(tr);
            });
        }

        function cancelPreview() {
            csvFile = null;
            csvData = [];
            csvFileInput.value = '';
            previewSection.classList.remove('show');
        }

        // Confirm and upload CSV to queue
        async function confirmAndUpload() {
            if (!csvFile) {
                alert('No CSV file selected');
                return;
            }

            document.getElementById('confirmUploadBtn').disabled = true;
            document.getElementById('confirmUploadBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

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
                    alert(`Successfully uploaded! ${result.queued_count} emails added to queue.`);
                    previewSection.classList.remove('show');
                    uploadActions.style.display = 'flex';
                    
                    // Refresh queue list and status
                    loadQueueList();
                    refreshStatus();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to upload CSV');
            } finally {
                document.getElementById('confirmUploadBtn').disabled = false;
                document.getElementById('confirmUploadBtn').innerHTML = '<i class="fas fa-check"></i> Confirm & Upload';
            }
        }

        // Attachment handling
        async function handleAttachmentSelect(file) {
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            attachmentDropZone.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Uploading...</p>';

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
                        attachmentDropZone.innerHTML = '<i class="fas fa-paperclip"></i><p>Click to add a common attachment for all emails</p>';
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

        // Start sending process
        async function startSending() {
            if (isSending) {
                alert('Already sending emails');
                return;
            }

            const status = await getStatus();
            if (status.pending === 0) {
                alert('No pending emails in queue');
                return;
            }

            if (!confirm(`Start sending ${status.pending} emails?`)) {
                return;
            }

            document.getElementById('progressSection').classList.add('show');
            isSending = true;
            sendBtn.disabled = true;
            
            // Start processing
            processQueue();
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
                document.getElementById('currentEmailInfo').classList.add('show');
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
            
            // Refresh queue list
            loadQueueList();
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
            document.getElementById('currentEmailInfo').classList.remove('show');
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

        // Load queue list
        async function loadQueueList() {
            try {
                const response = await fetch('process_bulk_mail.php?action=queue_list');
                const result = await response.json();
                
                if (result.success) {
                    const tbody = document.getElementById('queueTableBody');
                    
                    if (result.queue.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--apple-gray);">No emails in queue</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    result.queue.forEach(item => {
                        const tr = document.createElement('tr');
                        const createdDate = new Date(item.created_at).toLocaleString();
                        
                        tr.innerHTML = `
                            <td>${item.recipient_email}</td>
                            <td>${item.recipient_name || '-'}</td>
                            <td>${item.subject}</td>
                            <td><span class="status-badge ${item.status}">${item.status}</span></td>
                            <td>${createdDate}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (error) {
                console.error('Error loading queue:', error);
            }
        }

        // Get status
        async function getStatus() {
            try {
                const response = await fetch('process_bulk_mail.php?action=status');
                const result = await response.json();
                return result;
            } catch (error) {
                console.error('Error getting status:', error);
                return { pending: 0, processing: 0, completed: 0, failed: 0, total: 0 };
            }
        }

        // Refresh status
        async function refreshStatus() {
            const status = await getStatus();
            if (status.success) {
                document.getElementById('pendingCount').textContent = status.pending || 0;
                document.getElementById('processingCount').textContent = status.processing || 0;
                document.getElementById('completedCount').textContent = status.completed || 0;
                document.getElementById('failedCount').textContent = status.failed || 0;
            }
        }

        // Load queue list on page load
        loadQueueList();

        // Auto-refresh status and queue every 5 seconds if not sending
        setInterval(() => {
            if (!isSending) {
                refreshStatus();
                loadQueueList();
            }
        }, 5000);
    </script>
</body>
</html>