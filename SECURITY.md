# Security Policy

## Current Security Status

✅ **This project now implements secure authentication and data storage for local/personal server use.**

This habit tracker uses PHP-based server-side authentication with proper password hashing and session management. It is designed for **personal, local server use** or deployment on a trusted server.

## Implemented Security Features

### Authentication
- ✅ **Password hashing**: Passwords are hashed using PHP's `password_hash()` with bcrypt (PASSWORD_DEFAULT)
- ✅ **Session management**: Secure server-side sessions with httponly cookies
- ✅ **CSRF protection**: CSRF tokens required for all data-modifying operations
- ✅ **Rate limiting**: Login attempts are limited (5 attempts, 15-minute lockout)
- ✅ **Session timeout**: Sessions expire after 1 hour of inactivity
- ✅ **No client-side password storage**: All authentication happens server-side

### Data Storage
- ✅ **Server-side storage**: All habit data is stored in JSON files on the server
- ✅ **Per-user data isolation**: Each user has their own data file
- ✅ **Protected data directory**: `.htaccess` prevents direct access to JSON files
- ✅ **No localStorage usage**: All sensitive data stays on the server
- ✅ **Secure file permissions**: Data directory is protected from web access

### Security Headers
- ✅ **X-Frame-Options**: Prevents clickjacking attacks
- ✅ **X-XSS-Protection**: Enables browser XSS protection
- ✅ **X-Content-Type-Options**: Prevents MIME sniffing
- ✅ **Referrer-Policy**: Controls referrer information
- ✅ **Content-Security-Policy**: Restricts resource loading

## Deployment Considerations

### ✅ Local/Personal Use (Current Status: SECURE)

For localhost or trusted personal server use:
- ✅ All security features are implemented
- ✅ Safe for personal habit tracking
- ✅ No additional steps needed
- ✅ HTTP is acceptable on localhost

**You're ready to use the app as-is for personal use!**

### ⚠️ Public Server Deployment (Additional Steps Required)

If deploying to a public web server, **you MUST complete this checklist:**

#### **Critical Requirements (Non-Negotiable)**

1. **HTTPS/SSL Certificate**
   - ❌ **NEVER deploy without HTTPS**
   - Use Let's Encrypt (free) or purchase certificate
   - Our code automatically enables secure cookies when HTTPS is detected
   - Reason: Prevents session hijacking and man-in-the-middle attacks

2. **Strict File Permissions**
   ```bash
   sudo chmod 700 data/                    # Owner read/write/execute only
   sudo chmod 600 data/*.json             # Owner read/write only
   sudo chown www-data:www-data data/     # Web server owns files
   ```
   - Reason: Prevents other users on shared hosting from reading password hashes

3. **PHP Production Configuration**
   ```ini
   display_errors = Off          # Don't show errors to users
   log_errors = On              # Log errors to file instead
   expose_php = Off             # Don't advertise PHP version
   session.cookie_secure = On   # HTTPS-only cookies
   ```
   - Reason: Prevents information leakage to attackers

4. **HTTP to HTTPS Redirect**
   ```apache
   # Force HTTPS
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
   - Reason: Ensures users always connect securely

5. **Regular Backups**
   - Schedule automated backups of `data/` directory
   - Store backups off-server
   - Test restore process
   - Reason: Protect against data loss

#### **Recommended Security Enhancements**

6. **Server-Level Rate Limiting**
   - Install and configure Fail2Ban
   - Monitor failed login attempts in PHP logs
   - Ban IPs after repeated failures
   - Reason: Adds defense-in-depth beyond app-level rate limiting

7. **HTTP Security Headers**
   - HSTS (Strict-Transport-Security)
   - Content-Security-Policy
   - Already configured in `api/.htaccess`
   - Verify they're working: https://securityheaders.com

8. **Firewall Configuration**
   ```bash
   sudo ufw allow 80/tcp     # HTTP (for redirect)
   sudo ufw allow 443/tcp    # HTTPS
   sudo ufw allow 22/tcp     # SSH
   sudo ufw enable
   ```

9. **Keep Software Updated**
   ```bash
   sudo apt update && sudo apt upgrade    # Ubuntu/Debian
   ```
   - Update PHP, Apache, OS packages regularly
   - Subscribe to security mailing lists

#### **Optional: For High-Security Needs**

10. **Database Migration**
    - Migrate from JSON files to MySQL/PostgreSQL
    - Provides better access controls and transaction safety
    - Overkill for most personal use cases

11. **Encryption at Rest**
    - Encrypt JSON files on disk
    - Use PHP's openssl or sodium extension
    - Only needed for highly sensitive data

12. **Two-Factor Authentication (2FA)**
    - Add TOTP support (Google Authenticator, Authy)
    - Requires additional development

13. **Email Verification & Password Reset**
    - Verify email addresses during registration
    - Add "forgot password" functionality
    - Requires mail server configuration

### For Developers

If forking this project for commercial/SaaS use, consider:
- Database support (MySQL/PostgreSQL) instead of JSON files
- Redis/Memcached for session storage at scale
- Container deployment (Docker/Kubernetes)
- CI/CD pipeline with security scanning
- Automated security testing (OWASP ZAP, etc.)
- DDoS protection (Cloudflare, AWS Shield)
- Compliance certifications (SOC 2, GDPR, etc.)

## Upcoming Security Enhancements

The following security features are planned for future releases:

### Email Integration (Optional)
- **Email verification** for new accounts
- **Password reset** via secure token links
- **Email validation** and sanitization
- **SMTP security** best practices
- **Configuration flags** to enable/disable email features (for self-hosters)

⚠️ **Note**: When email integration is added, it will be OPTIONAL via configuration. Self-hosters can choose to:
- Enable email features (requires SMTP setup)
- Disable email (username-only, current behavior)

### Data Export Security
- ✅ **Already implemented**: JSON export endpoint with authentication
- **Planned**: Export encryption option for sensitive data
- **Planned**: Export history/audit log

### Account Management
- **Planned**: Secure account deletion with data purge confirmation
- **Planned**: Password change with current password verification
- **Planned**: Session management (view active sessions, logout all devices)

## Migration from Previous Version

If you were using the old client-side version:
- Old data stored in localStorage will NOT be automatically migrated
- You will need to create a new account through the registration screen
- Manual migration: Export old data as CSV, then manually re-enter habits
- The new system does not read from localStorage

## Security Improvements Completed

The following security enhancements have been implemented:
- ✅ Proper password hashing implementation (bcrypt)
- ✅ Server-side authentication with sessions
- ✅ CSRF token protection
- ✅ Rate limiting on login attempts
- ✅ Server-side data storage (no localStorage for sensitive data)
- ✅ Security headers implementation
- ✅ Protected data directory

## Reporting a Vulnerability

If you discover a security vulnerability in this project, please report it by:

1. **Opening a GitHub Issue** - Label it as "security"
2. **Email** - Contact the repository owner directly through GitHub

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

### Response Time
As this is a personal project and proof of concept:
- I will acknowledge reports within 7 days
- Fixes will be implemented on a best-effort basis
- Critical issues affecting personal data will be prioritized

## Supported Versions

| Version | Supported          | Status |
| ------- | ------------------ | ------ |
| Current (Secure) | ✅ Production-Ready | Secure for local use; requires HTTPS for public deployment |
| Legacy (Pre-Oct 2025) | ❌ Deprecated | Plain-text passwords, localStorage only - do not use |

## Disclaimer

This software is provided "as is" under the MIT License. Use at your own risk. The author takes no responsibility for any security breaches, data loss, or other issues arising from the use of this software.
