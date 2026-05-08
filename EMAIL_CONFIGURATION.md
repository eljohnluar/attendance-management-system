# Email Configuration Guide

## Overview
The Smart Attendance System has a flexible email configuration system that works across different devices and deployments.

## Default Behavior (Recommended)
By default, **`ENABLE_SMTP_EMAIL` is set to `false`** in `includes/config.php`. This means:
- ✅ Emails are logged to the `logs/` directory as HTML files
- ✅ Works on any device without configuration
- ✅ No SMTP server needed
- ✅ Admins can view email content in the logs folder

All verification links, password reset links, and login codes are still created and functional. Users can retrieve them from the `logs/` folder on the server.

## To Enable Actual Email Sending

### Option 1: Use Local Development SMTP (Localhost)
If you're running a local SMTP service like Papercut, MailHog, or FakeSMTP on port 25, 1025, 2525, or 8025, the system will auto-detect it on localhost automatically.

### Option 2: Manual SMTP Configuration (Gmail, Outlook, Custom Server)

Edit `includes/config.php` and update the `SMTP_CONFIG` array:

```php
define('SMTP_CONFIG', [
    'host' => 'smtp.gmail.com',        // Your SMTP server
    'port' => 587,                      // Typically 587 (TLS) or 465 (SSL)
    'auth' => true,                     // Most servers require auth
    'username' => 'your-email@gmail.com', // Your email
    'password' => 'your-app-password',   // Your password or app-specific password
    'from_email' => 'noreply@domain.com',
    'from_name' => 'Smart Attendance System',
    'secure' => PHPMailer::ENCRYPTION_STARTTLS  // or ENCRYPTION_SMTPS for port 465
]);
```

Then set:
```php
define('ENABLE_SMTP_EMAIL', true);
```

### Option 3: Environment Variables (Production)
Set environment variables on your server:
- `MAIL_HOST` - SMTP server hostname
- `MAIL_PORT` - SMTP port
- `MAIL_USERNAME` - Your email
- `MAIL_PASSWORD` - Your password
- `MAIL_FROM_EMAIL` - From address (optional)
- `MAIL_FROM_NAME` - From name (optional)

## Gmail Setup (Recommended for Testing)
1. Go to [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
2. Generate an App Password (requires 2FA enabled)
3. Use the 16-character password in the configuration
4. Keep `host` as `smtp.gmail.com` and `port` as `587`

## Viewing Logged Emails
When `ENABLE_SMTP_EMAIL` is false, all emails are saved to:
- `logs/emails.log` - Text log of all emails
- `logs/email_YYYYMMDD_HHMMSS.html` - HTML version of each email
- `logs/email_failures.log` - SMTP failures (if enabled but failed)

You can view these files through:
1. Server file manager
2. Admins panel (if implemented)
3. FTP/SFTP access

## Troubleshooting

**"Email saved to logs (SMTP failed)" message:**
- This means `ENABLE_SMTP_EMAIL` is true but SMTP connection failed
- Either set `ENABLE_SMTP_EMAIL` to false (recommended), or fix your SMTP configuration

**Emails not arriving (SMTP enabled):**
- Check Gmail/Outlook spam folder
- Verify username and password are correct
- Ensure App Password is used for Gmail (not regular password)
- Check server firewall allows outbound SMTP connections

**Local SMTP not detected:**
- Ensure Papercut/MailHog is running
- Verify it's on localhost and port 25, 1025, 2525, or 8025
- Check firewall settings

## Best Practices

| Deployment | Recommended | Why |
|---|---|---|
| **Local Development** | Log to files or use Papercut | Easy, no config needed |
| **Multi-device/Testing** | Log to files | Works everywhere, no SMTP needed |
| **Production** | Use Gmail App Password or SendGrid | Reliable, easy to manage |
| **Enterprise** | Use company SMTP + environment variables | Secure, centralized config |

---

For more help, check the `logs/` directory for email content and error messages.
