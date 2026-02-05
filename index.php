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
    <title>MailDash | Compose Message</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:ital,wght@0,400;0,600;0,700;1,400&family=Lora:ital,wght@0,400..700;1,400..700&family=Spectral:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Spectral', 'Crimson Text', Georgia, serif;
            background: linear-gradient(135deg, #f5f3ed 0%, #ebe8dd 100%);
            color: #2d3e2e;
            display: flex;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(139, 154, 102, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(106, 124, 89, 0.04) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(163, 146, 118, 0.02) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .content-area {
            flex: 1;
            padding: 60px 80px;
            overflow-y: auto;
            background: transparent;
        }

        /* Decorative Header */
        .page-header {
            text-align: center;
            margin-bottom: 48px;
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 3px;
            background: linear-gradient(90deg, transparent, #8b9a66, transparent);
        }

        .page-header h1 {
            font-family: 'Lora', 'Crimson Text', serif;
            font-size: 42px;
            font-weight: 600;
            color: #2d3e2e;
            margin-bottom: 12px;
            letter-spacing: 1px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .page-header .subtitle {
            font-family: 'Spectral', serif;
            font-size: 16px;
            font-style: italic;
            color: #6a7c59;
            letter-spacing: 0.5px;
        }

        /* Compose Card */
        .compose-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            padding: 56px 64px;
            border-radius: 8px;
            border: 1px solid rgba(139, 154, 102, 0.15);
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 
                0 20px 60px rgba(45, 62, 46, 0.08),
                0 0 1px rgba(139, 154, 102, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            position: relative;
        }

        .compose-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 40px;
            right: 40px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139, 154, 102, 0.3), transparent);
        }

        .compose-card::after {
            content: '✦';
            position: absolute;
            top: 32px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: #a39276;
            opacity: 0.4;
        }

        .compose-card h3 {
            font-family: 'Lora', serif;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2d3e2e;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .compose-subtitle {
            color: #6a7c59;
            font-size: 15px;
            font-style: italic;
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px solid rgba(139, 154, 102, 0.15);
            text-align: center;
            letter-spacing: 0.3px;
        }

        .form-group {
            margin-bottom: 32px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 12px;
            font-weight: 500;
            color: #3d5438;
            font-size: 15px;
            letter-spacing: 0.5px;
            font-family: 'Spectral', serif;
        }

        label::before {
            content: '◆';
            display: inline-block;
            margin-right: 8px;
            font-size: 8px;
            color: #8b9a66;
            opacity: 0.6;
            vertical-align: middle;
        }

        input[type="email"], 
        input[type="text"], 
        textarea, 
        select {
            width: 100%;
            padding: 16px 20px;
            border: 1.5px solid #d4cdb8;
            border-radius: 6px;
            font-size: 15px;
            font-family: 'Spectral', Georgia, serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(to bottom, #fdfcf9, #ffffff);
            color: #2d3e2e;
            box-shadow: 
                inset 0 1px 3px rgba(0,0,0,0.04),
                0 1px 0 rgba(255,255,255,0.8);
        }

        input[type="email"]:focus, 
        input[type="text"]:focus, 
        textarea:focus, 
        select:focus {
            outline: none;
            border-color: #8b9a66;
            box-shadow: 
                0 0 0 3px rgba(139, 154, 102, 0.1),
                inset 0 1px 3px rgba(0,0,0,0.04),
                0 1px 0 rgba(255,255,255,0.8);
            background: #ffffff;
        }

        input::placeholder,
        textarea::placeholder {
            color: #a39276;
            opacity: 0.6;
            font-style: italic;
        }

        textarea {
            min-height: 240px;
            resize: vertical;
            line-height: 1.8;
            font-size: 15px;
        }

        input[type="file"] {
            padding: 12px 0;
            font-size: 14px;
            font-family: 'Spectral', serif;
            color: #3d5438;
        }

        input[type="file"]::file-selector-button {
            padding: 10px 20px;
            border: 1.5px solid #d4cdb8;
            border-radius: 4px;
            background: linear-gradient(to bottom, #fdfcf9, #f5f3ed);
            color: #3d5438;
            font-family: 'Spectral', serif;
            font-weight: 500;
            cursor: pointer;
            margin-right: 16px;
            transition: all 0.2s;
        }

        input[type="file"]::file-selector-button:hover {
            background: #8b9a66;
            color: #ffffff;
            border-color: #8b9a66;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 16px;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 1px solid rgba(139, 154, 102, 0.15);
        }

        .btn-send {
            background: linear-gradient(135deg, #6a7c59 0%, #5a6b4a 100%);
            color: #ffffff;
            padding: 18px 40px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            font-family: 'Spectral', serif;
            flex: 1;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            box-shadow: 
                0 4px 12px rgba(106, 124, 89, 0.25),
                inset 0 1px 0 rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .btn-send::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-send:hover::before {
            left: 100%;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 8px 20px rgba(106, 124, 89, 0.35),
                inset 0 1px 0 rgba(255,255,255,0.2);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        .btn-preview {
            background: rgba(255, 255, 255, 0.95);
            color: #3d5438;
            padding: 18px 40px;
            border: 1.5px solid #d4cdb8;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            font-family: 'Spectral', serif;
            flex: 1;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(45, 62, 46, 0.08);
        }

        .btn-preview:hover {
            background: #8b9a66;
            border-color: #8b9a66;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 154, 102, 0.25);
        }

        .btn-preview:active {
            transform: translateY(0);
        }

        .btn-preview i {
            margin-right: 8px;
        }

        .btn-send i {
            margin-right: 8px;
        }

        /* Input with file attachment */
        .input-with-file {
            display: flex;
            gap: 12px;
            align-items: stretch;
        }

        .input-with-file input[type="text"] {
            flex: 1;
        }

        .btn-attach-list {
            background: linear-gradient(to bottom, #fdfcf9, #f5f3ed);
            border: 1.5px solid #d4cdb8;
            padding: 16px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Spectral', serif;
            color: #3d5438;
            white-space: nowrap;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .btn-attach-list:hover {
            background: #8b9a66;
            border-color: #8b9a66;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(139, 154, 102, 0.2);
        }

        .btn-attach-list i {
            font-size: 13px;
        }

        .help-text {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            color: #a39276;
            font-style: italic;
            letter-spacing: 0.2px;
        }

        .help-text::before {
            content: '※ ';
            opacity: 0.6;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(45, 62, 46, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(to bottom, #fdfcf9, #ffffff);
            border-radius: 8px;
            max-width: 560px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 
                0 30px 80px rgba(45, 62, 46, 0.25),
                0 0 1px rgba(139, 154, 102, 0.3);
            border: 1px solid rgba(139, 154, 102, 0.2);
            animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 28px 32px;
            border-bottom: 1px solid rgba(139, 154, 102, 0.2);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .modal-header.error {
            background: linear-gradient(135deg, #f4e8e8 0%, #ede0e0 100%);
            color: #8b4a4a;
        }

        .modal-header.success {
            background: linear-gradient(135deg, #e8f4e8 0%, #e0ede0 100%);
            color: #4a7c4a;
        }

        .modal-header.warning {
            background: linear-gradient(135deg, #f4f0e8 0%, #edebe0 100%);
            color: #8b7a4a;
        }

        .modal-header i {
            font-size: 28px;
        }

        .modal-title {
            flex: 1;
            font-size: 22px;
            font-weight: 600;
            font-family: 'Lora', serif;
            letter-spacing: 0.5px;
        }

        .modal-body {
            padding: 32px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Spectral', serif;
            line-height: 1.7;
            color: #2d3e2e;
        }

        .failed-emails-list {
            background: rgba(245, 243, 237, 0.6);
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(139, 154, 102, 0.15);
        }

        .failed-emails-list h4 {
            font-size: 16px;
            font-weight: 600;
            font-family: 'Lora', serif;
            color: #2d3e2e;
            margin-bottom: 16px;
            letter-spacing: 0.3px;
        }

        .failed-email-item {
            padding: 12px 16px;
            background: #ffffff;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 3px solid #8b4a4a;
            font-size: 14px;
            color: #6a7c59;
        }

        .failed-email-item .email {
            font-weight: 600;
            color: #2d3e2e;
        }

        .failed-email-item .reason {
            font-size: 13px;
            color: #a39276;
            margin-top: 6px;
            font-style: italic;
        }

        .modal-footer {
            padding: 20px 32px;
            border-top: 1px solid rgba(139, 154, 102, 0.2);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(250, 248, 240, 0.5);
        }

        .btn-modal {
            padding: 12px 28px;
            border-radius: 6px;
            border: none;
            font-size: 15px;
            font-weight: 500;
            font-family: 'Spectral', serif;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }

        .btn-modal-primary {
            background: linear-gradient(135deg, #6a7c59 0%, #5a6b4a 100%);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(106, 124, 89, 0.25);
        }

        .btn-modal-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(106, 124, 89, 0.35);
        }

        .btn-modal-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #3d5438;
            border: 1.5px solid #d4cdb8;
        }

        .btn-modal-secondary:hover {
            background: #f5f3ed;
            border-color: #8b9a66;
            color: #2d3e2e;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 30px 20px;
            }

            .compose-card {
                padding: 40px 28px;
            }

            .page-header h1 {
                font-size: 32px;
            }

            .button-group {
                flex-direction: column;
            }

            .input-with-file {
                flex-direction: column;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(245, 243, 237, 0.5);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #a39276, #8b9a66);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #8b9a66, #6a7c59);
        }

        /* Decorative Elements */
        .ornament {
            text-align: center;
            margin: 24px 0;
            color: #a39276;
            opacity: 0.4;
            font-size: 14px;
            letter-spacing: 4px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-area" id="contentArea">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Compose Message</h1>
                <p class="subtitle">Crafted correspondence with care and precision</p>
            </div>

            <div class="compose-card">
                <h3>New Correspondence</h3>
                <p class="compose-subtitle">Compose and send your message with elegance</p>
                
                <form action="send.php" method="POST" enctype="multipart/form-data" id="composeForm">
                    <div class="form-group">
                        <label>Primary Recipient</label>
                        <input type="email" name="email" required placeholder="recipient@example.com">
                    </div>

                    <!-- CC Field -->
                    <div class="form-group">
                        <label>Carbon Copy (Optional)</label>
                        <div class="input-with-file">
                            <input type="text" name="cc" id="ccInput" placeholder="cc1@example.com, cc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('ccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="cc_file" id="ccFile" accept=".txt,.csv" style="display: none;" onchange="handleEmailListUpload(this, 'ccInput')">
                        </div>
                        <small class="help-text">Multiple recipients may be added via text file</small>
                    </div>

                    <!-- BCC Field -->
                    <div class="form-group">
                        <label>Blind Carbon Copy (Optional)</label>
                        <div class="input-with-file">
                            <input type="text" name="bcc" id="bccInput" placeholder="bcc1@example.com, bcc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('bccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="bcc_file" id="bccFile" accept=".txt,.csv" style="display: none;" onchange="handleEmailListUpload(this, 'bccInput')">
                        </div>
                        <small class="help-text">Recipients will receive message privately</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject Line</label>
                        <input type="text" name="subject" required placeholder="Enter the subject of your message">
                    </div>
                    
                    <div class="form-group">
                        <label>Message Content</label>
                        <textarea name="message" required placeholder="Compose your message here..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachments (Optional)</label>
                        <input type="file" name="attachment" id="attachment">
                        <small class="help-text">Supporting documents may be included</small>
                    </div>

                    <div class="ornament">✦ ✦ ✦</div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-preview" id="previewBtn">
                            <i class="fa-solid fa-eye"></i> Preview
                        </button>
                        <button type="submit" class="btn-send">
                            <i class="fa-solid fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Preview Email Functionality
        document.getElementById('previewBtn').addEventListener('click', () => {
            const recipientEmail = document.querySelector('input[name="email"]').value;
            const subject = document.querySelector('input[name="subject"]').value;
            const message = document.querySelector('textarea[name="message"]').value;
            const attachment = document.querySelector('input[name="attachment"]').files[0];

            // Validate required fields
            if (!recipientEmail || !subject || !message) {
                alert('Please fill in all required fields (Recipient, Subject, and Message) before previewing.');
                return;
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
                'message': message,
                'attachment_name': attachment ? attachment.name : ''
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

        // Handle Email List File Upload
        function handleEmailListUpload(fileInput, targetInputId) {
            const file = fileInput.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                
                // Parse emails from file (supports comma, semicolon, newline separated)
                let emails = content
                    .split(/[,;\n\r]+/)
                    .map(email => email.trim())
                    .filter(email => email.length > 0);

                // Set the emails in the target input
                const targetInput = document.getElementById(targetInputId);
                const currentValue = targetInput.value.trim();
                
                if (currentValue) {
                    // Append to existing emails
                    targetInput.value = currentValue + ', ' + emails.join(', ');
                } else {
                    targetInput.value = emails.join(', ');
                }

                // Show success message
                alert(`✓ Loaded ${emails.length} email(s) from file`);
            };

            reader.readAsText(file);
        }
    </script>
</body>
</html>