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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SXC MDTS - Compose Email</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background-color: #fafafa;
            color: #1a1a1a;
            padding: 20px;
        }

        .content-area {
            max-width: 1200px;
            margin: 0 auto;
        }

        .compose-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .compose-card h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
            letter-spacing: -0.5px;
        }

        .compose-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e5e5e5;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1a1a1a;
            font-size: 14px;
        }

        label .required {
            color: #e53935;
            margin-left: 2px;
        }

        input[type="email"], 
        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d0d0;
            border-radius: 7px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s;
            background-color: #ffffff;
        }

        input[type="email"]:focus, 
        input[type="text"]:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.1);
        }

        /* Rich Text Editor */
        .rte-container {
            border: 1px solid #d0d0d0;
            border-radius: 7px;
            background: white;
            overflow: hidden;
            transition: all 0.2s;
        }

        .rte-container:focus-within {
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.1);
        }

        .rte-toolbar {
            border-bottom: 1px solid #e5e5e5;
            background: #fafafa;
            padding: 10px 12px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            align-items: center;
        }

        .rte-btn {
            background: white;
            border: 1px solid transparent;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: #444;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .rte-btn:hover {
            background: #e8f0fe;
            border-color: #d2e3fc;
        }

        .rte-btn.active {
            background: #d2e3fc;
            border-color: #1a73e8;
            color: #1a73e8;
        }

        .rte-separator {
            width: 1px;
            background: #e5e5e5;
            height: 24px;
            margin: 0 4px;
        }

        .rte-editor {
            min-height: 350px;
            max-height: 500px;
            overflow-y: auto;
            padding: 16px;
            font-size: 15px;
            line-height: 1.6;
            outline: none;
        }

        .rte-editor:empty:before {
            content: attr(data-placeholder);
            color: #999;
            pointer-events: none;
        }

        /* Signature Editor */
        .signature-editor {
            min-height: 120px;
            max-height: 200px;
        }

        /* Color Picker */
        .color-picker-wrapper {
            position: relative;
            display: inline-block;
        }

        .color-palette {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #d0d0d0;
            border-radius: 6px;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            grid-template-columns: repeat(8, 1fr);
            gap: 4px;
            width: 200px;
            margin-top: 4px;
        }

        .color-palette.active {
            display: grid;
        }

        .color-option {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.15s;
        }

        .color-option:hover {
            border-color: #1a73e8;
            transform: scale(1.1);
        }

        /* File Attachments */
        .attachment-area {
            border: 2px dashed #d0d0d0;
            border-radius: 7px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #fafafa;
        }

        .attachment-area:hover {
            border-color: #1a73e8;
            background: #f0f7ff;
        }

        .attachment-area.dragover {
            border-color: #1a73e8;
            background: #e8f0fe;
        }

        .attachment-icon {
            font-size: 32px;
            color: #666;
            margin-bottom: 12px;
        }

        .attachment-text {
            color: #666;
            font-size: 14px;
        }

        .attachment-subtext {
            color: #999;
            font-size: 12px;
            margin-top: 4px;
        }

        /* File Preview Grid */
        .file-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .file-card {
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 12px;
            position: relative;
            transition: all 0.2s;
        }

        .file-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .file-card-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #f44336;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s;
        }

        .file-card-remove:hover {
            background: #d32f2f;
        }

        .file-card-icon {
            font-size: 32px;
            color: #1a73e8;
            text-align: center;
            margin-bottom: 8px;
        }

        .file-card-name {
            font-size: 12px;
            color: #1a1a1a;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
        }

        .file-card-size {
            font-size: 11px;
            color: #666;
        }

        /* Buttons */
        .button-row {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e5e5;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a73e8;
            color: white;
        }

        .btn-primary:hover {
            background: #1557b0;
        }

        .btn-secondary {
            background: white;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }

        .btn-secondary:hover {
            background: #f0f7ff;
        }

        .btn-outline {
            background: white;
            color: #666;
            border: 1px solid #d0d0d0;
        }

        .btn-outline:hover {
            background: #fafafa;
        }

        /* Helper text */
        .helper-text {
            font-size: 13px;
            color: #666;
            margin-top: 6px;
        }

        /* Logout button */
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            color: #666;
            border: 1px solid #d0d0d0;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: #f44336;
            color: white;
            border-color: #f44336;
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>

    <div class="content-area">
        <div class="compose-card">
            <h3>Compose New Email</h3>
            <p class="compose-subtitle">Create and send professional emails with rich formatting</p>

            <form id="emailForm">
                <!-- Recipient Email -->
                <div class="form-group">
                    <label>
                        Recipient Email <span class="required">*</span>
                    </label>
                    <input type="email" id="recipientEmail" name="email" required placeholder="recipient@example.com">
                    <p class="helper-text">Separate multiple emails with commas</p>
                </div>

                <!-- CC -->
                <div class="form-group">
                    <label>CC (Optional)</label>
                    <input type="text" id="ccInput" name="cc" placeholder="cc@example.com">
                </div>

                <!-- BCC -->
                <div class="form-group">
                    <label>BCC (Optional)</label>
                    <input type="text" id="bccInput" name="bcc" placeholder="bcc@example.com">
                </div>

                <!-- Subject -->
                <div class="form-group">
                    <label>
                        Subject <span class="required">*</span>
                    </label>
                    <input type="text" id="subject" name="subject" required placeholder="Email subject">
                </div>

                <!-- Article Title -->
                <div class="form-group">
                    <label>
                        Article Title <span class="required">*</span>
                    </label>
                    <input type="text" id="articletitle" name="articletitle" required placeholder="Main article heading">
                </div>

                <!-- Message Body -->
                <div class="form-group">
                    <label>
                        Message <span class="required">*</span>
                    </label>
                    <div class="rte-container">
                        <div class="rte-toolbar">
                            <button type="button" class="rte-btn" data-command="bold" title="Bold (Ctrl+B)">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="rte-btn" data-command="italic" title="Italic (Ctrl+I)">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="rte-btn" data-command="underline" title="Underline (Ctrl+U)">
                                <i class="fas fa-underline"></i>
                            </button>
                            <div class="rte-separator"></div>
                            <div class="color-picker-wrapper">
                                <button type="button" class="rte-btn" id="colorPickerBtn" title="Text Color">
                                    <i class="fas fa-palette"></i>
                                </button>
                                <div class="color-palette" id="colorPalette">
                                    <!-- Colors will be generated by JS -->
                                </div>
                            </div>
                            <button type="button" class="rte-btn" data-command="removeFormat" title="Clear Formatting">
                                <i class="fas fa-remove-format"></i>
                            </button>
                        </div>
                        <div class="rte-editor" id="messageEditor" contenteditable="true" data-placeholder="Type your message here..."></div>
                    </div>
                </div>

                <!-- Signature -->
                <div class="form-group">
                    <label>Signature (Optional)</label>
                    <div class="rte-container">
                        <div class="rte-toolbar">
                            <button type="button" class="rte-btn" data-command="bold" data-target="signature">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="rte-btn" data-command="italic" data-target="signature">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="rte-btn" data-command="underline" data-target="signature">
                                <i class="fas fa-underline"></i>
                            </button>
                            <div class="rte-separator"></div>
                            <div class="color-picker-wrapper">
                                <button type="button" class="rte-btn" id="signatureColorBtn" title="Text Color">
                                    <i class="fas fa-palette"></i>
                                </button>
                                <div class="color-palette" id="signatureColorPalette">
                                    <!-- Colors will be generated by JS -->
                                </div>
                            </div>
                        </div>
                        <div class="rte-editor signature-editor" id="signatureEditor" contenteditable="true" data-placeholder="Add your signature..."></div>
                    </div>
                    <p class="helper-text">Your signature will be automatically appended to all emails</p>
                </div>

                <!-- File Attachments -->
                <div class="form-group">
                    <label>Attachments (Optional)</label>
                    <div class="attachment-area" id="attachmentArea">
                        <div class="attachment-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="attachment-text">
                            <strong>Click to upload</strong> or drag and drop files here
                        </div>
                        <div class="attachment-subtext">
                            Multiple files supported
                        </div>
                        <input type="file" id="attachmentInput" multiple style="display: none;">
                    </div>
                    <div class="file-preview-grid" id="filePreviewGrid"></div>
                </div>

                <!-- Action Buttons -->
                <div class="button-row">
                    <button type="button" class="btn btn-primary" id="sendBtn">
                        <i class="fas fa-paper-plane"></i>
                        Send Email
                    </button>
                    <button type="button" class="btn btn-secondary" id="previewBtn">
                        <i class="fas fa-eye"></i>
                        Preview
                    </button>
                    <button type="button" class="btn btn-outline" id="clearBtn">
                        <i class="fas fa-eraser"></i>
                        Clear Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Rich Text Editor Class
        class RichTextEditor {
            constructor(editorId) {
                this.editor = document.getElementById(editorId);
                this.initializeEditor();
            }

            initializeEditor() {
                // Track active formats
                this.editor.addEventListener('keyup', () => this.updateToolbarState());
                this.editor.addEventListener('mouseup', () => this.updateToolbarState());
            }

            execCommand(command, value = null) {
                document.execCommand(command, false, value);
                this.editor.focus();
                this.updateToolbarState();
            }

            updateToolbarState() {
                const commands = ['bold', 'italic', 'underline'];
                commands.forEach(cmd => {
                    const btn = document.querySelector(`.rte-btn[data-command="${cmd}"]`);
                    if (btn && document.queryCommandState(cmd)) {
                        btn.classList.add('active');
                    } else if (btn) {
                        btn.classList.remove('active');
                    }
                });
            }

            getHTML() {
                return this.editor.innerHTML;
            }

            setHTML(html) {
                this.editor.innerHTML = html;
            }

            insertHTML(html) {
                this.editor.focus();
                document.execCommand('insertHTML', false, html);
            }
        }

        // Initialize editors
        const messageEditor = new RichTextEditor('messageEditor');
        const signatureEditor = new RichTextEditor('signatureEditor');

        // Load signature from settings
        const defaultSignature = <?php echo json_encode($settings['signature']); ?>;
        if (defaultSignature) {
            signatureEditor.setHTML(defaultSignature);
        }

        // Toolbar button handlers
        document.querySelectorAll('.rte-btn[data-command]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const command = btn.getAttribute('data-command');
                const target = btn.getAttribute('data-target');
                
                if (target === 'signature') {
                    signatureEditor.execCommand(command);
                } else {
                    messageEditor.execCommand(command);
                }
            });
        });

        // Color picker setup
        const colors = [
            '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef',
            '#f44336', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4',
            '#009688', '#4caf50', '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800', '#ff5722',
            '#795548', '#9e9e9e', '#607d8b', '#ffffff'
        ];

        function createColorPalette(paletteId, editor) {
            const palette = document.getElementById(paletteId);
            colors.forEach(color => {
                const colorOption = document.createElement('div');
                colorOption.className = 'color-option';
                colorOption.style.backgroundColor = color;
                colorOption.addEventListener('click', (e) => {
                    e.stopPropagation();
                    editor.execCommand('foreColor', color);
                    palette.classList.remove('active');
                });
                palette.appendChild(colorOption);
            });
        }

        createColorPalette('colorPalette', messageEditor);
        createColorPalette('signatureColorPalette', signatureEditor);

        // Color picker toggle
        document.getElementById('colorPickerBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('colorPalette').classList.toggle('active');
        });

        document.getElementById('signatureColorBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('signatureColorPalette').classList.toggle('active');
        });

        // Close color palettes when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.color-palette').forEach(p => p.classList.remove('active'));
        });

        // File attachment handling
        const attachedFiles = [];
        const attachmentArea = document.getElementById('attachmentArea');
        const attachmentInput = document.getElementById('attachmentInput');
        const filePreviewGrid = document.getElementById('filePreviewGrid');

        attachmentArea.addEventListener('click', () => {
            attachmentInput.click();
        });

        attachmentInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Drag and drop
        attachmentArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            attachmentArea.classList.add('dragover');
        });

        attachmentArea.addEventListener('dragleave', () => {
            attachmentArea.classList.remove('dragover');
        });

        attachmentArea.addEventListener('drop', (e) => {
            e.preventDefault();
            attachmentArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                attachedFiles.push(file);
            });
            renderFilePreview();
        }

        function renderFilePreview() {
            filePreviewGrid.innerHTML = '';
            attachedFiles.forEach((file, index) => {
                const card = document.createElement('div');
                card.className = 'file-card';
                
                const icon = getFileIcon(file.name);
                
                card.innerHTML = `
                    <button type="button" class="file-card-remove" onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="file-card-icon">
                        <i class="${icon}"></i>
                    </div>
                    <div class="file-card-name" title="${file.name}">${file.name}</div>
                    <div class="file-card-size">${formatFileSize(file.size)}</div>
                `;
                filePreviewGrid.appendChild(card);
            });
        }

        function removeFile(index) {
            attachedFiles.splice(index, 1);
            renderFilePreview();
        }

        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'fas fa-file-pdf',
                'doc': 'fas fa-file-word',
                'docx': 'fas fa-file-word',
                'xls': 'fas fa-file-excel',
                'xlsx': 'fas fa-file-excel',
                'ppt': 'fas fa-file-powerpoint',
                'pptx': 'fas fa-file-powerpoint',
                'jpg': 'fas fa-file-image',
                'jpeg': 'fas fa-file-image',
                'png': 'fas fa-file-image',
                'gif': 'fas fa-file-image',
                'zip': 'fas fa-file-archive',
                'rar': 'fas fa-file-archive',
                'txt': 'fas fa-file-alt'
            };
            return iconMap[ext] || 'fas fa-file';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Preview button
        document.getElementById('previewBtn').addEventListener('click', () => {
            const recipientEmail = document.getElementById('recipientEmail').value;
            const subject = document.getElementById('subject').value;
            const articletitle = document.getElementById('articletitle').value;

            if (!recipientEmail || !subject || !articletitle) {
                alert('Please fill in all required fields before previewing.');
                return;
            }

            // Combine message and signature
            const messageHTML = messageEditor.getHTML();
            const signatureHTML = signatureEditor.getHTML();
            
            let fullMessage = messageHTML;
            if (signatureHTML.trim() && signatureHTML !== '<br>') {
                fullMessage += '<br><br><div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">' + signatureHTML + '</div>';
            }

            // Create form and submit to preview.php in new tab
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'preview.php';
            form.target = '_blank';

            const fields = {
                'email': recipientEmail,
                'subject': subject,
                'articletitle': articletitle,
                'message': fullMessage
            };

            Object.keys(fields).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        // Send button
        document.getElementById('sendBtn').addEventListener('click', () => {
            const recipientEmail = document.getElementById('recipientEmail').value;
            const subject = document.getElementById('subject').value;
            const articletitle = document.getElementById('articletitle').value;

            if (!recipientEmail || !subject || !articletitle) {
                alert('Please fill in all required fields.');
                return;
            }

            if (!confirm('Are you sure you want to send this email?')) {
                return;
            }

            // Combine message and signature
            const messageHTML = messageEditor.getHTML();
            const signatureHTML = signatureEditor.getHTML();
            
            let fullMessage = messageHTML;
            if (signatureHTML.trim() && signatureHTML !== '<br>') {
                fullMessage += '<br><br>' + signatureHTML;
            }

            const formData = new FormData();
            formData.append('email', recipientEmail);
            formData.append('cc', document.getElementById('ccInput').value);
            formData.append('bcc', document.getElementById('bccInput').value);
            formData.append('subject', subject);
            formData.append('articletitle', articletitle);
            formData.append('message', fullMessage);
            formData.append('message_is_html', 'true');

            // Add attachments
            attachedFiles.forEach((file, index) => {
                formData.append('attachments[]', file);
            });

            // Show loading state
            const sendBtn = document.getElementById('sendBtn');
            const originalText = sendBtn.innerHTML;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            sendBtn.disabled = true;

            fetch('send.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Display send.php response
                document.open();
                document.write(html);
                document.close();
            })
            .catch(error => {
                alert('Error sending email: ' + error);
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            });
        });

        // Clear button
        document.getElementById('clearBtn').addEventListener('click', () => {
            if (confirm('Are you sure you want to clear all fields?')) {
                document.getElementById('emailForm').reset();
                messageEditor.setHTML('');
                signatureEditor.setHTML(defaultSignature);
                attachedFiles.length = 0;
                renderFilePreview();
            }
        });
    </script>
</body>
</html>