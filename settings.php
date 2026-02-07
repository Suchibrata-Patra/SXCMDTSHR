<?php
session_start();
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

// Load and merge granular settings
$settingsFile = 'settings.json';
$allSettings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$userSettings = $allSettings[$_SESSION['smtp_user']] ?? [];

// Default 50+ Options Infrastructure
$defaults = [
    // Identity & Authority
    'display_name' => '', 'designation' => '', 'dept' => 'CS', 'hod_email' => '', 
    'staff_id' => '', 'room_no' => '', 'ext_no' => '',
    
    // Automation & Compliance
    'auto_bcc_hod' => true, 'archive_sent' => true, 'read_receipts' => false,
    'delayed_send' => '0', 'attach_size_limit' => '25', 'auto_label_sent' => true,
    'priority_level' => 'normal', 'mandatory_subject' => true,
    
    // Editor & Composition
    'font_family' => 'Inter', 'font_size' => '14', 'spell_check' => true,
    'auto_correct' => true, 'smart_reply' => false, 'rich_text' => true,
    'default_cc' => '', 'default_bcc' => '', 'undo_send_delay' => '10',
    
    // Interface Personalization
    'sidebar_color' => 'white', 'compact_mode' => false, 'dark_mode' => 'auto',
    'show_avatars' => true, 'anim_speed' => 'normal', 'blur_effects' => true,
    'density' => 'relaxed', 'font_weight' => 'medium',
    
    // Notifications & Security
    'push_notif' => true, 'sound_alerts' => 'tink', 'browser_notif' => true,
    'two_factor' => false, 'session_timeout' => '60', 'ip_lock' => false,
    'debug_logs' => false, 'activity_report' => 'weekly'
];
$s = array_merge($defaults, $userSettings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | SXC MDTS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--apple-bg);
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: #000;
        }

        /* Sidebar Navigation */
        .settings-nav {
            width: 240px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            padding: 40px 10px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--apple-gray);
            padding: 10px 15px;
            text-transform: uppercase;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #1c1c1e;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }

        .nav-link:hover { background: rgba(0,0,0,0.05); }
        .nav-link.active { background: var(--apple-blue); color: white; }

        /* Content Area */
        .settings-content {
            flex: 1;
            padding: 60px 80px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
        }

        .setting-row:last-child { border-bottom: none; }

        .setting-info { display: flex; flex-direction: column; }
        .setting-title { font-size: 14px; font-weight: 500; }
        .setting-desc { font-size: 12px; color: var(--apple-gray); }

        /* Apple Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input { opacity: 0; width: 0; height: 0; }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #D1D1D6;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px; width: 20px;
            left: 2px; bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .slider { background-color: #34C759; }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Inputs & Selects */
        input[type="text"], input[type="email"], select {
            border: none;
            background: #F2F2F7;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            text-align: right;
        }

        .btn-deploy {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,122,255,0.3);
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="settings-nav">
        <div class="nav-group-label">Institutional</div>
        <a href="#id" class="nav-link active"><span class="material-icons">person</span> Identity</a>
        <a href="#compliance" class="nav-link"><span class="material-icons">gavel</span> Compliance</a>
        
        <div class="nav-group-label">Application</div>
        <a href="#editor" class="nav-link"><span class="material-icons">edit</span> Composition</a>
        <a href="#appearance" class="nav-link"><span class="material-icons">palette</span> UI & UX</a>
        <a href="#security" class="nav-link"><span class="material-icons">shield</span> Security</a>
    </div>

    <main class="settings-content">
        <form id="appleSettingsForm">
            <h2 id="id">Official Identity</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Display Name</span>
                        <span class="setting-desc">How your name appears in the 'From' field</span>
                    </div>
                    <input type="text" name="display_name" value="<?= $s['display_name'] ?>">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Official Designation</span>
                        <span class="setting-desc">Your title for institutional signatures</span>
                    </div>
                    <input type="text" name="designation" value="<?= $s['designation'] ?>">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Staff ID Reference</span>
                        <span class="setting-desc">UID for administrative logs</span>
                    </div>
                    <input type="text" name="staff_id" value="<?= $s['staff_id'] ?>">
                </div>
            </div>

            <h2 id="compliance">Institutional Protocol</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">HOD Oversight (BCC)</span>
                        <span class="setting-desc">Auto-copy HOD on all outgoing faculty mail</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="auto_bcc_hod" <?= $s['auto_bcc_hod'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Subject Compliance</span>
                        <span class="setting-desc">Prevent sending if subject is empty</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="mandatory_subject" <?= $s['mandatory_subject'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Read Receipts</span>
                        <span class="setting-desc">Request acknowledgement for every email</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="read_receipts" <?= $s['read_receipts'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <h2 id="editor">Composition Preferences</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Undo Send Delay</span>
                        <span class="setting-desc">Seconds to retract mail after clicking send</span>
                    </div>
                    <select name="undo_send_delay">
                        <option value="0" <?= $s['undo_send_delay']=='0'?'selected':'' ?>>Instant</option>
                        <option value="10" <?= $s['undo_send_delay']=='10'?'selected':'' ?>>10 Seconds</option>
                        <option value="30" <?= $s['undo_send_delay']=='30'?'selected':'' ?>>30 Seconds</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Spell Check</span>
                        <span class="setting-desc">Live grammar and spelling correction</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="spell_check" <?= $s['spell_check'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <h2 id="appearance">Interface & Experience</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Visual Density</span>
                        <span class="setting-desc">Adjust whitespace between UI elements</span>
                    </div>
                    <select name="density">
                        <option value="relaxed">Relaxed</option>
                        <option value="compact">Compact</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Glassmorphism Effects</span>
                        <span class="setting-desc">Enable background blur and translucency</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="blur_effects" <?= $s['blur_effects'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-deploy">Apply Changes</button>
        </form>
    </main>

    <script>
        // Smooth highlighting for nav
        document.querySelectorAll('.nav-link').forEach(link => {
            link.onclick = (e) => {
                document.querySelector('.nav-link.active').classList.remove('active');
                link.classList.add('active');
            }
        });

        // AJAX Save
        document.getElementById('appleSettingsForm').onsubmit = function(e) {
            e.preventDefault();
            const data = new FormData(this);
            fetch('save_settings.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => alert('Settings Deployed Successfully'));
        }
    </script>
</body>
</html>