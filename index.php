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

        input[type="email"]:focus,
        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        input::placeholder {
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

        .file-upload-area:hover {
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

        /* ========== FILE PREVIEW GRID ========== */
        .file-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .file-preview-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            position: relative;
            transition: all 0.2s;
        }

        .file-preview-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .file-preview-icon {
            font-size: 32px;
            color: var(--apple-blue);
            margin-bottom: 8px;
        }

        .file-preview-name {
            font-size: 12px;
            color: #1c1c1e;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .file-preview-size {
            font-size: 11px;
            color: var(--apple-gray);
        }

        .file-remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #FF3B30;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .file-preview-item:hover .file-remove-btn {
            opacity: 1;
        }

        /* ========== EMAIL LIST UPLOAD ========== */
        .email-list-upload {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #F2F2F7;
            border-radius: 6px;
            font-size: 13px;
            color: var(--apple-blue);
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-weight: 500;
            margin-top: 8px;
        }

        .email-list-upload:hover {
            background: #E5E5EA;
        }

        .email-list-upload .material-icons {
            font-size: 16px;
        }

        /* ========== RICH TEXT EDITOR ========== */
        .editor-wrapper {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            transition: all 0.2s;
            overflow: hidden;
        }

        .editor-wrapper.focused {
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* Fix for Quill toolbar select boxes stacking */
        .ql-toolbar select {
            width: auto !important;
            display: inline-block !important;
        }

        /* Ensure the toolbar doesn't hide behind other elements */
        .ql-toolbar.ql-snow {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }

        .ql-toolbar {
            border: none;
            border-bottom: 1px solid var(--border);
            background: #FAFAFA;
            padding: 12px;
        }

        .ql-container {
            border: none;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            min-height: 250px;
        }

        .ql-editor {
            min-height: 250px;
            padding: 16px;
        }

        .ql-editor.ql-blank::before {
            color: var(--apple-gray);
            font-style: normal;
        }

        /* Signature editor smaller */
        #signatureContainer .ql-container {
            min-height: 120px;
        }

        #signatureContainer .ql-editor {
            min-height: 120px;
        }

        /* Quill toolbar buttons */
        .ql-toolbar button {
            border-radius: 4px;
        }

        .ql-toolbar button:hover {
            background: rgba(0, 122, 255, 0.1);
        }

        .ql-toolbar button.ql-active {
            background: rgba(0, 122, 255, 0.15);
            color: var(--apple-blue);
        }

        /* ========== ACTION BUTTONS ========== */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'Inter', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn .material-icons {
            font-size: 18px;
        }

        .btn-preview {
            background: white;
            color: #1c1c1e;
            border: 1px solid var(--border);
        }

        .btn-preview:hover {
            background: #F2F2F7;
        }

        .btn-send {
            background: var(--apple-blue);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
        }

        .btn-send:hover {
            background: #0051D5;
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
            transform: translateY(-1px);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        /* ========== COLLAPSIBLE SECTIONS ========== */
        .section-toggle {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-toggle .material-icons {
            font-size: 20px;
            color: var(--apple-gray);
            transition: transform 0.2s;
        }

        .section-toggle.collapsed .material-icons {
            transform: rotate(-90deg);
        }

        .section-content {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .section-content.collapsed {
            max-height: 0;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .compose-container {
                padding: 20px;
            }

            .page-header {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .file-preview-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }

        /* ========== SCROLLBAR ========== */
        .content-area::-webkit-scrollbar {
            width: 8px;
        }

        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .content-area::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .content-area::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
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
                    <h1 class="page-title">Compose Email</h1>
                    <p class="page-subtitle">Create and send professional emails to your contacts</p>
                </div>
            </div>

            <!-- Compose Form -->
            <div class="compose-container">
                <form id="composeForm" action="send.php" method="POST" enctype="multipart/form-data">
                    <!-- Recipients Section -->
                    <div class="form-section">
                        <h2 class="section-title">Recipients</h2>

                        <div class="form-group">
                            <label for="email">
                                To
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="email" id="email" name="email" placeholder="recipient@example.com" required>
                            <label for="toFileUpload" class="email-list-upload">
                                <span class="material-icons">upload_file</span>
                                Import from file
                            </label>
                            <input type="file" id="toFileUpload" accept=".txt,.csv"
                                onchange="handleEmailListUpload(this, 'email')">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cc">
                                    CC
                                    <span class="label-optional">(Optional)</span>
                                </label>
                                <input type="text" id="cc" name="cc" placeholder="cc@example.com">
                                <label for="ccFileUpload" class="email-list-upload">
                                    <span class="material-icons">upload_file</span>
                                    Import from file
                                </label>
                                <input type="file" id="ccFileUpload" accept=".txt,.csv"
                                    onchange="handleEmailListUpload(this, 'cc')">
                            </div>

                            <div class="form-group">
                                <label for="bcc">
                                    BCC
                                    <span class="label-optional">(Optional)</span>
                                </label>
                                <input type="text" id="bcc" name="bcc" placeholder="bcc@example.com">
                                <label for="bccFileUpload" class="email-list-upload">
                                    <span class="material-icons">upload_file</span>
                                    Import from file
                                </label>
                                <input type="file" id="bccFileUpload" accept=".txt,.csv"
                                    onchange="handleEmailListUpload(this, 'bcc')">
                            </div>
                        </div>
                    </div>

                    <!-- Email Details Section -->
                    <div class="form-section">
                        <h2 class="section-title">Email Details</h2>

                        <div class="form-group">
                            <label for="subject">
                                Subject
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" id="subject" name="subject" placeholder="Enter email subject" required>
                        </div>

                        <div class="form-group">
                            <label for="articletitle">
                                Article Title
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" id="articletitle" name="articletitle" placeholder="Enter article title"
                                required>
                            <p class="field-description">This will appear as the main heading in your email</p>
                        </div>
                    </div>

                    <!-- Message Section -->
                    <div class="form-section">
                        <h2 class="section-title">Message</h2>

                        <div class="form-group">
                            <div class="editor-wrapper" id="editorContainer">
                                <div id="toolbar">
                                    <select class="ql-header">
                                        <option value="1">Heading 1</option>
                                        <option value="2">Heading 2</option>
                                        <option value="3">Heading 3</option>
                                        <option selected>Normal</option>
                                    </select>
                                    <button class="ql-bold"></button>
                                    <button class="ql-italic"></button>
                                    <button class="ql-underline"></button>
                                    <button class="ql-strike"></button>
                                    <select class="ql-color"></select>
                                    <select class="ql-background"></select>
                                    <button class="ql-list" value="ordered"></button>
                                    <button class="ql-list" value="bullet"></button>
                                    <select class="ql-align"></select>
                                    <button class="ql-link"></button>
                                    <button class="ql-image"></button>
                                    <button class="ql-clean"></button>
                                </div>
                                <div id="editor"></div>
                            </div>
                            <input type="hidden" name="message" id="messageInput">
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <!-- Signature Section -->
                    <div class="form-section">
                        <div class="section-toggle" onclick="toggleSection('signatureSection')">
                            <span class="material-icons">expand_more</span>
                            <h2 class="section-title" style="margin: 0;">Email Signature</h2>
                        </div>

                        <div class="section-content" id="signatureSection">
                            <div class="form-group" style="margin-top: 16px;">
                                <label for="signatureWish">Closing Wish</label>
                                <input type="text" id="signatureWish" name="signatureWish" class="form-input"
                                    placeholder="e.g., Best Regards, Warm Wishes, Sincerely">
                                <p class="field-description">Your closing greeting</p>
                            </div>

                            <div class="form-group">
                                <label for="signatureName">Name</label>
                                <input type="text" id="signatureName" name="signatureName" class="form-input"
                                    placeholder="e.g., Durba Bhattacharya">
                                <p class="field-description">Your full name</p>
                            </div>

                            <div class="form-group">
                                <label for="signatureDesignation">Designation</label>
                                <input type="text" id="signatureDesignation" name="signatureDesignation"
                                    class="form-input" placeholder="e.g., H.O.D of SXC MDTS">
                                <p class="field-description">Your title or position</p>
                            </div>

                            <div class="form-group">
                                <label for="signatureExtra">Additional Text (Optional)</label>
                                <textarea id="signatureExtra" name="signatureExtra" class="form-input" rows="3"
                                    placeholder="Any additional information"></textarea>
                                <p class="field-description">Extra details like contact info, department, etc.</p>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Add to your existing form submission logic
                        document.addEventListener('DOMContentLoaded', function () {
                            const form = document.getElementById('emailForm'); // Adjust to your form ID

                            if (form) {
                                const originalSubmit = form.onsubmit;

                                form.onsubmit = function (e) {
                                    // Build signature from components
                                    const wish = document.getElementById('signatureWish').value.trim();
                                    const name = document.getElementById('signatureName').value.trim();
                                    const designation = document.getElementById('signatureDesignation').value.trim();
                                    const extra = document.getElementById('signatureExtra').value.trim();

                                    // Create formatted signature
                                    let signature = '';
                                    if (wish) signature += wish + '\n';
                                    if (name) signature += name + '\n';
                                    if (designation) signature += designation + '\n';
                                    if (extra) signature += '\n' + extra;

                                    // Create hidden input for signature if it doesn't exist
                                    let signatureInput = document.getElementById('hiddenSignature');
                                    if (!signatureInput) {
                                        signatureInput = document.createElement('input');
                                        signatureInput.type = 'hidden';
                                        signatureInput.id = 'hiddenSignature';
                                        signatureInput.name = 'signature';
                                        form.appendChild(signatureInput);
                                    }
                                    signatureInput.value = signature;

                                    // Also send individual components
                                    const components = ['signatureWish', 'signatureName', 'signatureDesignation', 'signatureExtra'];
                                    components.forEach(comp => {
                                        let input = document.getElementById('hidden_' + comp);
                                        if (!input) {
                                            input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.id = 'hidden_' + comp;
                                            input.name = comp;
                                            form.appendChild(input);
                                        }
                                        input.value = document.getElementById(comp).value;
                                    });

                                    // Call original submit handler if exists
                                    if (originalSubmit) {
                                        return originalSubmit.call(form, e);
                                    }
                                };
                            }
                        });
                    </script>

                    <!-- Attachments Section -->
                    <div class="form-section">
                        <div class="section-toggle" onclick="toggleSection('attachmentsSection')">
                            <span class="material-icons">expand_more</span>
                            <h2 class="section-title" style="margin: 0;">Attachments</h2>
                        </div>

                        <div class="section-content" id="attachmentsSection">
                            <div class="form-group" style="margin-top: 16px;">
                                <label for="attachments" class="file-upload-area">
                                    <div class="material-icons file-upload-icon">cloud_upload</div>
                                    <div class="file-upload-text">Click to upload or drag files here</div>
                                    <div class="file-upload-hint">Supported: PDF, DOC, XLS, Images, ZIP (Max 25MB)</div>
                                </label>
                                <input type="file" id="attachments" name="attachments[]" multiple
                                    onchange="handleFileSelect(event)">
                                <div id="filePreviewGrid" class="file-preview-grid"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-preview" id="previewBtn">
                            <span class="material-icons">visibility</span>
                            Preview Email
                        </button>
                        <button type="submit" class="btn btn-send">
                            <span class="material-icons">send</span>
                            Send Email
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

        // Section toggle functionality
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const toggle = section.previousElementSibling;

            section.classList.toggle('collapsed');
            toggle.classList.toggle('collapsed');
        }

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
                    <span class="material-icons file-preview-icon">${icon}</span>
                    <div class="file-preview-name">${escapeHtml(file.name)}</div>
                    <div class="file-preview-size">${size}</div>
                    <button type="button" class="file-remove-btn" onclick="removeFile(${index})" title="Remove file">
                        <span class="material-icons" style="font-size: 14px;">close</span>
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
            return iconMap[ext] || 'insert_drive_file';
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