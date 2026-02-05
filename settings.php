<?php
// settings.php
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
    $allSettings = json_decode($jsonContent, true);
    
    // Check if JSON is valid and is an array
    if (is_array($allSettings)) {
        // Get settings for current user
        $settings = $allSettings[$_SESSION['smtp_user']] ?? [];
    }
}

// Set defaults if not found - ensure all keys exist
$settings = array_merge([
    'display_name' => '',
    'signature' => '',
    'default_subject_prefix' => '',
    'cc_yourself' => false,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'theme' => 'light',
    'auto_save_drafts' => true,
    'email_preview' => true
], $settings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SXC MDTS</title>
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

        .settings-header {
            margin-bottom: 32px;
        }

        .settings-header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        .settings-header p {
            color: #666;
            font-size: 15px;
        }

        .settings-container {
            max-width: 800px;
        }

        .settings-card {
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
        }

        .settings-section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }

        .setting-item {
            margin-bottom: 24px;
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1a1a1a;
            font-size: 14px;
        }

        .setting-item input[type="text"],
        .setting-item input[type="email"],
        .setting-item input[type="number"],
        .setting-item textarea,
        .setting-item select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .setting-item input:focus,
        .setting-item textarea:focus,
        .setting-item select:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
        }

        .setting-item textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            margin-bottom: 0;
            cursor: pointer;
        }

        .setting-description {
            font-size: 13px;
            color: #666;
            margin-top: 6px;
        }

        .settings-actions {
            display: flex;
            gap: 12px;
            padding-top: 24px;
            border-top: 1px solid #e5e5e5;
        }

        .btn-save {
            padding: 12px 24px;
            background-color: #1a1a1a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save:hover {
            background-color: #000;
            transform: translateY(-1px);
        }

        .btn-cancel {
            padding: 12px 24px;
            background-color: #f5f5f5;
            color: #1a1a1a;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background-color: #e5e5e5;
        }

        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
        }

        .success-message.show {
            display: block;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content-area">
            <div class="settings-header">
                <h1>Settings</h1>
                <p>Manage your email preferences and configuration</p>
            </div>

            <div id="successMessage" class="success-message">
                <i class="fa-solid fa-check-circle"></i> Settings saved successfully!
            </div>

            <div class="settings-container">
                <form id="settingsForm" method="POST">
                    <!-- Account Information -->
                    <div class="settings-card">
                        <div class="settings-section-title">
                            <i class="fa-solid fa-user"></i> Account Information
                        </div>
                        
                        <div class="setting-item">
                            <label for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?php echo htmlspecialchars($settings['display_name']); ?>" 
                                   placeholder="Your Name">
                            <div class="setting-description">This name will appear as the sender name in emails</div>
                        </div>

                        <div class="setting-item">
                            <label for="signature">Email Signature</label>
                            <textarea id="signature" name="signature" 
                                      placeholder="Best regards,&#10;Your Name&#10;Your Title"><?php echo htmlspecialchars($settings['signature']); ?></textarea>
                            <div class="setting-description">Automatically added to the end of your emails</div>
                        </div>
                    </div>

                    <!-- Email Preferences -->
                    <div class="settings-card">
                        <div class="settings-section-title">
                            <i class="fa-solid fa-envelope"></i> Email Preferences
                        </div>
                        
                        <div class="setting-item">
                            <label for="default_subject_prefix">Default Subject Prefix</label>
                            <input type="text" id="default_subject_prefix" name="default_subject_prefix" 
                                   value="<?php echo htmlspecialchars($settings['default_subject_prefix']); ?>" 
                                   placeholder="e.g., [Important]">
                            <div class="setting-description">Prefix to be added to all email subjects</div>
                        </div>

                        <div class="setting-item">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="cc_yourself" name="cc_yourself" value="1"
                                       <?php echo $settings['cc_yourself'] ? 'checked' : ''; ?>>
                                <label for="cc_yourself">Always CC yourself on sent emails</label>
                            </div>
                        </div>

                        <div class="setting-item">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="email_preview" name="email_preview" value="1"
                                       <?php echo $settings['email_preview'] ? 'checked' : ''; ?>>
                                <label for="email_preview">Enable email preview before sending</label>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Configuration -->
                    <div class="settings-card">
                        <div class="settings-section-title">
                            <i class="fa-solid fa-server"></i> SMTP Configuration
                        </div>
                        
                        <div class="two-column">
                            <div class="setting-item">
                                <label for="smtp_host">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" 
                                       value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" 
                                       placeholder="smtp.gmail.com">
                            </div>

                            <div class="setting-item">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="text" id="smtp_port" name="smtp_port" 
                                       value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" 
                                       placeholder="587">
                            </div>
                        </div>
                        <div class="setting-description">Configure your SMTP server settings for email delivery</div>
                    </div>

                    <!-- Application Preferences -->
                    <div class="settings-card">
                        <div class="settings-section-title">
                            <i class="fa-solid fa-sliders"></i> Application Preferences
                        </div>
                        
                        <div class="setting-item">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme">
                                <option value="light" <?php echo $settings['theme'] == 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo $settings['theme'] == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="auto" <?php echo $settings['theme'] == 'auto' ? 'selected' : ''; ?>>Auto</option>
                            </select>
                        </div>

                        <div class="setting-item">
                            <div class="checkbox-wrapper">
                                <input type="checkbox" id="auto_save_drafts" name="auto_save_drafts" value="1"
                                       <?php echo $settings['auto_save_drafts'] ? 'checked' : ''; ?>>
                                <label for="auto_save_drafts">Auto-save drafts</label>
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions -->
                    <div class="settings-card">
                        <div class="settings-actions">
                            <button type="submit" class="btn-save">
                                <i class="fa-solid fa-save"></i> Save Settings
                            </button>
                            <button type="button" class="btn-cancel" onclick="window.location.href='index.php'">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('save_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successMsg = document.getElementById('successMessage');
                    successMsg.classList.add('show');
                    
                    // Scroll to top to show message
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    
                    // Hide message after 3 seconds
                    setTimeout(() => {
                        successMsg.classList.remove('show');
                    }, 3000);
                } else {
                    alert('Error saving settings: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving settings. Please try again.');
            });
        });
    </script>
</body>
</html>