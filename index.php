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
    <title>SXC MDTS - Compose Email</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Quill Rich Text Editor CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background-color: #ffffff;
            color: #202124;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            padding: 24px 40px;
            overflow-y: auto;
            background-color: #f8f9fa;
        }

        /* Compose Card */
        .compose-card {
            background: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
            max-width: 900px;
            margin: 0 auto;
        }

        .compose-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e8eaed;
        }

        .compose-header h3 {
            font-size: 22px;
            font-weight: 500;
            color: #202124;
            letter-spacing: 0;
        }

        .compose-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #3c4043;
            font-size: 14px;
        }

        input[type="email"], 
        input[type="text"], 
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            background-color: #ffffff;
            color: #202124;
        }

        input[type="email"]:focus, 
        input[type="text"]:focus, 
        select:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }

        input[type="email"]:hover, 
        input[type="text"]:hover {
            border-color: #5f6368;
        }

        /* Rich Text Editor Container */
        .editor-container {
            border: 1px solid #dadce0;
            border-radius: 4px;
            background: white;
            margin-bottom: 16px;
        }

        .editor-container:hover {
            border-color: #5f6368;
        }

        .editor-container.focused {
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }

        /* Quill Editor Customization */
        #editor {
            min-height: 300px;
            max-height: 500px;
            overflow-y: auto;
            font-size: 14px;
            line-height: 1.6;
            padding: 12px;
        }

        .ql-toolbar.ql-snow {
            border: none;
            border-bottom: 1px solid #dadce0;
            padding: 12px;
            background: #f8f9fa;
        }

        .ql-container.ql-snow {
            border: none;
        }

        .ql-editor {
            padding: 12px;
            min-height: 250px;
        }

        .ql-editor.ql-blank::before {
            color: #80868b;
            font-style: normal;
        }

        /* Signature Section */
        .signature-section {
            border-top: 1px solid #e8eaed;
            padding-top: 12px;
            margin-top: 12px;
        }

        .signature-label {
            font-size: 12px;
            color: #5f6368;
            margin-bottom: 8px;
            display: block;
        }

        #signatureEditor {
            min-height: 120px;
            max-height: 200px;
            font-size: 13px;
        }

        /* File Attachments */
        .attachment-area {
            margin-top: 16px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: #5f6368;
            transition: all 0.2s;
            font-weight: 500;
        }

        .file-input-label:hover {
            background: #f8f9fa;
            border-color: #1a73e8;
            color: #1a73e8;
        }

        .file-input-label i {
            font-size: 16px;
        }

        input[type="file"] {
            display: none;
        }

        /* File Preview Grid */
        .file-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .file-preview-item {
            position: relative;
            aspect-ratio: 1;
            border: 1px solid #dadce0;
            border-radius: 8px;
            padding: 12px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.2s;
        }

        .file-preview-item:hover {
            background: #e8eaed;
            border-color: #5f6368;
        }

        .file-preview-icon {
            font-size: 32px;
            color: #5f6368;
            margin-bottom: 8px;
        }

        .file-preview-name {
            font-size: 11px;
            color: #202124;
            word-break: break-word;
            line-height: 1.3;
            max-height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .file-preview-size {
            font-size: 10px;
            color: #5f6368;
            margin-top: 4px;
        }

        .file-remove-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ea4335;
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s;
            opacity: 0;
        }

        .file-preview-item:hover .file-remove-btn {
            opacity: 1;
        }

        .file-remove-btn:hover {
            background: #d33828;
            transform: scale(1.1);
        }

        /* Input with Attach List Button */
        .input-with-file {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }

        .input-with-file input[type="text"] {
            flex: 1;
        }

        .btn-attach-list {
            background: white;
            border: 1px solid #dadce0;
            padding: 10px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #5f6368;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-attach-list:hover {
            background: #f8f9fa;
            border-color: #1a73e8;
            color: #1a73e8;
        }

        .help-text {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #5f6368;
        }

        /* Action Buttons */
        .compose-actions {
            padding: 16px 24px;
            border-top: 1px solid #e8eaed;
            display: flex;
            gap: 12px;
            background: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-send {
            background-color: #1a73e8;
            color: white;
            flex: 1;
        }

        .btn-send:hover {
            background-color: #1557b0;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
        }

        .btn-send:active {
            background-color: #1146a0;
        }

        .btn-preview {
            background-color: #ffffff;
            color: #5f6368;
            border: 1px solid #dadce0;
        }

        .btn-preview:hover {
            background-color: #f8f9fa;
            border-color: #5f6368;
            color: #202124;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f3f4;
        }

        ::-webkit-scrollbar-thumb {
            background: #dadce0;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #bdc1c6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 16px;
            }

            .compose-body {
                padding: 16px;
            }

            .file-preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-area">
            <div class="compose-card">
                <div class="compose-header">
                    <h3>New Message</h3>
                </div>

                <form action="send.php" method="POST" enctype="multipart/form-data" id="composeForm">
                    <div class="compose-body">
                        <!-- Recipient Email -->
                        <div class="form-group">
                            <label>To</label>
                            <input type="email" name="email" required placeholder="recipient@example.com">
                        </div>

                        <!-- CC Field -->
                        <div class="form-group">
                            <label>Cc</label>
                            <div class="input-with-file">
                                <input type="text" name="cc" id="ccInput" placeholder="cc1@example.com, cc2@example.com">
                                <button type="button" class="btn-attach-list" onclick="document.getElementById('ccFile').click()">
                                    <i class="fa-solid fa-paperclip"></i> Attach List
                                </button>
                                <input type="file" name="cc_file" id="ccFile" accept=".txt,.csv" onchange="handleEmailListUpload(this, 'ccInput')">
                            </div>
                            <small class="help-text">Separate multiple emails with commas or upload a text file</small>
                        </div>

                        <!-- BCC Field -->
                        <div class="form-group">
                            <label>Bcc</label>
                            <div class="input-with-file">
                                <input type="text" name="bcc" id="bccInput" placeholder="bcc1@example.com, bcc2@example.com">
                                <button type="button" class="btn-attach-list" onclick="document.getElementById('bccFile').click()">
                                    <i class="fa-solid fa-paperclip"></i> Attach List
                                </button>
                                <input type="file" name="bcc_file" id="bccFile" accept=".txt,.csv" onchange="handleEmailListUpload(this, 'bccInput')">
                            </div>
                            <small class="help-text">Separate multiple emails with commas or upload a text file</small>
                        </div>

                        <!-- Subject -->
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" required placeholder="Email subject">
                        </div>

                        <!-- Article Title -->
                        <div class="form-group">
                            <label>Article Title</label>
                            <input type="text" name="articletitle" required placeholder="Article title for template">
                        </div>

                        <!-- Rich Text Editor for Message -->
                        <div class="form-group">
                            <label>Message</label>
                            <div class="editor-container" id="editorContainer">
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
                            <input type="hidden" name="message" id="messageInput">
                            <input type="hidden" name="message_is_html" value="true">
                        </div>

                        <!-- Signature Editor -->
                        <div class="form-group">
                            <label>Signature</label>
                            <div class="editor-container" id="signatureContainer">
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

                        <!-- File Attachments -->
                        <div class="attachment-area">
                            <div class="file-input-wrapper">
                                <label for="attachments" class="file-input-label">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span>Attach Files</span>
                                </label>
                                <input type="file" name="attachments[]" id="attachments" multiple onchange="handleFileSelect(event)">
                            </div>
                            
                            <!-- File Preview Grid -->
                            <div class="file-preview-grid" id="filePreviewGrid"></div>
                        </div>
                    </div>

                    <div class="compose-actions">
                        <button type="button" class="btn btn-preview" id="previewBtn">
                            <i class="fa-solid fa-eye"></i> Preview
                        </button>
                        <button type="submit" class="btn btn-send">
                            <i class="fa-solid fa-paper-plane"></i> Send
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
            placeholder: 'Compose your message...'
        });

        const quillSignature = new Quill('#signatureEditor', {
            modules: {
                toolbar: '#signatureToolbar'
            },
            theme: 'snow',
            placeholder: 'Your email signature...'
        });

        // Load default signature from settings
        const defaultSignature = <?php echo $defaultSignature; ?>;
        if (defaultSignature) {
            quillSignature.root.innerHTML = defaultSignature;
        }

        // Focus effects for editor containers
        const editorContainer = document.getElementById('editorContainer');
        const signatureContainer = document.getElementById('signatureContainer');

        quillMessage.on('selection-change', function(range) {
            if (range) {
                editorContainer.classList.add('focused');
            } else {
                editorContainer.classList.remove('focused');
            }
        });

        quillSignature.on('selection-change', function(range) {
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
                    <button type="button" class="file-remove-btn" onclick="removeFile(${index})">
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
            reader.onload = function(e) {
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

                alert(`✓ Loaded ${emails.length} email(s) from file`);
            };

            reader.readAsText(file);
        }

        // Form submission - combine message + signature
        document.getElementById('composeForm').addEventListener('submit', function(e) {
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