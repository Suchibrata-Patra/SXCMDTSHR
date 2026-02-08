<?php
// htdocs/index.php
session_start();

// Security check: Redirect to login if session credentials do not exist
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

// Clear temp attachments on page load (fresh start)
$_SESSION['temp_attachments'] = [];

// Load settings from JSON file
$settingsFile = 'settings.json';
$settings = [];

if (file_exists($settingsFile)) {
    $jsonContent = file_get_contents($settingsFile);
    $allSettings = json_decode($jsonContent, true) ?? [];
    // Get settings for current user
    $settings = $allSettings[$_SESSION['smtp_user']] ?? [];
}

// Set defaults if not found
$settings = array_merge([
    'signature' => '',
    'default_subject_prefix' => '',
    'cc_yourself' => false,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'display_name' => ''
], $settings);

// Update session settings for immediate use
$_SESSION['user_settings'] = $settings;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email — SXC MDTS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Quill Rich Text Editor CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
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
            -webkit-font-smoothing: antialiased;
        }

        /* ========== MAIN LAYOUT ========== */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: var(--apple-bg);
        }

        /* ========== HEADER ========== */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 24px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1c1c1e;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 15px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        /* ========== COMPOSE CONTAINER ========== */
        .compose-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px;
        }

        /* ========== FORM SECTIONS ========== */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 17px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 20px;
            letter-spacing: -0.3px;
        }

        /* ========== FORM GROUPS ========== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1c1c1e;
            font-size: 14px;
        }

        .label-optional {
            font-size: 13px;
            color: var(--apple-gray);
            font-weight: 400;
            margin-left: 4px;
        }

        .field-description {
            font-size: 13px;
            color: var(--apple-gray);
            margin-top: 6px;
        }

        /* ========== INPUT FIELDS ========== */
        input[type="email"],
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            background: white;
            color: #1c1c1e;
            transition: all 0.2s;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input[type="email"]:focus,
        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        input::placeholder,
        textarea::placeholder {
            color: var(--apple-gray);
        }

        /* Two-column grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ========== FILE UPLOAD AREA ========== */
        .file-upload-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background: #FAFAFA;
            transition: all 0.2s;
            cursor: pointer;
        }

        .file-upload-area:hover,
        .file-upload-area.drag-over {
            border-color: var(--apple-blue);
            background: #F5F9FF;
        }

        .file-upload-icon {
            font-size: 48px;
            color: var(--apple-blue);
            margin-bottom: 12px;
        }

        .file-upload-text {
            font-size: 15px;
            color: #1c1c1e;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .file-upload-hint {
            font-size: 13px;
            color: var(--apple-gray);
        }

        input[type="file"] {
            display: none;
        }

        /* ========== UPLOAD PROGRESS (Individual file) ========== */
        .upload-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 12px;
            display: none;
        }

        .upload-item.active {
            display: block;
        }

        .upload-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .upload-filename {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .upload-percentage {
            font-size: 13px;
            color: var(--apple-gray);
        }

        .upload-progress-bar-container {
            height: 4px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: var(--apple-blue);
            transition: width 0.3s;
            width: 0%;
        }

        /* ========== FILE PREVIEW CARDS ========== */
        .file-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .file-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            position: relative;
            transition: all 0.2s;
            cursor: pointer;
            text-align: center;
        }

        .file-card:hover {
            box-shadow: var(--card-shadow);
            transform: translateY(-2px);
        }

        .file-card-icon {
            font-size: 48px;
            color: var(--apple-blue);
            margin-bottom: 12px;
        }

        .file-card-name {
            font-size: 13px;
            color: #1c1c1e;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
        }

        .file-card-size {
            font-size: 12px;
            color: var(--apple-gray);
            margin-bottom: 8px;
        }

        .file-card-badge {
            display: inline-block;
            font-size: 11px;
            padding: 4px 8px;
            background: #E8F5E9;
            color: var(--success-green);
            border-radius: 4px;
            font-weight: 500;
        }

        .file-card-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            background: rgba(255, 59, 48, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .file-card:hover .file-card-remove {
            opacity: 1;
        }

        .file-card-remove .material-icons {
            font-size: 16px;
            color: white;
        }

        .file-card-download {
            margin-top: 8px;
            font-size: 12px;
            color: var(--apple-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .file-card-download .material-icons {
            font-size: 16px;
        }

        .size-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
            font-size: 13px;
            color: #856404;
            display: none;
        }

        .size-warning.active {
            display: block;
        }

        /* ========== RICH TEXT EDITOR ========== */
        .editor-wrapper {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .ql-toolbar {
            border: none;
            border-bottom: 1px solid var(--border);
            background: #FAFAFA;
        }

        .ql-container {
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            min-height: 200px;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
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

        .btn-primary:hover {
            background: #0066CC;
        }

        .btn-secondary {
            background: #F2F2F7;
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #E5E5EA;
        }

        .btn .material-icons {
            font-size: 18px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .compose-container {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .file-cards-container {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content-area">
            <!-- Header -->
            <div class="page-header">
                <div class="header-container">
                    <h1 class="page-title">Compose New Email</h1>
                    <p class="page-subtitle">Send professional emails with templates and signatures</p>
                </div>
            </div>

            <!-- Compose Form -->
            <div class="compose-container">
                <form id="composeForm" method="POST" action="send.php" enctype="multipart/form-data">
                    
                    <!-- Recipients Section -->
                    <div class="form-section">
                        <h3 class="section-title">Recipients</h3>
                        
                        <div class="form-group">
                            <label for="email">
                                To
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="email" name="email" id="email" 
                                   placeholder="recipient@example.com" required>
                            <p class="field-description">Primary recipient email address</p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cc">
                                    CC
                                    <span class="label-optional">(optional)</span>
                                </label>
                                <input type="text" name="cc" id="cc" 
                                       placeholder="cc1@example.com, cc2@example.com">
                            </div>

                            <div class="form-group">
                                <label for="bcc">
                                    BCC
                                    <span class="label-optional">(optional)</span>
                                </label>
                                <input type="text" name="bcc" id="bcc" 
                                       placeholder="bcc@example.com">
                            </div>
                        </div>
                    </div>

                    <!-- Email Details Section -->
                    <div class="form-section">
                        <h3 class="section-title">Email Details</h3>
                        
                        <div class="form-group">
                            <label for="subject">
                                Subject
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" name="subject" id="subject" 
                                   placeholder="Enter email subject" required>
                        </div>

                        <div class="form-group">
                            <label for="articletitle">
                                Article Title
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" name="articletitle" id="articletitle" 
                                   placeholder="Article or document title" required>
                        </div>
                    </div>

                    <!-- Message Section -->
                    <div class="form-section">
                        <h3 class="section-title">Message</h3>
                        
                        <div class="form-group">
                            <label>Email Body</label>
                            <div class="editor-wrapper">
                                <div id="quillMessage"></div>
                            </div>
                            <input type="hidden" name="message" id="messageInput">
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <div class="form-section">
                        <h3 class="section-title">Email Signature</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="signatureWish">Closing Wish</label>
                                <input type="text" id="signatureWish" name="signatureWish"
                                    placeholder="e.g., Best Regards, Warm Wishes">
                                <p class="field-description">Your closing greeting</p>
                            </div>

                            <div class="form-group">
                                <label for="signatureName">Name</label>
                                <input type="text" id="signatureName" name="signatureName"
                                    placeholder="e.g., Durba Bhattacharya">
                                <p class="field-description">Your full name</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="signatureDesignation">Designation</label>
                            <input type="text" id="signatureDesignation" name="signatureDesignation"
                                placeholder="e.g., H.O.D of SXC MDTS">
                            <p class="field-description">Your title or position</p>
                        </div>

                        <div class="form-group">
                            <label for="signatureExtra">Additional Information <span class="label-optional">(optional)</span></label>
                            <textarea id="signatureExtra" name="signatureExtra" rows="3"
                                placeholder="Any extra details like contact info, department, etc."></textarea>
                            <p class="field-description">Additional text that will appear in your signature</p>
                        </div>
                    </div>

                    <!-- Attachments Section -->
                    <!-- File Upload Section - Add this to your index.php form -->

<!-- Add after the message editor section and before the submit button -->

<!-- Attachments Section -->
<div class="form-section">
    <label class="form-label">
        <i class="material-icons">attach_file</i>
        Attachments
        <span class="optional-tag">Optional</span>
    </label>

    <!-- Drop Zone -->
    <div class="drop-zone" id="dropZone">
        <div class="drop-zone-icon">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="drop-zone-text">
            <strong>Click to upload</strong> or drag and drop
        </div>
        <div class="drop-zone-hint">
            PDF, DOC, XLS, PPT, Images, ZIP (Max 20MB per file, 25MB total)
        </div>
    </div>

    <!-- Hidden file input -->
    <input type="file" id="fileUpload" style="display: none;" multiple>

    <!-- Hidden input for attachment IDs -->
    <input type="hidden" name="attachment_ids" id="attachmentIds" value="">

    <!-- Attachment Cards Container -->
    <div id="attachmentCards" class="attachment-cards" style="display: none;"></div>
</div>

<!-- CSS Styles - Add this to the <style> section -->
<style>
    /* Drop Zone Styles */
    .drop-zone {
        border: 2px dashed var(--border);
        border-radius: 12px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
        margin-bottom: 20px;
    }

    .drop-zone:hover {
        border-color: var(--apple-blue);
        background: rgba(0, 122, 255, 0.02);
    }

    .drop-zone.drag-over {
        border-color: var(--apple-blue);
        background: rgba(0, 122, 255, 0.05);
        transform: scale(1.02);
    }

    .drop-zone-icon {
        font-size: 48px;
        color: var(--apple-blue);
        margin-bottom: 15px;
    }

    .drop-zone-text {
        font-size: 16px;
        color: #1c1c1e;
        margin-bottom: 8px;
    }

    .drop-zone-text strong {
        color: var(--apple-blue);
    }

    .drop-zone-hint {
        font-size: 13px;
        color: var(--apple-gray);
    }

    /* Attachment Cards */
    .attachment-cards {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 15px;
    }

    .attachment-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        transition: all 0.2s ease;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .attachment-card:hover {
        border-color: var(--apple-blue);
        box-shadow: 0 2px 12px rgba(0, 122, 255, 0.1);
    }

    .attachment-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        flex-shrink: 0;
    }

    .attachment-icon.uploading {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        animation: pulse 1.5s ease-in-out infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .attachment-info {
        flex: 1;
        min-width: 0;
    }

    .attachment-name {
        font-size: 14px;
        font-weight: 500;
        color: #1c1c1e;
        margin-bottom: 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .attachment-size {
        font-size: 12px;
        color: var(--apple-gray);
    }

    .attachment-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .btn-download,
    .btn-remove {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        background: var(--apple-bg);
        color: var(--apple-gray);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-download:hover {
        background: rgba(0, 122, 255, 0.1);
        color: var(--apple-blue);
    }

    .btn-remove:hover {
        background: rgba(255, 59, 48, 0.1);
        color: #ff3b30;
    }

    /* Notifications */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s ease;
        z-index: 10000;
    }

    .notification.show {
        opacity: 1;
        transform: translateX(0);
    }

    .notification-success {
        border-left: 4px solid var(--success-green);
    }

    .notification-success i {
        color: var(--success-green);
        font-size: 20px;
    }

    .notification-error {
        border-left: 4px solid #ff3b30;
    }

    .notification-error i {
        color: #ff3b30;
        font-size: 20px;
    }

    .notification span {
        font-size: 14px;
        color: #1c1c1e;
        flex: 1;
    }

    /* Optional tag in form labels */
    .optional-tag {
        font-size: 11px;
        color: var(--apple-gray);
        font-weight: 400;
        margin-left: 8px;
    }
</style>

<!-- JavaScript - Add before closing </body> tag -->
<script src="attachment-upload.js"></script>

<!-- Or include inline if preferred -->
<script>
    // attachment-upload.js - File Upload Handler with Card UI

    class AttachmentUploader {
        constructor() {
            this.uploadedFiles = [];
            this.maxFileSize = 20 * 1024 * 1024; // 20MB
            this.maxTotalSize = 25 * 1024 * 1024; // 25MB
            this.allowedExtensions = [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'zip', 'rar', '7z',
                'txt', 'csv', 'json', 'xml'
            ];

            this.init();
        }

        init() {
            const fileInput = document.getElementById('fileUpload');
            const dropZone = document.getElementById('dropZone');
            const attachmentContainer = document.getElementById('attachmentCards');

            // File input change handler
            fileInput.addEventListener('change', (e) => {
                this.handleFiles(e.target.files);
                e.target.value = ''; // Reset input
            });

            // Drag and drop handlers
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('drag-over');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                this.handleFiles(e.dataTransfer.files);
            });

            // Click to upload
            dropZone.addEventListener('click', () => {
                fileInput.click();
            });
        }

        handleFiles(files) {
            Array.from(files).forEach(file => {
                // Validate file extension
                const extension = file.name.split('.').pop().toLowerCase();
                if (!this.allowedExtensions.includes(extension)) {
                    this.showError(`File type .${extension} is not allowed`);
                    return;
                }

                // Validate file size
                if (file.size > this.maxFileSize) {
                    this.showError(`File ${file.name} exceeds 20MB limit`);
                    return;
                }

                // Check total size
                const currentTotalSize = this.uploadedFiles.reduce((sum, f) => sum + f.file_size, 0);
                if (currentTotalSize + file.size > this.maxTotalSize) {
                    this.showError('Total upload size exceeds 25MB limit');
                    return;
                }

                // Upload file
                this.uploadFile(file);
            });
        }

        uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            // Create temporary card with progress
            const tempId = 'temp-' + Date.now();
            this.createFileCard({
                id: tempId,
                original_name: file.name,
                formatted_size: this.formatBytes(file.size),
                extension: file.name.split('.').pop().toLowerCase(),
                uploading: true
            });

            // Upload via AJAX
            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    // Remove temporary card
                    this.removeCard(tempId);

                    if (data.success) {
                        // Add to uploaded files
                        this.uploadedFiles.push(data);

                        // Create permanent card
                        this.createFileCard(data);

                        // Update hidden input with attachment IDs
                        this.updateAttachmentIds();

                        this.showSuccess(`${file.name} uploaded successfully`);
                    } else {
                        this.showError(data.error || 'Upload failed');
                    }
                })
                .catch(error => {
                    this.removeCard(tempId);
                    this.showError('Upload error: ' + error.message);
                });
        }

        createFileCard(fileData) {
            const container = document.getElementById('attachmentCards');

            const card = document.createElement('div');
            card.className = 'attachment-card';
            card.setAttribute('data-file-id', fileData.id);

            if (fileData.uploading) {
                card.innerHTML = `
                <div class="attachment-icon uploading">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <div class="attachment-info">
                    <div class="attachment-name">${this.escapeHtml(fileData.original_name)}</div>
                    <div class="attachment-size">Uploading...</div>
                </div>
            `;
            } else {
                const icon = this.getFileIcon(fileData.extension);
                const downloadUrl = `download.php?id=${encodeURIComponent(fileData.encrypted_id)}`;

                card.innerHTML = `
                <div class="attachment-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="attachment-info">
                    <div class="attachment-name">${this.escapeHtml(fileData.original_name)}</div>
                    <div class="attachment-size">${fileData.formatted_size}</div>
                </div>
                <div class="attachment-actions">
                    <a href="${downloadUrl}" class="btn-download" title="Download" target="_blank">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="btn-remove" onclick="attachmentUploader.removeFile('${fileData.encrypted_id}')" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            }

            container.appendChild(card);
            container.style.display = 'flex';
        }

        removeFile(encryptedId) {
            // Find and remove from array
            this.uploadedFiles = this.uploadedFiles.filter(f => f.encrypted_id !== encryptedId);

            // Remove card from DOM
            const cards = document.querySelectorAll('.attachment-card');
            cards.forEach(card => {
                const cardId = card.getAttribute('data-file-id');
                const fileData = this.uploadedFiles.find(f => f.id == cardId) ||
                    this.uploadedFiles.find(f => f.encrypted_id === cardId);
                if (fileData && fileData.encrypted_id === encryptedId) {
                    card.remove();
                }
            });

            // Update hidden input
            this.updateAttachmentIds();

            // Hide container if empty
            const container = document.getElementById('attachmentCards');
            if (this.uploadedFiles.length === 0) {
                container.style.display = 'none';
            }

            this.showSuccess('File removed');
        }

        removeCard(cardId) {
            const card = document.querySelector(`[data-file-id="${cardId}"]`);
            if (card) {
                card.remove();
            }
        }

        updateAttachmentIds() {
            const input = document.getElementById('attachmentIds');
            const ids = this.uploadedFiles.map(f => f.encrypted_id).join(',');
            input.value = ids;

            console.log('Updated attachment IDs:', ids);
        }

        getFileIcon(extension) {
            const icons = {
                'pdf': 'fa-file-pdf',
                'doc': 'fa-file-word',
                'docx': 'fa-file-word',
                'xls': 'fa-file-excel',
                'xlsx': 'fa-file-excel',
                'ppt': 'fa-file-powerpoint',
                'pptx': 'fa-file-powerpoint',
                'jpg': 'fa-file-image',
                'jpeg': 'fa-file-image',
                'png': 'fa-file-image',
                'gif': 'fa-file-image',
                'webp': 'fa-file-image',
                'zip': 'fa-file-archive',
                'rar': 'fa-file-archive',
                '7z': 'fa-file-archive',
                'txt': 'fa-file-alt',
                'csv': 'fa-file-csv',
                'json': 'fa-file-code',
                'xml': 'fa-file-code'
            };

            return icons[extension] || 'fa-file';
        }

        formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        showError(message) {
            this.showNotification(message, 'error');
        }

        showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${this.escapeHtml(message)}</span>
        `;

            document.body.appendChild(notification);

            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    }

    // Initialize when DOM is ready
    let attachmentUploader;
    document.addEventListener('DOMContentLoaded', () => {
        attachmentUploader = new AttachmentUploader();
    });</script>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" id="previewBtn">
                            <span class="material-icons">preview</span>
                            Preview Email
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons">send</span>
                            Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quill Rich Text Editor JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

    <script>
        // Initialize Quill editor
        const quillMessage = new Quill('#quillMessage', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'color': [] }, { 'background': [] }],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Type your message here...'
        });

        // ========== FILE UPLOAD FUNCTIONALITY ==========
        const fileInput = document.getElementById('fileInput');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileCardsContainer = document.getElementById('fileCardsContainer');
        const uploadItem = document.getElementById('uploadItem');
        const uploadFilename = document.getElementById('uploadFilename');
        const uploadPercentage = document.getElementById('uploadPercentage');
        const uploadProgressBar = document.getElementById('uploadProgressBar');
        const sizeWarning = document.getElementById('sizeWarning');
        const sizeWarningText = document.getElementById('sizeWarningText');
        const attachmentIdsInput = document.getElementById('attachmentIds');

        let uploadedFiles = [];
        let totalSize = 0;
        const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
        const MAX_TOTAL_SIZE = 25 * 1024 * 1024; // 25MB

        // Click to upload
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            handleFiles(files);
        });

        // File selection
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Handle file uploads
        async function handleFiles(files) {
            for (let file of files) {
                // Check individual file size
                if (file.size > MAX_FILE_SIZE) {
                    alert(`File "${file.name}" exceeds 20MB limit`);
                    continue;
                }

                // Check total size
                if (totalSize + file.size > MAX_TOTAL_SIZE) {
                    showSizeWarning(totalSize, file.size);
                    break;
                }

                await uploadFile(file);
            }
            
            // Clear file input
            fileInput.value = '';
        }

        // Upload single file with progress
        async function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            // Show progress
            uploadItem.classList.add('active');
            uploadFilename.textContent = file.name;
            uploadPercentage.textContent = '0%';
            uploadProgressBar.style.width = '0%';

            try {
                const xhr = new XMLHttpRequest();

                // Progress tracking
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        uploadProgressBar.style.width = percentComplete + '%';
                        uploadPercentage.textContent = percentComplete + '%';
                    }
                });

                // Upload complete
                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            uploadedFiles.push(response);
                            totalSize += response.file_size;
                            addFileCard(response);
                            updateAttachmentIds();
                            
                            // Hide progress after 500ms
                            setTimeout(() => {
                                uploadItem.classList.remove('active');
                            }, 500);
                        } else {
                            alert('Upload failed: ' + response.error);
                            uploadItem.classList.remove('active');
                        }
                    }
                });

                xhr.open('POST', 'upload_handler.php');
                xhr.send(formData);

            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed: ' + error.message);
                uploadItem.classList.remove('active');
            }
        }

        // Add file card
        function addFileCard(fileData) {
            const card = document.createElement('div');
            card.className = 'file-card';
            card.dataset.fileId = fileData.id;
            card.dataset.encryptedId = fileData.encrypted_id;

            const icon = getFileIcon(fileData.extension);
            
            card.innerHTML = `
                <div class="material-icons file-card-icon">${icon}</div>
                <div class="file-card-name" title="${fileData.original_name}">${fileData.original_name}</div>
                <div class="file-card-size">${fileData.formatted_size}</div>
                ${fileData.deduplicated ? '<div class="file-card-badge">✓ Reused</div>' : ''}
                <div class="file-card-download">
                    <span class="material-icons">download</span>
                    <span>Click to download</span>
                </div>
                <div class="file-card-remove">
                    <span class="material-icons">close</span>
                </div>
            `;

            // Click to download
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.file-card-remove')) {
                    downloadFile(fileData.encrypted_id, fileData.original_name);
                }
            });

            // Remove file handler
            card.querySelector('.file-card-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                removeFile(fileData.id);
                card.remove();
            });

            fileCardsContainer.appendChild(card);
        }

        // Download file
        function downloadFile(encryptedId, originalName) {
            window.location.href = `download_file.php?fid=${encodeURIComponent(encryptedId)}`;
        }

        // Remove file
        function removeFile(fileId) {
            const index = uploadedFiles.findIndex(f => f.id === fileId);
            if (index > -1) {
                totalSize -= uploadedFiles[index].file_size;
                uploadedFiles.splice(index, 1);
                updateAttachmentIds();
                hideSizeWarning();
            }
        }

        // Update hidden input with attachment IDs
        function updateAttachmentIds() {
            const ids = uploadedFiles.map(f => f.id).join(',');
            attachmentIdsInput.value = ids;
        }

        // Show size warning
        function showSizeWarning(currentSize, attemptedSize) {
            const currentMB = (currentSize / 1024 / 1024).toFixed(2);
            const attemptedMB = (attemptedSize / 1024 / 1024).toFixed(2);
            sizeWarningText.textContent = `Cannot add file. Current total: ${currentMB}MB, attempted: ${attemptedMB}MB. Maximum is 25MB.`;
            sizeWarning.classList.add('active');
        }

        // Hide size warning
        function hideSizeWarning() {
            if (totalSize < MAX_TOTAL_SIZE * 0.9) {
                sizeWarning.classList.remove('active');
            }
        }

        // Get file icon
        function getFileIcon(extension) {
            const iconMap = {
                'pdf': 'picture_as_pdf',
                'doc': 'description',
                'docx': 'description',
                'xls': 'table_chart',
                'xlsx': 'table_chart',
                'ppt': 'slideshow',
                'pptx': 'slideshow',
                'jpg': 'image',
                'jpeg': 'image',
                'png': 'image',
                'gif': 'image',
                'zip': 'folder_zip',
                'rar': 'folder_zip',
                'txt': 'text_snippet',
                'csv': 'table_view'
            };
            return iconMap[extension] || 'insert_drive_file';
        }

        // ========== FORM SUBMISSION ==========
        document.getElementById('composeForm').addEventListener('submit', function(e) {
            // Get message content from Quill
            const messageHtml = quillMessage.root.innerHTML;
            
            // Set hidden input
            document.getElementById('messageInput').value = messageHtml;
        });

        // Preview button
        document.getElementById('previewBtn').addEventListener('click', () => {
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const articletitle = document.getElementById('articletitle').value;

            if (!email || !subject || !articletitle) {
                alert('Please fill in all required fields (To, Subject, and Article Title) before previewing.');
                return;
            }

            // Get message content
            const messageHtml = quillMessage.root.innerHTML;

            // Get signature components
            const signatureWish = document.getElementById('signatureWish').value;
            const signatureName = document.getElementById('signatureName').value;
            const signatureDesignation = document.getElementById('signatureDesignation').value;
            const signatureExtra = document.getElementById('signatureExtra').value;

            // Create preview form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'preview.php';
            form.target = '_blank';

            const fields = {
                'email': email,
                'subject': subject,
                'articletitle': articletitle,
                'message': messageHtml,
                'signatureWish': signatureWish,
                'signatureName': signatureName,
                'signatureDesignation': signatureDesignation,
                'signatureExtra': signatureExtra
            };

            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
        
    </script>
</body>

</html>