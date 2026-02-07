<?php
// htdocs/index.php
session_start();

// Security check: Redirect to login if session credentials do not exist
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

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

// Prepare signature for JavaScript (escape for JSON)
$defaultSignature = json_encode($settings['signature']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email – SXC MDTS</title>

    <!-- Google Fonts - Nature.com uses Harding and Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Harding:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Quill Rich Text Editor CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #fff;
            color: #191919;
            display: flex;
            height: 100vh;
            overflow: hidden;
            line-height: 1.6;
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: #fff;
        }

        /* Nature.com style header */
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px 40px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 400;
        }

        .breadcrumb a {
            color: #0973dc;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #006bb3;
            text-decoration: underline;
        }

        .breadcrumb-separator {
            margin: 0 8px;
            color: #999;
        }

        .page-type {
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            color: #0973dc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        /* Article-style container */
        .compose-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px 40px 80px;
        }

        .compose-header {
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px solid #e0e0e0;
        }

        .compose-title {
            font-family: 'Harding', Georgia, serif;
            font-size: 42px;
            font-weight: 600;
            line-height: 1.2;
            color: #191919;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .compose-subtitle {
            font-size: 18px;
            color: #666;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Form sections - Nature.com article sections */
        .form-section {
            margin-bottom: 48px;
        }

        .section-title {
            font-family: 'Harding', Georgia, serif;
            font-size: 24px;
            font-weight: 600;
            color: #191919;
            margin-bottom: 20px;
            letter-spacing: -0.3px;
        }

        .section-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        /* Form groups with Nature.com styling */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group-compact {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #191919;
            font-size: 14px;
            letter-spacing: 0.2px;
        }

        .label-optional {
            font-size: 13px;
            color: #999;
            font-weight: 400;
            margin-left: 4px;
        }

        /* Input fields with Nature.com aesthetic */
        input[type="email"],
        input[type="text"],
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d0d0d0;
            border-radius: 3px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background-color: #fff;
            color: #191919;
        }

        input[type="email"]:focus,
        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #0973dc;
            box-shadow: 0 0 0 2px rgba(9, 115, 220, 0.1);
        }

        input[type="email"]:hover:not(:focus),
        input[type="text"]:hover:not(:focus) {
            border-color: #999;
        }

        input::placeholder {
            color: #999;
            font-weight: 400;
        }

        /* Two-column grid for compact fields */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Rich Text Editor - Nature.com style */
        .editor-wrapper {
            border: 1px solid #d0d0d0;
            border-radius: 3px;
            background: #fff;
            transition: all 0.2s;
        }

        .editor-wrapper:hover {
            border-color: #999;
        }

        .editor-wrapper.focused {
            border-color: #0973dc;
            box-shadow: 0 0 0 2px rgba(9, 115, 220, 0.1);
        }

        /* Quill Editor Customization */
        .ql-toolbar.ql-snow {
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 14px;
            background: #fafafa;
            font-family: 'Inter', sans-serif;
        }

        .ql-container.ql-snow {
            border: none;
            font-family: 'Inter', sans-serif;
        }

        .ql-editor {
            padding: 16px;
            min-height: 300px;
            font-size: 15px;
            line-height: 1.7;
            color: #191919;
        }

        .ql-editor.ql-blank::before {
            color: #999;
            font-style: normal;
            font-weight: 400;
        }

        #signatureEditor .ql-editor {
            min-height: 120px;
            font-size: 14px;
        }

        /* Input with attach button */
        .input-with-button {
            display: flex;
            gap: 10px;
        }

        .input-with-button input {
            flex: 1;
        }

        .btn-attach-list {
            background: #fff;
            border: 1px solid #d0d0d0;
            padding: 12px 18px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #191919;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-attach-list:hover {
            background: #fafafa;
            border-color: #0973dc;
            color: #0973dc;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }

        /* File Attachments - Nature.com card style */
        .attachment-section {
            margin-top: 32px;
            padding: 24px;
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 3px;
        }

        .attachment-section-title {
            font-size: 15px;
            font-weight: 600;
            color: #191919;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #fff;
            border: 1px solid #d0d0d0;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            color: #191919;
            transition: all 0.2s;
            font-weight: 500;
        }

        .file-input-label:hover {
            background: #0973dc;
            border-color: #0973dc;
            color: #fff;
        }

        .file-input-label i {
            font-size: 14px;
        }

        input[type="file"] {
            display: none;
        }

        /* File Preview Grid - Nature.com cards */
        .file-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .file-preview-item {
            position: relative;
            background: #fff;
            border: 1px solid #d0d0d0;
            border-radius: 3px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
        }

        .file-preview-item:hover {
            border-color: #999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .file-preview-icon {
            font-size: 36px;
            color: #0973dc;
            margin-bottom: 10px;
        }

        .file-preview-name {
            font-size: 12px;
            color: #191919;
            word-break: break-word;
            line-height: 1.4;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .file-preview-size {
            font-size: 11px;
            color: #666;
        }

        .file-remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #d0d0d0;
            color: #666;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all 0.2s;
            opacity: 0;
        }

        .file-preview-item:hover .file-remove-btn {
            opacity: 1;
        }

        .file-remove-btn:hover {
            background: #d32f2f;
            border-color: #d32f2f;
            color: #fff;
        }

        /* Action buttons - Nature.com style */
        .compose-actions {
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 3px;
            font-size: 15px;
            font-weight: 500;
            border: 1px solid;
            transition: all 0.2s;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }

        .btn-send {
            background: #0973dc;
            color: #fff;
            border-color: #0973dc;
            flex: 1;
        }

        .btn-send:hover {
            background: #006bb3;
            border-color: #006bb3;
            box-shadow: 0 2px 8px rgba(9, 115, 220, 0.3);
        }

        .btn-preview {
            background: #fff;
            color: #191919;
            border-color: #d0d0d0;
        }

        .btn-preview:hover {
            background: #fafafa;
            border-color: #999;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f5f5f5;
        }

        ::-webkit-scrollbar-thumb {
            background: #c0c0c0;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .compose-container {
                padding: 32px 24px 60px;
            }

            .compose-title {
                font-size: 32px;
            }

            .section-title {
                font-size: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .file-preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-area">
            <!-- Nature.com style header -->
            <div class="page-header">
                <div class="header-container">
                    <div class="breadcrumb">
                        <a href="index.php">Dashboard</a>
                        <span class="breadcrumb-separator">›</span>
                        <span>Compose Email</span>
                    </div>
                    <span class="page-type">Email Composer</span>
                </div>
            </div>

            <!-- Article-style compose form -->
            <div class="compose-container">
                <header class="compose-header">
                    <h1 class="compose-title">Compose New Message</h1>
                    <p class="compose-subtitle">Create and send professional emails with rich formatting, attachments,
                        and custom signatures.</p>
                </header>

                <form action="send.php" method="POST" enctype="multipart/form-data" id="composeForm">

                    <!-- Recipients Section -->
                    <section class="form-section">
                        <h2 class="section-title">Recipients</h2>
                        <p class="section-description">Specify the primary and additional recipients for your message.
                        </p>

                        <div class="form-group">
                            <label>To <span style="color: #d32f2f;">*</span></label>
                            <input type="email" name="email" required placeholder="recipient@example.com">
                        </div>

                        <div class="form-grid">
                            <div class="form-group-compact">
                                <label>Cc <span class="label-optional">(optional)</span></label>
                                <div class="input-with-button">
                                    <input type="text" name="cc" id="ccInput" placeholder="cc@example.com">
                                    <button type="button" class="btn-attach-list"
                                        onclick="document.getElementById('ccFile').click()">
                                        <i class="fa-solid fa-paperclip"></i> List
                                    </button>
                                    <input type="file" name="cc_file" id="ccFile" accept=".txt,.csv"
                                        onchange="handleEmailListUpload(this, 'ccInput')">
                                </div>
                                <small class="help-text">Separate multiple emails with commas</small>
                            </div>

                            <div class="form-group-compact">
                                <label>Bcc <span class="label-optional">(optional)</span></label>
                                <div class="input-with-button">
                                    <input type="text" name="bcc" id="bccInput" placeholder="bcc@example.com">
                                    <button type="button" class="btn-attach-list"
                                        onclick="document.getElementById('bccFile').click()">
                                        <i class="fa-solid fa-paperclip"></i> List
                                    </button>
                                    <input type="file" name="bcc_file" id="bccFile" accept=".txt,.csv"
                                        onchange="handleEmailListUpload(this, 'bccInput')">
                                </div>
                                <small class="help-text">Hidden recipients for privacy</small>
                            </div>
                        </div>
                    </section>

                    <!-- Email Details Section -->
                    <section class="form-section">
                        <h2 class="section-title">Email Details</h2>

                        <div class="form-group">
                            <label>Subject <span style="color: #d32f2f;">*</span></label>
                            <input type="text" name="subject" required
                                placeholder="Enter a clear, descriptive subject line">
                        </div>

                        <div class="form-group">
                            <label>Article Title <span style="color: #d32f2f;">*</span></label>
                            <input type="text" name="articletitle" required placeholder="Title for email template">
                            <small class="help-text">This appears in the email header template</small>
                        </div>
                    </section>

                    <!-- Message Composition Section -->
                    <section class="form-section">
                        <h2 class="section-title">Message Content</h2>
                        <p class="section-description">Compose your message using the rich text editor with formatting
                            options.</p>

                        <div class="form-group">
                            <label>Message Body <span style="color: #d32f2f;">*</span></label>
                            <div class="editor-wrapper" id="editorContainer">
                                <div id="toolbar">
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                    <select class="ql-color"></select>
                                    <select class="ql-background"></select>
                                    <button class="ql-list" value="ordered"></button>
                                    <button class="ql-list" value="bullet"></button>
                                    <select class="ql-align"></select>
                                    <button class="ql-link"></button>
                                </div>
                                <div id="editor"></div>
                            </div>
                            <input type="hidden" name="message" id="messageInput" required>
                            <input type="hidden" name="message_is_html" value="true">
                        </div>
                    </section>

                    <!-- Signature Section -->
                    <section class="form-section">
                        <h2 class="section-title">Email Signature</h2>
                        <p class="section-description">Add or edit your email signature. This will be automatically
                            appended to your message.</p>

                        <div class="form-group">
                            <label>Signature <span class="label-optional">(optional)</span></label>
                            <div class="editor-wrapper" id="signatureContainer">
                                <div id="signatureToolbar">
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                    <select class="ql-color"></select>
                                    <button class="ql-link"></button>
                                </div>
                                <div id="signatureEditor"></div>
                            </div>
                        </div>
                    </section>

                    <!-- Attachments Section -->
                    <div class="attachment-section">
                        <div class="attachment-section-title">
                            <i class="fa-solid fa-paperclip"></i>
                            File Attachments
                        </div>
                        <div class="file-input-wrapper">
                            <label for="attachments" class="file-input-label">
                                <i class="fa-solid fa-upload"></i>
                                <span>Choose Files</span>
                            </label>
                            <input type="file" name="attachments[]" id="attachments" multiple
                                onchange="handleFileSelect(event)">
                        </div>
                        <small class="help-text">You can select multiple files. Supported formats: PDF, DOC, DOCX, XLS,
                            XLSX, images, and more.</small>

                        <!-- File Preview Grid -->
                        <div class="file-preview-grid" id="filePreviewGrid"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="compose-actions">
                        <button type="button" class="btn btn-preview" id="previewBtn">
                            <i class="fa-solid fa-eye"></i> Preview Email
                        </button>
                        <button type="submit" class="btn btn-send">
                            <i class="fa-solid fa-paper-plane"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quill Rich Text Editor JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
        // Initialize Quill editors
        const quillMessage = new Quill('#editor', {
            modules: {
                toolbar: '#toolbar'
            },
            theme: 'snow',
            placeholder: 'Type your message here...'
        });

        const quillSignature = new Quill('#signatureEditor', {
            modules: {
                toolbar: '#signatureToolbar'
            },
            theme: 'snow',
            placeholder: 'Your professional signature...'
        });

        // Load default signature from settings
        const defaultSignature = <? php echo $defaultSignature; ?>;
        if (defaultSignature) {
            quillSignature.root.innerHTML = defaultSignature;
        }

        // Focus effects for editor containers
        const editorContainer = document.getElementById('editorContainer');
        const signatureContainer = document.getElementById('signatureContainer');

        quillMessage.on('selection-change', function (range) {
            if (range) {
                editorContainer.classList.add('focused');
            } else {
                editorContainer.classList.remove('focused');
            }
        });

        quillSignature.on('selection-change', function (range) {
            if (range) {
                signatureContainer.classList.add('focused');
            } else {
                signatureContainer.classList.remove('focused');
            }
        });

        // File handling variables
        let selectedFiles = [];

        // Handle file selection
        function handleFileSelect(event) {
            const files = Array.from(event.target.files);
            selectedFiles = [...selectedFiles, ...files];
            renderFilePreview();
        }

        // Render file preview grid
        function renderFilePreview() {
            const grid = document.getElementById('filePreviewGrid');
            grid.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'file-preview-item';

                const icon = getFileIcon(file.name);
                const size = formatFileSize(file.size);

                item.innerHTML = `
                    <i class="fa-solid ${icon} file-preview-icon"></i>
                    <div class="file-preview-name">${escapeHtml(file.name)}</div>
                    <div class="file-preview-size">${size}</div>
                    <button type="button" class="file-remove-btn" onclick="removeFile(${index})" title="Remove file">
                        <i class="fa-solid fa-times"></i>
                    </button>
                `;

                grid.appendChild(item);
            });

            // Update the file input with a DataTransfer object
            updateFileInput();
        }

        // Remove file from selection
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            renderFilePreview();
        }

        // Update the actual file input with selected files
        function updateFileInput() {
            const input = document.getElementById('attachments');
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            input.files = dt.files;
        }

        // Get appropriate icon for file type
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
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
                'zip': 'fa-file-zipper',
                'rar': 'fa-file-zipper',
                'txt': 'fa-file-lines',
                'csv': 'fa-file-csv'
            };
            return iconMap[ext] || 'fa-file';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Handle Email List File Upload
        function handleEmailListUpload(fileInput, targetInputId) {
            const file = fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                const content = e.target.result;

                // Parse emails from file
                let emails = content
                    .split(/[,;\n\r]+/)
                    .map(email => email.trim())
                    .filter(email => email.length > 0);

                // Set the emails in the target input
                const targetInput = document.getElementById(targetInputId);
                const currentValue = targetInput.value.trim();

                if (currentValue) {
                    targetInput.value = currentValue + ', ' + emails.join(', ');
                } else {
                    targetInput.value = emails.join(', ');
                }

                alert(`✓ Successfully loaded ${emails.length} email address${emails.length !== 1 ? 'es' : ''} from file`);
            };

            reader.readAsText(file);
        }

        // Form submission - combine message + signature
        document.getElementById('composeForm').addEventListener('submit', function (e) {
            // Get message content
            const messageHtml = quillMessage.root.innerHTML;

            // Get signature content
            const signatureHtml = quillSignature.root.innerHTML;

            // Combine message and signature
            let finalHtml = messageHtml;

            // Only add signature if it's not empty
            if (signatureHtml.trim() && signatureHtml !== '<p><br></p>') {
                finalHtml += '<br><br>' + signatureHtml;
            }

            // Set the combined HTML to hidden input
            document.getElementById('messageInput').value = finalHtml;
        });

        // Preview Email Functionality
        document.getElementById('previewBtn').addEventListener('click', () => {
            const recipientEmail = document.querySelector('input[name="email"]').value;
            const subject = document.querySelector('input[name="subject"]').value;
            const articletitle = document.querySelector('input[name="articletitle"]').value;

            // Validate required fields
            if (!recipientEmail || !subject || !articletitle) {
                alert('Please fill in all required fields (To, Subject, and Article Title) before previewing.');
                return;
            }

            // Get message content
            const messageHtml = quillMessage.root.innerHTML;

            // Get signature content
            const signatureHtml = quillSignature.root.innerHTML;

            // Combine message and signature
            let finalHtml = messageHtml;
            if (signatureHtml.trim() && signatureHtml !== '<p><br></p>') {
                finalHtml += '<br><br>' + signatureHtml;
            }

            // Create a form to submit to preview.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'preview.php';
            form.target = '_blank';

            // Add form fields
            const fields = {
                'email': recipientEmail,
                'subject': subject,
                'articletitle': articletitle,
                'message': finalHtml
            };

            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }

            // Add form to body and submit
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    </script>
</body>

</html>