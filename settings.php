<?php
// settings.php
session_start();

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

// Load settings from JSON file
$settingsFile = 'settings.json';
$allSettings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$userSettings = $allSettings[$_SESSION['smtp_user']] ?? [];

// Sophisticated Institutional Defaults
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
    <title>Protocol Configuration | SXC MDTS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --nature-red: #a10420;
            --glass-black: #000000;
            --text-muted: #666666;
            --border-soft: #eeeeee;
            --bg-neutral: #ffffff;
            --sidebar-width: 280px;
        }

        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-neutral);
            color: var(--glass-black);
            margin: 0;
            display: flex;
            -webkit-font-smoothing: antialiased;
        }

        .main-content {
            flex: 1;
            padding: 80px 120px;
            overflow-y: auto;
        }

        .config-wrapper {
            max-width: 840px;
            margin: 0 auto;
        }

        /* Editorial Header */
        .header-stack {
            margin-bottom: 64px;
            border-bottom: 1px solid var(--glass-black);
            padding-bottom: 32px;
        }

        .header-stack h1 {
            font-family: 'Crimson Pro', serif;
            font-size: 42px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .header-stack p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 8px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* Sophisticated Section Layout */
        .config-section {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 60px;
            margin-bottom: 80px;
            animation: fadeIn 0.8s ease-out;
        }

        .section-info h2 {
            font-family: 'Crimson Pro', serif;
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .section-info p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Clean Form Elements */
        .form-row {
            margin-bottom: 32px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 10px;
            color: var(--text-muted);
        }

        input[type="text"], 
        input[type="email"], 
        input[type="number"], 
        select, 
        textarea {
            width: 100%;
            border: none;
            border-bottom: 1px solid #ddd;
            padding: 12px 0;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            color: var(--glass-black);
            background: transparent;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-bottom-color: var(--nature-red);
        }

        /* Toggle Switches (Modern Class) */
        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-soft);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 20px;
        }

        .switch input { opacity: 0; width: 0; height: 0; }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #eee;
            transition: .4s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px; width: 14px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider { background-color: var(--nature-red); }
        input:checked + .slider:before { transform: translateX(22px); }

        /* Actions Bar */
        .sticky-footer {
            position: sticky;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 24px 0;
            margin-top: 40px;
            border-top: 1px solid var(--border-soft);
            display: flex;
            justify-content: flex-end;
            gap: 24px;
        }

        .btn {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            padding: 14px 32px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .btn-save {
            background: var(--glass-black);
            color: white;
        }

        .btn-save:hover {
            background: var(--nature-red);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(161, 4, 32, 0.2);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #toast {
            position: fixed;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--glass-black);
            color: white;
            padding: 12px 24px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            display: none;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="toast">PROTOCOL UPDATED</div>

    <main class="main-content">
        <div class="config-wrapper">
            <header class="header-stack">
                <p>System Configuration</p>
                <h1>Institutional Protocol</h1>
            </header>

            <form id="worldClassSettings">
                <section class="config-section">
                    <div class="section-info">
                        <h2>Personal Identity</h2>
                        <p>Define how your academic credentials appear in official correspondence.</p>
                    </div>
                    <div class="section-fields">
                        <div class="form-row">
                            <label>Full Legal Name</label>
                            <input type="text" name="display_name" value="<?= htmlspecialchars($settings['display_name']) ?>" placeholder="e.g. Professor Alex Sterling">
                        </div>
                        <div class="form-row">
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label>Designation</label>
                                    <input type="text" name="designation" value="<?= htmlspecialchars($settings['designation']) ?>" placeholder="Head of Department">
                                </div>
                                <div>
                                    <label>Academic Unit</label>
                                    <select name="department">
                                        <option value="Computer Science" <?= $settings['department'] == 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                                        <option value="Physics" <?= $settings['department'] == 'Physics' ? 'selected' : '' ?>>Physics</option>
                                        <option value="Mathematics" <?= $settings['department'] == 'Mathematics' ? 'selected' : '' ?>>Mathematics</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-info">
                        <h2>Compliance</h2>
                        <p>Configure automated oversight and reporting parameters.</p>
                    </div>
                    <div class="section-fields">
                        <div class="form-row">
                            <label>Supervisory Address (BCC)</label>
                            <input type="email" name="hod_email" value="<?= htmlspecialchars($settings['hod_email']) ?>" placeholder="authority@sxccal.edu">
                        </div>
                        <div class="toggle-group">
                            <span style="font-size: 14px; font-weight: 500;">Mandatory Oversight Policy</span>
                            <label class="switch">
                                <input type="checkbox" name="always_bcc_hod" value="1" <?= $settings['always_bcc_hod'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="config-section">
                    <div class="section-info">
                        <h2>Correspondence</h2>
                        <p>Standardize your email signatures and archiving logic.</p>
                    </div>
                    <div class="section-fields">
                        <div class="form-row">
                            <label>Institutional Signature</label>
                            <textarea name="signature_template" rows="4"><?= htmlspecialchars($settings['signature_template']) ?></textarea>
                        </div>
                        <div class="form-row">
                            <label>Default Priority Status</label>
                            <select name="default_priority">
                                <option value="normal" <?= $settings['default_priority'] == 'normal' ? 'selected' : '' ?>>Standard Correspondence</option>
                                <option value="high" <?= $settings['default_priority'] == 'high' ? 'selected' : '' ?>>Urgent/Administrative</option>
                            </select>
                        </div>
                    </div>
                </section>

                <div class="sticky-footer">
                    <button type="button" class="btn btn-ghost" onclick="window.history.back()">Discard</button>
                    <button type="submit" class="btn btn-save">Deploy Settings</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.getElementById('worldClassSettings').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('save_settings.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const toast = document.getElementById('toast');
                    toast.style.display = 'block';
                    setTimeout(() => toast.style.display = 'none', 3000);
                }
            });
        });
    </script>
</body>
</html>