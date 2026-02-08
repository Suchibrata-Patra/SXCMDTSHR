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
                                   placeholder="recipient@example.com" value="suchibratapatra2003@gmail.com" required>
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
                                   placeholder="Enter email subject" value="Invitatino to the Campus as Guest" required>
                        </div>

                        <div class="form-group">
                            <label for="articletitle">
                                Article Title
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" name="articletitle" id="articletitle" 
                                   placeholder="Article or document title" value="This is the email Tagline" required>
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
                    <div class="form-section">
                        <h3 class="section-title">Attachments</h3>
                        
                        <div class="file-upload-area" id="fileUploadArea">
                            <div class="material-icons file-upload-icon">cloud_upload</div>
                            <p class="file-upload-text">Click to upload or drag and drop</p>
                            <p class="file-upload-hint">Maximum 20MB per file, 25MB total</p>
                            <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.txt,.csv">
                        </div>

                        <!-- Individual Upload Progress -->
                        <div class="upload-item" id="uploadItem">
                            <div class="upload-item-header">
                                <span class="upload-filename" id="uploadFilename"></span>
                                <span class="upload-percentage" id="uploadPercentage">0%</span>
                            </div>
                            <div class="upload-progress-bar-container">
                                <div class="upload-progress-bar" id="uploadProgressBar"></div>
                            </div>
                        </div>
                        
                        <!-- Size Warning -->
                        <div class="size-warning" id="sizeWarning">
                            <span class="material-icons" style="vertical-align: middle; font-size: 18px;">warning</span>
                            <span id="sizeWarningText"></span>
                        </div>

                        <!-- File Preview Cards -->
                        <div class="file-cards-container" id="fileCardsContainer"></div>
                        
                        <!-- Hidden input for attachment IDs -->
                        <input type="hidden" name="attachment_ids" id="attachmentIds" value="">
                    </div>

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