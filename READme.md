# BeautyMail - Professional Email Sender

## 🚀 Features

### Core Features
- **Dynamic SMTP Configuration** - Users can configure their own email server settings
- **Beautiful Email Templates** - 4 professionally designed templates (Business, Creative, Minimal, Modern)
- **Bulk & Single Email Sending** - Support for both individual and mass email campaigns
- **Real-time Progress Tracking** - Live updates showing email sending progress
- **File Attachments** - Support for documents, images, and company logos
- **Responsive Design** - Works perfectly on desktop and mobile devices
- **Email Preview** - Preview emails before sending
- **Error Handling** - Comprehensive error reporting and logging

### Template Styles
1. **Business Professional** - Corporate blue theme, perfect for official communications
2. **Creative & Modern** - Vibrant pink theme, ideal for marketing campaigns
3. **Minimal & Clean** - Gray minimalist design, great for newsletters
4. **Modern & Fresh** - Green gradient theme, perfect for tech companies

## 📋 Requirements

### Server Requirements
- PHP 7.4 or higher
- PHPMailer library
- Web server (Apache/Nginx)
- Write permissions for uploads and logs directories

### PHPMailer Installation
```bash
# Using Composer (recommended)
composer require phpmailer/phpmailer

# Or download manually from GitHub
https://github.com/PHPMailer/PHPMailer
```

## 🛠️ Installation Steps

### 1. File Structure
Create the following directory structure:
```
beautymail/
├── index.html (main interface)
├── send_email.php (backend script)
├── PHPMailer/ (PHPMailer library)
│   ├── src/
│   │   ├── PHPMailer.php
│   │   ├── SMTP.php
│   │   └── Exception.php
├── uploads/ (created automatically)
├── uploads/documents/ (created automatically)
└── logs/ (created automatically)
```

### 2. Set Directory Permissions
```bash
chmod 755 beautymail/
chmod 777 uploads/
chmod 777 logs/
```

### 3. Configure PHP Settings
Ensure these settings in your `php.ini`:
```ini
file_uploads = On
upload_max_filesize = 10M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

## 🔧 SMTP Configuration Guide

### Popular SMTP Providers

#### Gmail
- Host: `smtp.gmail.com`
- Port: `587` (TLS) or `465` (SSL)
- Security: TLS
- Username: Your Gmail address
- Password: App Password (not your Gmail password)

#### Outlook/Hotmail
- Host: `smtp-mail.outlook.com`
- Port: `587`
- Security: TLS
- Username: Your Outlook email
- Password: Your Outlook password

#### Yahoo
- Host: `smtp.mail.yahoo.com`
- Port: `587` or `465`
- Security: TLS or SSL
- Username: Your Yahoo email
- Password: App Password

#### Hostinger
- Host: `smtp.hostinger.com`
- Port: `587`
- Security: TLS
- Username: Your email address
- Password: Your email password

#### Custom/Business Email
- Check with your email provider for SMTP settings
- Usually: `smtp.yourdomain.com`
- Common ports: 587 (TLS), 465 (SSL), 25 (insecure)

## 📧 Usage Instructions

### Single Email Sending
1. Configure SMTP settings
2. Choose email template
3. Select "Single Email" option
4. Fill recipient details
5. Add content and attachments
6. Preview and send

### Bulk Email Sending
1. Configure SMTP settings
2. Choose email template  
3. Select "Bulk Email" option
4. Upload .txt file with email list (one email per line)
5. Add content and attachments
6. Preview and send
7. Monitor real-time progress

### Email List Format (.txt file)
```
john@example.com
jane@company.com
user@domain.org
customer@business.net
```

## 🎨 Customization

### Adding New Templates
To add new email templates, modify the `generateBeautyMailTemplate()` function in `send_email.php`:

```php
$templateStyles = [
    'your_template' => [
        'primary' => '#your-primary-color',
        'secondary' => '#your-secondary-color',
        'background' => '#your-background-color',
        'text' => '#your-text-color',
        'accent' => '#your-accent-color'
    ]
];
```

### Styling Modifications
- Main styles are in the HTML file's `<style>` section
- Email template styles are in the PHP file
- All styles use modern CSS with gradients and animations

## 🔒 Security Features

### File Upload Security
- File type validation
- File size limits (5MB for logos, 10MB for documents)
- Unique filename generation
- Automatic cleanup after sending

### Input Sanitization
- HTML entity encoding
- Input validation
- Email format validation
- CSRF protection ready

### Error Handling
- Comprehensive error logging
- User-friendly error messages
- Debug information for troubleshooting

## 📊 Monitoring & Logging

### Email Activity Logs
The system automatically logs:
- Timestamp of email sending
- Recipient email addresses
- Email subjects
- Success/failure status
- Error messages
- User IP addresses

Log file location: `logs/email_log.json`

### Progress Tracking
- Real-time progress bar for bulk emails
- Individual email status updates
- Success/failure counters
- Detailed error reporting

## 🎯 Best Practices

### For Bulk Emails
1. **Respect Rate Limits** - Don't send too many emails too quickly
2. **Clean Email Lists** - Remove invalid/bounced emails
3. **Personalization** - Use recipient names when possible
4. **Content Quality** - Avoid spam trigger words
5. **Unsubscribe Links** - Include unsubscribe options for marketing emails

### For SMTP Usage
1. **Use App Passwords** - For Gmail and Yahoo
2. **Monitor Quotas** - Check daily sending limits
3. **Authenticate Properly** - Use correct credentials
4. **Test Configuration** - Send test emails first

### For Security
1. **Keep Libraries Updated** - Regular PHPMailer updates
2. **Validate Inputs** - Always sanitize user data
3. **Limit File Sizes** - Prevent server overload
4. **Monitor Logs** - Check for suspicious activity

## 🐛 Troubleshooting

### Common Issues

#### SMTP Connection Failed
- Check SMTP credentials
- Verify host and port settings
- Check firewall restrictions
- Enable "Less secure app access" if using Gmail

#### File Upload Errors
- Check directory permissions
- Verify PHP upload settings
- Ensure sufficient disk space
- Check file size limits

#### Email Not Received
- Check spam folders
- Verify recipient email addresses
- Check SMTP quotas
- Review email content for spam triggers

#### JavaScript Errors
- Enable browser developer tools
- Check console for errors
- Verify file paths
- Check CORS settings

### Debug Mode
Enable debug mode by uncommenting this line in `send_email.php`:
```php
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
```

## 📱 Mobile Responsiveness

The interface is fully responsive and includes:
- Touch-friendly buttons and inputs
- Optimized layouts for small screens
- Readable text sizes
- Proper viewport settings
- Mobile-optimized email templates

## 🔄 Updates & Maintenance

### Regular Maintenance
1. **Clean Upload Directory** - Remove old temporary files
2. **Rotate Log Files** - Prevent logs from growing too large
3. **Update Dependencies** - Keep PHPMailer current
4. **Monitor Performance** - Check server resources

### Feature Enhancements
- Email scheduling functionality
- Template customization interface
- Advanced analytics and reporting
- Integration with popular CRM systems
- API endpoints for external applications

## 📞 Support

For issues and questions:
1. Check the troubleshooting section
2. Review PHP error logs
3. Test with simple configurations first
4. Verify all requirements are met

## 📄 License

This project is open source and available under the MIT License. Feel free to modify and distribute according to your needs.

---

**BeautyMail** - Making professional email sending beautiful and simple! ✨