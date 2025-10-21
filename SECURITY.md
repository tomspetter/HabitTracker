# Security Policy

## Current Security Status

✅ **This project now implements secure authentication and data storage for local/personal server use.**

This habit tracker uses PHP-based server-side authentication with proper password hashing and session management. It is designed for **personal, local server use** or deployment on a trusted server.

## Implemented Security Features

### Data Encryption (MySQL Version)
- ✅ **AES-256-CBC encryption**: Habit names are encrypted server-side before storage in database
- ✅ **User-specific encryption keys**: Each user gets a unique encryption key derived from master key + user ID
- ✅ **Unique IVs**: Each encrypted value uses its own Initialization Vector for maximum security
- ✅ **Environment variable storage**: Master encryption key stored in `HABIT_ENCRYPTION_KEY` environment variable (never in code)
- ✅ **Breach protection**: Even if database is compromised, habit names remain encrypted and unreadable without the master key
- ✅ **OpenSSL implementation**: Uses PHP's OpenSSL extension with industry-standard AES-256-CBC

**What's Protected in a Database Breach:**
- ✅ Habit names (encrypted) - e.g., "Stop drinking", "Therapy sessions"
- ✅ Passwords (bcrypt hashed) - cannot be reversed
- ❌ Dates (not encrypted) - needed for efficient queries, meaningless without habit names
- ❌ Colors (not encrypted) - just UI preferences, not sensitive
- ❌ Emails (not encrypted) - needed for login, not considered secret

### Authentication & Email Verification
- ✅ **Email verification**: Required for new account registration using 6-digit codes
- ✅ **Password reset flow**: Secure 3-step password recovery with verification codes
- ✅ **Password hashing**: Passwords are hashed using PHP's `password_hash()` with bcrypt (PASSWORD_DEFAULT)
- ✅ **Session management**: Secure server-side sessions with httponly cookies
- ✅ **CSRF protection**: CSRF tokens required for all data-modifying operations
- ✅ **Login rate limiting**: 5 failed attempts trigger 15-minute lockout
- ✅ **Code rate limiting**: 60-second cooldown between verification code resends
- ✅ **Code expiration**: Verification codes expire after 15 minutes, reset tokens after 5 minutes
- ✅ **Attempt limiting**: Maximum 5 attempts per verification code before requiring new code
- ✅ **Email enumeration prevention**: Password reset doesn't reveal whether user exists
- ✅ **Session timeout**: Sessions expire after 1 hour of inactivity
- ✅ **No client-side password storage**: All authentication happens server-side

### Data Storage
- ✅ **MySQL database**: All habit data is stored securely in MySQL database
- ✅ **Per-user data isolation**: Each user has their own data with unique encryption keys
- ✅ **Protected database**: Encrypted habit names ensure privacy even in a database breach
- ✅ **No localStorage usage**: All sensitive data stays on the server
- ✅ **Database access control**: MySQL user privileges limit access to application database only

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

1. **Email Service Configuration**
   - ⚠️ **Required for registration and password reset**
   - Copy `app/email_config.sample.php` to `app/email_config.php`
   - Add your Brevo API key and sender email
   - Verify your domain with SPF, DKIM, and DMARC records
   - Set `EMAIL_ENABLED = false` to disable email features (registration will fail)
   - Reason: Users cannot register or reset passwords without email verification

2. **HTTPS/SSL Certificate**
   - ❌ **NEVER deploy without HTTPS**
   - Use Let's Encrypt (free) or purchase certificate
   - Our code automatically enables secure cookies when HTTPS is detected
   - Reason: Prevents session hijacking and man-in-the-middle attacks

3. **Strict File Permissions**
   ```bash
   sudo chmod 700 app/data/                    # Owner read/write/execute only
   sudo chmod 600 app/data/*.json             # Owner read/write only
   sudo chmod 600 app/email_config.php        # Protect API keys
   sudo chown www-data:www-data app/data/     # Web server owns files
   sudo chown www-data:www-data app/email_config.php
   ```
   - Reason: Prevents other users on shared hosting from reading password hashes and API keys

4. **PHP Production Configuration**
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

10. ~~**Database Migration**~~ ✅ **COMPLETED - MySQL support with encryption**
    - ✅ MySQL/PostgreSQL support implemented
    - ✅ AES-256-CBC encryption for habit names
    - ✅ User-specific encryption keys
    - ✅ Environment variable for master encryption key

11. ~~**Encryption at Rest**~~ ✅ **COMPLETED - AES-256 encryption**
    - ✅ Habit names encrypted with AES-256-CBC
    - ✅ Uses PHP's OpenSSL extension
    - ✅ User-specific keys with unique IVs per value
    - ✅ Master key stored in environment variable

12. **Two-Factor Authentication (2FA)**
    - Add TOTP support (Google Authenticator, Authy)
    - Requires additional development

13. ~~**Email Verification & Password Reset**~~ ✅ **COMPLETED in v1.0.0**
    - ✅ Email verification for new accounts (6-digit codes)
    - ✅ Password reset with verification codes
    - ✅ Brevo API integration
    - ✅ Rate limiting and code expiration

### For Developers

If forking this project for commercial/SaaS use, consider:
- ✅ **MySQL database** - already implemented with encryption
- Redis/Memcached for session storage at scale
- Container deployment (Docker/Kubernetes)
- CI/CD pipeline with security scanning
- Automated security testing (OWASP ZAP, etc.)
- DDoS protection (Cloudflare, AWS Shield)
- Compliance certifications (SOC 2, GDPR, etc.)
- Database connection pooling for high concurrency
- Read replicas for scaling read operations

## Email Security Features (v1.0.0)

✅ **Implemented Email Security:**

### Email Verification System
- **6-digit verification codes** sent via Brevo API
- **Code expiration**: 15-minute validity window
- **Attempt limiting**: Maximum 5 attempts per code
- **Rate limiting**: 60-second cooldown between resends
- **Secure storage**: Verification codes hashed and stored server-side

### Password Reset Flow
- **3-step verification process**:
  1. Request reset code (sent to email)
  2. Verify 6-digit code
  3. Set new password with temporary token (5-minute expiry)
- **Email enumeration prevention**: Doesn't reveal if email exists
- **Token expiration**: Reset tokens expire after 5 minutes
- **Auto-cleanup**: All codes cleared after successful password reset

### Email Configuration
- **Optional feature**: Can be disabled via `EMAIL_ENABLED` flag
- **API key protection**: `email_config.php` excluded from git
- **Brevo API integration**: Transactional email service
- **DNS authentication**: Supports SPF, DKIM, DMARC records

⚠️ **Note**: Email features are OPTIONAL. Self-hosters can choose to:
- Enable email features (requires Brevo API key setup)
- Disable email (set `EMAIL_ENABLED = false` - registration will fail)

## Upcoming Security Enhancements

The following security features are planned for future releases:

### Data Export Security
- ✅ **CSV Export**: User-friendly format for viewing in spreadsheets
- ✅ **JSON Export**: Complete backup format
- ✅ **Auto-detection Import**: Accepts both CSV and JSON files
- ✅ **Authentication Required**: All export/import requires valid session
- **Planned**: Export encryption option for sensitive data
- **Planned**: Export history/audit log

### Account Management
- ✅ **Password Change**: Requires current password verification
- ✅ **Account Deletion**: Secure deletion with password confirmation and data purge
- ✅ **Data Export/Import**: Users can backup and restore their data
- **Planned**: Session management (view active sessions, logout all devices)
- **Planned**: Login history and activity log

## Migration from Previous Version

If you were using the old client-side version:
- Old data stored in localStorage will NOT be automatically migrated
- You will need to create a new account through the registration screen
- Manual migration: Export old data as CSV, then manually re-enter habits
- The new system does not read from localStorage

## Security Improvements Completed

The following security enhancements have been implemented:

### Authentication & Sessions
- ✅ Proper password hashing implementation (bcrypt)
- ✅ Server-side authentication with sessions
- ✅ CSRF token protection on all state-changing operations
- ✅ Rate limiting on login attempts (5 attempts, 15-minute lockout)
- ✅ Session timeout (1 hour of inactivity)
- ✅ Secure session cookies (httponly, samesite)

### Data Protection
- ✅ Server-side data storage (no localStorage for sensitive data)
- ✅ Per-user data isolation
- ✅ Protected data directory (`.htaccess` blocks direct access)
- ✅ Secure file permissions guidance

### Account Management
- ✅ Password change with current password verification
- ✅ Secure account deletion (password confirmation + data purge)
- ✅ Data export (CSV and JSON) with authentication
- ✅ Data import with format auto-detection

### HTTP Security
- ✅ Security headers implementation (X-Frame-Options, CSP, etc.)
- ✅ CSRF tokens for all API endpoints
- ✅ Input validation and sanitization
- ✅ Proper error handling (no information leakage)

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
| 1.0.0+ (Current) | ✅ Production-Ready | Secure for local use; requires HTTPS for public deployment |
| Legacy (Pre-Oct 2024) | ❌ Deprecated | Plain-text passwords, localStorage only - do not use |

## Disclaimer

This software is provided "as is" under the MIT License. Use at your own risk. The author takes no responsibility for any security breaches, data loss, or other issues arising from the use of this software.
