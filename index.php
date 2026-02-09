<?php
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'settings_helper.php';

// Get user settings for default values
$userEmail = $_SESSION['smtp_user'];
$settings = $_SESSION['user_settings'] ?? [];
$displayName = $settings['display_name'] ?? "St. Xavier's College";

// Pre-fill data from URL parameters (for reply/forward)
$replyTo = $_GET['reply_to'] ?? '';
$subjectParam = $_GET['subject'] ?? '';
$bodyParam = $_GET['body'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
            min-height: 100vh;
        }

        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .compose-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 16px;
            color: #6b7280;
            font-weight: 400;
        }

        .compose-form {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #007AFF;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-label.required::after {
            content: "*";
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #007AFF;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 200px;
            font-family: 'Inter', sans-serif;
        }

        .form-hint {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }

        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            background: #f9fafb;
            transition: all 0.3s;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #007AFF;
            background: #eff6ff;
        }

        .file-upload-area.drag-over {
            border-color: #007AFF;
            background: #dbeafe;
        }

        .file-upload-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 12px;
        }

        .file-upload-text {
            font-size: 15px;
            color: #374151;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .file-upload-hint {
            font-size: 13px;
            color: #6b7280;
        }

        #attachments {
            display: none;
        }

        .file-list {
            margin-top: 16px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .file-item-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .file-item-icon {
            font-size: 24px;
            color: #007AFF;
        }

        .file-item-name {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .file-item-size {
            font-size: 13px;
            color: #6b7280;
        }

        .file-item-remove {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .file-item-remove:hover {
            background: #fee2e2;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .email-preview-toggle {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 16px;
        }

        .email-preview-toggle:hover {
            background: #e5e7eb;
        }

        .template-preview {
            display: none;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin-top: 16px;
        }

        .template-preview.active {
            display: block;
        }

        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 32px 0;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .compose-form {
                padding: 24px 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="compose-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Compose Email</h1>
                    <p class="page-subtitle">Send professional emails with custom templates</p>
                </div>

                <!-- Compose Form -->
                <form class="compose-form" method="POST" action="send.php" enctype="multipart/form-data" id="composeForm">
                    
                    <!-- Recipient Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-friends"></i>
                            Recipients
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="email">To</label>
                            <input 
                                type="email" 
                                class="form-input" 
                                id="email" 
                                name="email" 
                                placeholder="recipient@example.com"
                                value="<?= htmlspecialchars($replyTo) ?>"
                                required
                            >
                            <p class="form-hint">Primary recipient email address</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cc_emails">CC (Carbon Copy)</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="cc_emails" 
                                name="cc_emails" 
                                placeholder="cc1@example.com, cc2@example.com"
                            >
                            <p class="form-hint">Separate multiple emails with commas</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="bcc_emails">BCC (Blind Carbon Copy)</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="bcc_emails" 
                                name="bcc_emails" 
                                placeholder="bcc1@example.com, bcc2@example.com"
                            >
                            <p class="form-hint">Recipients won't see each other's email addresses</p>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Email Content Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-envelope"></i>
                            Email Content
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="subject">Subject</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="subject" 
                                name="subject" 
                                placeholder="Enter email subject"
                                value="<?= htmlspecialchars($subjectParam) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="article_title">Article Title</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="article_title" 
                                name="article_title" 
                                placeholder="Main heading in email template"
                                value="Official Communication"
                            >
                            <p class="form-hint">This appears as the main heading in the email template</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" for="message">Message</label>
                            <textarea 
                                class="form-textarea" 
                                id="message" 
                                name="message" 
                                placeholder="Enter your message here..."
                                required
                            ><?= htmlspecialchars($bodyParam) ?></textarea>
                            <p class="form-hint">Your main message content</p>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Signature Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-signature"></i>
                            Email Signature
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="signature_wish">Closing Wish</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="signature_wish" 
                                name="signature_wish" 
                                placeholder="Best Regards,"
                                value="Best Regards,"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="signature_name">Name</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="signature_name" 
                                name="signature_name" 
                                placeholder="Dr. Durba Bhattacharya"
                                value="Dr. Durba Bhattacharya"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="signature_designation">Designation</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="signature_designation" 
                                name="signature_designation" 
                                placeholder="Head of Department, Data Science"
                                value="Head of Department, Data Science"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="signature_extra">Additional Info</label>
                            <input 
                                type="text" 
                                class="form-input" 
                                id="signature_extra" 
                                name="signature_extra" 
                                placeholder="St. Xavier's College (Autonomous), Kolkata"
                                value="St. Xavier's College (Autonomous), Kolkata"
                            >
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Attachments Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-paperclip"></i>
                            Attachments
                        </div>

                        <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('attachments').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">Click to upload or drag and drop</div>
                            <div class="file-upload-hint">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG (Max 10MB each)</div>
                        </div>

                        <input 
                            type="file" 
                            id="attachments" 
                            name="attachments[]" 
                            multiple 
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                        >

                        <div class="file-list" id="fileList"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send Email
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-redo"></i>
                            Reset
                        </button>
                        <a href="inbox.php" class="btn btn-secondary">
                            <i class="fas fa-inbox"></i>
                            Inbox
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('fileList');
        const fileUploadArea = document.getElementById('fileUploadArea');
        let selectedFiles = [];

        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            handleFiles(Array.from(e.target.files));
        });

        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files);
            handleFiles(files);
        });

        function handleFiles(files) {
            files.forEach(file => {
                // Check file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    alert(`File ${file.name} is too large. Maximum size is 10MB.`);
                    return;
                }
                
                if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            });
            
            updateFileList();
            updateFileInput();
        }

        function updateFileList() {
            if (selectedFiles.length === 0) {
                fileList.innerHTML = '';
                return;
            }

            fileList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-item">
                    <div class="file-item-info">
                        <i class="fas fa-file file-item-icon"></i>
                        <div>
                            <div class="file-item-name">${escapeHtml(file.name)}</div>
                            <div class="file-item-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="file-item-remove" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
        }

        function updateFileInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileInput();
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
                document.getElementById('composeForm').reset();
                selectedFiles = [];
                updateFileList();
            }
        }

        // Form validation
        document.getElementById('composeForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();

            if (!email || !subject || !message) {
                e.preventDefault();
                alert('Please fill in all required fields (To, Subject, and Message)');
                return false;
            }

            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }

            // Show sending indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        });
    </script>
</body>
</html>