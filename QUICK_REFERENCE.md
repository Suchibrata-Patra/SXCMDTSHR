# IMAP Settings Refactor - Quick Reference Card

## 🚀 Quick Start

### 1. Install (5 minutes)
```bash
# Backup first!
cp settings_helper.php settings_helper.php.backup

# Run migration
mysql -u USER -p DATABASE < database_migration.sql

# Replace files
cp new_files/* /path/to/htdocs/
```

### 2. Make Someone Super Admin
```sql
INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
VALUES ('admin@sxccal.edu', 'is_super_admin', 'true', NOW())
ON DUPLICATE KEY UPDATE setting_value = 'true';
```

### 3. Test
1. Login → IMAP config loads to session
2. Go to Inbox → Sync works
3. Go to Settings → Save IMAP settings
4. Try to change again → Locked!

---

## 📋 What Changed

| Before | After |
|--------|-------|
| Hardcoded IMAP settings | Database-stored settings |
| Direct IMAP config in code | Session-based config |
| Unlimited changes | One-time change + lock |
| No audit trail | Full admin action logging |

---

## 🔑 Key Functions

```php
// Load settings to session (login.php)
loadImapConfigToSession($email, $password);

// Get IMAP config from session
$config = getImapConfigFromSession();

// Check if locked
if (areSettingsLocked($email)) { /* ... */ }

// Check if super admin
if (isSuperAdmin()) { /* ... */ }

// Fetch emails (new way)
fetchNewMessagesFromSession($email, 50);

// Log admin action
logSuperAdminAction($admin, $action, $user, $details);
```

---

## 🗄️ Database

### New Settings Keys
- `imap_server` - Server hostname
- `imap_port` - Port number
- `imap_encryption` - ssl/tls/none
- `imap_username` - Email address
- `settings_locked` - true/false
- `is_super_admin` - true/false

### New Table
```sql
admin_audit_log
├── admin_email
├── action
├── target_user
├── details (JSON)
├── ip_address
└── created_at
```

---

## 🔒 Security Flow

```
┌─────────────────────────────────────────┐
│ User saves IMAP settings (1st time)    │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ Validate input (server, port, etc.)    │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ Save to database                        │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ Set settings_locked = true              │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ Future changes → BLOCKED                │
│ (unless super_admin)                    │
└─────────────────────────────────────────┘
```

---

## 🛠️ Troubleshooting

### "IMAP not configured"
```php
// Check session
var_dump($_SESSION['imap_config']);

// Check database
SELECT * FROM user_settings 
WHERE user_email = 'user@sxccal.edu' 
AND setting_key LIKE 'imap_%';
```

### "Settings locked"
```sql
-- Unlock (super admin only)
UPDATE user_settings 
SET setting_value = 'false' 
WHERE setting_key = 'settings_locked' 
AND user_email = 'user@sxccal.edu';
```

### Connection fails
```bash
# Test IMAP manually
openssl s_client -connect imap.hostinger.com:993
```

---

## 📁 Files Modified

| File | Changes |
|------|---------|
| `settings_helper.php` | +200 lines (IMAP functions) |
| `login.php` | +15 lines (load to session) |
| `imap_helper.php` | +50 lines (session-based) |
| `fetch_inbox_messages.php` | -20 lines (simplified) |
| `save_settings.php` | +100 lines (lock mechanism) |

---

## ✅ Checklist

- [ ] Database backup
- [ ] File backup
- [ ] Run migration SQL
- [ ] Replace PHP files
- [ ] Set super admin
- [ ] Test login
- [ ] Test sync
- [ ] Test settings save
- [ ] Test lock mechanism
- [ ] Check audit logs

---

## 📞 Common Queries

**Q: How to make a user super admin?**
```sql
INSERT INTO user_settings VALUES 
('admin@email.com', 'is_super_admin', 'true', NOW());
```

**Q: How to unlock settings for a user?**
```sql
UPDATE user_settings SET setting_value = 'false' 
WHERE user_email = 'user@email.com' 
AND setting_key = 'settings_locked';
```

**Q: Where are passwords stored?**
A: ONLY in session (`$_SESSION['smtp_pass']`). NEVER in database.

**Q: Can users change IMAP settings?**
A: Only ONCE. After that, locked. Only super admin can unlock.

**Q: What if session expires?**
A: User logs in again → settings reload from database.

---

## 🎯 Best Practices

1. ✅ Always backup before deployment
2. ✅ Test in staging environment first
3. ✅ Monitor error logs after deployment
4. ✅ Set up at least 1 super admin
5. ✅ Review audit logs weekly
6. ✅ Keep database credentials secure
7. ✅ Use HTTPS in production
8. ✅ Set proper session timeout

---

## 📊 Audit Log Example

```sql
SELECT * FROM admin_audit_log 
ORDER BY created_at DESC 
LIMIT 10;
```

Output:
```
admin_email        | action              | target_user      | created_at
-------------------|---------------------|------------------|-------------------
admin@sxccal.edu   | UNLOCK_SETTINGS     | user@sxccal.edu  | 2026-02-08 14:30
admin@sxccal.edu   | IMAP_SETTINGS_OVERRIDE | user2@sxccal.edu | 2026-02-08 13:15
```

---

## 🔐 Session Structure

```php
$_SESSION = [
    'smtp_user' => 'user@sxccal.edu',
    'smtp_pass' => '********',
    'authenticated' => true,
    'user_role' => 'user', // or 'super_admin'
    'imap_config' => [
        'imap_server' => 'imap.hostinger.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'user@sxccal.edu',
        'imap_password' => '********'
    ]
];
```

---

## 📝 Notes

- Password is from login, not stored in DB
- Settings lock is per-user, not global
- Super admin can override any lock
- All admin actions are logged
- Session expires after timeout
- IMAP config reloads on each login

---

**Version:** 1.0.0  
**Last Updated:** February 8, 2026  
**Compatibility:** PHP 7.4+, MySQL 5.7+
