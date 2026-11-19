# Quick SMTP Setup Guide

## Gmail SMTP Configuration (Step-by-Step)

### 1. Get Gmail App Password
1. Go to https://myaccount.google.com/security
2. Enable **2-Step Verification** (if not already enabled)
3. Go to https://myaccount.google.com/apppasswords
4. Select "Mail" and your device name
5. Click **Generate**
6. Copy the 16-character password (example: `abcd efgh ijkl mnop`)
7. Remove spaces when entering: `abcdefghijklmnop`

### 2. Configure in Civicare Admin
1. Login as Admin
2. Go to **Settings** → **SMTP Settings** tab
3. Fill in these exact values:

```
Enable SMTP: ✓ [checked]
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
SMTP Username: your-email@gmail.com
SMTP Password: [paste 16-char App Password here]
From Email: your-email@gmail.com
From Name: Civicare System
```

4. Click **Save SMTP Settings**

### 3. Test Configuration
1. Click **Test SMTP** button
2. Enter your email: `your-email@gmail.com`
3. Click **Send Test Email**
4. Wait 5-10 seconds
5. Check your inbox (and spam folder)

### Expected Results
✓ **Success:** "Test email sent successfully to your-email@gmail.com"
✗ **Failure:** See error message for troubleshooting

## Common Issues & Solutions

### Issue: "Failed to connect to smtp.gmail.com:587"
**Solution:**
- Check internet connection
- Verify SMTP Host is exactly: `smtp.gmail.com` (no extra spaces)
- Try port 465 with SSL encryption instead
- Temporarily disable firewall to test

### Issue: "Authentication failed"
**Solutions:**
- Verify you're using **App Password**, not regular Gmail password
- App Password should be 16 characters (remove spaces)
- Username should be full email: `your-email@gmail.com`
- Make sure 2-Step Verification is enabled in Google Account
- Try generating a new App Password

### Issue: "Failed to enable TLS encryption"
**Solutions:**
- Open XAMPP Control Panel
- Edit php.ini (Config button next to Apache)
- Find line: `;extension=openssl`
- Remove semicolon: `extension=openssl`
- Save and restart Apache
- Try test again

**Alternative:** Use SSL instead of TLS
- Change Port to: `465`
- Change Encryption to: `SSL`

### Issue: Email goes to spam
**Solutions:**
- Add sender email to contacts
- Mark test email as "Not Spam"
- For production, use custom domain email

## Testing Checklist

- [ ] 2-Step Verification enabled in Google Account
- [ ] App Password generated (16 characters)
- [ ] SMTP Host: `smtp.gmail.com`
- [ ] SMTP Port: `587`
- [ ] Encryption: `TLS`
- [ ] Username: full Gmail address
- [ ] Password: App Password (not regular password)
- [ ] From Email: same Gmail address
- [ ] Enable SMTP: checked
- [ ] Email Notifications: enabled (Notifications tab)
- [ ] Test email sent successfully
- [ ] Test email received in inbox

## Alternative: Outlook/Office 365

If Gmail doesn't work, try Outlook:

```
Enable SMTP: ✓
SMTP Host: smtp.office365.com
SMTP Port: 587
Encryption: TLS
SMTP Username: your-email@outlook.com
SMTP Password: [your Outlook password]
From Email: your-email@outlook.com
From Name: Civicare System
```

**Note:** Outlook uses regular password, not App Password

## Still Not Working?

1. **Check PHP OpenSSL:**
   - Create file: `c:/xampp/htdocs/test.php`
   - Content: `<?php phpinfo(); ?>`
   - Open: http://localhost/test.php
   - Search for "openssl" - should see "OpenSSL support: enabled"

2. **Check Firewall:**
   - Windows Firewall → Allow an app
   - Make sure Apache/PHP can access network

3. **Test with Online Tool:**
   - Use https://www.smtper.net/ to test SMTP credentials
   - If it works there but not in Civicare, it's a code issue
   - If it doesn't work there either, it's a credentials issue

4. **Check Error Logs:**
   - Look in: `c:/xampp/apache/logs/error.log`
   - Check for SMTP-related errors

## Success!

Once you see "✓ Test email sent successfully", your SMTP is configured correctly.

Now test the complaint workflow:
1. File a complaint as citizen
2. Check citizen's email for "Complaint Filed" notification
3. Validate complaint as admin
4. Check citizen's email for "Complaint Validated" notification

All complaint status changes will now send real-time emails!

---
**Need Help?** Check [EMAIL_NOTIFICATION_SETUP.md](EMAIL_NOTIFICATION_SETUP.md) for detailed documentation.
