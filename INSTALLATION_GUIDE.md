# Inbox System Installation Guide

Complete guide to set up the inbox functionality for SXC MDTS email system.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Database Setup](#database-setup)
3. [File Installation](#file-installation)
4. [Configuration](#configuration)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

Before installation, ensure you have:

- ✅ PHP 7.4 or higher with IMAP extension enabled
- ✅ MySQL 5.7+ or MariaDB 10.2+
- ✅ Access to your database server
- ✅ IMAP credentials for email accounts
- ✅ Existing SXC MDTS installation

### Check PHP IMAP Extension

Run this command to check if IMAP is enabled:

```bash
php -m | grep imap
```

If not enabled, install it:

**For Ubuntu/Debian:**
```bash
sudo apt-get install php-imap
sudo systemctl restart apache2
```

**For CentOS/RHEL:**
```bash
sudo yum install php-imap
sudo systemctl restart httpd
```

---

## 🗄️ Database Setup

### Step 1: Create Database Tables

Run the SQL script to create required tables:

```bash
mysql -u your_username -p your_database < create_inbox_table.sql
```

Or manually execute in your MySQL client:

```sql
-- Create inbox_messages table
CREATE TABLE IF NOT EXISTS inbox_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    subject TEXT NOT NULL,
    body LONGTEXT NOT NULL,
    received_date DATETIME NOT NULL,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    has_attachments TINYINT(1) DEFAULT 0,
    is_starred TINYINT(1) DEFAULT 0,
    is_important TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME DEFAULT NULL,
    INDEX idx_user_email (user_email),
    INDEX idx_message_id (message_id),
    INDEX idx_received_date (received_date DESC),
    INDEX idx_is_read (is_read),
    INDEX idx_is_deleted (is_deleted),
    UNIQUE KEY unique_message (message_id, user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create inbox_sync_status table
CREATE TABLE IF NOT EXISTS inbox_sync_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL UNIQUE,
    last_sync_date DATETIME NOT NULL,
    last_message_id VARCHAR(255) DEFAULT NULL,
    total_messages INT DEFAULT 0,
    unread_count INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_email (user_email),
    INDEX idx_last_sync (last_sync_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create composite indexes for optimization
CREATE INDEX idx_inbox_main ON inbox_messages (user_email, is_deleted, received_date DESC);
CREATE INDEX idx_unread_count ON inbox_messages (user_email, is_read, is_deleted);
```

### Step 2: Add IMAP Settings to user_settings Table

If not already present, add IMAP configuration fields:

```sql
-- Example: Add IMAP settings for a user
INSERT INTO user_settings (user_email, setting_key, setting_value) VALUES
('user@example.com', 'imap_server', 'imap.gmail.com'),
('user@example.com', 'imap_port', '993'),
('user@example.com', 'imap_password', 'your_app_password_here');
```

**Important for Gmail Users:**
- Use App Passwords, not your regular Gmail password
- Enable IMAP in Gmail settings
- Create App Password: https://myaccount.google.com/apppasswords

---

## 📁 File Installation

### Step 1: Copy Database Functions

Add the inbox functions to your existing `db_config.php` file:

```php
// At the end of db_config.php, add:
require_once 'inbox_functions.php';
```

Or manually copy all functions from `inbox_functions.php` into `db_config.php`.

### Step 2: Upload All Files

Copy these files to your web root directory:

```
/your-web-root/
├── inbox.php                    # Main inbox page
├── inbox_functions.php          # Database functions (or add to db_config.php)
├── imap_helper.php             # IMAP connection and fetching
├── fetch_inbox_messages.php    # AJAX endpoint for syncing
├── mark_read.php               # AJAX endpoint for read/unread
├── delete_inbox_message.php    # AJAX endpoint for delete
├── view_message.php            # Individual message viewer
└── (existing files remain unchanged)
```

### Step 3: Set File Permissions

Ensure proper permissions:

```bash
chmod 644 inbox.php
chmod 644 inbox_functions.php
chmod 644 imap_helper.php
chmod 644 fetch_inbox_messages.php
chmod 644 mark_read.php
chmod 644 delete_inbox_message.php
chmod 644 view_message.php
```

---

## ⚙️ Configuration

### Step 1: Update Sidebar

Update `sidebar.php` to add the Inbox link (if not already present):

```php
<a href="inbox.php" class="nav-item <?= ($current_page == 'inbox') ? 'active' : ''; ?>">
    <span class="material-icons">inbox</span>
    <span>Inbox</span>
    <?php if ($unreadCount > 0): ?>
    <span class="badge"><?= $unreadCount ?></span>
    <?php endif; ?>
</a>
```

### Step 2: Configure IMAP Settings

Each user must configure their IMAP settings:

**Via Settings Page:**
1. Go to Settings → Email Configuration
2. Add IMAP server details:
   - **Server:** imap.gmail.com (for Gmail)
   - **Port:** 993
   - **Password:** Your app password

**Direct Database Insert:**
```sql
INSERT INTO user_settings (user_email, setting_key, setting_value) VALUES
('user@example.com', 'imap_server', 'imap.gmail.com'),
('user@example.com', 'imap_port', '993'),
('user@example.com', 'imap_password', 'generated_app_password');
```

### Step 3: Test IMAP Connection

Create a test script `test_imap.php`:

```php
<?php
session_start();
require_once 'imap_helper.php';

$connection = connectToIMAP(
    'imap.gmail.com',
    993,
    'your_email@example.com',
    'your_app_password'
);

if ($connection) {
    echo "✅ IMAP connection successful!";
    $count = imap_num_msg($connection);
    echo "<br>Total messages: $count";
    imap_close($connection);
} else {
    echo "❌ IMAP connection failed!";
}
?>
```

---

## 🧪 Testing

### Test 1: Database Tables

Verify tables exist:

```sql
SHOW TABLES LIKE 'inbox_%';
SELECT COUNT(*) FROM inbox_messages;
SELECT COUNT(*) FROM inbox_sync_status;
```

### Test 2: Access Inbox Page

1. Log in to SXC MDTS
2. Navigate to `https://your-domain.com/inbox.php`
3. You should see the inbox interface (empty at first)

### Test 3: Sync Messages

1. Click the "Sync Mail" button
2. Wait for synchronization to complete
3. Messages should appear in the inbox
4. Check for any errors in browser console

### Test 4: Read/Unread Toggle

1. Click on any unread message
2. It should change from bold (unread) to normal (read)
3. Click the "Mark as Unread" icon to test reverse

### Test 5: Delete Message

1. Click the delete icon on any message
2. Confirm deletion
3. Message should disappear from inbox

---

## 🐛 Troubleshooting

### Issue: IMAP Extension Not Found

**Error:** `Call to undefined function imap_open()`

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-imap
sudo systemctl restart apache2

# Check if enabled
php -m | grep imap
```

### Issue: IMAP Connection Failed

**Error:** `Could not connect to IMAP server`

**Possible Causes:**
1. **Incorrect credentials** - Verify email and password
2. **IMAP not enabled** - Enable IMAP in email settings
3. **App Password required** - Use app-specific password for Gmail
4. **Firewall blocking** - Allow port 993 outbound
5. **SSL certificate issues** - Update PHP OpenSSL

**Solution for Gmail:**
- Go to: https://myaccount.google.com/security
- Enable 2-Step Verification
- Create App Password
- Use app password instead of regular password

### Issue: Messages Not Appearing

**Possible Causes:**
1. No messages in IMAP inbox
2. Database insert failing
3. Duplicate message_id constraint

**Debug:**
```php
// Check error logs
tail -f /var/log/apache2/error.log

// Or add debug to fetch_inbox_messages.php
error_log("Fetched: " . print_r($result, true));
```

### Issue: Unread Count Not Updating

**Solution:**
Clear cache and reload:
```php
// In inbox.php, force cache clear
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
```

### Issue: Slow Performance

**Solutions:**
1. **Add database indexes:**
   ```sql
   CREATE INDEX idx_inbox_main ON inbox_messages (user_email, is_deleted, received_date DESC);
   ```

2. **Limit sync messages:**
   In `fetch_inbox_messages.php`, reduce limit:
   ```php
   $result = fetchNewMessages($userEmail, $imapConfig, 25); // Instead of 50
   ```

3. **Enable database query caching:**
   ```sql
   SET GLOBAL query_cache_size = 268435456;
   SET GLOBAL query_cache_type = ON;
   ```

### Issue: Session Timeout

**Solution:**
Increase PHP session lifetime:
```php
// In php.ini or .htaccess
session.gc_maxlifetime = 3600
session.cookie_lifetime = 3600
```

---

## 🔒 Security Recommendations

### 1. Encrypt IMAP Passwords

Store IMAP passwords encrypted:

```php
function encryptPassword($password) {
    $key = 'your-secret-key-here';
    return openssl_encrypt($password, 'AES-256-CBC', $key);
}

function decryptPassword($encrypted) {
    $key = 'your-secret-key-here';
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key);
}
```

### 2. Rate Limiting

Prevent abuse of sync endpoint:

```php
// In fetch_inbox_messages.php
$lastSync = getLastSyncDate($userEmail);
if ($lastSync && (time() - strtotime($lastSync)) < 60) {
    echo json_encode(['error' => 'Please wait before syncing again']);
    exit;
}
```

### 3. Input Sanitization

Always validate and sanitize user input:

```php
$messageId = filter_var($_POST['message_id'], FILTER_VALIDATE_INT);
if (!$messageId) {
    die('Invalid message ID');
}
```

### 4. HTTPS Only

Ensure all pages use HTTPS:

```php
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

## 📊 Performance Optimization

### 1. Database Optimization

```sql
-- Analyze tables
ANALYZE TABLE inbox_messages;
ANALYZE TABLE inbox_sync_status;

-- Optimize tables monthly
OPTIMIZE TABLE inbox_messages;
```

### 2. Cron Job for Auto-Sync

Set up automatic syncing:

```bash
# crontab -e
*/15 * * * * php /path/to/auto_sync_inbox.php > /dev/null 2>&1
```

Create `auto_sync_inbox.php`:
```php
<?php
require_once 'db_config.php';
require_once 'imap_helper.php';

// Get all active users
$users = getAllActiveUsers();
foreach ($users as $user) {
    $settings = getSettingsWithDefaults($user['email']);
    $imapConfig = [...]; // Get IMAP config
    fetchNewMessages($user['email'], $imapConfig, 50);
}
?>
```

---

## 🎉 Deployment Checklist

Before going live:

- [ ] Database tables created
- [ ] All files uploaded with correct permissions
- [ ] PHP IMAP extension enabled
- [ ] IMAP credentials configured for all users
- [ ] Test sync functionality
- [ ] Test read/unread toggle
- [ ] Test delete functionality
- [ ] Test pagination
- [ ] SSL/HTTPS enabled
- [ ] Error logging configured
- [ ] Backup system in place
- [ ] Performance tested with large inboxes
- [ ] Security audit completed

---

## 📞 Support

If you encounter issues:

1. Check error logs: `/var/log/apache2/error.log`
2. Enable debug mode in PHP
3. Test IMAP connection independently
4. Verify database structure matches schema

For additional help, contact your system administrator or refer to:
- PHP IMAP Documentation: https://www.php.net/manual/en/book.imap.php
- MySQL Documentation: https://dev.mysql.com/doc/

---

## 🔄 Updates & Maintenance

### Regular Maintenance Tasks

**Weekly:**
- Review error logs
- Check sync status for all users
- Verify database growth

**Monthly:**
- Optimize database tables
- Archive old messages (>6 months)
- Update IMAP passwords if needed

**Quarterly:**
- Security audit
- Performance review
- User feedback collection

---

## 📝 Changelog

### Version 1.0.0 (Initial Release)
- ✅ IMAP inbox fetching
- ✅ Read/Unread status tracking
- ✅ Message deletion (soft delete)
- ✅ Search and filtering
- ✅ Pagination support
- ✅ Responsive UI matching sent_history.php

---

**Installation Complete! 🎊**

Your inbox system is now ready to use. Navigate to `inbox.php` to start receiving emails.
