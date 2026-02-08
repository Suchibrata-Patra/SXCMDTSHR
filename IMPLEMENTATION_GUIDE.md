# IMAP Settings Refactor - Implementation Guide

## Overview
This implementation refactors the SXC MDTS mail system to:
1. Remove all hardcoded IMAP server settings
2. Store IMAP configuration in the database (user_settings table)
3. Load IMAP settings into session at login time
4. Implement one-time settings change with settings lock mechanism
5. Provide super admin override capability with audit logging

## Security Improvements
- ✅ No hardcoded IMAP credentials
- ✅ Session-based configuration (server-side only)
- ✅ One-time IMAP settings change per user
- ✅ Super admin override with audit trail
- ✅ Input validation and sanitization
- ✅ SQL injection protection via prepared statements

---

## Installation Steps

### Step 1: Database Migration
Run the migration script to create required tables and add default settings:

```bash
mysql -u u955994755_DB_supremacy -p u955994755_SXC_MDTS < database_migration.sql
```

Or execute via phpMyAdmin:
1. Open phpMyAdmin
2. Select database `u955994755_SXC_MDTS`
3. Go to SQL tab
4. Paste contents of `database_migration.sql`
5. Click "Go"

### Step 2: Replace Files
Replace the following files with the updated versions:

```bash
# Core files
cp settings_helper.php /path/to/htdocs/settings_helper.php
cp login.php /path/to/htdocs/login.php
cp imap_helper.php /path/to/htdocs/imap_helper.php
cp fetch_inbox_messages.php /path/to/htdocs/fetch_inbox_messages.php
cp save_settings.php /path/to/htdocs/save_settings.php
```

**IMPORTANT:** Make sure to backup existing files before replacing!

### Step 3: Configure Super Admin (Optional)
To grant super admin privileges to a user:

```sql
INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
VALUES ('admin@sxccal.edu', 'is_super_admin', 'true', NOW())
ON DUPLICATE KEY UPDATE setting_value = 'true', updated_at = NOW();
```

Replace `admin@sxccal.edu` with the actual admin email.

### Step 4: Test the System
1. **Login Test**: Login with an existing account
   - IMAP config should be loaded to session
   - Check session variables in PHP: `$_SESSION['imap_config']`

2. **Inbox Sync Test**: Go to inbox and click "Sync"
   - Should fetch messages using session-based config
   - Check error logs if sync fails

3. **Settings Lock Test**: Go to Settings → Mail Settings
   - Save IMAP settings once
   - Try to change them again
   - Should show "Settings locked" message
   - Only super admin can override

---

## How It Works

### Login Flow
```
User Login → SMTP Authentication
     ↓
Load User Settings from DB
     ↓
Populate $_SESSION['imap_config'] with:
  - imap_server
  - imap_port
  - imap_encryption
  - imap_username
  - imap_password (from login)
     ↓
Set $_SESSION['user_role'] 
(super_admin or user)
     ↓
Redirect to Dashboard
```

### IMAP Connection Flow
```
User clicks "Sync Inbox"
     ↓
fetch_inbox_messages.php called
     ↓
Gets IMAP config from SESSION
(NOT from hardcoded values)
     ↓
Connects to IMAP server
     ↓
Fetches new messages
     ↓
Saves to database
```

### Settings Lock Mechanism
```
User saves IMAP settings (first time)
     ↓
Validate settings
     ↓
Save to database
     ↓
Set settings_locked = true
     ↓
Future attempts to change blocked
(unless super_admin)
```

### Super Admin Override
```
Super Admin modifies locked settings
     ↓
Check if $_SESSION['user_role'] == 'super_admin'
     ↓
If yes:
  - Allow modification
  - Log to admin_audit_log table
  - Record: action, timestamp, IP
     ↓
If no:
  - Return "Unauthorized" error
```

---

## Session Variables

After successful login, these session variables are available:

```php
$_SESSION['smtp_user']        // User's email
$_SESSION['smtp_pass']        // User's password
$_SESSION['authenticated']    // true
$_SESSION['user_role']        // 'user' or 'super_admin'
$_SESSION['imap_config']      // Array with:
    [
        'imap_server' => 'imap.hostinger.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'user@sxccal.edu',
        'imap_password' => '********'
    ]
```

---

## Database Schema Changes

### New Table: admin_audit_log
Tracks all super admin actions:

```sql
CREATE TABLE `admin_audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_email` VARCHAR(255) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_user` VARCHAR(255) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Updated Table: user_settings
New setting keys added:

| setting_key       | Purpose                          | Example Value       |
|-------------------|----------------------------------|---------------------|
| imap_server       | IMAP server hostname             | imap.hostinger.com  |
| imap_port         | IMAP port number                 | 993                 |
| imap_encryption   | Encryption type                  | ssl                 |
| imap_username     | IMAP login username              | user@sxccal.edu     |
| settings_locked   | Whether settings are locked      | true/false          |
| is_super_admin    | Super admin privilege flag       | true/false          |

**Note:** `imap_password` is NOT stored in database. It comes from login credentials.

---

## Security Considerations

### Password Handling
- ✅ User password is obtained during login (SMTP authentication)
- ✅ Password is stored in `$_SESSION['smtp_pass']` (server-side)
- ✅ Password is NEVER stored in database or cookies
- ✅ Password is used for both SMTP and IMAP connections

### Settings Lock
- ✅ Users can configure IMAP settings ONCE
- ✅ After first save, `settings_locked = true`
- ✅ Prevents accidental or malicious reconfiguration
- ✅ Only super admin can unlock (with audit trail)

### Input Validation
All IMAP settings are validated before saving:
- Server: Must be non-empty
- Port: Must be 1-65535
- Encryption: Must be ssl/tls/none
- Username: Must be valid email format

### SQL Injection Protection
All database queries use prepared statements with parameter binding.

---

## Troubleshooting

### Problem: "IMAP not configured" error
**Solution:**
1. Check if user has IMAP settings in database:
```sql
SELECT * FROM user_settings 
WHERE user_email = 'user@sxccal.edu' 
AND setting_key LIKE 'imap_%';
```

2. If missing, run migration script to add defaults

3. Ask user to logout and login again to reload session

### Problem: "Could not connect to IMAP server"
**Solution:**
1. Check session IMAP config:
```php
var_dump($_SESSION['imap_config']);
```

2. Verify settings are correct in database

3. Test IMAP connection manually:
```bash
openssl s_client -connect imap.hostinger.com:993
```

4. Check PHP error logs for detailed error messages

### Problem: "Settings locked" when trying to change
**Solution:**
This is expected behavior! Options:
1. If user needs to change: Grant super_admin role
2. If accidentally locked: Super admin can unlock:
```sql
UPDATE user_settings 
SET setting_value = 'false' 
WHERE user_email = 'user@sxccal.edu' 
AND setting_key = 'settings_locked';
```

### Problem: Super admin actions not logging
**Solution:**
1. Check if admin_audit_log table exists:
```sql
SHOW TABLES LIKE 'admin_audit_log';
```

2. If missing, create it using migration script

3. Check if super admin has correct role:
```sql
SELECT * FROM user_settings 
WHERE user_email = 'admin@sxccal.edu' 
AND setting_key = 'is_super_admin';
```

---

## API Reference

### New Functions in settings_helper.php

#### `getImapConfigFromSession()`
Gets IMAP configuration from session
```php
$config = getImapConfigFromSession();
// Returns: array or null
```

#### `loadImapConfigToSession($email, $password)`
Loads IMAP config into session during login
```php
loadImapConfigToSession('user@sxccal.edu', 'password123');
```

#### `areSettingsLocked($email)`
Checks if settings are locked for a user
```php
if (areSettingsLocked('user@sxccal.edu')) {
    echo "Settings are locked!";
}
```

#### `isSuperAdmin()`
Checks if current session user is super admin
```php
if (isSuperAdmin()) {
    // Allow admin actions
}
```

#### `logSuperAdminAction($admin, $action, $target, $details)`
Logs super admin actions to audit trail
```php
logSuperAdminAction(
    'admin@sxccal.edu',
    'UNLOCK_SETTINGS',
    'user@sxccal.edu',
    ['reason' => 'User requested unlock']
);
```

### Updated Functions in imap_helper.php

#### `connectToIMAPFromSession()`
Connects to IMAP using session config (NEW)
```php
$connection = connectToIMAPFromSession();
if ($connection) {
    // Use connection
}
```

#### `fetchNewMessagesFromSession($email, $limit)`
Fetches messages using session config (NEW)
```php
$result = fetchNewMessagesFromSession('user@sxccal.edu', 50);
```

#### `fetchNewMessages($email, $config, $limit)` 
DEPRECATED - now uses session config internally
```php
// Old way (still works but deprecated)
fetchNewMessages($email, $config, 50);

// New way
fetchNewMessagesFromSession($email, 50);
```

---

## Migration Checklist

- [ ] Backup database
- [ ] Backup existing PHP files
- [ ] Run database migration script
- [ ] Replace updated PHP files
- [ ] Configure super admin (if needed)
- [ ] Test login with existing user
- [ ] Test inbox sync
- [ ] Test settings save (first time)
- [ ] Test settings lock (second attempt)
- [ ] Test super admin override
- [ ] Check audit logs
- [ ] Monitor error logs for 24 hours
- [ ] Update documentation

---

## Support

If you encounter issues:

1. **Check error logs:**
   - `/var/log/apache2/error.log` (Linux)
   - PHP error log in browser console

2. **Enable debug mode:**
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. **Verify session data:**
   ```php
   session_start();
   echo '<pre>';
   print_r($_SESSION);
   echo '</pre>';
   ```

4. **Check database connectivity:**
   ```php
   $pdo = getDatabaseConnection();
   if ($pdo) {
       echo "Database connected!";
   } else {
       echo "Database connection failed!";
   }
   ```

---

## License
SXC MDTS - Internal Use Only
© 2026 St. Xavier's College, Kolkata
