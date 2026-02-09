<!-- <?php
/**
 * IMAP SETTINGS PAGE - SEPARATE FROM OTHER SETTINGS
 * This page handles ONLY IMAP configuration
 */

session_start();

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';
require_once 'settings_helper.php';

$userEmail = $_SESSION['smtp_user'];
$settings = getSettingsWithDefaults($userEmail);
$isLocked = areSettingsLocked($userEmail);
$isSuperAdmin = isSuperAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Check if settings are locked
        if ($isLocked && !$isSuperAdmin) {
            echo json_encode([
                'success' => false,
                'message' => 'IMAP settings are locked. Super admin authorization required.'
            ]);
            exit();
        }
        
        // Validate IMAP settings
        $imapData = [
            'imap_server' => trim($_POST['imap_server'] ?? ''),
            'imap_port' => trim($_POST['imap_port'] ?? ''),
            'imap_encryption' => trim($_POST['imap_encryption'] ?? ''),
            'imap_username' => trim($_POST['imap_username'] ?? '')
        ];
        
        $validation = validateImapSettings($imapData);
        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $validation['errors'])
            ]);
            exit();
        }
        
        // Save each setting
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $pdo->beginTransaction();
        
        foreach ($imapData as $key => $value) {
            saveSetting($userEmail, $key, $value);
        }
        
        // Lock settings if this is first-time save and not super admin
        if (!$isLocked && !$isSuperAdmin) {
            saveSetting($userEmail, 'settings_locked', true);
            $pdo->commit();
            
            // Update session IMAP config
            loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
            
            echo json_encode([
                'success' => true,
                'message' => 'IMAP settings saved and locked successfully',
                'locked' => true
            ]);
        } else {
            $pdo->commit();
            
            // Update session IMAP config
            loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
            
            echo json_encode([
                'success' => true,
                'message' => 'IMAP settings updated successfully',
                'locked' => $isLocked
            ]);
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMAP Settings - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 40px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .lock-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .lock-badge.locked {
            background: #fef3c7;
            color: #92400e;
        }

        .lock-badge.unlocked {
            background: #d1fae5;
            color: #065f46;
        }

        .lock-badge.admin {
            background: #dbeafe;
            color: #1e40af;
        }

        .warning-box {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid;
        }

        .warning-box.yellow {
            background: #fff7ed;
            border-left-color: #f59e0b;
            color: #78350f;
        }

        .warning-box.blue {
            background: #f0f9ff;
            border-left-color: #0284c7;
            color: #0c4a6e;
        }

        .warning-box.red {
            background: #fef2f2;
            border-left-color: #ef4444;
            color: #991b1b;
        }

        .warning-title {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #52525b;
            margin-bottom: 8px;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e5e5ea;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #007AFF;
            box-shadow: 0 0 0 3px rgba(0,122,255,0.1);
        }

        input:disabled, select:disabled {
            background: #f5f5f7;
            cursor: not-allowed;
            color: #8e8e93;
        }

        .form-help {
            font-size: 12px;
            color: #8e8e93;
            margin-top: 6px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0051D5;
        }

        .btn-primary:disabled {
            background: #d1d1d6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #e5e5ea;
        }

        .btn-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007AFF;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="settings.php" class="back-link">← Back to Settings</a>
        
        <div class="card">
            <div class="header">
                <h1>📧 Mail Server Configuration (IMAP)</h1>
                <div>
                    <?php if ($isLocked): ?>
                        <?php if ($isSuperAdmin): ?>
                            <span class="lock-badge admin">🔓 Super Admin Mode</span>
                        <?php else: ?>
                            <span class="lock-badge locked">🔒 Settings Locked</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="lock-badge unlocked">🔓 Can Be Modified Once</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isLocked && !$isSuperAdmin): ?>
                <div class="warning-box red">
                    <div class="warning-title">⚠️ Settings Locked</div>
                    <p>
                        Your IMAP settings have been configured and locked for security. 
                        These settings can only be modified by a super administrator. 
                        If you need to change your mail server configuration, please contact 
                        the system administrator.
                    </p>
                </div>
            <?php elseif (!$isLocked): ?>
                <div class="warning-box yellow">
                    <div class="warning-title">ℹ️ Important Notice</div>
                    <p>
                        You can configure these IMAP settings <strong>only once</strong>. 
                        After saving, the settings will be locked and cannot be changed 
                        without super administrator authorization. Please ensure all 
                        information is correct before saving.
                    </p>
                </div>
            <?php endif; ?>

            <form id="imapForm" method="POST">
                <div class="form-group">
                    <label for="imap_server">IMAP Server Address *</label>
                    <input 
                        type="text" 
                        id="imap_server" 
                        name="imap_server" 
                        value="<?= htmlspecialchars($settings['imap_server']) ?>"
                        <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                        required
                        placeholder="e.g., imap.hostinger.com"
                    >
                    <p class="form-help">The hostname of your IMAP mail server</p>
                </div>

                <div class="form-group">
                    <label for="imap_port">IMAP Port *</label>
                    <input 
                        type="number" 
                        id="imap_port" 
                        name="imap_port" 
                        value="<?= htmlspecialchars($settings['imap_port']) ?>"
                        <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                        min="1" 
                        max="65535"
                        required
                        placeholder="993"
                    >
                    <p class="form-help">Common ports: 993 (SSL), 143 (TLS)</p>
                </div>

                <div class="form-group">
                    <label for="imap_encryption">Encryption Type *</label>
                    <select 
                        id="imap_encryption" 
                        name="imap_encryption"
                        <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                        required
                    >
                        <option value="ssl" <?= $settings['imap_encryption'] === 'ssl' ? 'selected' : '' ?>>
                            SSL/TLS (Recommended)
                        </option>
                        <option value="tls" <?= $settings['imap_encryption'] === 'tls' ? 'selected' : '' ?>>
                            STARTTLS
                        </option>
                        <option value="none" <?= $settings['imap_encryption'] === 'none' ? 'selected' : '' ?>>
                            None (Not Recommended)
                        </option>
                    </select>
                    <p class="form-help">SSL/TLS provides the highest security</p>
                </div>

                <div class="form-group">
                    <label for="imap_username">IMAP Username (Email) *</label>
                    <input 
                        type="email" 
                        id="imap_username" 
                        name="imap_username" 
                        value="<?= htmlspecialchars($settings['imap_username']) ?>"
                        <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                        required
                        placeholder="user@sxccal.edu"
                    >
                    <p class="form-help">Usually your full email address</p>
                </div>

                <div class="warning-box blue">
                    <div class="warning-title">🔐 Password Information</div>
                    <p>
                        Your email password is <strong>not stored in the database</strong>. 
                        It is securely obtained during login and kept only in your session. 
                        The same password you use to login will be used for IMAP access.
                    </p>
                </div>

                <div class="actions">
                    <button 
                        type="submit" 
                        class="btn btn-primary"
                        <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                    >
                        <?php if ($isLocked && $isSuperAdmin): ?>
                            🔓 Save Changes (Admin Override)
                        <?php elseif ($isLocked): ?>
                            🔒 Settings Locked
                        <?php else: ?>
                            💾 Save IMAP Settings
                        <?php endif; ?>
                    </button>

                    <?php if ($isSuperAdmin && $isLocked): ?>
                        <button 
                            type="button" 
                            class="btn btn-danger"
                            onclick="unlockSettings()"
                        >
                            🔓 Unlock Settings for User
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('imapForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const isLocked = <?= $isLocked ? 'true' : 'false' ?>;
            const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
            
            // Confirm first-time save
            if (!isLocked && !isSuperAdmin) {
                if (!confirm('⚠️ Warning: After saving, these settings will be LOCKED and cannot be changed without super admin authorization. Are you sure all information is correct?')) {
                    return;
                }
            }
            
            const formData = new FormData(this);
            
            fetch('imap_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    alert('✅ ' + result.message);
                    if (result.locked) {
                        alert('🔒 Settings are now locked. Only super admin can modify them.');
                    }
                    window.location.href = 'settings.php';
                } else {
                    alert('❌ Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            });
        });

        function unlockSettings() {
            if (!confirm('Are you sure you want to unlock IMAP settings for this user? This action will be logged.')) {
                return;
            }
            
            fetch('save_settings.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'settings_locked=false'
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Settings unlocked successfully');
                    location.reload();
                } else {
                    alert('❌ Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            });
        }
    </script>
</body>
</html> -->