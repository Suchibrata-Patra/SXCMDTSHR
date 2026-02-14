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

// Load settings from database
require_once 'settings_helper.php';
$userEmail = $_SESSION['smtp_user'];
$settings = getSettingsWithDefaults($userEmail);

// --- Signature field defaults derived from user_settings ---

// Closing wish: use the stored 'signature' key as the wish line, or fall back to empty
$sig_wish        = htmlspecialchars($settings['signature'] ?? '', ENT_QUOTES, 'UTF-8');

// Full name from display_name
$sig_name        = htmlspecialchars($settings['display_name'] ?? '', ENT_QUOTES, 'UTF-8');

// Designation / title
$sig_designation = htmlspecialchars($settings['designation'] ?? '', ENT_QUOTES, 'UTF-8');

// Build extra info from available identity fields (dept, room_no, ext_no, staff_id)
$extraParts = [];
if (!empty($settings['dept']))     $extraParts[] = htmlspecialchars($settings['dept'],     ENT_QUOTES, 'UTF-8');
if (!empty($settings['room_no'])) $extraParts[] = 'Room ' . htmlspecialchars($settings['room_no'], ENT_QUOTES, 'UTF-8');
if (!empty($settings['ext_no']))  $extraParts[] = 'Ext. ' . htmlspecialchars($settings['ext_no'],  ENT_QUOTES, 'UTF-8');
if (!empty($settings['staff_id'])) $extraParts[] = 'ID: '  . htmlspecialchars($settings['staff_id'], ENT_QUOTES, 'UTF-8');
$sig_extra = implode("\n", $extraParts);

// Merge with legacy defaults for any code that still reads $settings
$settings = array_merge([
    'signature'              => '',
    'default_subject_prefix' => '',
    'cc_yourself'            => false,
    'smtp_host'              => 'smtp.gmail.com',
    'smtp_port'              => '587',
    'display_name'           => ''
], $settings);

// Update session settings for immediate use
$_SESSION['user_settings'] = $settings;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --ink:        #1a1a2e;
            --ink-2:      #2d2d44;
            --ink-3:      #6b6b8a;
            --ink-4:      #a8a8c0;
            --bg:         #f0f0f7;
            --surface:    #ffffff;
            --surface-2:  #f7f7fc;
            --border:     rgba(100,100,160,0.12);
            --border-2:   rgba(100,100,160,0.22);
            --blue:       #4875c1;
            --blue-2:     #c6d3ea;
            --blue-glow:  rgba(79,70,229,0.15);
            --red:        #ef4444;
            --green:      #10b981;
            --amber:      #f59e0b;
            --r:          10px;
            --r-lg:       16px;
            --shadow:     0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg:  0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:       cubic-bezier(.4,0,.2,1);
            --ease-spring:cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── LAYOUT ── */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: var(--bg);
        }
        .content-area::-webkit-scrollbar { width: 5px; }
        .content-area::-webkit-scrollbar-track { background: transparent; }
        .content-area::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 10px; }

        /* ── TOP BAR ── */
        .page-header {
            height: 60px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        .header-container {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .page-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-title .material-icons-round { font-size: 20px; color: var(--blue); }

        .page-subtitle {
            font-size: 13px;
            color: var(--ink-4);
            font-weight: 400;
            padding-left: 8px;
            border-left: 1px solid var(--border-2);
            margin-left: 4px;
        }

        /* ── COMPOSE CONTAINER ── */
        .compose-container {
            max-width: 860px;
            margin: 0 auto;
            padding: 28px 28px 48px;
        }

        /* ── FORM SECTIONS ── */
        .form-section {
            background: var(--surface);
            border-radius: var(--r-lg);
            padding: 22px 24px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: box-shadow .2s;
        }
        .form-section:focus-within {
            box-shadow: var(--shadow-lg);
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink-3);
            text-transform: uppercase;
            letter-spacing: .7px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .section-title .material-icons-round { font-size: 15px; color: var(--blue); }

        /* ── FORM GROUPS ── */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group:last-child { margin-bottom: 0; }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--ink-2);
            font-size: 13px;
            letter-spacing: -.1px;
        }

        .label-optional {
            font-size: 12px;
            color: var(--ink-4);
            font-weight: 400;
            margin-left: 4px;
        }

        .field-description {
            font-size: 12px;
            color: var(--ink-4);
            margin-top: 5px;
        }

        /* ── INPUT FIELDS ── */
        input[type="email"],
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border-2);
            border-radius: var(--r);
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            background: var(--surface-2);
            color: var(--ink);
            transition: border-color .2s, box-shadow .2s, background .2s;
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
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
            background: var(--surface);
        }

        input::placeholder,
        textarea::placeholder { color: var(--ink-4); }

        /* Two-column grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* ── FILE UPLOAD AREA ── */
        .file-upload-area {
            border: 2px dashed var(--border-2);
            border-radius: var(--r-lg);
            padding: 32px;
            text-align: center;
            background: var(--surface-2);
            transition: all .2s var(--ease);
            cursor: pointer;
        }

        .file-upload-area:hover,
        .file-upload-area.drag-over {
            border-color: var(--blue);
            background: var(--blue-glow);
        }

        .file-upload-icon {
            font-size: 44px;
            color: var(--blue);
            margin-bottom: 10px;
        }

        .file-upload-text {
            font-size: 14px;
            color: var(--ink-2);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .file-upload-hint {
            font-size: 12px;
            color: var(--ink-4);
        }

        input[type="file"] { display: none; }

        /* ── UPLOAD PROGRESS ── */
        .upload-item {
            background: var(--surface);
            border: 1.5px solid var(--border-2);
            border-radius: var(--r);
            padding: 12px 16px;
            margin-top: 12px;
            display: none;
            animation: rowFadeIn .2s var(--ease) both;
        }
        .upload-item.active { display: block; }

        .upload-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .upload-filename {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .upload-percentage {
            font-size: 12px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
        }

        .upload-progress-bar-container {
            height: 5px;
            background: var(--surface-2);
            border-radius: 3px;
            overflow: hidden;
        }

        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--blue), var(--blue-2));
            transition: width 0.3s var(--ease);
            width: 0%;
            border-radius: 3px;
        }

        /* ── FILE PREVIEW CARDS ── */
        .file-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .file-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--r-lg);
            padding: 16px 14px 14px;
            position: relative;
            transition: all .18s var(--ease);
            cursor: pointer;
            text-align: center;
            animation: rowFadeIn .2s var(--ease) both;
        }
        .file-card::before {
            content: '';
            position: absolute; inset: 0;
            border-radius: var(--r-lg);
            background: linear-gradient(135deg, rgba(79,70,229,.04), transparent);
            opacity: 0; transition: opacity .2s;
        }
        .file-card:hover { border-color: var(--blue); box-shadow: var(--shadow-lg); transform: translateY(-2px); }
        .file-card:hover::before { opacity: 1; }

        .file-card-icon {
            font-size: 44px;
            color: var(--blue);
            margin-bottom: 10px;
        }

        .file-card-name {
            font-size: 12px;
            color: var(--ink);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
        }

        .file-card-size {
            font-size: 11px;
            color: var(--ink-4);
            margin-bottom: 8px;
            font-family: 'DM Mono', monospace;
        }

        .file-card-badge {
            display: inline-block;
            font-size: 10px;
            padding: 3px 8px;
            background: rgba(16,185,129,.1);
            color: var(--green);
            border-radius: 20px;
            font-weight: 700;
            letter-spacing: .3px;
            border: 1px solid rgba(16,185,129,.2);
        }

        .file-card-remove {
            position: absolute;
            top: 7px; right: 7px;
            width: 22px; height: 22px;
            background: rgba(239,68,68,.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity .18s;
        }
        .file-card:hover .file-card-remove { opacity: 1; }
        .file-card-remove .material-icons { font-size: 14px; color: white; }

        .file-card-download {
            margin-top: 8px;
            font-size: 11px;
            color: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-weight: 600;
        }
        .file-card-download .material-icons { font-size: 14px; }

        .size-warning {
            background: rgba(245,158,11,.08);
            border: 1.5px solid rgba(245,158,11,.3);
            border-radius: var(--r);
            padding: 11px 14px;
            margin-top: 12px;
            font-size: 13px;
            color: #92400e;
            display: none;
            align-items: center;
            gap: 8px;
        }
        .size-warning.active { display: flex; }
        .size-warning .material-icons { font-size: 18px; color: var(--amber); flex-shrink: 0; }

        /* ── RICH TEXT EDITOR ── */
        .editor-wrapper {
            border: 1.5px solid var(--border-2);
            border-radius: var(--r);
            overflow: hidden;
            background: var(--surface);
            transition: border-color .2s, box-shadow .2s;
        }
        .editor-wrapper:focus-within {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }

        .ql-toolbar {
            border: none !important;
            border-bottom: 1px solid var(--border) !important;
            background: var(--surface-2);
        }

        .ql-container {
            border: none !important;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            min-height: 200px;
        }

        /* ── ACTION BUTTONS ── */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            height: 42px;
            padding: 0 22px;
            border: none;
            border-radius: var(--r);
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all .18s var(--ease);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
            box-shadow: 0 2px 8px var(--blue-glow);
        }
        .btn-primary:hover { background: #3a62a8; box-shadow: 0 4px 16px var(--blue-glow); transform: translateY(-1px); }
        .btn-primary:active { transform: scale(.97); }

        .btn-secondary {
            background: var(--surface-2);
            color: var(--ink-2);
            border: 1.5px solid var(--border-2);
        }
        .btn-secondary:hover { background: var(--bg); border-color: var(--blue); color: var(--blue); }

        .btn .material-icons { font-size: 17px; }
        .btn .material-icons-round { font-size: 17px; }

        /* ── ANIMATIONS ── */
        @keyframes rowFadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .compose-container { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .file-cards-container { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
            .page-subtitle { display: none; }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <!-- TOP BAR -->
        <div class="page-header">
            <div class="header-container">
                <h1 class="page-title">
                    <span class="material-icons-round">edit_note</span>
                    Compose Email
                </h1>
                <span class="page-subtitle">Send professional emails with templates and signatures</span>
            </div>
        </div>

        <div class="content-area">
            <!-- Compose Form -->
            <div class="compose-container">
                <form id="composeForm" method="POST" action="send.php" enctype="multipart/form-data">
                    
                    <!-- Recipients Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="material-icons-round">group</span>
                            Recipients
                        </h3>
                        
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
                        <h3 class="section-title">
                            <span class="material-icons-round">subject</span>
                            Email Details
                        </h3>
                        
                        <div class="form-group">
                            <label for="subject">
                                Subject
                                <span style="color: #FF3B30;">*</span>
                            </label>
                            <input type="text" name="subject" id="subject" 
                                   placeholder="Enter email subject" value="Invitation to the Campus as Guest" required>
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
                        <h3 class="section-title">
                            <span class="material-icons-round">chat_bubble_outline</span>
                            Message
                        </h3>
                        
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
                        <h3 class="section-title">
                            <span class="material-icons-round">draw</span>
                            Email Signature
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="signatureWish">Closing Wish</label>
                                <input type="text" id="signatureWish" name="signatureWish"
                                    placeholder="e.g., Best Regards, Warm Wishes"
                                    value="<?= $sig_wish ?>">
                                <p class="field-description">Your closing greeting</p>
                            </div>

                            <div class="form-group">
                                <label for="signatureName">Name</label>
                                <input type="text" id="signatureName" name="signatureName"
                                    placeholder="e.g., Durba Bhattacharya"
                                    value="<?= $sig_name ?>">
                                <p class="field-description">Your full name</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="signatureDesignation">Designation</label>
                            <input type="text" id="signatureDesignation" name="signatureDesignation"
                                placeholder="e.g., H.O.D of SXC MDTS"
                                value="<?= $sig_designation ?>">
                            <p class="field-description">Your title or position</p>
                        </div>

                        <div class="form-group">
                            <label for="signatureExtra">Additional Information <span class="label-optional">(optional)</span></label>
                            <textarea id="signatureExtra" name="signatureExtra" rows="3"
                                placeholder="Any extra details like contact info, department, etc."><?= $sig_extra ?></textarea>
                            <p class="field-description">Additional text that will appear in your signature</p>
                        </div>
                    </div>

                    <!-- Attachments Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <span class="material-icons-round">attach_file</span>
                            Attachments
                        </h3>
                        
                        <div class="file-upload-area" id="fileUploadArea">
                            <span class="material-icons-round file-upload-icon">cloud_upload</span>
                            <p class="file-upload-text">Drop files here or click to upload</p>
                            <p class="file-upload-hint">Maximum 20MB per file · 25MB total</p>
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
                            <span class="material-icons-round">warning_amber</span>
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
                            <span class="material-icons-round">preview</span>
                            Preview Email
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-icons-round">send</span>
                            Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div><!-- /.content-area -->
    </div><!-- /.main-content -->

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
                <span class="material-icons-round file-card-icon">${icon}</span>
                <div class="file-card-name" title="${fileData.original_name}">${fileData.original_name}</div>
                <div class="file-card-size">${fileData.formatted_size}</div>
                ${fileData.deduplicated ? '<div class="file-card-badge">✓ Reused</div>' : ''}
                <div class="file-card-download">
                    <span class="material-icons-round">download</span>
                    <span>Download</span>
                </div>
                <div class="file-card-remove">
                    <span class="material-icons-round" style="font-size:14px;color:white">close</span>
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