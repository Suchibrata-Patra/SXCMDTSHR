<!-- ============================================================
     IMAP SETTINGS SECTION
     Add this to your settings.php page
     ============================================================ -->

<?php
require_once __DIR__ . '/security_handler.php';
// Get current settings
$settings = getSettingsWithDefaults($userEmail);
$isLocked = areSettingsLocked($userEmail);
$isSuperAdmin = isSuperAdmin();
?>

<style>
.settings-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1c1c1e;
}

.lock-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.lock-badge.unlocked {
    background: #d1fae5;
    color: #065f46;
}

.lock-badge.admin {
    background: #dbeafe;
    color: #1e40af;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #52525b;
    margin-bottom: 8px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e5e5ea;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #007AFF;
    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
}

.form-input:disabled {
    background: #f5f5f7;
    cursor: not-allowed;
}

.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e5e5ea;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    cursor: pointer;
}

.form-select:disabled {
    background: #f5f5f7;
    cursor: not-allowed;
}

.form-help {
    font-size: 12px;
    color: #8e8e93;
    margin-top: 6px;
}

.warning-box {
    background: #fff7ed;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.warning-title {
    font-weight: 600;
    color: #92400e;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.warning-text {
    font-size: 14px;
    color: #78350f;
    line-height: 1.6;
}

.btn-save {
    padding: 12px 24px;
    background: #007AFF;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-save:hover {
    background: #0051D5;
}

.btn-save:disabled {
    background: #d1d1d6;
    cursor: not-allowed;
}

.admin-unlock {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #fee2e2;
    color: #991b1b;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
}

.admin-unlock:hover {
    background: #fecaca;
}
</style>

<!-- IMAP Settings Section -->
<div class="settings-section">
    <div class="section-header">
        <div>
            <h3 class="section-title">📧 Mail Server Configuration (IMAP)</h3>
        </div>
        <div>
            <?php if ($isLocked): ?>
                <?php if ($isSuperAdmin): ?>
                    <span class="lock-badge admin">
                        🔓 Super Admin Mode
                    </span>
                <?php else: ?>
                    <span class="lock-badge">
                        🔒 Settings Locked
                    </span>
                <?php endif; ?>
            <?php else: ?>
                <span class="lock-badge unlocked">
                    🔓 Can Be Modified Once
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isLocked && !$isSuperAdmin): ?>
        <div class="warning-box">
            <div class="warning-title">
                ⚠️ Settings Locked
            </div>
            <p class="warning-text">
                Your IMAP settings have been configured and locked for security. 
                These settings can only be modified by a super administrator. 
                If you need to change your mail server configuration, please contact 
                the system administrator.
            </p>
        </div>
    <?php elseif (!$isLocked): ?>
        <div class="warning-box">
            <div class="warning-title">
                ℹ️ Important Notice
            </div>
            <p class="warning-text">
                You can configure these IMAP settings <strong>only once</strong>. 
                After saving, the settings will be locked and cannot be changed 
                without super administrator authorization. Please ensure all 
                information is correct before saving.
            </p>
        </div>
    <?php endif; ?>

    <form id="imapSettingsForm">
        <!-- IMAP Server -->
        <div class="form-group">
            <label class="form-label" for="imap_server">
                IMAP Server Address *
            </label>
            <input 
                type="text" 
                id="imap_server" 
                name="imap_server" 
                class="form-input" 
                value="<?= htmlspecialchars($settings['imap_server'] ?? 'imap.hostinger.com') ?>"
                <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                required
                placeholder="e.g., imap.hostinger.com"
            >
            <p class="form-help">The hostname of your IMAP mail server</p>
        </div>

        <!-- IMAP Port -->
        <div class="form-group">
            <label class="form-label" for="imap_port">
                IMAP Port *
            </label>
            <input 
                type="number" 
                id="imap_port" 
                name="imap_port" 
                class="form-input" 
                value="<?= htmlspecialchars($settings['imap_port'] ?? '993') ?>"
                <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                min="1" 
                max="65535"
                required
                placeholder="993"
            >
            <p class="form-help">Common ports: 993 (SSL), 143 (TLS), 143 (none)</p>
        </div>

        <!-- IMAP Encryption -->
        <div class="form-group">
            <label class="form-label" for="imap_encryption">
                Encryption Type *
            </label>
            <select 
                id="imap_encryption" 
                name="imap_encryption" 
                class="form-select"
                <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                required
            >
                <option value="ssl" <?= ($settings['imap_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>
                    SSL/TLS (Recommended)
                </option>
                <option value="tls" <?= ($settings['imap_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>
                    STARTTLS
                </option>
                <option value="none" <?= ($settings['imap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>
                    None (Not Recommended)
                </option>
            </select>
            <p class="form-help">SSL/TLS provides the highest security</p>
        </div>

        <!-- IMAP Username -->
        <div class="form-group">
            <label class="form-label" for="imap_username">
                IMAP Username (Email) *
            </label>
            <input 
                type="email" 
                id="imap_username" 
                name="imap_username" 
                class="form-input" 
                value="<?= htmlspecialchars($settings['imap_username'] ?? $userEmail) ?>"
                <?= ($isLocked && !$isSuperAdmin) ? 'disabled' : '' ?>
                required
                placeholder="user@sxccal.edu"
            >
            <p class="form-help">Usually your full email address</p>
        </div>

        <!-- Password Info -->
        <div class="warning-box" style="background: #f0f9ff; border-left-color: #0284c7;">
            <div class="warning-title" style="color: #0c4a6e;">
                🔐 Password Information
            </div>
            <p class="warning-text" style="color: #0c4a6e;">
                Your email password is <strong>not stored in the database</strong>. 
                It is securely obtained during login and kept only in your session. 
                The same password you use to login will be used for IMAP access.
            </p>
        </div>

        <!-- Save Button -->
        <div style="margin-top: 30px;">
            <button 
                type="submit" 
                class="btn-save"
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
                    class="admin-unlock"
                    onclick="unlockSettings()"
                    style="margin-left: 12px;"
                >
                    🔓 Unlock Settings for User
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.getElementById('imapSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Show confirmation for first-time save
    const isLocked = <?= $isLocked ? 'true' : 'false' ?>;
    const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
    
    if (!isLocked && !isSuperAdmin) {
        if (!confirm('⚠️ Warning: After saving, these settings will be LOCKED and cannot be changed without super admin authorization. Are you sure all information is correct?')) {
            return;
        }
    }
    
    // Save settings
    fetch('save_settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            if (result.locked) {
                alert('🔒 Settings are now locked. Only super admin can modify them.');
            }
            location.reload();
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
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'settings_locked=false'
    })
    .then(response => response.json())
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
