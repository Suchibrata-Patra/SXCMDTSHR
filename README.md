# IMAP Settings Refactor - Complete Implementation Package

## 📦 Package Contents

This package contains all files needed to refactor the SXC MDTS mail system to use database-stored IMAP settings with security enhancements.

### Core Files (Replace Existing)
1. **settings_helper.php** (13KB) - Enhanced settings management with IMAP functions
2. **login.php** (9.3KB) - Updated login with session config loading
3. **imap_helper.php** (12KB) - Session-based IMAP operations
4. **fetch_inbox_messages.php** (1.7KB) - Simplified fetch using session config
5. **save_settings.php** (6.9KB) - Settings save with lock mechanism

### UI Component (Add to Settings Page)
6. **imap_settings_ui_component.php** (12KB) - Ready-to-use settings interface

### Database
7. **database_migration.sql** (3.8KB) - Creates tables and default settings

### Documentation
8. **IMPLEMENTATION_GUIDE.md** (10KB) - Complete implementation guide
9. **QUICK_REFERENCE.md** (6.8KB) - Quick reference for developers

---

## 🎯 What This Solves

### Problems Fixed
✅ Hardcoded IMAP server settings throughout codebase  
✅ No centralized configuration management  
✅ Users could change critical settings repeatedly  
✅ No audit trail for admin actions  
✅ Email passwords stored insecurely  

### New Features
✅ Database-stored IMAP configuration  
✅ Session-based IMAP access (no hardcoded values)  
✅ One-time settings change with automatic locking  
✅ Super admin override with audit logging  
✅ Secure password handling (session-only)  
✅ Input validation and SQL injection protection  

---

## 🚀 Quick Installation

### Step 1: Backup (CRITICAL!)
```bash
# Backup database
mysqldump -u USER -p DATABASE > backup_$(date +%Y%m%d).sql

# Backup files
cp settings_helper.php settings_helper.php.backup
cp login.php login.php.backup
cp imap_helper.php imap_helper.php.backup
cp fetch_inbox_messages.php fetch_inbox_messages.php.backup
cp save_settings.php save_settings.php.backup
```

### Step 2: Database Migration
```bash
mysql -u u955994755_DB_supremacy -p u955994755_SXC_MDTS < database_migration.sql
```

### Step 3: Replace Files
```bash
# Copy new files to your htdocs directory
cp settings_helper.php /path/to/htdocs/
cp login.php /path/to/htdocs/
cp imap_helper.php /path/to/htdocs/
cp fetch_inbox_messages.php /path/to/htdocs/
cp save_settings.php /path/to/htdocs/
```

### Step 4: Add UI Component to Settings Page
Open your `settings.php` and add the IMAP settings section from `imap_settings_ui_component.php`

### Step 5: Configure Super Admin
```sql
INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
VALUES ('admin@sxccal.edu', 'is_super_admin', 'true', NOW())
ON DUPLICATE KEY UPDATE setting_value = 'true', updated_at = NOW();
```

### Step 6: Test
1. Login with existing account
2. Check inbox sync works
3. Try to save IMAP settings
4. Verify settings lock

---

## 🔐 Security Architecture

### Session-Based Configuration
```
Login → Load from DB → Store in Session → Use Everywhere
  ↓
$_SESSION['imap_config'] = [
    'imap_server' => 'imap.hostinger.com',
    'imap_port' => 993,
    'imap_encryption' => 'ssl',
    'imap_username' => 'user@sxccal.edu',
    'imap_password' => '********' // From login, NOT stored in DB
]
```

### Settings Lock Flow
```
First Save → Validate → Save to DB → Lock Settings → Future Changes Blocked
                                           ↓
                                   Only Super Admin Can Override
                                           ↓
                                   All Overrides Logged
```

### Password Security
- ✅ Password obtained during SMTP authentication
- ✅ Stored ONLY in server-side session
- ✅ NEVER stored in database
- ✅ NEVER sent to client
- ✅ Session expires after timeout
- ✅ Must re-login to renew

---

## 📊 Database Schema

### user_settings Table (Updated)
```sql
CREATE TABLE user_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP,
    UNIQUE KEY (user_email, setting_key)
);
```

**New Setting Keys:**
- `imap_server` - IMAP server hostname
- `imap_port` - IMAP port number
- `imap_encryption` - ssl/tls/none
- `imap_username` - IMAP login username
- `settings_locked` - true/false
- `is_super_admin` - true/false

### admin_audit_log Table (New)
```sql
CREATE TABLE admin_audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_email VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_user VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔧 Key Functions Reference

### settings_helper.php
```php
// Load IMAP config to session
loadImapConfigToSession($email, $password)

// Get IMAP config from session
getImapConfigFromSession() : array|null

// Check if settings are locked
areSettingsLocked($email) : bool

// Check if super admin
isSuperAdmin() : bool

// Lock/Unlock settings
lockSettings($email) : bool
unlockSettings($email) : bool

// Log admin actions
logSuperAdminAction($admin, $action, $target, $details)

// Validate IMAP settings
validateImapSettings($settings) : array
```

### imap_helper.php
```php
// Connect using session config
connectToIMAPFromSession() : resource|false

// Fetch messages using session config
fetchNewMessagesFromSession($email, $limit) : array

// Quick sync check
quickSyncCheckFromSession($email) : array
```

---

## 🎨 UI Component Features

The provided UI component includes:

✅ Lock status indicator (badge)  
✅ Warning messages for lock state  
✅ Input validation  
✅ Disabled inputs when locked  
✅ Super admin override button  
✅ Responsive design  
✅ Inline help text  
✅ Password security notice  
✅ Confirmation dialogs  

---

## 📋 Migration Checklist

### Pre-Deployment
- [ ] Read IMPLEMENTATION_GUIDE.md thoroughly
- [ ] Read QUICK_REFERENCE.md for quick tips
- [ ] Review all modified files
- [ ] Test in staging environment
- [ ] Backup database
- [ ] Backup PHP files

### Deployment
- [ ] Run database migration script
- [ ] Replace PHP files
- [ ] Add UI component to settings page
- [ ] Configure super admin
- [ ] Clear PHP opcode cache (if applicable)
- [ ] Restart Apache/Nginx (if needed)

### Post-Deployment
- [ ] Test login functionality
- [ ] Test inbox sync
- [ ] Test settings save (first time)
- [ ] Test settings lock (second attempt)
- [ ] Test super admin override
- [ ] Check error logs
- [ ] Check audit logs
- [ ] Monitor for 24 hours
- [ ] Update internal documentation

---

## 🛠️ Troubleshooting Guide

### Issue: "IMAP not configured"
**Cause:** Session doesn't have IMAP config  
**Solution:**
1. Check database: `SELECT * FROM user_settings WHERE setting_key LIKE 'imap_%'`
2. Logout and login again
3. If still fails, check error logs

### Issue: "Could not connect to IMAP server"
**Cause:** Wrong IMAP settings or network issue  
**Solution:**
1. Verify settings in database
2. Test connection: `openssl s_client -connect imap.hostinger.com:993`
3. Check firewall rules
4. Check error logs for detailed message

### Issue: Settings locked unexpectedly
**Cause:** User already saved settings once  
**Solution:**
1. This is expected behavior
2. To unlock: Super admin must do it
3. Or manually: `UPDATE user_settings SET setting_value='false' WHERE setting_key='settings_locked'`

### Issue: Super admin actions not logging
**Cause:** admin_audit_log table missing  
**Solution:**
1. Run database migration script
2. Verify table exists: `SHOW TABLES LIKE 'admin_audit_log'`

---

## 📞 Support & Maintenance

### Error Logging
All errors are logged to PHP error log. Check:
```bash
# Linux
tail -f /var/log/apache2/error.log

# Or check PHP error log location
php -i | grep error_log
```

### Audit Trail
Review admin actions regularly:
```sql
SELECT 
    admin_email,
    action,
    target_user,
    created_at
FROM admin_audit_log
ORDER BY created_at DESC
LIMIT 50;
```

### Session Monitoring
To debug session issues:
```php
<?php
session_start();
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
?>
```

---

## 🔄 Backward Compatibility

### Legacy Functions
Old functions still work but log deprecation warnings:

```php
// Old way (deprecated but works)
fetchNewMessages($email, $imapConfig, 50);

// New way (recommended)
fetchNewMessagesFromSession($email, 50);
```

### Migration Path
1. Deploy new code
2. Existing users continue to work with defaults
3. Users configure IMAP settings at their convenience
4. No immediate action required from users

---

## 📈 Performance Impact

### Minimal Overhead
- Session read: ~0.01ms
- Database query (cached): ~1-5ms
- IMAP connection: Same as before
- Overall impact: Negligible

### Scalability
- Session data per user: ~500 bytes
- Database rows per user: ~6 new rows
- Audit log growth: ~10 KB per month per admin

---

## 🔮 Future Enhancements

Possible improvements for future versions:

1. **Multi-account Support**
   - Allow users to configure multiple IMAP accounts
   - Switch between accounts in UI

2. **Settings Backup/Restore**
   - Export settings to JSON
   - Import settings from backup

3. **Scheduled Unlocking**
   - Auto-unlock after X days
   - Time-limited unlock tokens

4. **Advanced Audit**
   - Visual audit trail dashboard
   - Email notifications for admin actions
   - Compliance reporting

5. **OAuth2 Support**
   - Replace password with OAuth2 tokens
   - Enhanced security

---

## 📄 License & Credits

**Project:** SXC MDTS - Mail Delivery & Tracking System  
**Institution:** St. Xavier's College, Kolkata  
**Version:** 1.0.0  
**Date:** February 8, 2026  
**License:** Internal Use Only

---

## ✅ Summary

### What You Get
- ✅ 5 updated PHP files
- ✅ 1 ready-to-use UI component
- ✅ 1 database migration script
- ✅ 2 comprehensive documentation files
- ✅ Complete implementation package

### What It Does
- ✅ Removes all hardcoded IMAP settings
- ✅ Centralizes configuration in database
- ✅ Loads settings to session at login
- ✅ Locks settings after first save
- ✅ Provides super admin override
- ✅ Logs all admin actions
- ✅ Secures password handling

### Time to Deploy
- Reading docs: 30 minutes
- Testing in staging: 1 hour
- Production deployment: 15 minutes
- **Total: ~2 hours**

### Risk Level
**LOW** - Backward compatible, minimal changes to existing code

---

## 📞 Need Help?

If you encounter issues:

1. **Check Documentation**
   - IMPLEMENTATION_GUIDE.md - Detailed guide
   - QUICK_REFERENCE.md - Quick tips

2. **Check Logs**
   - PHP error log
   - Database query log
   - Audit log

3. **Debug Mode**
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

4. **Test Queries**
   ```sql
   -- Check settings
   SELECT * FROM user_settings WHERE user_email = 'your@email.com';
   
   -- Check locks
   SELECT * FROM user_settings WHERE setting_key = 'settings_locked';
   
   -- Check audit
   SELECT * FROM admin_audit_log ORDER BY created_at DESC;
   ```

---

**Ready to deploy? Start with IMPLEMENTATION_GUIDE.md**

Good luck! 🚀
