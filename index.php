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
    <title>MailDash | Dashboard</title>
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

        /* Main Content */
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

        /* Compose Card */
        .compose-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            border: 1px solid #e5e5e5;
            max-width: 800px;
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
        input[type="text"], 
        textarea, 
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d0d0d0;
            border-radius:7px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s;
            background-color: #ffffff;
        }

        input[type="email"]:focus, 
        input[type="text"]:focus, 
        textarea:focus, 
        select:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 1px #1a1a1a;
        }

        textarea {
            min-height: 200px;
            resize: vertical;
            line-height: 1.6;
        }

        input[type="file"] {
            padding: 8px 0;
            font-size: 14px;
        }

        .btn-send {
            background-color: #1a1a1a;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 2px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .btn-send:hover {
            background-color: #000000;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-send:active {
            transform: translateY(0);
        }

        .btn-preview {
            background-color: #ffffff;
            color: #1a1a1a;
            padding: 14px 32px;
            border: 1px solid #d0d0d0;
            border-radius: 2px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }

        .btn-preview:hover {
            background-color: #f5f5f5;
            border-color: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .btn-preview:active {
            transform: translateY(0);
        }

        .btn-preview i {
            margin-right: 6px;
        }

        .btn-send i {
            margin-right: 6px;
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
            border-radius: 2px;
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
            border-color: #1a1a1a;
            color: #1a1a1a;
        }

        .btn-attach-list i {
            font-size: 12px;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #999;
            font-style: italic;
        }

        /* Error Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header.error {
            background: #ffebee;
            color: #d32f2f;
        }

        .modal-header.success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .modal-header.warning {
            background: #fff3e0;
            color: #f57c00;
        }

        .modal-header i {
            font-size: 24px;
        }

        .modal-title {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
            max-height: 400px;
            overflow-y: auto;
        }

        .failed-emails-list {
            background: #f5f5f5;
            border-radius: 4px;
            padding: 16px;
            margin-top: 16px;
        }

        .failed-emails-list h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .failed-email-item {
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            margin-bottom: 8px;
            border-left: 3px solid #d32f2f;
            font-size: 13px;
            color: #666;
        }

        .failed-email-item .email {
            font-weight: 600;
            color: #1a1a1a;
        }

        .failed-email-item .reason {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-modal {
            padding: 10px 20px;
            border-radius: 4px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-modal-primary {
            background: #1a1a1a;
            color: white;
        }

        .btn-modal-primary:hover {
            background: #000;
        }

        .btn-modal-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-modal-secondary:hover {
            background: #e5e5e5;
            color: #1a1a1a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 20px;
            }
        }

        /* Scrollbar Styling */
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-area" id="contentArea">
            <div class="compose-card">
                <h3>Draft Mail</h3>
                <p class="compose-subtitle">Send professional emails with attachments</p>
                
                <form action="send.php" method="POST" enctype="multipart/form-data" id="composeForm">
                    <div class="form-group">
                        <label>Recipient Email (To)</label>
                        <input type="email" name="email" required placeholder="recipient@example.com">
                    </div>

                    <!-- CC Field -->
                    <div class="form-group">
                        <label>CC ( if any)</label>
                        <div class="input-with-file">
                            <input type="text" name="cc" id="ccInput" placeholder="cc1@example.com, cc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('ccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="cc_file" id="ccFile" accept=".txt,.csv" style="display: none;" onchange="handleEmailListUpload(this, 'ccInput')">
                        </div>
                        <small class="help-text">Separate bulk emails as text file</small>
                    </div>

                    <!-- BCC Field -->
                    <div class="form-group">
                        <label>BCC (if any)</label>
                        <div class="input-with-file">
                            <input type="text" name="bcc" id="bccInput" placeholder="bcc1@example.com, bcc2@example.com">
                            <button type="button" class="btn-attach-list" onclick="document.getElementById('bccFile').click()">
                                <i class="fa-solid fa-paperclip"></i> Attach List
                            </button>
                            <input type="file" name="bcc_file" id="bccFile" accept=".txt,.csv" style="display: none;" onchange="handleEmailListUpload(this, 'bccInput')">
                        </div>
                        <small class="help-text">Separate bulk emails as text file</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="subject" required placeholder="Enter Your Mail Subject">
                    </div>
                    <div class="form-group">
                        <label>articletitle</label>
                        <input type="text" name="articletitle" required placeholder="Enter Your Mail Subject">
                    </div>
                    
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" required placeholder="Compose your message..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Attachment (Optional)</label>
                        <input type="file" name="attachment" id="attachment">
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="btn-preview" id="previewBtn">
                            <i class="fa-solid fa-eye"></i> Preview Email
                        </button>
                        <button type="submit" class="btn-send">
                            <i class="fa-solid fa-paper-plane"></i> Send Email
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
            const articletitle = document.querySelector('input[name="articletitle"]').value;
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
                'articletitle':articletitle,
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