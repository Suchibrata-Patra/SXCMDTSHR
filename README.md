# SXC MDTS Inbox System

A complete, secure, and high-performance inbox system for the SXC MDTS email platform. Built with PHP, MySQL, and IMAP, featuring an Apple-inspired UI design.

---

## ✨ Features

### Core Functionality
- 📥 **IMAP Email Fetching** - Automatically sync emails from any IMAP server
- 📧 **Message Management** - Read, mark unread, delete messages
- 🔍 **Advanced Search** - Filter by sender, subject, date range
- 📄 **Pagination** - Smooth navigation through large inboxes
- 🏷️ **Unread Tracking** - Instant read/unread status updates
- 📎 **Attachment Detection** - Identifies messages with attachments
- 🗑️ **Soft Delete** - Messages moved to trash, not permanently deleted

### Security Features
- 🔐 **Session-based Authentication** - Secure user verification
- 🔒 **SQL Injection Protection** - Prepared statements throughout
- 🛡️ **XSS Prevention** - All output properly sanitized
- 🚫 **CSRF Protection** - JSON-based API endpoints
- 🔑 **App Password Support** - Secure IMAP authentication
- 📊 **Activity Logging** - All actions logged for audit

### Performance Features
- ⚡ **Database Indexing** - Optimized queries for fast loading
- 🔄 **Incremental Sync** - Fetches only new messages
- 💾 **Smart Caching** - Prevents duplicate message fetching
- 📊 **Lazy Loading** - Pagination prevents memory issues
- 🎯 **AJAX Updates** - No page reload for status changes

### UI/UX Features
- 🎨 **Apple-Inspired Design** - Clean, modern interface
- 📱 **Fully Responsive** - Works on desktop, tablet, mobile
- ⚡ **Instant Feedback** - Real-time status updates
- 🌈 **Visual States** - Clear unread/read styling
- 🔔 **Toast Notifications** - Non-intrusive success/error messages
- 🖱️ **Hover Actions** - Quick access to message actions

---

## 📁 Project Structure

```
inbox-system/
├── inbox.php                    # Main inbox page UI
├── inbox_functions.php          # Database CRUD functions
├── imap_helper.php             # IMAP connection & email fetching
├── fetch_inbox_messages.php    # AJAX endpoint - sync messages
├── mark_read.php               # AJAX endpoint - mark read/unread
├── delete_inbox_message.php    # AJAX endpoint - delete message
├── view_message.php            # Individual message viewer
├── create_inbox_table.sql      # Database schema
├── INSTALLATION_GUIDE.md       # Complete setup instructions
└── README.md                   # This file
```

---

## 🚀 Quick Start

### 1. Install PHP IMAP Extension

```bash
# Ubuntu/Debian
sudo apt-get install php-imap
sudo systemctl restart apache2

# Verify installation
php -m | grep imap
```

### 2. Create Database Tables

```bash
mysql -u username -p database_name < create_inbox_table.sql
```

### 3. Upload Files

Copy all files to your web root directory and set permissions:

```bash
chmod 644 *.php
chmod 644 *.sql
```

### 4. Configure IMAP Settings

Add to your `user_settings` table:

```sql
INSERT INTO user_settings (user_email, setting_key, setting_value) VALUES
('user@example.com', 'imap_server', 'imap.gmail.com'),
('user@example.com', 'imap_port', '993'),
('user@example.com', 'imap_password', 'your_app_password');
```

### 5. Access Inbox

Navigate to: `https://your-domain.com/inbox.php`

---

## 📋 Requirements

### Server Requirements
- **PHP:** 7.4 or higher
- **PHP Extensions:** 
  - `imap` (required)
  - `pdo_mysql` (required)
  - `openssl` (required)
  - `mbstring` (recommended)
- **MySQL:** 5.7+ or MariaDB 10.2+
- **Apache/Nginx:** With mod_rewrite enabled

### Email Server Requirements
- IMAP server access (port 993 for SSL)
- Valid email credentials
- For Gmail: App Password required

---

## 🎨 UI Design Principles

This inbox system follows the same design language as `sent_history.php`:

### Color Palette
```css
--apple-blue: #007AFF      /* Primary actions */
--apple-gray: #8E8E93      /* Secondary text */
--apple-bg: #F2F2F7        /* Background */
--border: #E5E5EA          /* Borders */
--unread-bg: #f0f9ff       /* Unread messages */
```

### Typography
- **Font Family:** Inter, -apple-system
- **Headings:** 700 weight, -0.8px letter spacing
- **Body:** 400-500 weight, 1.6 line height
- **UI Elements:** 600 weight, -0.08px letter spacing

### Spacing
- **Container Padding:** 30px-40px
- **Component Gaps:** 12px-20px
- **Border Radius:** 8px-12px for cards

---

## 🔧 Configuration Options

### Database Functions (db_config.php)

All inbox functions are namespaced and documented:

```php
// Fetch messages with filters
$messages = getInboxMessages($userEmail, $limit, $offset, $filters);

// Get counts
$total = getInboxMessageCount($userEmail, $filters);
$unread = getUnreadCount($userEmail);

// Update status
markMessageAsRead($messageId, $userEmail);
markMessageAsUnread($messageId, $userEmail);

// Soft delete
deleteInboxMessage($messageId, $userEmail);
```

### IMAP Configuration

Customize in `imap_helper.php`:

```php
// Adjust sync limit (default: 50)
$result = fetchNewMessages($userEmail, $imapConfig, 25);

// Change IMAP timeout
imap_timeout(IMAP_OPENTIMEOUT, 30);
```

### UI Customization

Modify CSS variables in `inbox.php`:

```css
:root {
    --apple-blue: #007AFF;     /* Change primary color */
    --perPage: 50;             /* Messages per page */
}
```

---

## 🔒 Security Best Practices

### 1. Authentication
```php
// Every page starts with:
session_start();
if (!isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
```

### 2. Database Queries
```php
// Always use prepared statements
$stmt = $pdo->prepare("SELECT * FROM inbox_messages WHERE id = :id");
$stmt->execute([':id' => $messageId]);
```

### 3. Output Sanitization
```php
// Always escape output
echo htmlspecialchars($message['subject']);
```

### 4. HTTPS Only
```php
if (empty($_SERVER['HTTPS'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

## 📊 Database Schema

### inbox_messages Table

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| message_id | VARCHAR(255) | Unique message identifier |
| user_email | VARCHAR(255) | Recipient email |
| sender_email | VARCHAR(255) | Sender email |
| sender_name | VARCHAR(255) | Sender display name |
| subject | TEXT | Email subject |
| body | LONGTEXT | Email body |
| received_date | DATETIME | Original receive date |
| fetched_at | DATETIME | When fetched from server |
| is_read | TINYINT(1) | Read status (0/1) |
| read_at | DATETIME | When marked as read |
| has_attachments | TINYINT(1) | Has attachments (0/1) |
| is_starred | TINYINT(1) | Starred (0/1) |
| is_deleted | TINYINT(1) | Soft delete flag (0/1) |

**Indexes:**
- `idx_user_email` - Fast user filtering
- `idx_message_id` - Duplicate prevention
- `idx_received_date` - Chronological sorting
- `idx_inbox_main` - Composite index for main query

---

## 🧪 Testing

### Manual Testing Checklist

- [ ] IMAP connection successful
- [ ] Messages sync correctly
- [ ] Unread messages highlighted
- [ ] Click message marks as read
- [ ] Toggle read/unread works
- [ ] Delete removes from view
- [ ] Search filters work
- [ ] Date filters work
- [ ] Pagination works
- [ ] Responsive on mobile
- [ ] Toast notifications appear
- [ ] No console errors

### Performance Testing

```bash
# Test database query performance
EXPLAIN SELECT * FROM inbox_messages 
WHERE user_email = 'user@example.com' 
AND is_deleted = 0 
ORDER BY received_date DESC 
LIMIT 50;

# Should use indexes
```

---

## 🐛 Common Issues & Solutions

### Issue: "Call to undefined function imap_open()"
**Solution:** Install PHP IMAP extension
```bash
sudo apt-get install php-imap
sudo systemctl restart apache2
```

### Issue: "IMAP connection failed"
**Solution:** 
1. Check IMAP credentials
2. For Gmail, use App Password
3. Enable IMAP in email settings
4. Check firewall allows port 993

### Issue: "Duplicate entry for key 'unique_message'"
**Solution:** This is normal - prevents duplicate messages

### Issue: Slow page loading
**Solution:**
1. Add database indexes (check SQL file)
2. Reduce sync limit to 25 messages
3. Enable query caching

---

## 🔄 Maintenance

### Daily
- Monitor error logs
- Check sync success rate

### Weekly
- Review unread counts
- Check disk space (messages table grows)

### Monthly
```sql
-- Optimize tables
OPTIMIZE TABLE inbox_messages;
ANALYZE TABLE inbox_messages;

-- Archive old messages (optional)
UPDATE inbox_messages 
SET is_deleted = 1 
WHERE received_date < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

---

## 🎯 Roadmap

Future enhancements planned:

- [ ] Attachment download functionality
- [ ] Rich text email display (HTML emails)
- [ ] Email reply functionality
- [ ] Message forwarding
- [ ] Folder/label support
- [ ] Advanced spam filtering
- [ ] Email templates
- [ ] Batch operations (select multiple)
- [ ] Export to PDF/CSV
- [ ] Email signatures

---

## 📄 License

This project is part of the SXC MDTS email system.

---

## 🙏 Credits

**Design Inspiration:** Apple Mail, Gmail
**Framework:** Custom PHP with modern CSS
**Icons:** Material Icons by Google
**Fonts:** Inter by Rasmus Andersson

---

## 📞 Support

For issues or questions:
1. Check `INSTALLATION_GUIDE.md` for detailed setup
2. Review error logs: `/var/log/apache2/error.log`
3. Contact system administrator

---

**Built with ❤️ for SXC MDTS**

Version 1.0.0 | Last Updated: February 2026
