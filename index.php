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
            background-color: #ffffff;
            color: #1a1a1a;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            padding: 40px 60px;
            overflow-y: auto;
            background-color: #fafafa;
        }

        .compose-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            max-width: 1200px;
            margin: 0 auto;
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
            letter-spacing: 0.2px;
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

        /* Custom Rich Text Editor */
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
            padding: 8px 12px;
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
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
            margin: 4px 4px;
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

        .rte-editor img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 12px 0;
        }

        /* Signature Editor */
        .signature-editor {
            min-height: 120px;
            max-height: 200px;
        }

        /* Color Picker Dropdown */
        .color-picker-dropdown {
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

        /* Input with file attachment */
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
            border: 1px solid #d0d0d0;
            padding: 12px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #666;
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-attach-list:hover {
            background: #f5f5f5;
            border-color: #1a73e8;
            color: #1a73e8;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #999;
            font-style: italic;
        }

        /* Attachment Area */
        .attachment-area {
            border: 2px dashed #d0d0d0;
            border-radius: 7px;
            padding: 24px;
            text-align: center;
            background: #fafafa;
            transition: all 0.2s;
            cursor: pointer;
        }

        .attachment-area:hover {
            border-color: #1a73e8;
            background: #f5f5f5;
        }

        .attachment-area.dragover {
            border-color: #1a73e8;
            background: #e8f0fe;
        }

        .attachment-icon {
            font-size: 32px;
            color: #999;
            margin-bottom: 12px;
        }

        .attachment-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .attachment-subtext {
            color: #999;
            font-size: 12px;
        }

        .file-list {
            margin-top: 16px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .file-icon {
            width: 32px;
            height: 32px;
            background: #1a73e8;
            color: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            font-size: 14px;
            color: #1a1a1a;
        }

        .file-size {
            font-size: 12px;
            color: #999;
        }

        .file-remove {
            background: none;
            border: none;
            color: #d32f2f;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .file-remove:hover {
            background: #ffebee;
        }

        /* Buttons */
        .btn-send {
            background-color: #1a73e8;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .btn-send:hover {
            background-color: #1557b0;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.3);
        }

        .btn-preview {
            background-color: #ffffff;
            color: #1a1a1a;
            padding: 14px 32px;
            border: 1px solid #d0d0d0;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .btn-preview:hover {
            background-color: #f5f5f5;
            border-color: #1a73e8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-preview i, .btn-send i {
            margin-right: 6px;
        }

        /* Preview Modal */
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .preview-modal.active {
            display: flex;
        }

        .preview-content {
            background: white;
            border-radius: 10px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .preview-header {
            padding: 24px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .preview-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .preview-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .preview-close:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        .preview-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .preview-email-frame {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            overflow: hidden;
        }

        .preview-email-header {
            background: white;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e5e5;
        }

        .preview-email-meta {
            font-size: 13px;
            margin-bottom: 8px;
            color: #666;
        }

        .preview-email-meta strong {
            color: #1a1a1a;
            font-weight: 500;
        }

        .preview-email-content {
            padding: 24px;
            background: white;
        }

        .preview-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Hidden file inputs */
        input[type="file"] {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 20px;
            }
            
            .compose-card {
                padding: 24px;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f5f5f5;
        }

        ::-webkit-scrollbar-thumb {
            background: #d0d0d0;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b0b0b0;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content-area">
            <div class="compose-card">
                <h3>Compose Email</h3>
                <p class="compose-subtitle">Create and send professional emails with rich formatting</p>
                
                <form id="composeForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Recipient Email</label>
                        <input type="email" name="email" id="recipientEmail" required placeholder="recipient@example.com">
                    </div>

                    <div class="form-group">
                        <label>CC (if any)</label>
                        <div class="input-with-file">
                            <input type="text" name="cc" id="ccInput" placeholder="cc1@example.com, cc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('ccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="cc_file" id="ccFile" accept=".txt,.csv" onchange="handleEmailListUpload(this, 'ccInput')">
                        </div>
                        <small class="help-text">Separate emails with commas or upload text file</small>
                    </div>

                    <div class="form-group">
                        <label>BCC (if any)</label>
                        <div class="input-with-file">
                            <input type="text" name="bcc" id="bccInput" placeholder="bcc1@example.com, bcc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('bccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="bcc_file" id="bccFile" accept=".txt,.csv" onchange="handleEmailListUpload(this, 'bccInput')">
                        </div>
                        <small class="help-text">Separate emails with commas or upload text file</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" id="subject" required placeholder="Enter your mail subject">
                    </div>

                    <div class="form-group">
                        <label>Article Title</label>
                        <input type="text" name="articletitle" id="articletitle" required placeholder="Enter article title for template">
                    </div>
                    
                    <div class="form-group">
                        <label>Message</label>
                        <div class="rte-container">
                            <div class="rte-toolbar" id="messageToolbar">
                                <button type="button" class="rte-btn" data-command="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
                                <button type="button" class="rte-btn" data-command="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
                                <button type="button" class="rte-btn" data-command="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                                <button type="button" class="rte-btn" data-command="strikeThrough" title="Strikethrough"><i class="fa-solid fa-strikethrough"></i></button>
                                
                                <div class="rte-separator"></div>
                                
                                <div class="color-picker-dropdown">
                                    <button type="button" class="rte-btn" id="textColorBtn" title="Text Color">
                                        <i class="fa-solid fa-font" style="color: #000;"></i>
                                    </button>
                                    <div class="color-palette" id="textColorPalette"></div>
                                </div>
                                
                                <div class="color-picker-dropdown">
                                    <button type="button" class="rte-btn" id="bgColorBtn" title="Background Color">
                                        <i class="fa-solid fa-fill-drip"></i>
                                    </button>
                                    <div class="color-palette" id="bgColorPalette"></div>
                                </div>
                                
                                <div class="rte-separator"></div>
                                
                                <button type="button" class="rte-btn" data-command="insertUnorderedList" title="Bullet List"><i class="fa-solid fa-list-ul"></i></button>
                                <button type="button" class="rte-btn" data-command="insertOrderedList" title="Numbered List"><i class="fa-solid fa-list-ol"></i></button>
                                
                                <div class="rte-separator"></div>
                                
                                <button type="button" class="rte-btn" onclick="insertLink('message')" title="Insert Link"><i class="fa-solid fa-link"></i></button>
                                <button type="button" class="rte-btn" onclick="insertImage('message')" title="Insert Image"><i class="fa-solid fa-image"></i></button>
                                
                                <div class="rte-separator"></div>
                                
                                <button type="button" class="rte-btn" data-command="removeFormat" title="Clear Formatting"><i class="fa-solid fa-eraser"></i></button>
                            </div>
                            <div class="rte-editor" id="messageEditor" contenteditable="true" data-placeholder="Compose your message..."></div>
                        </div>
                        <input type="file" id="messageImageUpload" accept="image/*" onchange="handleImageUpload(this, 'message')">
                    </div>

                    <div class="form-group">
                        <label>Signature</label>
                        <div class="rte-container">
                            <div class="rte-toolbar" id="signatureToolbar">
                                <button type="button" class="rte-btn" data-command="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
                                <button type="button" class="rte-btn" data-command="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
                                <button type="button" class="rte-btn" data-command="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
                                
                                <div class="rte-separator"></div>
                                
                                <div class="color-picker-dropdown">
                                    <button type="button" class="rte-btn" id="sigTextColorBtn" title="Text Color">
                                        <i class="fa-solid fa-font" style="color: #000;"></i>
                                    </button>
                                    <div class="color-palette" id="sigTextColorPalette"></div>
                                </div>
                                
                                <div class="rte-separator"></div>
                                
                                <button type="button" class="rte-btn" onclick="insertLink('signature')" title="Insert Link"><i class="fa-solid fa-link"></i></button>
                                <button type="button" class="rte-btn" onclick="insertImage('signature')" title="Insert Image"><i class="fa-solid fa-image"></i></button>
                            </div>
                            <div class="rte-editor signature-editor" id="signatureEditor" contenteditable="true" data-placeholder="Add your signature..."></div>
                        </div>
                        <input type="file" id="signatureImageUpload" accept="image/*" onchange="handleImageUpload(this, 'signature')">
                        <small class="help-text">This signature will be automatically appended to your email</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachments (Optional)</label>
                        <div class="attachment-area" id="attachmentArea" onclick="document.getElementById('attachmentInput').click()">
                            <div class="attachment-icon">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div class="attachment-text">Click to browse or drag files here</div>
                            <div class="attachment-subtext">Multiple files supported</div>
                        </div>
                        <input type="file" name="attachments[]" id="attachmentInput" multiple onchange="handleAttachmentUpload(this)">
                        <div class="file-list" id="fileList"></div>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="btn-preview" id="previewBtn">
                            <i class="fa-solid fa-eye"></i> Preview Email
                        </button>
                        <button type="button" class="btn-send" id="sendBtn">
                            <i class="fa-solid fa-paper-plane"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="preview-modal" id="previewModal">
        <div class="preview-content">
            <div class="preview-header">
                <h3>Email Preview</h3>
                <button class="preview-close" onclick="closePreview()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="preview-body">
                <div class="preview-email-frame">
                    <div class="preview-email-header">
                        <div class="preview-email-meta">
                            <strong>To:</strong> <span id="previewTo"></span>
                        </div>
                        <div class="preview-email-meta" id="previewCcRow" style="display: none;">
                            <strong>CC:</strong> <span id="previewCc"></span>
                        </div>
                        <div class="preview-email-meta" id="previewBccRow" style="display: none;">
                            <strong>BCC:</strong> <span id="previewBcc"></span>
                        </div>
                        <div class="preview-email-meta">
                            <strong>Subject:</strong> <span id="previewSubject"></span>
                        </div>
                    </div>
                    <div class="preview-email-content" id="previewContent"></div>
                </div>
            </div>
            <div class="preview-footer">
                <button type="button" class="btn-preview" onclick="closePreview()">Close</button>
                <button type="button" class="btn-send" onclick="confirmSend()">
                    <i class="fa-solid fa-paper-plane"></i> Confirm & Send
                </button>
            </div>
        </div>
    </div>

    <script>
        // Custom Rich Text Editor Implementation
        class RichTextEditor {
            constructor(editorId, toolbarId) {
                this.editor = document.getElementById(editorId);
                this.toolbar = document.getElementById(toolbarId);
                this.init();
            }

            init() {
                // Add event listeners to toolbar buttons
                const buttons = this.toolbar.querySelectorAll('.rte-btn[data-command]');
                buttons.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const command = btn.getAttribute('data-command');
                        this.execCommand(command);
                        this.updateToolbarState();
                    });
                });

                // Update toolbar state on selection change
                this.editor.addEventListener('mouseup', () => this.updateToolbarState());
                this.editor.addEventListener('keyup', () => this.updateToolbarState());
            }

            execCommand(command, value = null) {
                this.editor.focus();
                document.execCommand(command, false, value);
            }

            updateToolbarState() {
                const buttons = this.toolbar.querySelectorAll('.rte-btn[data-command]');
                buttons.forEach(btn => {
                    const command = btn.getAttribute('data-command');
                    if (document.queryCommandState(command)) {
                        btn.classList.add('active');
                    } else {
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
        const messageRTE = new RichTextEditor('messageEditor', 'messageToolbar');
        const signatureRTE = new RichTextEditor('signatureEditor', 'signatureToolbar');

        // Color palettes
        const colors = [
            '#000000', '#444444', '#666666', '#999999', '#cccccc', '#eeeeee', '#f3f3f3', '#ffffff',
            '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#0000ff', '#9900ff', '#ff00ff',
            '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#cfe2f3', '#d9d2e9', '#ead1dc',
            '#ea9999', '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#9fc5e8', '#b4a7d6', '#d5a6bd',
            '#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af', '#6fa8dc', '#8e7cc3', '#c27ba0',
            '#cc0000', '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3d85c6', '#674ea7', '#a64d79',
            '#990000', '#b45f06', '#bf9000', '#38761d', '#134f5c', '#0b5394', '#351c75', '#741b47',
            '#660000', '#783f04', '#7f6000', '#274e13', '#0c343d', '#073763', '#20124d', '#4c1130'
        ];

        function createColorPalette(paletteId, isBackground = false) {
            const palette = document.getElementById(paletteId);
            colors.forEach(color => {
                const colorOption = document.createElement('div');
                colorOption.className = 'color-option';
                colorOption.style.backgroundColor = color;
                colorOption.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const editor = paletteId.includes('sig') ? signatureRTE : messageRTE;
                    if (isBackground) {
                        editor.execCommand('backColor', color);
                    } else {
                        editor.execCommand('foreColor', color);
                    }
                    palette.classList.remove('active');
                });
                palette.appendChild(colorOption);
            });
        }

        createColorPalette('textColorPalette', false);
        createColorPalette('bgColorPalette', true);
        createColorPalette('sigTextColorPalette', false);

        // Color picker toggle
        document.getElementById('textColorBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('textColorPalette').classList.toggle('active');
            document.getElementById('bgColorPalette').classList.remove('active');
        });

        document.getElementById('bgColorBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('bgColorPalette').classList.toggle('active');
            document.getElementById('textColorPalette').classList.remove('active');
        });

        document.getElementById('sigTextColorBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('sigTextColorPalette').classList.toggle('active');
        });

        // Close color pickers when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.color-palette').forEach(palette => {
                palette.classList.remove('active');
            });
        });

        // Load signature from PHP settings
        const defaultSignature = <?php echo json_encode($settings['signature']); ?>;
        if (defaultSignature) {
            signatureRTE.setHTML(defaultSignature);
        }

        // Insert link
        function insertLink(editorType) {
            const url = prompt('Enter URL:');
            if (url) {
                const text = prompt('Enter link text:', url);
                if (text) {
                    const editor = editorType === 'message' ? messageRTE : signatureRTE;
                    editor.insertHTML(`<a href="${url}" target="_blank">${text}</a>`);
                }
            }
        }

        // Insert image
        function insertImage(editorType) {
            const inputId = editorType === 'message' ? 'messageImageUpload' : 'signatureImageUpload';
            document.getElementById(inputId).click();
        }

        function handleImageUpload(input, editorType) {
            const file = input.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const editor = editorType === 'message' ? messageRTE : signatureRTE;
                editor.insertHTML(`<img src="${e.target.result}" alt="Image" style="max-width: 100%; height: auto;">`);
            };
            reader.readAsDataURL(file);
            
            input.value = '';
        }

        // Handle file attachments
        const attachedFiles = new DataTransfer();

        function handleAttachmentUpload(input) {
            const files = input.files;
            for (let i = 0; i < files.length; i++) {
                attachedFiles.items.add(files[i]);
            }
            displayFileList();
        }

        function displayFileList() {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            const files = attachedFiles.files;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <div class="file-info">
                        <div class="file-icon">
                            <i class="fa-solid fa-file"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name">${escapeHtml(file.name)}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="file-remove" onclick="removeFile(${i})">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                `;
                fileList.appendChild(fileItem);
            }
        }

        function removeFile(index) {
            const newFiles = new DataTransfer();
            const files = attachedFiles.files;
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    newFiles.items.add(files[i]);
                }
            }
            attachedFiles.items.clear();
            for (let i = 0; i < newFiles.files.length; i++) {
                attachedFiles.items.add(newFiles.files[i]);
            }
            displayFileList();
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Drag and drop
        const attachmentArea = document.getElementById('attachmentArea');
        
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
            
            const files = e.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                attachedFiles.items.add(files[i]);
            }
            displayFileList();
        });

        // Handle Email List File Upload
        function handleEmailListUpload(fileInput, targetInputId) {
            const file = fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                let emails = content
                    .split(/[,;\n\r]+/)
                    .map(email => email.trim())
                    .filter(email => email.length > 0);

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

        // Preview functionality
        document.getElementById('previewBtn').addEventListener('click', () => {
            const recipientEmail = document.getElementById('recipientEmail').value;
            const cc = document.getElementById('ccInput').value;
            const bcc = document.getElementById('bccInput').value;
            const subject = document.getElementById('subject').value;

            if (!recipientEmail || !subject) {
                alert('Please fill in Recipient and Subject fields before previewing.');
                return;
            }

            const messageHtml = messageRTE.getHTML();
            const signatureHtml = signatureRTE.getHTML();
            
            let fullContent = messageHtml;
            if (signatureHtml && signatureHtml !== '' && !signatureHtml.match(/^<[^>]+><br><\/[^>]+>$/)) {
                fullContent += '<br><br><div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">' + signatureHtml + '</div>';
            }

            document.getElementById('previewTo').textContent = recipientEmail;
            document.getElementById('previewSubject').textContent = subject;
            document.getElementById('previewContent').innerHTML = fullContent;

            if (cc) {
                document.getElementById('previewCc').textContent = cc;
                document.getElementById('previewCcRow').style.display = 'block';
            } else {
                document.getElementById('previewCcRow').style.display = 'none';
            }

            if (bcc) {
                document.getElementById('previewBcc').textContent = bcc;
                document.getElementById('previewBccRow').style.display = 'block';
            } else {
                document.getElementById('previewBccRow').style.display = 'none';
            }

            document.getElementById('previewModal').classList.add('active');
        });

        function closePreview() {
            document.getElementById('previewModal').classList.remove('active');
        }

        function confirmSend() {
            closePreview();
            sendEmail();
        }

        // Send email
        document.getElementById('sendBtn').addEventListener('click', sendEmail);

        function sendEmail() {
            const recipientEmail = document.getElementById('recipientEmail').value;
            const subject = document.getElementById('subject').value;
            const articletitle = document.getElementById('articletitle').value;

            if (!recipientEmail || !subject || !articletitle) {
                alert('Please fill in all required fields.');
                return;
            }

            const messageHtml = messageRTE.getHTML();
            const signatureHtml = signatureRTE.getHTML();
            
            let fullMessage = messageHtml;
            if (signatureHtml && signatureHtml !== '' && !signatureHtml.match(/^<[^>]+><br><\/[^>]+>$/)) {
                fullMessage += '<br><br>' + signatureHtml;
            }

            const formData = new FormData();
            formData.append('email', recipientEmail);
            formData.append('cc', document.getElementById('ccInput').value);
            formData.append('bcc', document.getElementById('bccInput').value);
            formData.append('subject', subject);
            formData.append('articletitle', articletitle);
            formData.append('message', fullMessage);
            formData.append('message_is_html', 'true');

            const files = attachedFiles.files;
            for (let i = 0; i < files.length; i++) {
                formData.append('attachments[]', files[i]);
            }

            fetch('send.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                document.open();
                document.write(html);
                document.close();
            })
            .catch(error => {
                alert('Error sending email: ' + error);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Close modal on background click
        document.getElementById('previewModal').addEventListener('click', (e) => {
            if (e.target.id === 'previewModal') {
                closePreview();
            }
        });
    </script>
</body>
</html>