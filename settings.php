<?php
// settings.php
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

// Load settings from JSON file
$settingsFile = 'settings.json';
$allSettings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$userSettings = $allSettings[$_SESSION['smtp_user']] ?? [];

// Institutional Defaults
$settings = array_merge([
    'display_name' => '',
    'designation' => '',
    'department' => 'General',
    'hod_email' => '',
    'always_bcc_hod' => false,
    'signature_template' => "Best regards,\n{name}\n{designation}\nSt. Xavier's College (Autonomous)",
    'default_priority' => 'normal',
    'archive_duration' => '365',
    'email_preview' => true
], $userSettings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Configuration | SXC MDTS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --nature-red: #a10420;
            --inst-black: #1a1a1a;
            --inst-gray: #555555;
            --inst-border: #d1d1d1;
            --inst-bg: #fcfcfc;
        }

        body { 
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: var(--inst-black);
            display: flex;
            height: 100vh;
            margin: 0;
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            background-color: var(--inst-bg);
            padding: 60px;
        }

        .settings-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            border-bottom: 2px solid var(--inst-black);
            padding-bottom: 20px;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-family: 'Crimson Pro', serif;
            font-size: 36px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }

        .page-header p {
            color: var(--inst-gray);
            font-weight: 600;
            font-size: 14px;
            margin-top: 5px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .settings-card {
            background: white;
            border: 1px solid var(--inst-border);
            padding: 30px;
            position: relative;
        }

        .card-label {
            position: absolute;
            top: -12px;
            left: 20px;
            background: var(--inst-black);
            color: white;
            padding: 2px 12px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group:last-child { margin-bottom: 0; }

        label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: var(--inst-black);
        }

        input[type="text"], 
        input[type="email"], 
        select, 
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--inst-border);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            transition: border-color 0.2s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--nature-red);
            border-width: 1px;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .instruction {
            font-size: 12px;
            color: var(--inst-gray);
            margin-top: 6px;
            font-style: italic;
        }

        /* Institutional Checkbox Styling */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f8f8;
            border-left: 4px solid var(--nature-red);
        }

        .checkbox-group input {
            width: 18px;
            height: 18px;
            accent-color: var(--nature-red);
        }

        .save-bar {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn {
            padding: 12px 30px;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            border: none;
            font-size: 13px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--inst-black);
            color: white;
        }

        .btn-primary:hover {
            background: var(--nature-red);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--inst-border);
            color: var(--inst-gray);
        }

        #statusMessage {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: var(--nature-red);
            color: white;
            font-weight: 700;
            display: none;
            z-index: 2000;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="statusMessage">SETTINGS UPDATED SUCCESSFULLY</div>

    <div class="main-content">
        <div class="settings-wrapper">
            <header class="page-header">
                <h1>System Preferences</h1>
                <p>Configure institutional mailing protocols and personal identification.</p>
            </header>

            <form id="institutionalSettingsForm">
                <div class="settings-grid">
                    
                    <div class="settings-card">
                        <div class="card-label">Official Identity</div>
                        <div class="two-col">
                            <div class="form-group">
                                <label>Full Name (with Initials)</label>
                                <input type="text" name="display_name" value="<?= htmlspecialchars($settings['display_name']) ?>" placeholder="Dr. John Doe">
                            </div>
                            <div class="form-group">
                                <label>Designation</label>
                                <input type="text" name="designation" value="<?= htmlspecialchars($settings['designation']) ?>" placeholder="Assistant Professor">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department">
                                <option value="Computer Science" <?= $settings['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                <option value="Physics" <?= $settings['department'] == 'Physics' ? 'selected' : '' ?>>Physics</option>
                                <option value="Mathematics" <?= $settings['department'] == 'Mathematics' ? 'selected' : '' ?>>Mathematics</option>
                                <option value="Commerce" <?= $settings['department'] == 'Commerce' ? 'selected' : '' ?>>Commerce</option>
                            </select>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="card-label">Compliance & Oversight</div>
                        <div class="form-group">
                            <label>HOD / Reporting Authority Email</label>
                            <input type="email" name="hod_email" value="<?= htmlspecialchars($settings['hod_email']) ?>" placeholder="hod.cs@sxccal.edu">
                            <p class="instruction">Official correspondence oversight address.</p>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="always_bcc_hod" id="bcc_hod" value="1" <?= $settings['always_bcc_hod'] ? 'checked' : '' ?>>
                            <label for="bcc_hod" style="margin-bottom:0">Mandatory BCC to HOD for all outgoing mail</label>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="card-label">Signature & Templates</div>
                        <div class="form-group">
                            <label>Official Correspondence Signature</label>
                            <textarea name="signature_template" rows="4"><?= htmlspecialchars($settings['signature_template']) ?></textarea>
                            <p class="instruction">Use {name} and {designation} for automatic placeholders.</p>
                        </div>
                        <div class="two-col">
                            <div class="form-group">
                                <label>Default Mail Priority</label>
                                <select name="default_priority">
                                    <option value="normal" <?= $settings['default_priority'] == 'normal' ? 'selected' : '' ?>>Normal Correspondence</option>
                                    <option value="high" <?= $settings['default_priority'] == 'high' ? 'selected' : '' ?>>Urgent / Official</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Archive Duration (Days)</label>
                                <input type="number" name="archive_duration" value="<?= htmlspecialchars($settings['archive_duration']) ?>">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="save-bar">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Discard Changes</button>
                    <button type="submit" class="btn btn-primary">Authorize & Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('institutionalSettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('save_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const msg = document.getElementById('statusMessage');
                    msg.style.display = 'block';
                    setTimeout(() => msg.style.display = 'none', 3000);
                }
            })
            .catch(err => console.error('Error:', err));
        });
    </script>
</body>
</html>